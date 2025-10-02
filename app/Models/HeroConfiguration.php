<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroConfiguration extends Model
{
    protected $fillable = [
        'variant',
        'title',
        'subtitle',
        'primaryCta',
        'secondaryCta',
        'discountBadge',
        'trustBadges',
        'backgroundImage',
        'testimonials',
        'campaignData',
        'categories',
        'features',
        'stats',
        'featuredProducts',
        'videoUrl',
        'is_active',
    ];

    protected $casts = [
        'primaryCta' => 'array',
        'secondaryCta' => 'array',
        'discountBadge' => 'array',
        'trustBadges' => 'array',
        'testimonials' => 'array',
        'campaignData' => 'array',
        'categories' => 'array',
        'features' => 'array',
        'stats' => 'array',
        'featuredProducts' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
