<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Return as ReturnModel;
use App\Models\Invoice;

class EmailService
{
    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmation(Order $order)
    {
        try {
            $data = [
                'user' => $order->user,
                'order' => $order,
                'items' => $order->items()->with('product')->get(),
                'subject' => 'Order Confirmation - #' . $order->order_number
            ];

            Mail::send('emails.order.confirmation', $data, function ($message) use ($order) {
                $message->to($order->user->email, $order->user->name)
                       ->subject('Order Confirmation - #' . $order->order_number);
            });

            Log::info('Order confirmation email sent', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'email' => $order->user->email
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send order status update email
     */
    public function sendOrderStatusUpdate(Order $order, $oldStatus, $newStatus)
    {
        try {
            $data = [
                'user' => $order->user,
                'order' => $order,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'status_message' => $this->getStatusMessage($newStatus),
                'subject' => 'Order Update - #' . $order->order_number
            ];

            Mail::send('emails.order.status_update', $data, function ($message) use ($order) {
                $message->to($order->user->email, $order->user->name)
                       ->subject('Order Update - #' . $order->order_number);
            });

            Log::info('Order status update email sent', [
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send order status update email', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send payment confirmation email
     */
    public function sendPaymentConfirmation(Payment $payment)
    {
        try {
            $data = [
                'user' => $payment->order->user,
                'payment' => $payment,
                'order' => $payment->order,
                'subject' => 'Payment Confirmation - Order #' . $payment->order->order_number
            ];

            Mail::send('emails.payment.confirmation', $data, function ($message) use ($payment) {
                $message->to($payment->order->user->email, $payment->order->user->name)
                       ->subject('Payment Confirmation - Order #' . $payment->order->order_number);
            });

            Log::info('Payment confirmation email sent', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation email', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send invoice email with PDF attachment
     */
    public function sendInvoice(Invoice $invoice, $attachPdf = true)
    {
        try {
            $data = [
                'user' => $invoice->order->user,
                'invoice' => $invoice,
                'order' => $invoice->order,
                'items' => $invoice->invoiceItems()->with('product')->get(),
                'subject' => 'Invoice - #' . $invoice->formatted_invoice_number
            ];

            Mail::send('emails.invoice.invoice', $data, function ($message) use ($invoice, $attachPdf) {
                $message->to($invoice->order->user->email, $invoice->order->user->name)
                       ->subject('Invoice - #' . $invoice->formatted_invoice_number);
                
                if ($attachPdf && $invoice->pdf_path) {
                    $pdfPath = storage_path('app/public/' . $invoice->pdf_path);
                    if (file_exists($pdfPath)) {
                        $message->attach($pdfPath, [
                            'as' => 'Invoice_' . $invoice->formatted_invoice_number . '.pdf',
                            'mime' => 'application/pdf',
                        ]);
                    }
                }
            });

            Log::info('Invoice email sent', [
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->order_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send invoice email', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send return status update email
     */
    public function sendReturnStatusUpdate(ReturnModel $return, $oldStatus, $newStatus)
    {
        try {
            $data = [
                'user' => $return->user,
                'return' => $return,
                'order' => $return->order,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'status_message' => $this->getReturnStatusMessage($newStatus),
                'subject' => 'Return Update - #' . $return->return_number
            ];

            Mail::send('emails.returns.status_update', $data, function ($message) use ($return) {
                $message->to($return->user->email, $return->user->name)
                       ->subject('Return Update - #' . $return->return_number);
            });

            Log::info('Return status update email sent', [
                'return_id' => $return->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send return status update email', [
                'return_id' => $return->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send welcome email to new users
     */
    public function sendWelcomeEmail(User $user)
    {
        try {
            $data = [
                'user' => $user,
                'subject' => 'Welcome to ' . config('app.name')
            ];

            Mail::send('emails.auth.welcome', $data, function ($message) use ($user) {
                $message->to($user->email, $user->name)
                       ->subject('Welcome to ' . config('app.name'));
            });

            Log::info('Welcome email sent', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(User $user, $token)
    {
        try {
            $data = [
                'user' => $user,
                'token' => $token,
                'reset_url' => config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email),
                'subject' => 'Reset Password - ' . config('app.name')
            ];

            Mail::send('emails.auth.password_reset', $data, function ($message) use ($user) {
                $message->to($user->email, $user->name)
                       ->subject('Reset Password - ' . config('app.name'));
            });

            Log::info('Password reset email sent', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send shipping notification email
     */
    public function sendShippingNotification(Order $order)
    {
        try {
            $data = [
                'user' => $order->user,
                'order' => $order,
                'tracking_number' => $order->tracking_number,
                'shipping_carrier' => $order->shipping_carrier,
                'tracking_url' => $this->getTrackingUrl($order->shipping_carrier, $order->tracking_number),
                'subject' => 'Your Order Has Shipped - #' . $order->order_number
            ];

            Mail::send('emails.order.shipped', $data, function ($message) use ($order) {
                $message->to($order->user->email, $order->user->name)
                       ->subject('Your Order Has Shipped - #' . $order->order_number);
            });

            Log::info('Shipping notification email sent', [
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send shipping notification email', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send delivery confirmation email
     */
    public function sendDeliveryConfirmation(Order $order)
    {
        try {
            $data = [
                'user' => $order->user,
                'order' => $order,
                'items' => $order->items()->with('product')->get(),
                'subject' => 'Order Delivered - #' . $order->order_number
            ];

            Mail::send('emails.order.delivered', $data, function ($message) use ($order) {
                $message->to($order->user->email, $order->user->name)
                       ->subject('Order Delivered - #' . $order->order_number);
            });

            Log::info('Delivery confirmation email sent', [
                'order_id' => $order->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send delivery confirmation email', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send review request email
     */
    public function sendReviewRequest(Order $order)
    {
        try {
            $data = [
                'user' => $order->user,
                'order' => $order,
                'items' => $order->items()->with('product')->get(),
                'review_url' => config('app.frontend_url') . '/orders/' . $order->id . '/review',
                'subject' => 'How was your order? - #' . $order->order_number
            ];

            Mail::send('emails.order.review_request', $data, function ($message) use ($order) {
                $message->to($order->user->email, $order->user->name)
                       ->subject('How was your order? - #' . $order->order_number);
            });

            Log::info('Review request email sent', [
                'order_id' => $order->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send review request email', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get status message for order
     */
    private function getStatusMessage($status)
    {
        return match($status) {
            'pending' => 'Your order is being processed.',
            'confirmed' => 'Your order has been confirmed and will be shipped soon.',
            'processing' => 'Your order is currently being prepared for shipment.',
            'shipped' => 'Your order has been shipped and is on its way to you.',
            'out_for_delivery' => 'Your order is out for delivery and will arrive soon.',
            'delivered' => 'Your order has been delivered successfully.',
            'cancelled' => 'Your order has been cancelled.',
            'refunded' => 'Your order has been refunded.',
            default => 'Your order status has been updated.'
        };
    }

    /**
     * Get status message for return
     */
    private function getReturnStatusMessage($status)
    {
        return match($status) {
            'requested' => 'Your return request has been received and is being reviewed.',
            'approved' => 'Your return request has been approved. Please ship the items back to us.',
            'rejected' => 'Your return request has been rejected. Please contact support if you have questions.',
            'shipped' => 'Thank you for shipping the items. We will inspect them upon receipt.',
            'received' => 'We have received your returned items and are inspecting them.',
            'processed' => 'Your return has been processed and refund is being issued.',
            'completed' => 'Your return has been completed and refund has been issued.',
            default => 'Your return status has been updated.'
        };
    }

    /**
     * Get tracking URL for shipping carrier
     */
    private function getTrackingUrl($carrier, $trackingNumber)
    {
        if (!$trackingNumber) {
            return null;
        }

        return match(strtolower($carrier)) {
            'fedex' => "https://www.fedex.com/apps/fedextrack/?tracknumbers={$trackingNumber}",
            'ups' => "https://wwwapps.ups.com/tracking/tracking.cgi?tracknum={$trackingNumber}",
            'usps' => "https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1={$trackingNumber}",
            'dhl' => "https://www.dhl.com/en/express/tracking.html?AWB={$trackingNumber}",
            'bluedart' => "https://www.bluedart.com/tracking/{$trackingNumber}",
            'dtdc' => "https://dtdc.in/tracking/consignment_number_tracking.asp?strCnno={$trackingNumber}",
            default => null
        };
    }
}