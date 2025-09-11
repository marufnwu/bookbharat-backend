<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGeneratedContent extends Model
{
    use HasFactory;

    protected $table = 'user_generated_content';

    protected $fillable = [
        'user_id',
        'product_id',
        'content_type',
        'content',
        'media_files',
        'metadata',
        'source_platform',
        'external_id',
        'likes_count',
        'shares_count',
        'comments_count',
        'rating',
        'status',
        'is_featured',
        'allow_public_display',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'media_files' => 'array',
        'metadata' => 'array',
        'likes_count' => 'integer',
        'shares_count' => 'integer',
        'comments_count' => 'integer',
        'rating' => 'decimal:2',
        'is_featured' => 'boolean',
        'allow_public_display' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTotalEngagementAttribute(): int
    {
        return $this->likes_count + $this->shares_count + $this->comments_count;
    }

    public function getHashtagsAttribute(): array
    {
        return $this->metadata['hashtags'] ?? [];
    }

    public function getMentionsAttribute(): array
    {
        return $this->metadata['mentions'] ?? [];
    }

    public function getPrimaryImageAttribute(): ?string
    {
        if (!$this->media_files || !is_array($this->media_files)) {
            return null;
        }

        $imageFile = collect($this->media_files)->first(function ($file) {
            return isset($file['type']) && $file['type'] === 'image';
        });

        return $imageFile['url'] ?? null;
    }

    public function getPrimaryVideoAttribute(): ?string
    {
        if (!$this->media_files || !is_array($this->media_files)) {
            return null;
        }

        $videoFile = collect($this->media_files)->first(function ($file) {
            return isset($file['type']) && $file['type'] === 'video';
        });

        return $videoFile['url'] ?? null;
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePublicDisplay($query)
    {
        return $query->where('allow_public_display', true);
    }

    public function scopeByContentType($query, string $type)
    {
        return $query->where('content_type', $type);
    }

    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('source_platform', $platform);
    }

    public function scopeWithProduct($query)
    {
        return $query->whereNotNull('product_id');
    }

    public function scopeHighEngagement($query, int $threshold = 100)
    {
        return $query->whereRaw('(likes_count + shares_count + comments_count) >= ?', [$threshold]);
    }
}