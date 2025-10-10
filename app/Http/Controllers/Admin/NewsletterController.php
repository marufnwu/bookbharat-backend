<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class NewsletterController extends Controller
{
    /**
     * Get newsletter settings
     */
    public function getSettings()
    {
        $settings = NewsletterSetting::getAllGrouped();

        // Convert to a more usable format
        $formatted = [];
        foreach ($settings as $group => $groupSettings) {
            $formatted[$group] = [];
            foreach ($groupSettings as $setting) {
                $formatted[$group][$setting->key] = $setting->value;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }

    /**
     * Update newsletter settings
     */
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'general.sender_name' => 'nullable|string|max:255',
            'general.sender_email' => 'nullable|email|max:255',
            'general.reply_to_email' => 'nullable|email|max:255',
            'general.double_opt_in' => 'nullable|boolean',
            'general.welcome_email_enabled' => 'nullable|boolean',
            'general.unsubscribe_confirmation_enabled' => 'nullable|boolean',

            'subscription.available_preferences' => 'nullable|array',
            'subscription.default_preferences' => 'nullable|array',
            'subscription.gdpr_consent_required' => 'nullable|boolean',
            'subscription.gdpr_consent_text' => 'nullable|string',

            'email.welcome_subject' => 'nullable|string|max:255',
            'email.welcome_body' => 'nullable|string',
            'email.unsubscribe_subject' => 'nullable|string|max:255',
            'email.unsubscribe_body' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $settings = $request->all();

        // Save each setting
        foreach ($settings as $group => $groupSettings) {
            foreach ($groupSettings as $key => $value) {
                NewsletterSetting::setValue($key, $value, $group);
            }
        }

        // Clear cache
        Cache::forget('newsletter_settings');

        return response()->json([
            'success' => true,
            'message' => 'Newsletter settings updated successfully',
        ]);
    }

    /**
     * Get all subscribers with pagination and filters
     */
    public function getSubscribers(Request $request)
    {
        $query = NewsletterSubscriber::query();

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search by email or name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by preference
        if ($request->has('preference') && $request->preference) {
            $query->whereJsonContains('preferences', $request->preference);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->get('per_page', 20);
        $subscribers = $query->with('user')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $subscribers,
        ]);
    }

    /**
     * Get subscriber stats
     */
    public function getStats()
    {
        $stats = [
            'total' => NewsletterSubscriber::count(),
            'active' => NewsletterSubscriber::where('status', 'active')->count(),
            'unsubscribed' => NewsletterSubscriber::where('status', 'unsubscribed')->count(),
            'bounced' => NewsletterSubscriber::where('status', 'bounced')->count(),
            'complained' => NewsletterSubscriber::where('status', 'complained')->count(),
            'this_month' => NewsletterSubscriber::whereMonth('subscribed_at', now()->month)
                ->whereYear('subscribed_at', now()->year)
                ->count(),
            'this_week' => NewsletterSubscriber::whereBetween('subscribed_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'today' => NewsletterSubscriber::whereDate('subscribed_at', today())->count(),
        ];

        // Preferences breakdown
        $allSubscribers = NewsletterSubscriber::active()->get();
        $preferenceStats = [
            'books' => 0,
            'offers' => 0,
            'news' => 0,
            'events' => 0,
        ];

        foreach ($allSubscribers as $subscriber) {
            $prefs = $subscriber->preferences ?? [];
            foreach ($prefs as $pref) {
                if (isset($preferenceStats[$pref])) {
                    $preferenceStats[$pref]++;
                }
            }
        }

        $stats['preferences'] = $preferenceStats;

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Update subscriber status
     */
    public function updateSubscriberStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,unsubscribed,bounced,complained',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $subscriber = NewsletterSubscriber::findOrFail($id);
        $subscriber->status = $request->status;

        if ($request->status === 'unsubscribed') {
            $subscriber->unsubscribed_at = now();
        }

        $subscriber->save();

        return response()->json([
            'success' => true,
            'message' => 'Subscriber status updated successfully',
            'data' => $subscriber,
        ]);
    }

    /**
     * Delete subscriber
     */
    public function deleteSubscriber($id)
    {
        $subscriber = NewsletterSubscriber::findOrFail($id);
        $subscriber->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subscriber deleted successfully',
        ]);
    }

    /**
     * Bulk delete subscribers
     */
    public function bulkDeleteSubscribers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:newsletter_subscribers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        NewsletterSubscriber::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => count($request->ids) . ' subscribers deleted successfully',
        ]);
    }

    /**
     * Export subscribers
     */
    public function exportSubscribers(Request $request)
    {
        $query = NewsletterSubscriber::query();

        // Apply same filters as getSubscribers
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $subscribers = $query->get();

        // Generate CSV
        $csv = "Email,Name,Status,Preferences,Subscribed At,Source\n";

        foreach ($subscribers as $subscriber) {
            $preferences = implode(';', $subscriber->preferences ?? []);
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s"' . "\n",
                $subscriber->email,
                $subscriber->name ?? '',
                $subscriber->status,
                $preferences,
                $subscriber->subscribed_at ? $subscriber->subscribed_at->format('Y-m-d H:i:s') : '',
                $subscriber->source
            );
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="newsletter_subscribers_' . date('Y-m-d') . '.csv"');
    }
}

