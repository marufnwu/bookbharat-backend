<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EmailTemplateController extends Controller
{
    /**
     * List all email templates
     */
    public function index()
    {
        $templates = EmailTemplate::orderBy('type')->get()->groupBy('language');

        return response()->json([
            'success' => true,
            'templates' => $templates
        ]);
    }

    /**
     * Get single template
     */
    public function show($id)
    {
        $template = EmailTemplate::findOrFail($id);

        return response()->json([
            'success' => true,
            'template' => $template
        ]);
    }

    /**
     * Update template
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string',
            'html_content' => 'required|string',
            'text_content' => 'nullable|string',
            'variables' => 'nullable|array',
            'from_name' => 'nullable|string',
            'from_email' => 'nullable|email',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $template = EmailTemplate::findOrFail($id);
        $template->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'template' => $template
        ]);
    }

    /**
     * Preview template with sample data
     */
    public function preview(Request $request, $id)
    {
        $template = EmailTemplate::findOrFail($id);

        // Generate sample data for preview
        $sampleData = $this->getSampleData($template->type);

        // Render template
        $rendered = $template->render($sampleData);

        return response()->json([
            'success' => true,
            'preview' => $rendered
        ]);
    }

    /**
     * Send test email
     */
    public function sendTest(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $template = EmailTemplate::findOrFail($id);
        $email = $request->input('email');

        // Generate sample data
        $sampleData = $this->getSampleData($template->type);

        // Render template
        $rendered = $template->render($sampleData);

        // Send test email
        try {
            Mail::raw($rendered['text_content'] ?: strip_tags($rendered['content']), function ($message) use ($email, $rendered, $template) {
                $message->to($email)
                    ->from($template->from_email ?? config('mail.from.address'), $template->from_name ?? config('mail.from.name'))
                    ->subject('TEST: ' . $rendered['subject']);

                if ($template->html_content) {
                    $message->html($rendered['content']);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sample data based on template type
     */
    private function getSampleData(string $type): array
    {
        $samples = [
            'order_confirmation' => [
                'order_id' => '#ORD-12345',
                'customer_name' => 'John Doe',
                'order_total' => '₹1,299.00',
                'order_items' => [
                    ['name' => 'Sample Book 1', 'quantity' => 2, 'price' => 299.00],
                    ['name' => 'Sample Book 2', 'quantity' => 1, 'price' => 399.00],
                ],
                'shipping_address' => '123 Main St, City, State, 12345',
                'payment_method' => 'Credit Card',
            ],
            'order_shipped' => [
                'order_id' => '#ORD-12345',
                'customer_name' => 'John Doe',
                'tracking_number' => 'TRK123456789',
                'carrier' => 'Speed Post',
                'estimated_delivery' => '3-5 business days',
            ],
            'order_delivered' => [
                'order_id' => '#ORD-12345',
                'customer_name' => 'John Doe',
                'delivery_date' => date('F j, Y'),
            ],
            'order_cancelled' => [
                'order_id' => '#ORD-12345',
                'customer_name' => 'John Doe',
                'cancellation_reason' => 'Customer requested cancellation',
                'refund_amount' => '₹1,299.00',
            ],
            'welcome_email' => [
                'customer_name' => 'John Doe',
                'email' => 'john@example.com',
                'activation_link' => config('app.url') . '/activate/token123',
            ],
            'password_reset' => [
                'customer_name' => 'John Doe',
                'reset_link' => config('app.url') . '/reset-password/token123',
                'expiry_time' => '60 minutes',
            ],
            'abandoned_cart' => [
                'customer_name' => 'John Doe',
                'cart_items' => [
                    ['name' => 'Sample Book', 'quantity' => 1, 'price' => 299.00],
                ],
                'cart_total' => '₹299.00',
                'cart_link' => config('app.url') . '/cart',
            ],
            'payment_success' => [
                'order_id' => '#ORD-12345',
                'payment_amount' => '₹1,299.00',
                'payment_method' => 'Credit Card',
                'transaction_id' => 'TXN123456789',
            ],
            'payment_failed' => [
                'order_id' => '#ORD-12345',
                'payment_amount' => '₹1,299.00',
                'failure_reason' => 'Insufficient funds',
                'retry_link' => config('app.url') . '/checkout',
            ],
            'newsletter_welcome' => [
                'subscriber_name' => 'John Doe',
                'unsubscribe_link' => config('app.url') . '/unsubscribe/token123',
            ],
        ];

        return $samples[$type] ?? [];
    }
}
