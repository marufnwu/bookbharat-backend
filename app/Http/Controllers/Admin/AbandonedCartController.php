<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PersistentCart;
use App\Jobs\SendAbandonedCartEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AbandonedCartController extends Controller
{
    /**
     * List all abandoned carts
     */
    public function index(Request $request)
    {
        $query = PersistentCart::with('user')
            ->where('is_abandoned', true)
            ->orderBy('abandoned_at', 'desc');

        // Search by user email or session_id
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('session_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by recovery email count
        if ($request->has('recovery_emails')) {
            $query->where('recovery_email_count', $request->recovery_emails);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $carts = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $carts->items(),
            'pagination' => [
                'current_page' => $carts->currentPage(),
                'per_page' => $carts->perPage(),
                'total' => $carts->total(),
                'last_page' => $carts->lastPage(),
            ]
        ]);
    }

    /**
     * Get abandoned cart details
     */
    public function show($id)
    {
        $cart = PersistentCart::with('user')->findOrFail($id);

        if (!$cart->is_abandoned) {
            return response()->json([
                'success' => false,
                'message' => 'This cart is not abandoned'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $cart
        ]);
    }

    /**
     * Send manual recovery email
     */
    public function sendRecoveryEmail(Request $request, $id)
    {
        $cart = PersistentCart::with('user')->findOrFail($id);

        if (!$cart->is_abandoned) {
            return response()->json([
                'success' => false,
                'message' => 'This cart is not abandoned'
            ], 400);
        }

        if (!$cart->user) {
            return response()->json([
                'success' => false,
                'message' => 'Cart has no associated user'
            ], 400);
        }

        try {
            // Determine email type (first, second, or final)
            $emailCount = $cart->recovery_email_count;
            $emailType = match(true) {
                $emailCount === 0 => 'first_reminder',
                $emailCount === 1 => 'second_reminder',
                default => 'final_reminder'
            };

            // Dispatch job to send email
            SendAbandonedCartEmail::dispatch($cart->id, $emailType);

            Log::info('Manual abandoned cart recovery email dispatched', [
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'email_type' => $emailType
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recovery email sent successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send recovery email', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send recovery email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete abandoned cart
     */
    public function destroy($id)
    {
        $cart = PersistentCart::findOrFail($id);

        if (!$cart->is_abandoned) {
            return response()->json([
                'success' => false,
                'message' => 'This cart is not abandoned'
            ], 400);
        }

        $cart->delete();

        return response()->json([
            'success' => true,
            'message' => 'Abandoned cart deleted successfully'
        ]);
    }

    /**
     * Get abandoned cart statistics
     */
    public function statistics()
    {
        $stats = [
            'total_abandoned' => PersistentCart::where('is_abandoned', true)->count(),
            'total_value' => PersistentCart::where('is_abandoned', true)->sum('total_amount'),
            'by_recovery_count' => [
                'none' => PersistentCart::where('is_abandoned', true)->where('recovery_email_count', 0)->count(),
                'one' => PersistentCart::where('is_abandoned', true)->where('recovery_email_count', 1)->count(),
                'two' => PersistentCart::where('is_abandoned', true)->where('recovery_email_count', 2)->count(),
                'three_plus' => PersistentCart::where('is_abandoned', true)->where('recovery_email_count', '>=', 3)->count(),
            ],
            'recent_abandoned' => PersistentCart::where('is_abandoned', true)
                ->where('abandoned_at', '>=', now()->subDays(7))
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
