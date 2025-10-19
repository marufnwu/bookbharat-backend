<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentTransactionController extends Controller
{
    /**
     * Get paginated list of payment transactions with filters
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 25);
        $page = $request->input('page', 1);

        // Filters
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $gateway = $request->input('gateway');
        $status = $request->input('status');
        $minAmount = $request->input('min_amount');
        $maxAmount = $request->input('max_amount');
        $search = $request->input('search'); // For order number or transaction ID

        $query = Order::with(['user:id,name,email'])
            ->select([
                'id',
                'order_number',
                'user_id',
                'payment_method',
                'payment_status',
                'payment_transaction_id',
                'total',
                'subtotal',
                'shipping_cost',
                'tax',
                'created_at',
                'updated_at'
            ]);

        // Apply filters
        if ($startDate) {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        if ($gateway) {
            $query->where('payment_method', $gateway);
        }

        if ($status) {
            $query->where('payment_status', $status);
        }

        if ($minAmount) {
            $query->where('total', '>=', $minAmount);
        }

        if ($maxAmount) {
            $query->where('total', '<=', $maxAmount);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                  ->orWhere('payment_transaction_id', 'LIKE', "%{$search}%");
            });
        }

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        $transactions = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem()
            ]
        ]);
    }

    /**
     * Get detailed information about a specific transaction
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $transaction = Order::with([
            'user:id,name,email,phone',
            'items.product:id,name,sku',
            'items.product.images'
        ])
            ->findOrFail($id);

        // Get payment gateway response if available
        $gatewayResponse = null;
        if ($transaction->payment_gateway_response) {
            $gatewayResponse = is_string($transaction->payment_gateway_response)
                ? json_decode($transaction->payment_gateway_response, true)
                : $transaction->payment_gateway_response;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $transaction,
                'gateway_response' => $gatewayResponse,
                'timeline' => $this->getTransactionTimeline($transaction)
            ]
        ]);
    }

    /**
     * Get transaction timeline/history
     *
     * @param Order $order
     * @return array
     */
    private function getTransactionTimeline($order)
    {
        $timeline = [];

        // Order created
        $timeline[] = [
            'event' => 'Order Created',
            'status' => 'created',
            'timestamp' => $order->created_at,
            'details' => "Order #{$order->order_number} created"
        ];

        // Payment initiated (if different from created)
        if ($order->payment_initiated_at && $order->payment_initiated_at != $order->created_at) {
            $timeline[] = [
                'event' => 'Payment Initiated',
                'status' => 'initiated',
                'timestamp' => $order->payment_initiated_at,
                'details' => "Payment initiated via {$order->payment_method}"
            ];
        }

        // Payment status changes
        if ($order->payment_status) {
            $statusTimestamp = $order->updated_at;

            switch ($order->payment_status) {
                case 'paid':
                case 'success':
                case 'completed':
                    $timeline[] = [
                        'event' => 'Payment Successful',
                        'status' => 'success',
                        'timestamp' => $statusTimestamp,
                        'details' => "Payment completed successfully"
                    ];
                    break;

                case 'failed':
                    $timeline[] = [
                        'event' => 'Payment Failed',
                        'status' => 'failed',
                        'timestamp' => $statusTimestamp,
                        'details' => "Payment failed"
                    ];
                    break;

                case 'cancelled':
                    $timeline[] = [
                        'event' => 'Payment Cancelled',
                        'status' => 'cancelled',
                        'timestamp' => $statusTimestamp,
                        'details' => "Payment was cancelled"
                    ];
                    break;

                case 'pending':
                case 'processing':
                    $timeline[] = [
                        'event' => 'Payment Pending',
                        'status' => 'pending',
                        'timestamp' => $statusTimestamp,
                        'details' => "Payment is being processed"
                    ];
                    break;
            }
        }

        // Order status changes
        if ($order->status) {
            switch ($order->status) {
                case 'shipped':
                    $timeline[] = [
                        'event' => 'Order Shipped',
                        'status' => 'shipped',
                        'timestamp' => $order->updated_at,
                        'details' => "Order has been shipped"
                    ];
                    break;

                case 'delivered':
                    $timeline[] = [
                        'event' => 'Order Delivered',
                        'status' => 'delivered',
                        'timestamp' => $order->updated_at,
                        'details' => "Order has been delivered"
                    ];
                    break;
            }
        }

        // Sort by timestamp
        usort($timeline, function ($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });

        return $timeline;
    }

    /**
     * Export transactions to CSV
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        // Apply same filters as index
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $gateway = $request->input('gateway');
        $status = $request->input('status');

        $query = Order::with(['user:id,name,email']);

        if ($startDate) {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        if ($gateway) {
            $query->where('payment_method', $gateway);
        }

        if ($status) {
            $query->where('payment_status', $status);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'payment_transactions_' . now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Order Number',
                'Transaction ID',
                'Customer Name',
                'Customer Email',
                'Payment Method',
                'Payment Status',
                'Amount',
                'Created At',
                'Updated At'
            ]);

            // CSV Rows
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->order_number,
                    $transaction->payment_transaction_id ?? 'N/A',
                    $transaction->user->name ?? 'Guest',
                    $transaction->user->email ?? 'N/A',
                    strtoupper($transaction->payment_method ?? 'N/A'),
                    ucfirst($transaction->payment_status ?? 'N/A'),
                    $transaction->total,
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    $transaction->updated_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get webhook logs (if you store them separately)
     * For now, we'll extract webhook info from payment_gateway_response
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhookLogs(Request $request)
    {
        $perPage = $request->input('per_page', 25);
        $page = $request->input('page', 1);
        $gateway = $request->input('gateway');

        $query = Order::whereNotNull('payment_gateway_response')
            ->select([
                'id',
                'order_number',
                'payment_method',
                'payment_status',
                'payment_transaction_id',
                'payment_gateway_response',
                'created_at',
                'updated_at'
            ]);

        if ($gateway) {
            $query->where('payment_method', $gateway);
        }

        $query->orderBy('updated_at', 'desc');

        $logs = $query->paginate($perPage, ['*'], 'page', $page);

        // Parse gateway responses
        $logs->getCollection()->transform(function ($log) {
            if ($log->payment_gateway_response) {
                $log->parsed_response = is_string($log->payment_gateway_response)
                    ? json_decode($log->payment_gateway_response, true)
                    : $log->payment_gateway_response;
            }
            return $log;
        });

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem()
            ]
        ]);
    }
}


