<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderAutomationService;
use App\Jobs\ProcessOrderWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderAutomationService $orderService)
    {
        $this->orderService = $orderService;
        // Middleware is already handled in routes
        // $this->middleware('permission:manage-orders');
    }

    public function index(Request $request)
    {
        $query = Order::with(['user:id,name,email', 'orderItems.product:id,name'])
            ->withCount('orderItems');

        // Filters
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', function ($userQuery) use ($request) {
                      $userQuery->where('name', 'like', '%' . $request->search . '%')
                               ->orWhere('email', 'like', '%' . $request->search . '%');
                  });
            });
        }

        $orders = $query->orderBy('created_at', 'desc')
                       ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'stats' => $this->getOrderStats()
        ]);
    }

    public function show(Order $order)
    {
        $order->load([
            'user',
            'orderItems.product'
            // 'referralCode',
            // 'returns',
            // 'shipments'
        ]);

        return response()->json([
            'success' => true,
            'order' => $order,
            'timeline' => $this->getOrderTimeline($order),
            'available_actions' => $this->getAvailableActions($order)
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|string',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->orderService->updateOrderStatus($order, $request->status, $request->notes);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'order' => $order->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
            'status' => 'required|string',
            'notes' => 'nullable|string|max:500',
        ]);

        $updated = 0;
        $failed = 0;

        foreach ($request->order_ids as $orderId) {
            try {
                $order = Order::find($orderId);
                $this->orderService->updateOrderStatus($order, $request->status, $request->notes);
                $updated++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Updated {$updated} orders, {$failed} failed",
            'updated' => $updated,
            'failed' => $failed
        ]);
    }

    protected function getOrderStats(): array
    {
        return [
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'processing_orders' => Order::where('status', 'processing')->count(),
            'shipped_orders' => Order::where('status', 'shipped')->count(),
            'delivered_orders' => Order::where('status', 'delivered')->count(),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
            'total_revenue' => Order::where('status', 'delivered')->sum('total_amount'),
            'average_order_value' => Order::where('status', 'delivered')->avg('total_amount'),
        ];
    }

    protected function getOrderTimeline(Order $order): array
    {
        return [
            ['status' => 'pending', 'date' => $order->created_at, 'completed' => true],
            ['status' => 'confirmed', 'date' => $order->confirmed_at, 'completed' => (bool) $order->confirmed_at],
            ['status' => 'processing', 'date' => $order->processing_at, 'completed' => (bool) $order->processing_at],
            ['status' => 'shipped', 'date' => $order->shipped_at, 'completed' => (bool) $order->shipped_at],
            ['status' => 'delivered', 'date' => $order->delivered_at, 'completed' => (bool) $order->delivered_at],
        ];
    }

    protected function getAvailableActions(Order $order): array
    {
        return $this->orderService->getAvailableTransitions($order->status);
    }

    public function updatePaymentStatus(Request $request, Order $order)
    {
        $request->validate([
            'payment_status' => 'required|in:pending,paid,failed,refunded,partially_refunded',
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $oldStatus = $order->payment_status;
            $order->update([
                'payment_status' => $request->payment_status,
                'payment_transaction_id' => $request->transaction_id ?? $order->payment_transaction_id,
            ]);

            // Log the payment status change
            \App\Models\OrderWorkflow::recordTransition($order, $oldStatus, $request->payment_status, [
                'type' => 'payment_status_change',
                'notes' => $request->notes,
                'transaction_id' => $request->transaction_id,
            ]);

            // If payment is confirmed, process the order
            if ($request->payment_status === 'paid' && $oldStatus !== 'paid') {
                $this->orderService->processPaymentConfirmation($order);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment status updated successfully',
                'order' => $order->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function cancel(Request $request, Order $order)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'refund_amount' => 'nullable|numeric|min:0',
            'refund_method' => 'nullable|in:original,store_credit,manual',
        ]);

        try {
            DB::beginTransaction();

            // Check if order can be cancelled
            if (!$order->can_be_cancelled) {
                throw new \Exception('This order cannot be cancelled at current status');
            }

            $oldStatus = $order->status;

            // Update order status
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $request->reason,
            ]);

            // Record the cancellation
            \App\Models\OrderWorkflow::recordTransition($order, $oldStatus, 'cancelled', [
                'reason' => $request->reason,
                'refund_amount' => $request->refund_amount,
                'refund_method' => $request->refund_method,
                'cancelled_by' => auth()->user()->name,
            ]);

            // Release inventory
            foreach ($order->orderItems as $item) {
                if (method_exists($item->product, 'releaseStock')) {
                    $item->product->releaseStock($item->quantity);
                }
            }

            // Process refund if needed
            if ($request->refund_amount > 0 && $order->payment_status === 'paid') {
                $this->processRefund($order, $request->refund_amount, $request->refund_method ?? 'original');
            }

            // Send cancellation notification
            dispatch(new \App\Jobs\SendOrderNotification($order, 'order_cancelled'));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'order' => $order->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function refund(Request $request, Order $order)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
            'refund_method' => 'required|in:original,store_credit,manual',
            'items' => 'nullable|array',
            'items.*.order_item_id' => 'required_with:items|exists:order_items,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            // Validate refund amount
            $maxRefundable = $order->total_amount - $order->refunds()->sum('amount');
            if ($request->amount > $maxRefundable) {
                throw new \Exception("Maximum refundable amount is {$maxRefundable}");
            }

            // Create refund record
            $refund = $order->refunds()->create([
                'amount' => $request->amount,
                'reason' => $request->reason,
                'refund_method' => $request->refund_method,
                'status' => 'pending',
                'initiated_by' => auth()->id(),
                'items' => $request->items,
            ]);

            // Process refund based on method
            $refundResult = $this->processRefund($order, $request->amount, $request->refund_method);

            // Update refund status
            $refund->update([
                'status' => $refundResult['success'] ? 'completed' : 'failed',
                'processed_at' => now(),
                'transaction_reference' => $refundResult['reference'] ?? null,
            ]);

            // Update order payment status if fully refunded
            if ($order->refunds()->where('status', 'completed')->sum('amount') >= $order->total_amount) {
                $order->update(['payment_status' => 'refunded']);
            } elseif ($order->refunds()->where('status', 'completed')->exists()) {
                $order->update(['payment_status' => 'partially_refunded']);
            }

            // Send refund notification
            dispatch(new \App\Jobs\SendOrderNotification($order, 'refund_processed'));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund' => $refund,
                'order' => $order->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function getTimeline(Order $order)
    {
        $workflows = \App\Models\OrderWorkflow::where('order_id', $order->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $timeline = $workflows->map(function ($workflow) {
            return [
                'id' => $workflow->id,
                'from_status' => $workflow->from_status,
                'to_status' => $workflow->to_status,
                'triggered_by' => $workflow->triggered_by,
                'user' => $workflow->triggeredBy ? $workflow->triggeredBy->name : 'System',
                'reason' => $workflow->reason,
                'notes' => $workflow->notes,
                'metadata' => $workflow->metadata,
                'created_at' => $workflow->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'timeline' => $timeline,
            'order_number' => $order->order_number,
            'current_status' => $order->status,
        ]);
    }

    protected function processRefund(Order $order, float $amount, string $method): array
    {
        switch ($method) {
            case 'original':
                // Process refund through original payment gateway
                if ($order->payment_method === 'razorpay') {
                    // TODO: Implement Razorpay refund
                    return ['success' => true, 'reference' => 'RAZORPAY_' . uniqid()];
                } elseif ($order->payment_method === 'cashfree') {
                    // TODO: Implement Cashfree refund
                    return ['success' => true, 'reference' => 'CASHFREE_' . uniqid()];
                }
                break;

            case 'store_credit':
                // Add store credit to user account
                $order->user->increment('store_credit', $amount);
                return ['success' => true, 'reference' => 'CREDIT_' . uniqid()];

            case 'manual':
                // Manual refund - just record it
                return ['success' => true, 'reference' => 'MANUAL_' . uniqid()];
        }

        return ['success' => false, 'message' => 'Refund method not supported'];
    }

    public function getInvoice(Order $order)
    {
        try {
            $order->load(['user', 'orderItems.product', 'shippingAddress', 'billingAddress']);

            // Prepare invoice data
            $invoiceData = [
                'order' => $order,
                'company' => [
                    'name' => config('app.name', 'BookBharat'),
                    'address' => \App\Models\AdminSetting::get('company_address_line1', 'BookBharat HQ'),
                    'city' => \App\Models\AdminSetting::get('company_city', 'Mumbai') . ', ' . \App\Models\AdminSetting::get('company_state', 'Maharashtra') . ' ' . \App\Models\AdminSetting::get('company_pincode', '400001'),
                    'country' => \App\Models\AdminSetting::get('company_country', 'India'),
                    'email' => \App\Models\AdminSetting::get('support_email', 'support@bookbharat.com'),
                    'phone' => \App\Models\AdminSetting::get('contact_phone', '+91 9876543210'),
                    'gstin' => \App\Models\AdminSetting::get('gst_number', 'GSTIN123456789'),
                ],
                'invoice_number' => 'INV-' . $order->order_number,
                'invoice_date' => $order->created_at->format('d M Y'),
                'due_date' => $order->created_at->addDays(7)->format('d M Y'),
            ];

            // Return as JSON for now (can be enhanced to generate PDF)
            return response()->json([
                'success' => true,
                'invoice' => $invoiceData,
                'order_number' => $order->order_number,
                'download_url' => route('admin.orders.invoice.download', $order->id)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportOrders(Request $request)
    {
        $query = Order::with(['user:id,name,email'])->withCount('orderItems');

        // Apply filters if provided
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $orders = $query->get();

        $exportData = $orders->map(function ($order) {
            return [
                'Order Number' => $order->order_number,
                'Customer Name' => $order->user->name ?? 'N/A',
                'Customer Email' => $order->user->email ?? 'N/A',
                'Total Amount' => $order->total_amount,
                'Status' => $order->status,
                'Payment Status' => $order->payment_status,
                'Payment Method' => $order->payment_method,
                'Items Count' => $order->order_items_count,
                'Created At' => $order->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $exportData,
            'total' => $exportData->count(),
            'filters' => $request->only(['status', 'date_from', 'date_to'])
        ]);
    }
}
