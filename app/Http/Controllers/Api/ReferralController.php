<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SocialCommerceService;
use App\Models\ReferralCode;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReferralController extends Controller
{
    protected $socialCommerceService;

    public function __construct(SocialCommerceService $socialCommerceService)
    {
        $this->socialCommerceService = $socialCommerceService;
    }

    public function validateReferralCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'order_amount' => 'nullable|numeric|min:0',
        ]);

        $referral = ReferralCode::where('code', strtoupper($request->code))
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->with('user:id,name,email')
            ->first();

        if (!$referral) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired referral code'
            ], 404);
        }

        if ($referral->usage_limit && $referral->usage_count >= $referral->usage_limit) {
            return response()->json([
                'success' => false,
                'message' => 'Referral code usage limit reached'
            ], 400);
        }

        $orderAmount = $request->input('order_amount', 0);
        
        if ($orderAmount > 0 && $orderAmount < $referral->min_order_amount) {
            return response()->json([
                'success' => false,
                'message' => "Minimum order amount of {$referral->formatted_min_order_amount} required for this referral code"
            ], 400);
        }

        $discountAmount = 0;
        if ($orderAmount > 0) {
            if ($referral->discount_type === 'percentage') {
                $discountAmount = ($orderAmount * $referral->discount_value) / 100;
            } else {
                $discountAmount = min($referral->discount_value, $orderAmount);
            }
        }

        return response()->json([
            'success' => true,
            'referral' => [
                'id' => $referral->id,
                'code' => $referral->code,
                'discount_type' => $referral->discount_type,
                'discount_value' => $referral->discount_value,
                'min_order_amount' => $referral->min_order_amount,
                'usage_count' => $referral->usage_count,
                'usage_limit' => $referral->usage_limit,
                'expires_at' => $referral->expires_at,
                'referrer' => $referral->user,
                'discount_amount' => $discountAmount,
                'final_amount' => max(0, $orderAmount - $discountAmount),
            ]
        ]);
    }

    public function applyReferralCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'order_id' => 'required|exists:orders,id',
        ]);

        try {
            $order = Order::findOrFail($request->order_id);
            
            if ($order->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to modify this order'
                ], 403);
            }

            if ($order->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot apply referral code to processed order'
                ], 400);
            }

            $result = $this->socialCommerceService->trackReferralUsage($request->code, $order);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid referral code or order does not meet requirements'
                ], 400);
            }

            $order->update([
                'referral_code_id' => $result['referral']->id,
                'referral_discount' => $result['discount_applied'],
                'total_amount' => $order->total_amount - $result['discount_applied'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Referral code applied successfully',
                'discount_applied' => $result['discount_applied'],
                'new_total' => $order->total_amount,
                'commission_earned' => $result['commission_earned'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply referral code: ' . $e->getMessage()
            ], 400);
        }
    }

    public function getReferralStats(Request $request)
    {
        $user = Auth::user();
        
        $referrals = $user->referralCodes()->get();
        
        $totalUsage = $referrals->sum('usage_count');
        $totalRevenue = $referrals->sum('total_revenue');
        $totalCommission = $referrals->sum('commission_earned');
        
        $recentUsages = collect();
        foreach ($referrals as $referral) {
            $usages = $referral->usages()
                             ->with('order.user:id,name,email')
                             ->latest()
                             ->limit(10)
                             ->get();
            $recentUsages = $recentUsages->merge($usages);
        }
        
        $recentUsages = $recentUsages->sortByDesc('created_at')->take(20);

        $monthlyStats = $user->referralCodes()
                            ->selectRaw('
                                YEAR(created_at) as year,
                                MONTH(created_at) as month,
                                COUNT(*) as codes_created,
                                SUM(usage_count) as total_uses,
                                SUM(commission_earned) as monthly_commission,
                                SUM(total_revenue) as monthly_revenue
                            ')
                            ->where('created_at', '>=', now()->subMonths(12))
                            ->groupBy('year', 'month')
                            ->orderBy('year', 'desc')
                            ->orderBy('month', 'desc')
                            ->get();

        return response()->json([
            'success' => true,
            'stats' => [
                'total_referral_codes' => $referrals->count(),
                'active_referral_codes' => $referrals->where('is_active', true)->count(),
                'total_usage' => $totalUsage,
                'total_revenue_generated' => $totalRevenue,
                'total_commission_earned' => $totalCommission,
                'average_commission_per_use' => $totalUsage > 0 ? $totalCommission / $totalUsage : 0,
                'top_performing_code' => $referrals->sortByDesc('commission_earned')->first(),
            ],
            'recent_usages' => $recentUsages,
            'monthly_stats' => $monthlyStats,
        ]);
    }

    public function getPublicReferralInfo(string $code)
    {
        $referral = ReferralCode::where('code', strtoupper($code))
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->with('user:id,name')
            ->first();

        if (!$referral) {
            return response()->json([
                'success' => false,
                'message' => 'Referral code not found or expired'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'referral' => [
                'code' => $referral->code,
                'discount_type' => $referral->discount_type,
                'discount_value' => $referral->discount_value,
                'min_order_amount' => $referral->min_order_amount,
                'expires_at' => $referral->expires_at,
                'referrer_name' => $referral->user->name,
                'remaining_uses' => $referral->usage_limit ? max(0, $referral->usage_limit - $referral->usage_count) : null,
            ]
        ]);
    }

    public function toggleReferralCode(Request $request, ReferralCode $referralCode)
    {
        if ($referralCode->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify this referral code'
            ], 403);
        }

        $referralCode->update([
            'is_active' => !$referralCode->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => $referralCode->is_active ? 'Referral code activated' : 'Referral code deactivated',
            'is_active' => $referralCode->is_active
        ]);
    }

    public function updateReferralCode(Request $request, ReferralCode $referralCode)
    {
        if ($referralCode->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify this referral code'
            ], 403);
        }

        $request->validate([
            'usage_limit' => 'nullable|integer|min:' . $referralCode->usage_count,
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'nullable|boolean',
        ]);

        $updateData = $request->only(['usage_limit', 'expires_at', 'is_active']);
        
        $referralCode->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Referral code updated successfully',
            'referral_code' => $referralCode->fresh()
        ]);
    }

    public function deleteReferralCode(ReferralCode $referralCode)
    {
        if ($referralCode->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this referral code'
            ], 403);
        }

        if ($referralCode->usage_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete referral code that has been used'
            ], 400);
        }

        $referralCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Referral code deleted successfully'
        ]);
    }

    public function getLeaderboard()
    {
        $topReferrers = ReferralCode::with('user:id,name')
            ->selectRaw('user_id, SUM(commission_earned) as total_commission, SUM(usage_count) as total_referrals, SUM(total_revenue) as total_revenue')
            ->groupBy('user_id')
            ->orderBy('total_commission', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'leaderboard' => $topReferrers,
            'user_rank' => $this->getUserRank(Auth::id(), $topReferrers)
        ]);
    }

    protected function getUserRank($userId, $leaderboard)
    {
        foreach ($leaderboard as $index => $entry) {
            if ($entry->user_id == $userId) {
                return [
                    'rank' => $index + 1,
                    'total_commission' => $entry->total_commission,
                    'total_referrals' => $entry->total_referrals,
                    'total_revenue' => $entry->total_revenue,
                ];
            }
        }

        $userStats = ReferralCode::where('user_id', $userId)
            ->selectRaw('SUM(commission_earned) as total_commission, SUM(usage_count) as total_referrals, SUM(total_revenue) as total_revenue')
            ->first();

        return [
            'rank' => 'Unranked',
            'total_commission' => $userStats->total_commission ?? 0,
            'total_referrals' => $userStats->total_referrals ?? 0,
            'total_revenue' => $userStats->total_revenue ?? 0,
        ];
    }
}