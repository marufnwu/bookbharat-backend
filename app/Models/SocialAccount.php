<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'provider_username',
        'access_token',
        'refresh_token',
        'expires_at',
        'profile_data',
        'permissions',
        'is_active',
    ];

    protected $casts = [
        'profile_data' => 'array',
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getFollowersCountAttribute(): int
    {
        return $this->profile_data['followers_count'] ?? 0;
    }

    public function getFollowingCountAttribute(): int
    {
        return $this->profile_data['following_count'] ?? 0;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->profile_data['avatar'] ?? $this->profile_data['picture'] ?? null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}