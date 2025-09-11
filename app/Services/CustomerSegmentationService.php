<?php

namespace App\Services;

use App\Models\User;
use App\Models\CustomerSegment;
use App\Models\CustomerAnalytics;
use App\Models\Order;
use App\Jobs\UpdateCustomerAnalytics;
use App\Jobs\RecalculateCustomerSegments;
use Carbon\Carbon;

class CustomerSegmentationService
{
    public function calculateCustomerAnalytics(User $user)
    {
        $orders = $user->orders()->where('status', 'completed');
        $totalOrders = $orders->count();
        $totalSpent = $orders->sum('total_amount');
        
        if ($totalOrders === 0) {
            return $this->createAnalytics($user, [
                'lifetime_value' => 0,
                'average_order_value' => 0,
                'total_orders' => 0,
                'total_items_purchased' => 0,
                'total_spent' => 0,
                'days_since_first_order' => 0,
                'days_since_last_order' => 0,
                'purchase_frequency' => 0,
                'customer_segment' => 'new',
                'lifecycle_stage' => 'new',
                'churn_probability' => 0,
            ]);
        }
        
        $firstOrder = $orders->orderBy('created_at')->first();
        $lastOrder = $orders->orderBy('created_at', 'desc')->first();
        $totalItems = $orders->withCount('items')->get()->sum('items_count');
        
        $daysSinceFirst = Carbon::parse($firstOrder->created_at)->diffInDays(now());
        $daysSinceLast = Carbon::parse($lastOrder->created_at)->diffInDays(now());
        $avgOrderValue = $totalSpent / $totalOrders;
        $purchaseFrequency = $daysSinceFirst > 0 ? ($totalOrders / ($daysSinceFirst / 30)) : 0;
        
        // Calculate churn probability
        $churnProbability = $this->calculateChurnProbability($user, $daysSinceLast, $purchaseFrequency);
        
        // Determine customer segment
        $customerSegment = $this->determineCustomerSegment($totalSpent, $totalOrders, $daysSinceFirst);
        
        // Determine lifecycle stage
        $lifecycleStage = $this->determineLifecycleStage($daysSinceLast, $totalOrders, $churnProbability);
        
        // Calculate preferences
        $preferences = $this->calculateCustomerPreferences($user);
        
        return $this->createAnalytics($user, [
            'lifetime_value' => $this->calculateLifetimeValue($user, $avgOrderValue, $purchaseFrequency),
            'average_order_value' => $avgOrderValue,
            'total_orders' => $totalOrders,
            'total_items_purchased' => $totalItems,
            'total_spent' => $totalSpent,
            'days_since_first_order' => $daysSinceFirst,
            'days_since_last_order' => $daysSinceLast,
            'purchase_frequency' => $purchaseFrequency,
            'favorite_category' => $preferences['category'] ?? null,
            'favorite_brand' => $preferences['brand'] ?? null,
            'purchase_patterns' => $preferences['patterns'] ?? null,
            'churn_probability' => $churnProbability,
            'customer_segment' => $customerSegment,
            'lifecycle_stage' => $lifecycleStage,
            'preferences' => $preferences,
        ]);
    }
    
    protected function calculateLifetimeValue(User $user, float $avgOrderValue, float $purchaseFrequency)
    {
        // Simple CLV calculation: AOV × Purchase Frequency × Customer Lifespan
        $averageLifespan = 24; // months
        return $avgOrderValue * $purchaseFrequency * $averageLifespan;
    }
    
    protected function calculateChurnProbability(User $user, int $daysSinceLast, float $purchaseFrequency)
    {
        // Simple churn probability based on recency and frequency
        if ($purchaseFrequency === 0) {
            return 0.9;
        }
        
        $expectedDaysBetweenPurchases = 30 / $purchaseFrequency;
        $churnThreshold = $expectedDaysBetweenPurchases * 2;
        
        if ($daysSinceLast >= $churnThreshold) {
            return min(0.95, 0.1 + ($daysSinceLast / $churnThreshold) * 0.85);
        }
        
        return max(0.05, $daysSinceLast / $churnThreshold * 0.5);
    }
    
