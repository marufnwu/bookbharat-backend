<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionalCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'status',
        'starts_at',
        'ends_at',
        'campaign_rules',
        'target_audience',
        'banner_config',
        'email_config',
        'notification_config',
        'budget_limit',
        'current_spend',
        'target_participants',
        'actual_participants',
        'target_revenue',
        'actual_revenue',
        'priority',
        'auto_apply',
        'analytics_config',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'campaign_rules' => 'array',
        'target_audience' => 'array',
        'banner_config' => 'array',
        'email_config' => 'array',
        'notification_config' => 'array',
        'budget_limit' => 'decimal:2',
        'current_spend' => 'decimal:2',
        'target_revenue' => 'decimal:2',
        'actual_revenue' => 'decimal:2',
        'auto_apply' => 'boolean',
        'analytics_config' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'campaign_id');
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' && 
               $this->starts_at <= now() && 
               ($this->ends_at === null || $this->ends_at >= now());
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function getProgressPercentageAttribute(): float
    {
        if (!$this->target_revenue) {
            return 0;
        }

        return min(100, ($this->actual_revenue / $this->target_revenue) * 100);
    }

    public function getParticipationRateAttribute(): float
    {
        if (!$this->target_participants) {
            return 0;
        }

        return min(100, ($this->actual_participants / $this->target_participants) * 100);
    }

    public function getBudgetUtilizationAttribute(): float
    {
        if (!$this->budget_limit) {
            return 0;
        }

        return min(100, ($this->current_spend / $this->budget_limit) * 100);
    }

    public function getEstimatedRoiAttribute(): ?float
    {
        if ($this->current_spend <= 0) {
            return null;
        }

        return (($this->actual_revenue - $this->current_spend) / $this->current_spend) * 100;
    }

    public function getDurationInDaysAttribute(): int
    {
        return $this->starts_at->diffInDays($this->ends_at);
    }

    public function getDaysRemainingAttribute(): int
    {
        if ($this->is_expired || !$this->ends_at) {
            return 0;
        }

        return max(0, now()->diffInDays($this->ends_at, false));
    }

    public function canParticipate(User $user): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check budget limit
        if ($this->budget_limit && $this->current_spend >= $this->budget_limit) {
            return false;
        }

        // Check participant limit
        if ($this->target_participants && $this->actual_participants >= $this->target_participants) {
            return false;
        }

        // Check target audience criteria
        if (!empty($this->target_audience)) {
            return $this->matchesTargetAudience($user);
        }

        return true;
    }

    public function generateCouponForCampaign(array $couponConfig = []): Coupon
    {
        $defaultConfig = $this->getDefaultCouponConfig();
        $config = array_merge($defaultConfig, $couponConfig);

        return Coupon::create(array_merge($config, [
            'campaign_id' => $this->id,
            'created_by' => $this->created_by,
        ]));
    }

    public function getPerformanceMetrics(): array
    {
        return [
            'conversion_rate' => $this->calculateConversionRate(),
            'average_order_value' => $this->calculateAverageOrderValue(),
            'customer_acquisition_cost' => $this->calculateCustomerAcquisitionCost(),
            'revenue_per_participant' => $this->actual_participants > 0 ? 
                $this->actual_revenue / $this->actual_participants : 0,
            'cost_per_acquisition' => $this->calculateCostPerAcquisition(),
            'engagement_metrics' => $this->getEngagementMetrics(),
        ];
    }

    protected function matchesTargetAudience(User $user): bool
    {
        $audience = $this->target_audience;

        // Check customer groups
        if (!empty($audience['customer_groups'])) {
            $userGroupIds = $user->customerGroups()->pluck('customer_groups.id')->toArray();
            if (empty(array_intersect($userGroupIds, $audience['customer_groups']))) {
                return false;
            }
        }

        // Check geographic location
        if (!empty($audience['locations'])) {
            $userLocation = $user->addresses()->first()?->city;
            if ($userLocation && !in_array($userLocation, $audience['locations'])) {
                return false;
            }
        }

        // Check order history
        if (!empty($audience['order_criteria'])) {
            if (!$this->matchesOrderCriteria($user, $audience['order_criteria'])) {
                return false;
            }
        }

        // Check demographics
        if (!empty($audience['demographics'])) {
            if (!$this->matchesDemographics($user, $audience['demographics'])) {
                return false;
            }
        }

        // Check behavior criteria
        if (!empty($audience['behavior'])) {
            if (!$this->matchesBehaviorCriteria($user, $audience['behavior'])) {
                return false;
            }
        }

        return true;
    }

    protected function matchesOrderCriteria(User $user, array $criteria): bool
    {
        $orders = $user->orders()->where('status', 'delivered');

        if (isset($criteria['min_orders'])) {
            if ($orders->count() < $criteria['min_orders']) {
                return false;
            }
        }

        if (isset($criteria['min_total_spent'])) {
            if ($orders->sum('total_amount') < $criteria['min_total_spent']) {
                return false;
            }
        }

        if (isset($criteria['last_order_days'])) {
            $lastOrder = $orders->latest()->first();
            if (!$lastOrder || $lastOrder->created_at->diffInDays(now()) > $criteria['last_order_days']) {
                return false;
            }
        }

        return true;
    }

    protected function matchesDemographics(User $user, array $demographics): bool
    {
        if (!empty($demographics['age_range'])) {
            $userAge = $user->date_of_birth ? $user->date_of_birth->age : null;
            if ($userAge === null || 
                $userAge < $demographics['age_range']['min'] || 
                $userAge > $demographics['age_range']['max']) {
                return false;
            }
        }

        if (!empty($demographics['gender'])) {
            if ($user->gender !== $demographics['gender']) {
                return false;
            }
        }

        return true;
    }

    protected function matchesBehaviorCriteria(User $user, array $behavior): bool
    {
        if (!empty($behavior['browsing_categories'])) {
            $userBehavior = $user->behavior()->latest()->first();
            if (!$userBehavior) {
                return false;
            }

            $browsedCategories = $userBehavior->data['browsed_categories'] ?? [];
            if (empty(array_intersect($browsedCategories, $behavior['browsing_categories']))) {
                return false;
            }
        }

        return true;
    }

    protected function getDefaultCouponConfig(): array
    {
        $rules = $this->campaign_rules;

        return [
            'name' => $this->name . ' - Auto Generated',
            'description' => 'Auto-generated coupon for ' . $this->name . ' campaign',
            'type' => $rules['coupon_type'] ?? 'percentage',
            'value' => $rules['discount_value'] ?? 10,
            'minimum_order_amount' => $rules['min_order_amount'] ?? 0,
            'maximum_discount_amount' => $rules['max_discount_amount'] ?? null,
            'usage_limit' => $rules['usage_limit'] ?? null,
            'usage_limit_per_customer' => $rules['usage_limit_per_customer'] ?? 1,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->ends_at,
            'is_active' => true,
            'is_stackable' => $rules['is_stackable'] ?? false,
            'applicable_products' => $rules['applicable_products'] ?? null,
            'applicable_categories' => $rules['applicable_categories'] ?? null,
            'first_order_only' => $rules['first_order_only'] ?? 'no',
        ];
    }

    protected function calculateConversionRate(): float
    {
        if ($this->actual_participants === 0) {
            return 0;
        }

        $orders = Order::whereHas('coupons', function ($query) {
            $query->where('campaign_id', $this->id);
        })->where('status', '!=', 'cancelled')->count();

        return ($orders / $this->actual_participants) * 100;
    }

    protected function calculateAverageOrderValue(): float
    {
        $orders = Order::whereHas('coupons', function ($query) {
            $query->where('campaign_id', $this->id);
        })->where('status', '!=', 'cancelled');

        if ($orders->count() === 0) {
            return 0;
        }

        return $orders->avg('total_amount');
    }

    protected function calculateCustomerAcquisitionCost(): float
    {
        if ($this->actual_participants === 0) {
            return 0;
        }

        return $this->current_spend / $this->actual_participants;
    }

    protected function calculateCostPerAcquisition(): float
    {
        $newCustomers = $this->getNewCustomersCount();
        
        if ($newCustomers === 0) {
            return 0;
        }

        return $this->current_spend / $newCustomers;
    }

    protected function getNewCustomersCount(): int
    {
        return User::whereHas('orders.coupons', function ($query) {
            $query->where('campaign_id', $this->id);
        })
        ->whereDoesntHave('orders', function ($query) {
            $query->where('created_at', '<', $this->starts_at);
        })
        ->count();
    }

    protected function getEngagementMetrics(): array
    {
        return [
            'email_open_rate' => $this->analytics_config['email_open_rate'] ?? 0,
            'email_click_rate' => $this->analytics_config['email_click_rate'] ?? 0,
            'push_notification_open_rate' => $this->analytics_config['push_open_rate'] ?? 0,
            'banner_click_rate' => $this->analytics_config['banner_click_rate'] ?? 0,
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('ends_at', '<', now());
    }
}