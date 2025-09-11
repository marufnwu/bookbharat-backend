<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $template;
    protected $additionalData;

    public function __construct(Order $order, string $template, array $additionalData = [])
    {
        $this->order = $order;
        $this->template = $template;
        $this->additionalData = $additionalData;
    }

    public function handle(NotificationService $notificationService)
    {
        $user = $this->order->user;
        
        // Prepare notification data
        $data = $this->prepareNotificationData();
        
        // Send email notification
        if ($user->email_notifications_enabled ?? true) {
            $this->sendEmailNotification($data);
        }
        
        // Send SMS notification for important events
        if (($user->sms_notifications_enabled ?? true) && $this->shouldSendSMS()) {
            $this->sendSMSNotification($data);
        }
        
        // Send push notification if app token exists
        if ($user->push_token) {
            $this->sendPushNotification($data);
        }
        
        // Create in-app notification
        $this->createInAppNotification($data);
    }

    protected function prepareNotificationData(): array
    {
        $baseData = [
            'order' => $this->order,
            'user' => $this->order->user,
            'order_number' => $this->order->order_number,
            'order_total' => $this->order->total_amount,
            'order_status' => $this->order->status,
            'items_count' => $this->order->orderItems->count(),
            'delivery_date' => $this->order->estimated_delivery_date,
            'tracking_number' => $this->order->tracking_number,
        ];
        
        return array_merge($baseData, $this->additionalData);
    }

    protected function sendEmailNotification(array $data)
    {
        $emailClass = $this->getEmailClass();
        
        if ($emailClass) {
            Mail::to($this->order->user->email)
                ->send(new $emailClass($data));
        }
    }

    protected function sendSMSNotification(array $data)
    {
        $message = $this->getSMSMessage($data);
        
        if ($message && $this->order->user->phone) {
            // TODO: Integrate with SMS gateway (Twilio, MSG91, etc.)
            \Log::info('SMS would be sent', [
                'to' => $this->order->user->phone,
                'message' => $message,
                'template' => $this->template
            ]);
        }
    }

    protected function sendPushNotification(array $data)
    {
        $notification = [
            'title' => $this->getPushTitle(),
            'body' => $this->getPushBody($data),
            'data' => [
                'order_id' => $this->order->id,
                'type' => $this->template,
            ]
        ];
        
        // TODO: Integrate with FCM or other push service
        \Log::info('Push notification would be sent', $notification);
    }

    protected function createInAppNotification(array $data)
    {
        $this->order->user->notifications()->create([
            'type' => 'order_' . $this->template,
            'title' => $this->getNotificationTitle(),
            'message' => $this->getNotificationMessage($data),
            'data' => [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
            ],
            'is_read' => false,
        ]);
    }

    protected function getEmailClass(): ?string
    {
        $emailClasses = [
            'order_placed' => \App\Mail\OrderPlaced::class,
            'order_confirmed' => \App\Mail\OrderConfirmed::class,
            'order_shipped' => \App\Mail\OrderShipped::class,
            'order_delivered' => \App\Mail\OrderDelivered::class,
            'order_cancelled' => \App\Mail\OrderCancelled::class,
            'refund_processed' => \App\Mail\RefundProcessed::class,
            'payment_failed' => \App\Mail\PaymentFailed::class,
        ];
        
        return $emailClasses[$this->template] ?? null;
    }

    protected function getSMSMessage(array $data): ?string
    {
        $templates = [
            'order_placed' => "Your order #{$data['order_number']} has been placed successfully. Total: ₹{$data['order_total']}",
            'order_confirmed' => "Your order #{$data['order_number']} has been confirmed and will be processed soon.",
            'order_shipped' => "Your order #{$data['order_number']} has been shipped. Track: {$data['tracking_number']}",
            'order_delivered' => "Your order #{$data['order_number']} has been delivered. Thank you for shopping with BookBharat!",
            'order_cancelled' => "Your order #{$data['order_number']} has been cancelled. Refund will be processed if applicable.",
            'refund_processed' => "Refund for order #{$data['order_number']} has been processed. Amount: ₹{$data['refund_amount']}",
        ];
        
        return $templates[$this->template] ?? null;
    }

    protected function shouldSendSMS(): bool
    {
        $smsTemplates = [
            'order_placed', 'order_shipped', 'order_delivered', 
            'order_cancelled', 'refund_processed', 'payment_failed'
        ];
        
        return in_array($this->template, $smsTemplates);
    }

    protected function getPushTitle(): string
    {
        $titles = [
            'order_placed' => 'Order Placed Successfully',
            'order_confirmed' => 'Order Confirmed',
            'order_shipped' => 'Order Shipped',
            'order_delivered' => 'Order Delivered',
            'order_cancelled' => 'Order Cancelled',
            'refund_processed' => 'Refund Processed',
        ];
        
        return $titles[$this->template] ?? 'Order Update';
    }

    protected function getPushBody(array $data): string
    {
        $bodies = [
            'order_placed' => "Your order #{$data['order_number']} has been placed",
            'order_confirmed' => "Order #{$data['order_number']} is confirmed",
            'order_shipped' => "Order #{$data['order_number']} is on the way",
            'order_delivered' => "Order #{$data['order_number']} delivered",
            'order_cancelled' => "Order #{$data['order_number']} cancelled",
            'refund_processed' => "Refund processed for order #{$data['order_number']}",
        ];
        
        return $bodies[$this->template] ?? "Update for order #{$data['order_number']}";
    }

    protected function getNotificationTitle(): string
    {
        return $this->getPushTitle();
    }

    protected function getNotificationMessage(array $data): string
    {
        return $this->getPushBody($data);
    }
}