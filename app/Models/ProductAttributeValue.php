<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ProductAttributeValue extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'attribute_id',
        'value',
        'slug',
        'color_code',
        'image',
        'price_adjustment',
        'price_adjustment_type',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'attribute_id');
    }

    public function variantAttributes(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class, 'attribute_value_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function calculatePriceAdjustment($basePrice)
    {
        if ($this->price_adjustment_type === 'percentage') {
            return $basePrice * ($this->price_adjustment / 100);
        }
        
        return $this->price_adjustment;
    }

    public function setValueAttribute($value)
    {
        $this->attributes['value'] = $value;
        $this->attributes['slug'] = \Str::slug($value);
    }
}