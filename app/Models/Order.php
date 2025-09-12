<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Listeners\UpdateProductAssociations;

class Order extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'payment_status',
        'payment_method',
        'payment_transaction_id',
        'billing_address',
        'shipping_address',
        'notes',
        'shipped_at',
        'delivered_at',
        'referral_code_id',
        'referral_discount',
        'shipping_zone',
        'delivery_option_id',
        'insurance_amount',
        'shipping_details',
        'pickup_pincode',
        'delivery_pincode',
        'estimated_delivery_date',
        'tracking_number',
        'courier_partner',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'referral_discount' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'shipping_details' => 'array',
        'estimated_delivery_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    public function deliveryOption(): BelongsTo
    {
        return $this->belongsTo(DeliveryOption::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    // Mutators
    public function setOrderNumberAttribute($value)
    {
        $this->attributes['order_number'] = $value ?: 'ORD-' . strtoupper(uniqid());
    }

    // Accessors
    public function getTotalItemsAttribute()
    {
        return $this->orderItems->sum('quantity');
    }

    public function getCanBeCancelledAttribute()
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    // Boot method to register model events
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($order) {
            // When order status changes to delivered or completed, update product associations
            if ($order->isDirty('status') && in_array($order->status, ['delivered', 'completed'])) {
                $listener = new UpdateProductAssociations();
                $listener->handle($order);
            }
        });
    }
}
