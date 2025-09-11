<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class InventoryItem extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'product_id',
        'variant_id',
        'location_id',
        'available_quantity',
        'reserved_quantity',
        'on_order_quantity',
        'allocated_quantity',
        'damaged_quantity',
        'reorder_point',
        'max_stock_level',
        'unit_cost',
        'bin_location',
        'last_counted_at',
    ];

    protected $casts = [
        'available_quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'on_order_quantity' => 'integer',
        'allocated_quantity' => 'integer',
        'damaged_quantity' => 'integer',
        'reorder_point' => 'integer',
        'max_stock_level' => 'integer',
        'unit_cost' => 'decimal:2',
        'last_counted_at' => 'datetime',
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

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id', 'product_id')
            ->where('variant_id', $this->variant_id)
            ->where('location_id', $this->location_id);
    }

    // Scopes
    public function scopeLowStock($query)
    {
        return $query->whereRaw('available_quantity <= reorder_point');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('available_quantity', 0);
    }

    public function scopeOverstock($query)
    {
        return $query->whereRaw('available_quantity > max_stock_level');
    }

    public function scopeByLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    // Accessors
    public function getTotalQuantityAttribute()
    {
        return $this->available_quantity + $this->reserved_quantity + $this->allocated_quantity;
    }

    public function getActualAvailableAttribute()
    {
        return max(0, $this->available_quantity - $this->reserved_quantity - $this->allocated_quantity);
    }

    public function getStockStatusAttribute()
    {
        if ($this->available_quantity <= 0) {
            return 'out_of_stock';
        }
        
        if ($this->available_quantity <= $this->reorder_point) {
            return 'low_stock';
        }
        
        if ($this->available_quantity > $this->max_stock_level) {
            return 'overstock';
        }
        
        return 'in_stock';
    }

    public function getStockValueAttribute()
    {
        return $this->available_quantity * $this->unit_cost;
    }

    public function getNeedsReorderAttribute()
    {
        return $this->available_quantity <= $this->reorder_point;
    }

    // Methods
    public function adjustStock($quantity, $type, $reason, $userId = null, $notes = null)
    {
        $oldQuantity = $this->available_quantity;
        $newQuantity = max(0, $oldQuantity + $quantity);
        
        $this->update(['available_quantity' => $newQuantity]);
        
        // Record movement
        InventoryMovement::create([
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'location_id' => $this->location_id,
            'type' => $type,
            'reason' => $reason,
            'quantity' => abs($quantity),
            'quantity_before' => $oldQuantity,
            'quantity_after' => $newQuantity,
            'unit_cost' => $this->unit_cost,
            'created_by' => $userId,
            'notes' => $notes,
        ]);

        // Check for alerts
        $this->checkStockAlerts();

        return $newQuantity;
    }

    public function reserveStock($quantity)
    {
        if ($this->actual_available >= $quantity) {
            $this->increment('reserved_quantity', $quantity);
            return true;
        }
        return false;
    }

    public function releaseReservedStock($quantity)
    {
        $releaseQuantity = min($quantity, $this->reserved_quantity);
        $this->decrement('reserved_quantity', $releaseQuantity);
        return $releaseQuantity;
    }

    public function allocateStock($quantity)
    {
        if ($this->actual_available >= $quantity) {
            $this->increment('allocated_quantity', $quantity);
            return true;
        }
        return false;
    }

    public function fulfillAllocatedStock($quantity)
    {
        $fulfillQuantity = min($quantity, $this->allocated_quantity);
        $this->decrement('allocated_quantity', $fulfillQuantity);
        $this->decrement('available_quantity', $fulfillQuantity);
        return $fulfillQuantity;
    }

    protected function checkStockAlerts()
    {
        // Check for low stock alert
        if ($this->needs_reorder) {
            StockAlert::firstOrCreate([
                'product_id' => $this->product_id,
                'variant_id' => $this->variant_id,
                'location_id' => $this->location_id,
                'alert_type' => 'low_stock',
                'status' => 'active',
            ], [
                'threshold_quantity' => $this->reorder_point,
                'current_quantity' => $this->available_quantity,
                'triggered_at' => now(),
            ]);
        }

        // Check for out of stock alert
        if ($this->available_quantity <= 0) {
            StockAlert::firstOrCreate([
                'product_id' => $this->product_id,
                'variant_id' => $this->variant_id,
                'location_id' => $this->location_id,
                'alert_type' => 'out_of_stock',
                'status' => 'active',
            ], [
                'threshold_quantity' => 0,
                'current_quantity' => $this->available_quantity,
                'triggered_at' => now(),
            ]);
        }
    }
}