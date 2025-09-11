<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\CustomerGroup;
use App\Models\PricingTier;
use App\Models\PricingRule;
use Carbon\Carbon;

class PricingEngine
{
    public function calculatePrice(Product $product, ?ProductVariant $variant = null, ?User $user = null, array $context = [])
    {
        $basePrice = $variant ? $variant->price : $product->price;
        $comparePrice = $variant ? $variant->compare_price : $product->compare_price;
        
        $finalPrice = $basePrice;
        $appliedRules = [];
        
        // 1. Apply customer group pricing
        if ($user) {
            $customerGroupPrice = $this->getCustomerGroupPrice($product, $variant, $user, $context);
            if ($customerGroupPrice && $customerGroupPrice < $finalPrice) {
                $finalPrice = $customerGroupPrice;
                $appliedRules[] = 'customer_group_pricing';
            }
        }
        
        // 2. Apply quantity-based pricing
        $quantity = $context['quantity'] ?? 1;
        $tierPrice = $this->getTierPrice($product, $variant, $user, $quantity);
        if ($tierPrice && $tierPrice < $finalPrice) {
            $finalPrice = $tierPrice;
            $appliedRules[] = 'tier_pricing';
        }
        
        // 3. Apply dynamic pricing rules (flash sales, time-based, geographic)
        $rulePrice = $this->applyDynamicRules($product, $variant, $user, $context);
        if ($rulePrice && $rulePrice < $finalPrice) {
            $finalPrice = $rulePrice['price'];
            $appliedRules = array_merge($appliedRules, $rulePrice['rules']);
        }
        
        // 4. Apply promotional pricing
        $promoPrice = $this->getPromotionalPrice($product, $variant, $user, $context);
        if ($promoPrice && $promoPrice < $finalPrice) {
            $finalPrice = $promoPrice;
            $appliedRules[] = 'promotional_pricing';
        }
        
        return [
            'original_price' => $basePrice,
            'compare_price' => $comparePrice,
            'final_price' => max(0, $finalPrice),
            'discount_amount' => max(0, $basePrice - $finalPrice),
            'discount_percentage' => $basePrice > 0 ? round((($basePrice - $finalPrice) / $basePrice) * 100, 2) : 0,
            'applied_rules' => $appliedRules,
            'currency' => $context['currency'] ?? 'INR',
            'calculated_at' => now(),
        ];
    }

    protected function getCustomerGroupPrice(Product $product, ?ProductVariant $variant, User $user, array $context)
    {
        $customerGroups = $user->customerGroups()
            ->active()
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($customerGroups as $group) {
            $tier = PricingTier::where('product_id', $product->id)
                ->where('variant_id', $variant?->id)
                ->where('customer_group_id', $group->id)
                ->active()
                ->first();

            if ($tier) {
                return $tier->price;
            }

            // Apply group discount percentage
            if ($group->discount_percentage > 0) {
                $basePrice = $variant ? $variant->price : $product->price;
                return $basePrice * (1 - $group->discount_percentage / 100);
            }
        }

        return null;
    }

    protected function getTierPrice(Product $product, ?ProductVariant $variant, ?User $user, int $quantity)
    {
        $query = PricingTier::where('product_id', $product->id)
            ->where('variant_id', $variant?->id)
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($q) use ($quantity) {
                $q->whereNull('max_quantity')
                  ->orWhere('max_quantity', '>=', $quantity);
            })
            ->active()
            ->orderBy('min_quantity', 'desc');

        // Add customer group filter if user exists
        if ($user) {
            $customerGroupIds = $user->customerGroups()->pluck('customer_groups.id');
            $query->where(function ($q) use ($customerGroupIds) {
                $q->whereNull('customer_group_id')
                  ->orWhereIn('customer_group_id', $customerGroupIds);
            });
        } else {
            $query->whereNull('customer_group_id');
        }

