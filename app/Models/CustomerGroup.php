<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class CustomerGroup extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'discount_percentage',
        'pricing_type',
        'conditions',
        'benefits',
        'is_active',
        'is_automatic',
        'priority',
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'conditions' => 'array',
        'benefits' => 'array',
        'is_active' => 'boolean',
        'is_automatic' => 'boolean',
        'priority' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_group_users')
            ->withTimestamps();
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(PricingTier::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAutomatic($query)
    {
        return $query->where('is_automatic', true);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    // Methods
    public function checkUserEligibility(User $user)
    {
        if (!$this->is_automatic || !$this->conditions) {
            return false;
        }

        foreach ($this->conditions as $condition => $value) {
            if (!$this->evaluateCondition($user, $condition, $value)) {
                return false;
            }
        }

        return true;
    }

    protected function evaluateCondition(User $user, $condition, $value)
    {
        return match($condition) {
            'min_orders' => $user->orders()->where('status', 'completed')->count() >= $value,
            'min_total_spent' => $user->orders()->where('status', 'completed')->sum('total_amount') >= $value,
            'account_age_days' => $user->created_at->diffInDays(now()) >= $value,
            'min_annual_spent' => $user->orders()
                ->where('status', 'completed')
                ->where('created_at', '>=', now()->subYear())
                ->sum('total_amount') >= $value,
            default => false
        };
    }

    public function assignUserIfEligible(User $user)
    {
        if ($this->checkUserEligibility($user)) {
            $user->customerGroups()->syncWithoutDetaching([$this->id]);
            return true;
        }
        return false;
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = \Str::slug($value);
    }
}