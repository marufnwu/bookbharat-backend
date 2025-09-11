<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PricingEngine;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PricingController extends Controller
{
    protected PricingEngine $pricingEngine;

    public function __construct(PricingEngine $pricingEngine)
    {
        $this->pricingEngine = $pricingEngine;
    }

    /**
     * Get dynamic price for a product
     */
    public function getProductPrice(Request $request, $productId)
    {
        $request->validate([
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'integer|min:1',
            'location' => 'array',
            'location.country' => 'string',
            'location.state' => 'string',
        ]);

        $product = Product::findOrFail($productId);
        $variant = $request->variant_id ? ProductVariant::find($request->variant_id) : null;
        $user = Auth::user();
        
        $context = [
            'quantity' => $request->get('quantity', 1),
            'currency' => 'INR',
        ];
        
        if ($request->location) {
            $context['location'] = $request->location;
        }

        $pricing = $this->pricingEngine->calculatePrice($product, $variant, $user, $context);

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => $context['quantity'],
                'pricing' => $pricing,
                'is_personalized' => $user !== null,
                'user_segment' => $user?->customerGroups->first()?->name,
            ]
        ]);
    }

    /**
     * Get bulk pricing for cart items
     */
    public function getBulkPricing(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $context = [
            'currency' => 'INR',
            'bulk_order' => true,
        ];

        $results = $this->pricingEngine->bulkCalculatePrices($request->items, $user, $context);

        $summary = [
            'total_items' => array_sum(array_column($request->items, 'quantity')),
            'subtotal' => 0,
            'total_discount' => 0,
            'final_total' => 0,
        ];

        foreach ($results as $result) {
            $itemTotal = $result['pricing']['final_price'] * $result['quantity'];
            $itemDiscount = $result['pricing']['discount_amount'] * $result['quantity'];
            
            $summary['subtotal'] += ($result['pricing']['original_price'] * $result['quantity']);
            $summary['total_discount'] += $itemDiscount;
            $summary['final_total'] += $itemTotal;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $results,
                'summary' => $summary,
                'is_personalized' => $user !== null,
                'applied_discounts' => $this->getAppliedDiscounts($results),
            ]
        ]);
    }

    /**
     * Get available pricing tiers for a product
     */
    public function getPricingTiers(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);
        $user = Auth::user();
        $variantId = $request->get('variant_id');

        $tiers = [];
        $quantities = [1, 5, 10, 25, 50, 100];

        foreach ($quantities as $quantity) {
            $context = ['quantity' => $quantity];
            $variant = $variantId ? ProductVariant::find($variantId) : null;
            
            $pricing = $this->pricingEngine->calculatePrice($product, $variant, $user, $context);
            
            $tiers[] = [
                'quantity' => $quantity,
                'unit_price' => $pricing['final_price'],
                'total_price' => $pricing['final_price'] * $quantity,
                'savings_per_unit' => $pricing['discount_amount'],
                'total_savings' => $pricing['discount_amount'] * $quantity,
                'discount_percentage' => $pricing['discount_percentage'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'base_price' => $product->price,
                'tiers' => $tiers,
                'is_personalized' => $user !== null,
            ]
        ]);
    }

    /**
     * Get active promotions for a product
     */
    public function getActivePromotions(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);
        $user = Auth::user();

        // Get active pricing rules that apply to this product
        $activeRules = \App\Models\PricingRule::active()
            ->where(function ($query) use ($product) {
                $query->where('is_global', true)
                      ->orWhereJsonContains('product_filters->products', $product->id)
                      ->orWhereJsonContains('product_filters->categories', $product->category_id);
            })
            ->where('start_datetime', '<=', now())
            ->where('end_datetime', '>=', now())
            ->get();

        $promotions = $activeRules->map(function ($rule) {
            return [
                'id' => $rule->id,
                'name' => $rule->name,
                'description' => $rule->description,
                'rule_type' => $rule->rule_type,
                'discount_info' => $this->formatDiscountInfo($rule),
                'expires_at' => $rule->end_datetime,
                'usage_limit' => $rule->usage_limit,
                'usage_count' => $rule->usage_count,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->id,
                'active_promotions' => $promotions,
                'has_personalized_offers' => $user !== null && $user->customerGroups->count() > 0,
            ]
        ]);
    }

    /**
     * Get price history for a product
     */
    public function getPriceHistory(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);
        $days = $request->get('days', 30);
        $variantId = $request->get('variant_id');

        $history = \App\Models\PriceHistory::where('product_id', $productId)
            ->when($variantId, fn($q) => $q->where('variant_id', $variantId))
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'current_price' => $variantId ? 
                    ProductVariant::find($variantId)?->price : 
                    $product->price,
                'price_history' => $history->map(function ($entry) {
                    return [
                        'date' => $entry->created_at->toDateString(),
                        'old_price' => $entry->old_price,
                        'new_price' => $entry->new_price,
                        'change_type' => $entry->change_type,
                        'change_percentage' => $entry->change_percentage,
                        'reason' => $entry->change_reason,
                    ];
                }),
                'lowest_price' => $history->min('new_price'),
                'highest_price' => $history->max('new_price'),
                'price_trend' => $this->calculatePriceTrend($history),
            ]
        ]);
    }

    /**
     * Helper method to format discount information
     */
    protected function formatDiscountInfo($rule)
    {
        $actions = $rule->actions;
        
        if (isset($actions['discount_percentage'])) {
            return [
                'type' => 'percentage',
                'value' => $actions['discount_percentage'],
                'display' => $actions['discount_percentage'] . '% OFF',
            ];
        }
        
        if (isset($actions['discount_amount'])) {
            return [
                'type' => 'fixed',
                'value' => $actions['discount_amount'],
                'display' => '₹' . $actions['discount_amount'] . ' OFF',
            ];
        }
        
        return null;
    }

    /**
     * Helper method to get applied discounts summary
     */
    protected function getAppliedDiscounts($results)
    {
        $discounts = [];
        
        foreach ($results as $result) {
            foreach ($result['pricing']['applied_rules'] as $rule) {
                if (!in_array($rule, $discounts)) {
                    $discounts[] = $rule;
                }
            }
        }
        
        return $discounts;
    }

    /**
     * Helper method to calculate price trend
     */
    protected function calculatePriceTrend($history)
    {
        if ($history->count() < 2) {
            return 'stable';
        }

        $first = $history->first();
        $last = $history->last();

        $change = (($last->new_price - $first->new_price) / $first->new_price) * 100;

        if ($change > 5) return 'increasing';
        if ($change < -5) return 'decreasing';
        return 'stable';
    }
}