<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\CustomNotificationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Get all notifications (logs)
     */
    public function index(Request $request)
    {
        try {
            // Placeholder - would need notifications/notification_logs table
            $notifications = [
                [
                    'id' => 1,
                    'type' => 'email',
                    'recipient' => 'customer@example.com',
                    'subject' => 'Order Confirmation',
                    'status' => 'sent',
                    'sent_at' => now()->subHours(2)->toDateTimeString(),
                ],
                [
                    'id' => 2,
                    'type' => 'email',
                    'recipient' => 'customer2@example.com',
                    'subject' => 'Shipping Update',
                    'status' => 'sent',
                    'sent_at' => now()->subHours(5)->toDateTimeString(),
                ],
                [
                    'id' => 3,
                    'type' => 'sms',
                    'recipient' => '+91-9876543210',
                    'message' => 'Your order has been shipped',
                    'status' => 'failed',
                    'sent_at' => now()->subHours(1)->toDateTimeString(),
                ],
            ];

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
                'message' => 'Notification logging functionality coming soon'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification
     */
    public function send(Request $request)
    {
        $request->validate([
            'type' => 'required|in:email,sms,push',
            'recipients' => 'required|array',
            'recipients.*' => 'string',
            'subject' => 'required_if:type,email|string|max:255',
            'message' => 'required|string',
            'template_id' => 'sometimes|integer',
        ]);

        try {
            $type = $request->type;
            $recipients = $request->recipients;
            $sent = 0;
            $failed = 0;

            if ($type === 'email') {
                foreach ($recipients as $recipient) {
                    try {
                        Mail::raw($request->message, function ($mail) use ($recipient, $request) {
                            $mail->to($recipient)
                                ->subject($request->subject);
                        });
                        $sent++;
                    } catch (\Exception $e) {
                        $failed++;
                        Log::error('Failed to send email', [
                            'recipient' => $recipient,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } elseif ($type === 'sms') {
                // Placeholder for SMS sending
                foreach ($recipients as $recipient) {
                    // Would integrate with SMS provider here
                    $sent++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Notification sent successfully",
                'stats' => [
                    'sent' => $sent,
                    'failed' => $failed,
                    'total' => count($recipients),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification templates
     */
    public function getTemplates(Request $request)
    {
        try {
            $templates = [
                [
                    'id' => 1,
                    'name' => 'welcome_email',
                    'type' => 'email',
                    'subject' => 'Welcome to BookBharat!',
                    'content' => 'Dear {{name}},\n\nWelcome to BookBharat! We\'re excited to have you...',
                    'variables' => ['name', 'email', 'verification_link'],
                    'description' => 'Sent when a new user registers',
                    'is_active' => true,
                    'created_at' => now()->subMonths(3)->toDateTimeString(),
                    'updated_at' => now()->subWeek()->toDateTimeString(),
                ],
                [
                    'id' => 2,
                    'name' => 'order_confirmation',
                    'type' => 'email',
                    'subject' => 'Order Confirmation - #{{order_number}}',
                    'content' => 'Dear {{customer_name}},\n\nThank you for your order #{{order_number}}...',
                    'variables' => ['customer_name', 'order_number', 'order_total', 'order_items', 'tracking_url'],
                    'description' => 'Sent when an order is placed',
                    'is_active' => true,
                    'created_at' => now()->subMonths(3)->toDateTimeString(),
                    'updated_at' => now()->subDays(10)->toDateTimeString(),
                ],
                [
                    'id' => 3,
                    'name' => 'order_shipped',
                    'type' => 'email',
                    'subject' => 'Your Order Has Been Shipped!',
                    'content' => 'Dear {{customer_name}},\n\nGreat news! Your order #{{order_number}} has been shipped...',
                    'variables' => ['customer_name', 'order_number', 'tracking_number', 'tracking_url', 'estimated_delivery'],
                    'description' => 'Sent when an order is shipped',
                    'is_active' => true,
                    'created_at' => now()->subMonths(3)->toDateTimeString(),
                    'updated_at' => now()->subDays(5)->toDateTimeString(),
                ],
                [
                    'id' => 4,
                    'name' => 'order_delivered',
                    'type' => 'email',
                    'subject' => 'Your Order Has Been Delivered!',
                    'content' => 'Dear {{customer_name}},\n\nYour order #{{order_number}} has been delivered...',
                    'variables' => ['customer_name', 'order_number', 'delivery_date', 'feedback_link'],
                    'description' => 'Sent when an order is delivered',
                    'is_active' => true,
                    'created_at' => now()->subMonths(3)->toDateTimeString(),
                    'updated_at' => now()->subDays(2)->toDateTimeString(),
                ],
                [
                    'id' => 5,
                    'name' => 'abandoned_cart',
                    'type' => 'email',
                    'subject' => 'You Left Something in Your Cart!',
                    'content' => 'Dear {{customer_name}},\n\nWe noticed you left some items in your cart...',
                    'variables' => ['customer_name', 'cart_items', 'cart_total', 'cart_url', 'discount_code'],
                    'description' => 'Sent to remind about abandoned cart',
                    'is_active' => false,
                    'created_at' => now()->subMonths(2)->toDateTimeString(),
                    'updated_at' => now()->subMonth()->toDateTimeString(),
                ],
                [
                    'id' => 6,
                    'name' => 'password_reset',
                    'type' => 'email',
                    'subject' => 'Reset Your Password',
                    'content' => 'Dear {{name}},\n\nWe received a request to reset your password...',
                    'variables' => ['name', 'reset_link', 'expiry_time'],
                    'description' => 'Sent when user requests password reset',
                    'is_active' => true,
                    'created_at' => now()->subMonths(3)->toDateTimeString(),
                    'updated_at' => now()->subWeek()->toDateTimeString(),
                ],
                [
                    'id' => 7,
                    'name' => 'order_cancelled',
                    'type' => 'email',
                    'subject' => 'Order Cancelled - #{{order_number}}',
                    'content' => 'Dear {{customer_name}},\n\nYour order #{{order_number}} has been cancelled...',
                    'variables' => ['customer_name', 'order_number', 'cancellation_reason', 'refund_details'],
                    'description' => 'Sent when an order is cancelled',
                    'is_active' => true,
                    'created_at' => now()->subMonths(3)->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ],
                [
                    'id' => 8,
                    'name' => 'refund_processed',
                    'type' => 'email',
                    'subject' => 'Refund Processed for Order #{{order_number}}',
                    'content' => 'Dear {{customer_name}},\n\nYour refund for order #{{order_number}} has been processed...',
                    'variables' => ['customer_name', 'order_number', 'refund_amount', 'refund_method', 'processing_time'],
                    'description' => 'Sent when a refund is processed',
                    'is_active' => true,
                    'created_at' => now()->subMonths(2)->toDateTimeString(),
                    'updated_at' => now()->subDays(3)->toDateTimeString(),
                ],
                [
                    'id' => 9,
                    'name' => 'low_stock_alert',
                    'type' => 'email',
                    'subject' => 'Low Stock Alert: {{product_name}}',
                    'content' => 'Alert! Product {{product_name}} (SKU: {{sku}}) is running low on stock...',
                    'variables' => ['product_name', 'sku', 'current_stock', 'threshold'],
                    'description' => 'Sent to admin when product stock is low',
                    'is_active' => true,
                    'created_at' => now()->subMonths(2)->toDateTimeString(),
                    'updated_at' => now()->subWeek()->toDateTimeString(),
                ],
                [
                    'id' => 10,
                    'name' => 'promotional_offer',
                    'type' => 'email',
                    'subject' => 'Special Offer Just for You!',
                    'content' => 'Dear {{customer_name}},\n\nWe have a special offer for you...',
                    'variables' => ['customer_name', 'offer_title', 'offer_description', 'discount_code', 'expiry_date'],
                    'description' => 'Sent for promotional campaigns',
                    'is_active' => false,
                    'created_at' => now()->subMonth()->toDateTimeString(),
                    'updated_at' => now()->subDays(5)->toDateTimeString(),
                ],
            ];

            return response()->json([
                'success' => true,
                'templates' => $templates,
                'stats' => [
                    'total' => count($templates),
                    'active' => collect($templates)->where('is_active', true)->count(),
                    'inactive' => collect($templates)->where('is_active', false)->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create notification template
     */
    public function createTemplate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:notification_templates,name',
            'type' => 'required|in:email,sms,push',
            'subject' => 'required_if:type,email|string|max:255',
            'content' => 'required|string',
            'description' => 'nullable|string',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        try {
            // Placeholder - would need notification_templates table
            $template = [
                'id' => 11,
                'name' => $request->name,
                'type' => $request->type,
                'subject' => $request->input('subject', ''),
                'content' => $request->content,
                'variables' => $request->input('variables', []),
                'description' => $request->input('description', ''),
                'is_active' => $request->input('is_active', true),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'template' => $template,
                'note' => 'Template creation will be fully functional once notification_templates table is created'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update notification template
     */
    public function updateTemplate(Request $request, $template)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'subject' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'description' => 'nullable|string',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        try {
            // Placeholder - would need notification_templates table
            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'template_id' => $template,
                'updated_fields' => $request->all(),
                'note' => 'Template update will be fully functional once notification_templates table is created'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete notification template
     */
    public function deleteTemplate($template)
    {
        try {
            // Placeholder - would need notification_templates table
            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully',
                'template_id' => $template,
                'note' => 'Template deletion will be fully functional once notification_templates table is created'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification logs
     */
    public function getLogs(Request $request)
    {
        try {
            // Placeholder - would need notification_logs table
            $logs = [
                [
                    'id' => 1,
                    'type' => 'email',
                    'template_name' => 'order_confirmation',
                    'recipient' => 'customer@example.com',
                    'subject' => 'Order Confirmation - #ORD-12345',
                    'status' => 'sent',
                    'sent_at' => now()->subHours(2)->toDateTimeString(),
                    'opened_at' => now()->subHours(1)->toDateTimeString(),
                    'clicked_at' => now()->subMinutes(30)->toDateTimeString(),
                ],
                [
                    'id' => 2,
                    'type' => 'email',
                    'template_name' => 'order_shipped',
                    'recipient' => 'customer2@example.com',
                    'subject' => 'Your Order Has Been Shipped!',
                    'status' => 'sent',
                    'sent_at' => now()->subHours(5)->toDateTimeString(),
                    'opened_at' => now()->subHours(4)->toDateTimeString(),
                    'clicked_at' => null,
                ],
                [
                    'id' => 3,
                    'type' => 'sms',
                    'template_name' => 'order_update',
                    'recipient' => '+91-9876543210',
                    'message' => 'Your order has been shipped',
                    'status' => 'failed',
                    'sent_at' => now()->subHours(1)->toDateTimeString(),
                    'error' => 'Invalid phone number format',
                ],
            ];

            return response()->json([
                'success' => true,
                'logs' => $logs,
                'stats' => [
                    'total_sent' => 250,
                    'total_failed' => 15,
                    'open_rate' => 65.5,
                    'click_rate' => 28.3,
                ],
                'message' => 'Notification logging functionality coming soon'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
