<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    protected $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Display user's invoices.
     */
    public function index(Request $request)
    {
        try {
            $query = Invoice::with(['order:id,order_number,created_at'])
                ->whereHas('order', function ($q) {
                    $q->where('user_id', auth()->id());
                });

            // Apply filters
            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->from_date) {
                $query->whereDate('invoice_date', '>=', $request->from_date);
            }

            if ($request->to_date) {
                $query->whereDate('invoice_date', '<=', $request->to_date);
            }

            // Sorting
            switch ($request->sort) {
                case 'date_asc':
                    $query->orderBy('invoice_date', 'asc');
                    break;
                case 'date_desc':
                    $query->orderBy('invoice_date', 'desc');
                    break;
                case 'amount_asc':
                    $query->orderBy('total_amount', 'asc');
                    break;
                case 'amount_desc':
                    $query->orderBy('total_amount', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            $perPage = min($request->get('per_page', 15), 50);
            $invoices = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $invoices
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve invoices'
            ], 500);
        }
    }

    /**
     * Display a specific invoice.
     */
    public function show($id)
    {
        try {
            $invoice = Invoice::with(['order.user', 'invoiceItems.product'])
                ->whereHas('order', function ($q) {
                    $q->where('user_id', auth()->id());
                })
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $invoice
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice not found'
            ], 404);
        }
    }

    /**
     * Generate invoice for an order.
     */
    public function generateForOrder(Request $request, $orderId)
    {
        try {
            $order = Order::where('user_id', auth()->id())
                ->findOrFail($orderId);

            $invoice = $this->invoiceService->generateInvoiceForOrder($order);

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice generated successfully',
                'data' => $invoice->load(['order', 'invoiceItems.product'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download invoice PDF.
     */
    public function download($id)
    {
        try {
            $invoice = Invoice::with(['order.user', 'invoiceItems.product'])
                ->whereHas('order', function ($q) {
                    $q->where('user_id', auth()->id());
                })
                ->findOrFail($id);

            // Generate PDF if not exists
            if (!$invoice->pdf_path || !Storage::disk('public')->exists($invoice->pdf_path)) {
                $this->invoiceService->generatePDF($invoice);
                $invoice->refresh();
            }

            // Return file download response
            $filePath = storage_path('app/public/' . $invoice->pdf_path);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invoice PDF not found'
                ], 404);
            }

            return response()->download($filePath, 'invoice_' . $invoice->formatted_invoice_number . '.html');

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to download invoice'
            ], 500);
        }
    }

    /**
     * View invoice in browser.
     */
    public function view($id)
    {
        try {
            $invoice = Invoice::with(['order.user', 'invoiceItems.product'])
                ->whereHas('order', function ($q) {
                    $q->where('user_id', auth()->id());
                })
                ->findOrFail($id);

            // Generate PDF if not exists
            if (!$invoice->pdf_path || !Storage::disk('public')->exists($invoice->pdf_path)) {
                $this->invoiceService->generatePDF($invoice);
                $invoice->refresh();
            }

            $fileUrl = Storage::disk('public')->url($invoice->pdf_path);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'invoice' => $invoice,
                    'pdf_url' => $fileUrl
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to view invoice'
            ], 500);
        }
    }

    /**
     * Send invoice via email.
     */
    public function sendEmail($id)
    {
        try {
            $invoice = Invoice::with(['order.user'])
                ->whereHas('order', function ($q) {
                    $q->where('user_id', auth()->id());
                })
                ->findOrFail($id);

            $success = $this->invoiceService->sendInvoiceEmail($invoice);

            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Invoice sent successfully to your email'
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to send invoice email'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send invoice email'
            ], 500);
        }
    }

    /**
     * Get invoice statistics for the user.
     */
    public function stats()
    {
        try {
            $stats = Invoice::whereHas('order', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->selectRaw('
                COUNT(*) as total_invoices,
                SUM(total_amount) as total_amount,
                SUM(CASE WHEN status = "paid" THEN total_amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN status = "pending" THEN total_amount ELSE 0 END) as pending_amount,
                COUNT(CASE WHEN status = "paid" THEN 1 END) as paid_invoices,
                COUNT(CASE WHEN status = "pending" THEN 1 END) as pending_invoices,
                COUNT(CASE WHEN status = "pending" AND due_date < NOW() THEN 1 END) as overdue_invoices
            ')
            ->first();

            $monthlyStats = Invoice::whereHas('order', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->selectRaw('
                YEAR(invoice_date) as year,
                MONTH(invoice_date) as month,
                COUNT(*) as count,
                SUM(total_amount) as amount
            ')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'overview' => [
                        'total_invoices' => $stats->total_invoices ?? 0,
                        'total_amount' => $stats->total_amount ?? 0,
                        'paid_amount' => $stats->paid_amount ?? 0,
                        'pending_amount' => $stats->pending_amount ?? 0,
                        'paid_invoices' => $stats->paid_invoices ?? 0,
                        'pending_invoices' => $stats->pending_invoices ?? 0,
                        'overdue_invoices' => $stats->overdue_invoices ?? 0,
                    ],
                    'monthly_stats' => $monthlyStats
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve invoice statistics'
            ], 500);
        }
    }

    /**
     * Mark invoice as paid (for manual payment methods).
     */
    public function markAsPaid($id)
    {
        try {
            $invoice = Invoice::whereHas('order', function ($q) {
                $q->where('user_id', auth()->id());
            })->findOrFail($id);

            if ($invoice->status === 'paid') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invoice is already marked as paid'
                ], 400);
            }

            $invoice->markAsPaid();

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice marked as paid successfully',
                'data' => $invoice->fresh()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark invoice as paid'
            ], 500);
        }
    }

    /**
     * Get recent invoices for dashboard.
     */
    public function recent()
    {
        try {
            $recentInvoices = Invoice::with(['order:id,order_number'])
                ->whereHas('order', function ($q) {
                    $q->where('user_id', auth()->id());
                })
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $recentInvoices
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve recent invoices'
            ], 500);
        }
    }
}