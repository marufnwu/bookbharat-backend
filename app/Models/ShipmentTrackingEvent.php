<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentTrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'status',
        'description',
        'location',
        'timestamp',
        'raw_data',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Get the shipment that owns the tracking event.
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Scope to get recent events.
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('timestamp', 'desc')->limit($limit);
    }

    /**
     * Get formatted timestamp.
     */
    public function getFormattedTimestampAttribute(): string
    {
        return $this->timestamp->format('M d, Y h:i A');
    }

    /**
     * Get event icon based on status.
     */
    public function getIcon(): string
    {
        return match ($this->status) {
            'pending' => 'clock',
            'confirmed' => 'check-circle',
            'pickup_scheduled' => 'calendar',
            'picked_up' => 'package',
            'in_transit' => 'truck',
            'out_for_delivery' => 'map-pin',
            'delivered' => 'check-double',
            'cancelled' => 'x-circle',
            'returned' => 'arrow-left',
            'failed' => 'alert-circle',
            default => 'info',
        };
    }

    /**
     * Get event color based on status.
     */
    public function getColor(): string
    {
        return match ($this->status) {
            'delivered' => 'green',
            'cancelled', 'failed' => 'red',
            'returned' => 'orange',
            'in_transit', 'out_for_delivery' => 'blue',
            default => 'gray',
        };
    }
}