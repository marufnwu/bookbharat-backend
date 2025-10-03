<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'carrier_id',
        'service_code',
        'tracking_number',
        'status',
        'pickup_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'label_url',
        'invoice_url',
        'shipping_cost',
        'carrier_response',
        'cancelled_at',
        'cancellation_reason',
        'pod_url',
        'pod_received_at',
        'weight',
        'dimensions',
        'package_count',
        'notes',
    ];

    protected $casts = [
        'pickup_date' => 'datetime',
        'expected_delivery_date' => 'datetime',
        'actual_delivery_date' => 'datetime',
        'cancelled_at' => 'datetime',
        'pod_received_at' => 'datetime',
        'shipping_cost' => 'decimal:2',
        'weight' => 'decimal:3',
        'dimensions' => 'array',
        'carrier_response' => 'array',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PICKUP_SCHEDULED = 'pickup_scheduled';
    const STATUS_PICKED_UP = 'picked_up';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RETURNED = 'returned';
    const STATUS_FAILED = 'failed';

    /**
     * Get the order that owns the shipment.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the carrier for the shipment.
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }

    /**
     * Get the tracking events for the shipment.
     */
    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class);
    }

    /**
     * Scope to get active shipments.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_RETURNED, self::STATUS_FAILED]);
    }

    /**
     * Scope to get delivered shipments.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope to get in-transit shipments.
     */
    public function scopeInTransit($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PICKED_UP,
            self::STATUS_IN_TRANSIT,
            self::STATUS_OUT_FOR_DELIVERY
        ]);
    }

    /**
     * Check if the shipment can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return !in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
            self::STATUS_RETURNED,
            self::STATUS_OUT_FOR_DELIVERY
        ]);
    }

    /**
     * Check if the shipment is delivered.
     */
    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Check if the shipment is in transit.
     */
    public function isInTransit(): bool
    {
        return in_array($this->status, [
            self::STATUS_PICKED_UP,
            self::STATUS_IN_TRANSIT,
            self::STATUS_OUT_FOR_DELIVERY
        ]);
    }

    /**
     * Get the delivery time in days.
     */
    public function getDeliveryTimeInDays(): ?int
    {
        if (!$this->pickup_date || !$this->actual_delivery_date) {
            return null;
        }

        return $this->pickup_date->diffInDays($this->actual_delivery_date);
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'gray',
            self::STATUS_CONFIRMED => 'blue',
            self::STATUS_PICKUP_SCHEDULED => 'indigo',
            self::STATUS_PICKED_UP => 'purple',
            self::STATUS_IN_TRANSIT => 'yellow',
            self::STATUS_OUT_FOR_DELIVERY => 'orange',
            self::STATUS_DELIVERED => 'green',
            self::STATUS_CANCELLED => 'red',
            self::STATUS_RETURNED => 'pink',
            self::STATUS_FAILED => 'red',
            default => 'gray',
        };
    }

    /**
     * Update tracking status.
     */
    public function updateStatus(string $status, array $details = []): void
    {
        $this->status = $status;

        if ($status === self::STATUS_DELIVERED && isset($details['delivery_date'])) {
            $this->actual_delivery_date = $details['delivery_date'];
        }

        if ($status === self::STATUS_CANCELLED) {
            $this->cancelled_at = now();
            $this->cancellation_reason = $details['reason'] ?? null;
        }

        $this->save();

        // Log tracking event
        $this->trackingEvents()->create([
            'status' => $status,
            'description' => $details['description'] ?? "Status updated to {$status}",
            'location' => $details['location'] ?? null,
            'timestamp' => $details['timestamp'] ?? now(),
            'raw_data' => $details['raw_data'] ?? null,
        ]);
    }
}