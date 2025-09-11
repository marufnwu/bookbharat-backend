<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NewsletterController extends Controller
{
    /**
     * Subscribe to newsletter
     */
    public function subscribe(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
                'name' => 'nullable|string|max:255',
                'preferences' => 'nullable|array',
                'preferences.*' => 'string|in:books,offers,news,events',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email address provided.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $validator->validated()['email'];
            $name = $validator->validated()['name'] ?? null;
            $preferences = $validator->validated()['preferences'] ?? ['books', 'offers'];

            // Check if already subscribed
            $existingSubscription = $this->getSubscriptionByEmail($email);
            
            if ($existingSubscription) {
                // Update existing subscription
                $this->updateSubscription($email, $name, $preferences);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Your newsletter preferences have been updated successfully!',
                ], 200);
            }

            // Create new subscription
            $subscriptionData = [
                'email' => $email,
                'name' => $name,
                'preferences' => json_encode($preferences),
                'status' => 'active',
                'subscribed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'source' => $request->get('source', 'website'),
            ];

            // If user is authenticated, link subscription to user
            if (auth()->check()) {
                $subscriptionData['user_id'] = auth()->id();
            }

            // Store subscription (this would typically go to a newsletters table)
            $this->storeSubscription($subscriptionData);

            // Send welcome email
            $this->sendWelcomeEmail($email, $name);

            // Log subscription
            Log::info('Newsletter subscription', [
                'email' => $email,
                'preferences' => $preferences,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for subscribing! You will receive a confirmation email shortly.',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Newsletter subscription error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to subscribe at this time. Please try again later.',
            ], 500);
        }
    }

    /**
     * Unsubscribe from newsletter
     */
    public function unsubscribe(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
                'token' => 'nullable|string', // For secure unsubscribe links
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email address provided.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $validator->validated()['email'];

            // Check if subscription exists
            $subscription = $this->getSubscriptionByEmail($email);
            
            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email address is not subscribed to our newsletter.',
                ], 404);
            }

            // Update subscription status
            $this->updateSubscriptionStatus($email, 'unsubscribed');

            // Send confirmation email
            $this->sendUnsubscribeConfirmation($email);

            // Log unsubscription
            Log::info('Newsletter unsubscription', [
                'email' => $email,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'You have been successfully unsubscribed from our newsletter.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Newsletter unsubscription error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to unsubscribe at this time. Please try again later.',
            ], 500);
        }
    }

    /**
     * Update newsletter preferences
     */
    public function updatePreferences(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
                'preferences' => 'required|array',
                'preferences.*' => 'string|in:books,offers,news,events',
                'name' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid data provided.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $validator->validated()['email'];
            $preferences = $validator->validated()['preferences'];
            $name = $validator->validated()['name'] ?? null;

            // Check if subscription exists
            $subscription = $this->getSubscriptionByEmail($email);
            
            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email address is not subscribed to our newsletter.',
                ], 404);
            }

            // Update preferences
            $this->updateSubscription($email, $name, $preferences);

            return response()->json([
                'success' => true,
                'message' => 'Your newsletter preferences have been updated successfully!',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Newsletter preferences update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to update preferences at this time. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get subscription status
     */
    public function getStatus(Request $request)
    {
        try {
            $email = $request->get('email');
            
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Valid email address is required.',
                ], 422);
            }

            $subscription = $this->getSubscriptionByEmail($email);
            
            if (!$subscription) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscribed' => false,
                        'email' => $email,
                    ]
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subscribed' => $subscription['status'] === 'active',
                    'email' => $email,
                    'preferences' => json_decode($subscription['preferences'], true),
                    'subscribed_at' => $subscription['subscribed_at'],
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Newsletter status check error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to check subscription status.',
            ], 500);
        }
    }

    /**
     * Store subscription (simplified - in production, use a database table)
     */
    private function storeSubscription($data)
    {
        // For now, store in a simple file-based system
        // In production, you'd use a newsletters table in database
        $subscriptionsFile = storage_path('app/newsletter_subscriptions.json');
        
        $subscriptions = [];
        if (file_exists($subscriptionsFile)) {
            $subscriptions = json_decode(file_get_contents($subscriptionsFile), true) ?: [];
        }
        
        $subscriptions[$data['email']] = $data;
        
        file_put_contents($subscriptionsFile, json_encode($subscriptions, JSON_PRETTY_PRINT));
    }

    /**
     * Get subscription by email
     */
    private function getSubscriptionByEmail($email)
    {
        $subscriptionsFile = storage_path('app/newsletter_subscriptions.json');
        
        if (!file_exists($subscriptionsFile)) {
            return null;
        }
        
        $subscriptions = json_decode(file_get_contents($subscriptionsFile), true) ?: [];
        
        return $subscriptions[$email] ?? null;
    }

    /**
     * Update subscription
     */
    private function updateSubscription($email, $name, $preferences)
    {
        $subscription = $this->getSubscriptionByEmail($email);
        
        if ($subscription) {
            $subscription['name'] = $name;
            $subscription['preferences'] = json_encode($preferences);
            $subscription['updated_at'] = now()->toISOString();
            
            $this->storeSubscription($subscription);
        }
    }

    /**
     * Update subscription status
     */
    private function updateSubscriptionStatus($email, $status)
    {
        $subscription = $this->getSubscriptionByEmail($email);
        
        if ($subscription) {
            $subscription['status'] = $status;
            $subscription['updated_at'] = now()->toISOString();
            
            if ($status === 'unsubscribed') {
                $subscription['unsubscribed_at'] = now()->toISOString();
            }
            
            $this->storeSubscription($subscription);
        }
    }

    /**
     * Send welcome email
     */
    private function sendWelcomeEmail($email, $name = null)
    {
        try {
            $content = $this->formatWelcomeEmail($name, $email);
            
            Mail::raw($content, function ($message) use ($email, $name) {
                $message->to($email, $name)
                       ->subject('Welcome to BookBharat Newsletter!')
                       ->from(config('mail.from.address'), config('mail.from.name', 'BookBharat'));
            });

        } catch (\Exception $e) {
            Log::error('Failed to send newsletter welcome email: ' . $e->getMessage());
        }
    }

    /**
     * Send unsubscribe confirmation
     */
    private function sendUnsubscribeConfirmation($email)
    {
        try {
            $content = $this->formatUnsubscribeEmail();
            
            Mail::raw($content, function ($message) use ($email) {
                $message->to($email)
                       ->subject('You have been unsubscribed from BookBharat Newsletter')
                       ->from(config('mail.from.address'), config('mail.from.name', 'BookBharat'));
            });

        } catch (\Exception $e) {
            Log::error('Failed to send unsubscribe confirmation email: ' . $e->getMessage());
        }
    }

    /**
     * Format welcome email
     */
    private function formatWelcomeEmail($name, $email = null)
    {
        $greeting = $name ? "Dear {$name}" : "Hello there";
        $emailText = $email ?: 'your subscribed email address';
        
        return "
{$greeting},

Welcome to the BookBharat Newsletter!

Thank you for subscribing to our newsletter. You'll now receive:
• Latest book releases and recommendations
• Exclusive offers and discounts
• Updates on literary events and author spotlights
• Curated reading lists and book reviews

We're excited to have you as part of our reading community!

You can update your preferences or unsubscribe at any time by visiting your account settings or clicking the unsubscribe link in any of our emails.

Happy reading!

The BookBharat Team

---
This email was sent to {$emailText}.
If you didn't subscribe to this newsletter, please ignore this email.
        ";
    }

    /**
     * Format unsubscribe email
     */
    private function formatUnsubscribeEmail()
    {
        return "
Hello,

You have been successfully unsubscribed from the BookBharat Newsletter.

We're sorry to see you go! If you change your mind, you can always resubscribe by visiting our website or using any of our newsletter signup forms.

If this was a mistake, you can resubscribe at: " . config('app.url') . "/newsletter/subscribe

Thank you for being part of our reading community.

Best regards,
The BookBharat Team

---
This is a confirmation email for your unsubscription request.
        ";
    }
}