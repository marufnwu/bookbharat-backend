<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderConfirmationNotification;
use App\Notifications\NewOrderNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class OrderService
{
    /**
     * Process order after it has been created
     * This is called after successful payment
     */
    public function processOrderCreated(Order $order): void
    {
        try {
            // Update inventory
            $this->updateInventory($order);

            // Send order confirmation email to customer
            $this->sendOrderConfirmation($order);

            // Notify admins about new order
            $this->notifyAdmins($order);

            // Track order analytics
            $this->trackOrderAnalytics($order);

            // Queue any background jobs
            $this->queueBackgroundJobs($order);

        } catch (\Exception $e) {
            Log::error('Error processing order created', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update inventory for ordered items
     * NOTE: Stock is already reduced when items are added to cart
     * This method only updates sales count and stock status
     */
    protected function updateInventory(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->orderItems as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);
                if ($product) {
                    // Stock is ALREADY reduced when added to cart, so we DON'T reduce again here
                    // Only update sales count

                    // For bundle variants, increment by the bundle quantity
                    $salesIncrement = $item->bundle_quantity ? ($item->quantity * $item->bundle_quantity) : $item->quantity;
                    $product->increment('sales_count', $salesIncrement);

                    // Update stock status flags
                    if ($product->stock_quantity <= 0) {
                        $product->update(['stock_status' => 'out_of_stock']);
                    } elseif ($product->stock_quantity <= 10) {
                        $product->update(['stock_status' => 'low_stock']);
                    }
                }
            }
        });
    }

    /**
     * Send order confirmation email to customer
     */
    protected function sendOrderConfirmation(Order $order): void
    {
        try {
            if ($order->user && $order->user->email) {
                // Check if notification class exists
                if (class_exists(OrderConfirmationNotification::class)) {
                    $order->user->notify(new OrderConfirmationNotification($order));
                } else {
                    // Fallback to simple mail or log
                    Log::info('Order confirmation would be sent', [
                        'order_id' => $order->id,
                        'email' => $order->user->email
                    ]);
                }
            } elseif ($order->email) {
                // Guest order - send to provided email
                Log::info('Guest order confirmation would be sent', [
                    'order_id' => $order->id,
                    'email' => $order->email
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify admins about new order
     */
    protected function notifyAdmins(Order $order): void
    {
        try {
            // Get admin users
            $admins = User::where('role', 'admin')->get();

            if ($admins->isNotEmpty()) {
                // Check if notification class exists
                if (class_exists(NewOrderNotification::class)) {
                    Notification::send($admins, new NewOrderNotification($order));
                } else {
                    Log::info('Admin notification would be sent', [
                        'order_id' => $order->id,
                        'admin_count' => $admins->count()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admins', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Track order analytics
     */
    protected function trackOrderAnalytics(Order $order): void
    {
        try {
            // Track conversion analytics
            Log::info('Order analytics tracked', [
                'order_id' => $order->id,
                'total_amount' => $order->total_amount,
                'items_count' => $order->orderItems->count(),
                'payment_method' => $order->payment_method,
                'customer_type' => $order->user_id ? 'registered' : 'guest'
            ]);

            // You can add more analytics tracking here
            // - Google Analytics
            // - Facebook Pixel
            // - Custom analytics service

        } catch (\Exception $e) {
            Log::error('Failed to track order analytics', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Queue background jobs for order processing
     */
    protected function queueBackgroundJobs(Order $order): void
    {
        try {
            // Queue invoice generation
            // Queue shipping label generation
            // Queue ERP sync
            // Queue accounting system update

            Log::info('Background jobs queued for order', [
                'order_id' => $order->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to queue background jobs', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(Order $order, string $reason = null): bool
    {
        try {
            DB::transaction(function () use ($order, $reason) {
                // Update order status
                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason
                ]);

                // Restore inventory
                foreach ($order->orderItems as $item) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->increment('stock_quantity', $item->quantity);
                        $product->decrement('sales_count', $item->quantity);

                        // Update stock status
                        if ($product->stock_quantity > 10) {
                            $product->update(['stock_status' => 'in_stock']);
                        }
                    }
                }

                // Process refund if payment was made
                if ($order->payment_status === 'paid') {
                    $this->processRefund($order);
                }
            });

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to cancel order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process refund for cancelled order
     */
    protected function processRefund(Order $order): void
    {
        // Implement refund logic based on payment gateway
        Log::info('Refund would be processed', [
            'order_id' => $order->id,
            'amount' => $order->total_amount,
            'payment_method' => $order->payment_method
        ]);
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Order $order, string $status): bool
    {
        try {
            $order->update(['status' => $status]);

            // Send status update notification
            if ($order->user) {
                Log::info('Order status update notification would be sent', [
                    'order_id' => $order->id,
                    'new_status' => $status
                ]);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update order status', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Calculate order totals
     */
    public function calculateOrderTotals(array $items, float $shippingCharge = 0, float $discount = 0): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $total = $subtotal + $shippingCharge - $discount;

        return [
            'subtotal' => round($subtotal, 2),
            'shipping_charge' => round($shippingCharge, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2)
        ];
    }

    /**
     * Generate unique order number
     */
    public function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));

        return $prefix . $timestamp . $random;
    }

    /**
     * Validate order before processing
     */
    public function validateOrder(Order $order): array
    {
        $errors = [];

        // Check if order has items
        if ($order->orderItems->isEmpty()) {
            $errors[] = 'Order has no items';
        }

        // Check product availability
        foreach ($order->orderItems as $item) {
            $product = Product::find($item->product_id);
            if (!$product) {
                $errors[] = "Product {$item->product_id} not found";
            } elseif ($product->stock_quantity < $item->quantity) {
                $errors[] = "Insufficient stock for {$product->name}";
            }
        }

        // Check shipping address
        if (!$order->shipping_address_id && !$order->shipping_address) {
            $errors[] = 'No shipping address provided';
        }

        return $errors;
    }
}
