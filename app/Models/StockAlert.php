<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class StockAlert extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'product_id',
        'variant_id',
        'location_id',
        'alert_type',
        'threshold_quantity',
        'current_quantity',
        'status',
        'triggered_at',
        'resolved_at',
        'resolved_by',
        'notes',
    ];

    protected $casts = [
        'threshold_quantity' => 'integer',
        'current_quantity' => 'integer',
        'triggered_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('alert_type', $type);
    }

    public function scopeCritical($query)
    {
        return $query->whereIn('alert_type', ['out_of_stock', 'low_stock']);
    }

    public function scopeByLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    // Accessors
    public function getIsActiveAttribute()
    {
        return $this->status === 'active';
    }

    public function getIsResolvedAttribute()
    {
        return $this->status === 'resolved';
    }

    public function getSeverityAttribute()
    {
        return match($this->alert_type) {
            'out_of_stock' => 'critical',
            'low_stock' => 'high',
            'overstock' => 'medium',
            'reorder' => 'low',
            default => 'low'
        };
    }

    public function getFormattedTypeAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->alert_type));
    }

    public function getDurationAttribute()
    {
        $start = $this->triggered_at;
        $end = $this->resolved_at ?? now();
        
        return $start->diffInHours($end);
    }

    // Methods
    public function resolve($userId = null, $notes = null)
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'notes' => $notes,
        ]);
    }

    public function dismiss($userId = null, $notes = null)
    {
        $this->update([
            'status' => 'dismissed',
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'notes' => $notes,
        ]);
    }
}