        $tier = $query->first();
        return $tier ? $tier->price : null;
    }

    protected function applyDynamicRules(Product $product, ?ProductVariant $variant, ?User $user, array $context)
    {
        $applicableRules = PricingRule::active()
            ->where(function ($query) use ($context) {
                // Time-based rules
                $query->where(function ($q) use ($context) {
                    $q->where('rule_type', 'time_based')
                      ->where('start_datetime', '<=', now())
                      ->where('end_datetime', '>=', now());
                })
                // Flash sale rules
                ->orWhere(function ($q) use ($context) {
                    $q->where('rule_type', 'flash_sale')
                      ->where('start_datetime', '<=', now())
                      ->where('end_datetime', '>=', now())
                      ->where('usage_count', '<', DB::raw('usage_limit'));
                })
                // Geographic rules
                ->orWhere(function ($q) use ($context) {
                    if (isset($context['location'])) {
                        $q->where('rule_type', 'geographic');
                    }
                });
            })
            ->orderBy('priority', 'desc')
            ->get();

        $bestPrice = null;
        $appliedRuleNames = [];

        foreach ($applicableRules as $rule) {
            if ($this->evaluateRuleConditions($rule, $product, $variant, $user, $context)) {
                $calculatedPrice = $this->calculateRulePrice($rule, $product, $variant, $context);
                
                if ($bestPrice === null || $calculatedPrice < $bestPrice) {
                    $bestPrice = $calculatedPrice;
                    $appliedRuleNames = [$rule->rule_type];
                }
            }
        }

        return $bestPrice ? [
            'price' => $bestPrice,
            'rules' => $appliedRuleNames
        ] : null;
    }

    protected function evaluateRuleConditions(PricingRule $rule, Product $product, ?ProductVariant $variant, ?User $user, array $context)
    {
        $conditions = $rule->conditions;

        // Evaluate product filters
        if (isset($conditions['categories']) && !in_array($product->category_id, $conditions['categories'])) {
            return false;
        }

        if (isset($conditions['brands']) && !in_array($product->brand, $conditions['brands'])) {
            return false;
        }

        // Evaluate user conditions
        if ($user && isset($conditions['customer_groups'])) {
            $userGroupIds = $user->customerGroups()->pluck('customer_groups.id')->toArray();
            if (empty(array_intersect($userGroupIds, $conditions['customer_groups']))) {
                return false;
            }
        }

        // Evaluate geographic conditions
        if (isset($conditions['countries']) && isset($context['location']['country'])) {
            if (!in_array($context['location']['country'], $conditions['countries'])) {
                return false;
            }
        }

        // Evaluate time conditions for happy hour rules
        if ($rule->rule_type === 'happy_hour' && isset($conditions['time_range'])) {
            $currentHour = Carbon::now()->hour;
            $timeRange = $conditions['time_range'];
            if ($currentHour < $timeRange['start'] || $currentHour > $timeRange['end']) {
                return false;
            }
        }

        return true;
    }

    protected function calculateRulePrice(PricingRule $rule, Product $product, ?ProductVariant $variant, array $context)
    {
        $basePrice = $variant ? $variant->price : $product->price;
        $actions = $rule->actions;

        if (isset($actions['discount_percentage'])) {
            return $basePrice * (1 - $actions['discount_percentage'] / 100);
        }

        if (isset($actions['discount_amount'])) {
            return max(0, $basePrice - $actions['discount_amount']);
        }

        if (isset($actions['fixed_price'])) {
            return $actions['fixed_price'];
        }

        return $basePrice;
    }

    protected function getPromotionalPrice(Product $product, ?ProductVariant $variant, ?User $user, array $context)
    {
        // This would integrate with coupon/promotion system
        // For now, return null as promotional pricing would be handled separately
        return null;
    }

    public function bulkCalculatePrices(array $items, ?User $user = null, array $context = [])
    {
        $results = [];
        
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            $variant = isset($item['variant_id']) ? ProductVariant::find($item['variant_id']) : null;
            
            $itemContext = array_merge($context, [
                'quantity' => $item['quantity'] ?? 1
            ]);
            
            $results[] = [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => $item['quantity'] ?? 1,
                'pricing' => $this->calculatePrice($product, $variant, $user, $itemContext)
            ];
        }
        
        return $results;
    }
}