    protected function determineCustomerSegment(float $totalSpent, int $totalOrders, int $daysSinceFirst)
    {
        $annualSpent = $daysSinceFirst > 0 ? ($totalSpent / $daysSinceFirst) * 365 : $totalSpent;
        
        if ($annualSpent > 50000 || $totalOrders > 50 || ($daysSinceFirst > 730 && $totalOrders > 10)) {
            return 'vip';
        } elseif ($totalOrders >= 5 || $annualSpent > 10000) {
            return 'regular';
        } elseif ($daysSinceFirst < 180 && $totalOrders < 5) {
            return 'new';
        } else {
            return 'occasional';
        }
    }
    
    protected function determineLifecycleStage(int $daysSinceLast, int $totalOrders, float $churnProbability)
    {
        if ($totalOrders === 0 || $daysSinceLast === 0) {
            return 'new';
        }
        
        if ($churnProbability > 0.7) {
            return 'churned';
        } elseif ($churnProbability > 0.5) {
            return 'at_risk';
        } elseif ($daysSinceLast <= 30 && $totalOrders > 1) {
            return 'active';
        } else {
            return 'inactive';
        }
    }
    
    protected function calculateCustomerPreferences(User $user)
    {
        $orders = $user->orders()->where('status', 'completed')->with('items.product')->get();
        
        $categoryFreq = [];
        $brandFreq = [];
        $timePatterns = [];
        $priceRanges = [];
        
        foreach ($orders as $order) {
            $hour = Carbon::parse($order->created_at)->hour;
            $dayOfWeek = Carbon::parse($order->created_at)->dayOfWeek;
            
            $timePatterns['hours'][$hour] = ($timePatterns['hours'][$hour] ?? 0) + 1;
            $timePatterns['days'][$dayOfWeek] = ($timePatterns['days'][$dayOfWeek] ?? 0) + 1;
            
            foreach ($order->items as $item) {
                if ($item->product->category) {
                    $categoryId = $item->product->category_id;
                    $categoryFreq[$categoryId] = ($categoryFreq[$categoryId] ?? 0) + $item->quantity;
                }
                
                if ($item->product->brand) {
                    $brand = $item->product->brand;
                    $brandFreq[$brand] = ($brandFreq[$brand] ?? 0) + $item->quantity;
                }
                
                $price = $item->unit_price;
                if ($price <= 500) {
                    $priceRanges['budget'] = ($priceRanges['budget'] ?? 0) + 1;
                } elseif ($price <= 1500) {
                    $priceRanges['mid_range'] = ($priceRanges['mid_range'] ?? 0) + 1;
                } else {
                    $priceRanges['premium'] = ($priceRanges['premium'] ?? 0) + 1;
                }
            }
        }
        
        return [
            'category' => !empty($categoryFreq) ? array_keys($categoryFreq, max($categoryFreq))[0] : null,
            'brand' => !empty($brandFreq) ? array_keys($brandFreq, max($brandFreq))[0] : null,
            'preferred_price_range' => !empty($priceRanges) ? array_keys($priceRanges, max($priceRanges))[0] : null,
            'patterns' => [
                'preferred_hour' => !empty($timePatterns['hours']) ? array_keys($timePatterns['hours'], max($timePatterns['hours']))[0] : null,
                'preferred_day' => !empty($timePatterns['days']) ? array_keys($timePatterns['days'], max($timePatterns['days']))[0] : null,
            ],
        ];
    }
    
