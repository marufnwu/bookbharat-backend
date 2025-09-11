<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    /**
     * Submit contact form
     */
    public function submit(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|min:2|max:255',
                'email' => 'required|email|max:255',
                'subject' => 'required|string|min:5|max:255',
                'category' => 'required|string|in:order,shipping,returns,technical,general,feedback',
                'message' => 'required|string|min:10|max:2000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $contactData = $validator->validated();
            
            // Add timestamp and IP for tracking
            $contactData['submitted_at'] = now();
            $contactData['ip_address'] = $request->ip();
            $contactData['user_agent'] = $request->userAgent();
            
            // If user is authenticated, add user ID
            if (auth()->check()) {
                $contactData['user_id'] = auth()->id();
            }

            // Log the contact submission
            Log::channel('contact')->info('Contact form submission', $contactData);

            // Send email notification to admin
            $this->sendAdminNotification($contactData);
            
            // Send confirmation email to user
            $this->sendUserConfirmation($contactData);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for contacting us! We have received your message and will respond within 24 hours.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Contact form submission error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while submitting your message. Please try again or contact us directly.',
            ], 500);
        }
    }

    /**
     * Send notification email to admin
     */
    private function sendAdminNotification($data)
    {
        try {
            $adminEmail = config('mail.admin_email', 'admin@bookbharat.com');
            
            $emailData = [
                'subject' => 'New Contact Form Submission - ' . $data['category'],
                'name' => $data['name'],
                'email' => $data['email'],
                'category' => $data['category'],
                'subject_line' => $data['subject'],
                'message' => $data['message'],
                'submitted_at' => $data['submitted_at'],
                'user_id' => $data['user_id'] ?? 'Guest',
                'ip_address' => $data['ip_address']
            ];

            // Simple email for now - in production, you'd use a proper email template
            Mail::raw($this->formatAdminEmail($emailData), function ($message) use ($adminEmail, $data) {
                $message->to($adminEmail)
                       ->subject('New Contact Form: ' . $data['category'] . ' - ' . $data['subject'])
                       ->replyTo($data['email'], $data['name']);
            });

        } catch (\Exception $e) {
            Log::error('Failed to send admin notification email: ' . $e->getMessage());
        }
    }

    /**
     * Send confirmation email to user
     */
    private function sendUserConfirmation($data)
    {
        try {
            $emailContent = $this->formatUserConfirmationEmail($data);
            
            Mail::raw($emailContent, function ($message) use ($data) {
                $message->to($data['email'], $data['name'])
                       ->subject('Thank you for contacting BookBharat - We have received your message')
                       ->from(config('mail.from.address'), config('mail.from.name', 'BookBharat Support'));
            });

        } catch (\Exception $e) {
            Log::error('Failed to send user confirmation email: ' . $e->getMessage());
        }
    }

    /**
     * Format admin notification email
     */
    private function formatAdminEmail($data)
    {
        return "
New Contact Form Submission
==========================

From: {$data['name']} ({$data['email']})
Category: {$data['category']}
Subject: {$data['subject_line']}
Submitted: {$data['submitted_at']}
User ID: {$data['user_id']}
IP Address: {$data['ip_address']}

Message:
--------
{$data['message']}

---
This is an automated message from BookBharat Contact Form.
Reply directly to this email to respond to the customer.
        ";
    }

    /**
     * Format user confirmation email
     */
    private function formatUserConfirmationEmail($data)
    {
        $categoryLabels = [
            'order' => 'Order Support',
            'shipping' => 'Shipping & Delivery',
            'returns' => 'Returns & Refunds',
            'technical' => 'Technical Issues',
            'general' => 'General Inquiry',
            'feedback' => 'Feedback & Suggestions'
        ];

        $categoryLabel = $categoryLabels[$data['category']] ?? $data['category'];

        return "
Dear {$data['name']},

Thank you for contacting BookBharat! We have received your message and wanted to confirm that it has been successfully submitted.

Your Message Details:
---------------------
Category: {$categoryLabel}
Subject: {$data['subject']}
Submitted on: {$data['submitted_at']->format('F j, Y \a\t g:i A')}

What happens next?
- Our support team will review your message within 24 hours
- You will receive a detailed response via email
- For urgent matters, you can also call us at +91 12345 67890

If you have any additional questions or concerns, please don't hesitate to reach out.

Best regards,
BookBharat Customer Support Team

---
This is an automated confirmation email. Please do not reply to this message.
For support, visit: https://bookbharat.com/contact
        ";
    }

    /**
     * Get contact categories
     */
    public function getCategories()
    {
        $categories = [
            [
                'value' => 'order',
                'label' => 'Order Support',
                'description' => 'Questions about your orders, order status, or order modifications'
            ],
            [
                'value' => 'shipping',
                'label' => 'Shipping & Delivery',
                'description' => 'Delivery tracking, shipping costs, and delivery issues'
            ],
            [
                'value' => 'returns',
                'label' => 'Returns & Refunds',
                'description' => 'Return requests, refund status, and return policies'
            ],
            [
                'value' => 'technical',
                'label' => 'Technical Issues',
                'description' => 'Website problems, account issues, and technical support'
            ],
            [
                'value' => 'general',
                'label' => 'General Inquiry',
                'description' => 'General questions about our services and policies'
            ],
            [
                'value' => 'feedback',
                'label' => 'Feedback & Suggestions',
                'description' => 'Share your thoughts and suggestions for improvement'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $categories
        ], 200);
    }
}