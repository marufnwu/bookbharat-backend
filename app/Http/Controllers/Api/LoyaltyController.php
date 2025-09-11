<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyProgram;
use App\Models\CustomerPoint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoyaltyController extends Controller
{
    /**
     * Get customer's loyalty status and points
     */
    public function getCustomerLoyalty(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $currentProgram = LoyaltyProgram::active()
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->first();

        if (!$currentProgram) {
            return response()->json(['error' => 'No active loyalty program'], 404);
        }

        $totalPoints = $user->points()
            ->where('loyalty_program_id', $currentProgram->id)
            ->sum('points');

        $availablePoints = $user->points()
            ->where('loyalty_program_id', $currentProgram->id)
            ->where('transaction_type', '!=', 'redeemed')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->sum('points');

        $recentTransactions = $user->points()
            ->where('loyalty_program_id', $currentProgram->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Determine tier
        $tier = $this->calculateTier($availablePoints, $currentProgram);

        return response()->json([
            'success' => true,
            'data' => [
                'program' => [
                    'id' => $currentProgram->id,
                    'name' => $currentProgram->name,
                    'point_value' => $currentProgram->point_value,
                    'points_per_rupee' => $currentProgram->points_per_rupee,
                ],
                'customer' => [
                    'total_points_earned' => $totalPoints,
                    'available_points' => $availablePoints,
                    'current_tier' => $tier,
                    'points_to_next_tier' => $this->getPointsToNextTier($availablePoints, $currentProgram),
                    'tier_benefits' => $this->getTierBenefits($tier, $currentProgram),
                ],
                'recent_transactions' => $recentTransactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => $transaction->transaction_type,
                        'points' => $transaction->points,
                        'reason' => $transaction->earning_reason,
                        'order_amount' => $transaction->order_amount,
                        'date' => $transaction->created_at->toDateString(),
                        'expires_at' => $transaction->expires_at?->toDateString(),
                    ];
                }),
            ]
        ]);
    }

    /**
     * Get available rewards for redemption
     */
    public function getRewards(Request $request)
    {
        $user = Auth::user();
        $availablePoints = $this->getUserAvailablePoints($user);

        // Get reward options (discounts based on points)
        $rewards = [];
        $minRedemption = 100; // minimum points for redemption
        
        if ($availablePoints >= $minRedemption) {
            $maxDiscount = min($availablePoints, 5000); // max 5000 points = ₹50
            
            for ($points = $minRedemption; $points <= $maxDiscount; $points += 100) {
                $discountValue = $points * 0.01; // 100 points = ₹1
                
                $rewards[] = [
                    'points_required' => $points,
                    'discount_value' => $discountValue,
                    'discount_type' => 'fixed',
                    'display_text' => "₹{$discountValue} off with {$points} points",
                    'is_available' => $points <= $availablePoints,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'available_points' => $availablePoints,
                'min_redemption' => $minRedemption,
                'rewards' => $rewards,
                'point_value' => 0.01, // 1 point = ₹0.01
            ]
        ]);
    }

    /**
     * Redeem points for discount
     */
    public function redeemPoints(Request $request)
    {
        $request->validate([
            'points_to_redeem' => 'required|integer|min:100',
            'order_total' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();
        $pointsToRedeem = $request->points_to_redeem;
        $orderTotal = $request->order_total;
        
        $availablePoints = $this->getUserAvailablePoints($user);
        
        if ($pointsToRedeem > $availablePoints) {
            return response()->json([
                'success' => false,
                'error' => 'Insufficient points available'
            ], 400);
        }

        $discountValue = $pointsToRedeem * 0.01; // 100 points = ₹1
        $maxDiscountAllowed = $orderTotal * 0.5; // max 50% of order value

        if ($discountValue > $maxDiscountAllowed) {
            return response()->json([
                'success' => false,
                'error' => "Maximum discount allowed is ₹{$maxDiscountAllowed} (50% of order value)"
            ], 400);
        }

        // Generate redemption token for checkout
        $redemptionToken = \Str::random(32);
        
        // Store in session/cache for checkout validation
        cache()->put("points_redemption:{$redemptionToken}", [
            'user_id' => $user->id,
            'points' => $pointsToRedeem,
            'discount_value' => $discountValue,
            'expires_at' => now()->addMinutes(30),
        ], 1800); // 30 minutes

        return response()->json([
            'success' => true,
            'data' => [
                'redemption_token' => $redemptionToken,
                'points_redeemed' => $pointsToRedeem,
                'discount_value' => $discountValue,
                'remaining_points' => $availablePoints - $pointsToRedeem,
                'expires_in_minutes' => 30,
            ]
        ]);
    }

    /**
     * Get points earning opportunities
     */
    public function getEarningOpportunities(Request $request)
    {
        $user = Auth::user();
        
        $program = LoyaltyProgram::active()->first();
        if (!$program) {
            return response()->json(['error' => 'No active loyalty program'], 404);
        }

        $earningRules = $program->earning_rules;
        
        $opportunities = [
            [
                'action' => 'make_purchase',
                'description' => 'Earn points on every purchase',
                'points_per_rupee' => $program->points_per_rupee,
                'example' => "Spend ₹1000, earn " . ($program->points_per_rupee * 1000) . " points",
            ],
        ];

        // Add other earning opportunities from rules
        foreach ($earningRules as $rule => $points) {
            switch ($rule) {
                case 'product_review':
                    $opportunities[] = [
                        'action' => 'write_review',
                        'description' => 'Write product reviews',
                        'points' => $points,
                        'example' => "Write a review, earn {$points} points",
                    ];
                    break;
                    
                case 'referral':
                    $opportunities[] = [
                        'action' => 'refer_friend',
                        'description' => 'Refer friends to earn bonus points',
                        'points' => $points,
                        'example' => "Successful referral earns {$points} points",
                    ];
                    break;
                    
                case 'birthday':
                    $opportunities[] = [
                        'action' => 'birthday_bonus',
                        'description' => 'Birthday month bonus',
                        'multiplier' => $points,
                        'example' => "Get {$points}x points during your birthday month",
                    ];
                    break;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'program_name' => $program->name,
                'opportunities' => $opportunities,
                'tier_info' => $this->getTierInfo($program),
            ]
        ]);
    }

    /**
     * Award points for specific actions
     */
    public function awardPoints(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'action' => 'required|string',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'order_amount' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $user = User::find($request->user_id);
        $program = LoyaltyProgram::active()->first();
        
        if (!$program) {
            return response()->json(['error' => 'No active loyalty program'], 404);
        }

        $pointsEarned = $this->calculatePointsForAction(
            $request->action,
            $program,
            $request->order_amount
        );

        if ($pointsEarned <= 0) {
            return response()->json(['error' => 'No points available for this action'], 400);
        }

        // Create points transaction
        $transaction = CustomerPoint::create([
            'user_id' => $user->id,
            'loyalty_program_id' => $program->id,
            'transaction_type' => 'earned',
            'points' => $pointsEarned,
            'earning_reason' => $request->action,
            'reference_type' => $request->reference_type,
            'reference_id' => $request->reference_id,
            'order_amount' => $request->order_amount,
            'expires_at' => now()->addDays($program->points_expiry_days ?? 365),
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'points_earned' => $pointsEarned,
                'total_points' => $this->getUserAvailablePoints($user),
                'transaction_id' => $transaction->id,
                'expires_at' => $transaction->expires_at,
            ]
        ]);
    }

    /**
     * Process point redemption during checkout
     */
    public function processRedemption(Request $request)
    {
        $request->validate([
            'redemption_token' => 'required|string',
            'order_id' => 'required|exists:orders,id',
        ]);

        $redemptionData = cache()->get("points_redemption:{$request->redemption_token}");
        
        if (!$redemptionData) {
            return response()->json(['error' => 'Invalid or expired redemption token'], 400);
        }

        $user = User::find($redemptionData['user_id']);
        $program = LoyaltyProgram::active()->first();

        // Create redemption transaction
        $transaction = CustomerPoint::create([
            'user_id' => $user->id,
            'loyalty_program_id' => $program->id,
            'transaction_type' => 'redeemed',
            'points' => -$redemptionData['points'], // negative for redemption
            'earning_reason' => 'order_discount',
            'reference_type' => 'order',
            'reference_id' => $request->order_id,
            'notes' => "Redeemed {$redemptionData['points']} points for ₹{$redemptionData['discount_value']} discount",
        ]);

        // Clear redemption token
        cache()->forget("points_redemption:{$request->redemption_token}");

        return response()->json([
            'success' => true,
            'data' => [
                'points_redeemed' => $redemptionData['points'],
                'discount_applied' => $redemptionData['discount_value'],
                'transaction_id' => $transaction->id,
                'remaining_points' => $this->getUserAvailablePoints($user),
            ]
        ]);
    }

    // Helper methods
    protected function getUserAvailablePoints($user)
    {
        $program = LoyaltyProgram::active()->first();
        if (!$program) return 0;

        return $user->points()
            ->where('loyalty_program_id', $program->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->sum('points');
    }

    protected function calculateTier($points, $program)
    {
        $tiers = $program->tier_thresholds ?? [
            'bronze' => 0,
            'silver' => 1000,
            'gold' => 5000,
            'platinum' => 10000,
        ];

        $currentTier = 'bronze';
        foreach ($tiers as $tier => $threshold) {
            if ($points >= $threshold) {
                $currentTier = $tier;
            }
        }

        return $currentTier;
    }

    protected function getPointsToNextTier($points, $program)
    {
        $tiers = $program->tier_thresholds ?? [
            'bronze' => 0,
            'silver' => 1000,
            'gold' => 5000,
            'platinum' => 10000,
        ];

        $currentTier = $this->calculateTier($points, $program);
        $tierKeys = array_keys($tiers);
        $currentIndex = array_search($currentTier, $tierKeys);

        if ($currentIndex < count($tierKeys) - 1) {
            $nextTier = $tierKeys[$currentIndex + 1];
            return $tiers[$nextTier] - $points;
        }

        return 0; // Already at highest tier
    }

    protected function getTierBenefits($tier, $program)
    {
        $benefits = [
            'bronze' => ['1x points earning', 'Basic support'],
            'silver' => ['1.2x points earning', 'Priority support', 'Free shipping on orders >₹500'],
            'gold' => ['1.5x points earning', 'Priority support', 'Free shipping', 'Early access to sales'],
            'platinum' => ['2x points earning', '24/7 VIP support', 'Free shipping', 'Early access', 'Exclusive products'],
        ];

        return $benefits[$tier] ?? $benefits['bronze'];
    }

    protected function getTierInfo($program)
    {
        $tiers = $program->tier_thresholds ?? [
            'bronze' => 0,
            'silver' => 1000,
            'gold' => 5000,
            'platinum' => 10000,
        ];

        return array_map(function ($threshold, $tier) use ($program) {
            return [
                'name' => ucfirst($tier),
                'threshold' => $threshold,
                'benefits' => $this->getTierBenefits($tier, $program),
            ];
        }, $tiers, array_keys($tiers));
    }

    protected function calculatePointsForAction($action, $program, $orderAmount = null)
    {
        $earningRules = $program->earning_rules ?? [];

        switch ($action) {
            case 'purchase':
                return $orderAmount ? (int)($orderAmount * $program->points_per_rupee) : 0;
                
            case 'product_review':
                return $earningRules['product_review'] ?? 50;
                
            case 'referral':
                return $earningRules['referral'] ?? 500;
                
            case 'birthday':
                return $earningRules['birthday'] ?? 100;
                
            case 'social_share':
                return $earningRules['social_share'] ?? 10;
                
            default:
                return 0;
        }
    }
}