    protected function createAnalytics(User $user, array $data)
    {
        return CustomerAnalytics::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($data, ['calculated_at' => now()])
        );
    }
    
    public function assignUserToSegments(User $user)
    {
        $analytics = $user->analytics;
        if (!$analytics) {
            $analytics = $this->calculateCustomerAnalytics($user);
        }
        
        $segments = CustomerSegment::active()->dynamic()->get();
        $assignedSegments = [];
        
        foreach ($segments as $segment) {
            if ($this->evaluateSegmentCriteria($user, $analytics, $segment)) {
                $assignedSegments[] = $segment->id;
            }
        }
        
        // Update user segments
        $user->segments()->sync($assignedSegments);
        
        return $assignedSegments;
    }
    
    protected function evaluateSegmentCriteria(User $user, CustomerAnalytics $analytics, CustomerSegment $segment)
    {
        $criteria = $segment->criteria;
        
        foreach ($criteria as $criterion => $value) {
            switch ($criterion) {
                case 'min_lifetime_value':
                    if ($analytics->lifetime_value < $value) return false;
                    break;
                    
                case 'max_lifetime_value':
                    if ($analytics->lifetime_value > $value) return false;
                    break;
                    
                case 'min_total_orders':
                    if ($analytics->total_orders < $value) return false;
                    break;
                    
                case 'max_total_orders':
                    if ($analytics->total_orders > $value) return false;
                    break;
                    
                case 'min_avg_order_value':
                    if ($analytics->average_order_value < $value) return false;
                    break;
                    
                case 'max_avg_order_value':
                    if ($analytics->average_order_value > $value) return false;
                    break;
                    
                case 'customer_segment':
                    if ($analytics->customer_segment !== $value) return false;
                    break;
                    
                case 'lifecycle_stage':
                    if ($analytics->lifecycle_stage !== $value) return false;
                    break;
                    
                case 'max_churn_probability':
                    if ($analytics->churn_probability > $value) return false;
                    break;
                    
                case 'min_days_since_last_order':
                    if ($analytics->days_since_last_order < $value) return false;
                    break;
                    
                case 'max_days_since_last_order':
                    if ($analytics->days_since_last_order > $value) return false;
                    break;
                    
                case 'favorite_categories':
                    if (!in_array($analytics->favorite_category, $value)) return false;
                    break;
                    
                case 'favorite_brands':
                    if (!in_array($analytics->favorite_brand, $value)) return false;
                    break;
            }
        }
        
        return true;
    }
    
    public function recalculateAllSegments()
    {
        $users = User::has('orders')->get();
        
        foreach ($users in $users) {
            UpdateCustomerAnalytics::dispatch($user->id);
            RecalculateCustomerSegments::dispatch($user->id);
        }
        
        // Update segment customer counts
        $segments = CustomerSegment::all();
        foreach ($segments as $segment) {
            $count = $segment->users()->count();
            $segment->update([
                'customer_count' => $count,
                'last_calculated_at' => now(),
            ]);
        }
    }
    
    public function getSegmentInsights(CustomerSegment $segment)
    {
        $users = $segment->users()->with('analytics')->get();
        
        if ($users->isEmpty()) {
            return [
                'total_customers' => 0,
                'avg_lifetime_value' => 0,
                'avg_order_value' => 0,
                'avg_orders_count' => 0,
                'churn_rate' => 0,
                'revenue_contribution' => 0,
            ];
        }
        
        $analytics = $users->pluck('analytics')->filter();
        
        return [
            'total_customers' => $users->count(),
            'avg_lifetime_value' => $analytics->avg('lifetime_value'),
            'avg_order_value' => $analytics->avg('average_order_value'),
            'avg_orders_count' => $analytics->avg('total_orders'),
            'churn_rate' => $analytics->where('churn_probability', '>', 0.7)->count() / $users->count() * 100,
            'revenue_contribution' => $analytics->sum('total_spent'),
            'lifecycle_distribution' => $analytics->groupBy('lifecycle_stage')
                ->map(fn($group) => $group->count())
                ->toArray(),
        ];
    }
}