<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Return extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number',
        'order_id',
        'user_id',
        'return_type',
        'status',
        'reason',
        'description',
        'items',
        'refund_amount',
        'refund_method',
        'requested_at',
        'approved_at',
        'approved_by',
        'shipped_at',
        'return_tracking_number',
        'return_shipping_method',
        'received_at',
        'processed_at',
        'processed_by',
        'completed_at',
        'admin_notes',
        'quality_check_results',
        'images'
    ];

    protected $casts = [
        'items' => 'array',
        'quality_check_results' => 'array',
        'images' => 'array',
        'refund_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'requested');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByReturnType($query, $type)
    {
        return $query->where('return_type', $type);
    }

    // Accessors
    public function getIsRefundAttribute()
    {
        return $this->return_type === 'refund';
    }

    public function getIsExchangeAttribute()
    {
        return $this->return_type === 'exchange';
    }

    public function getIsStoreCreditAttribute()
    {
        return $this->return_type === 'store_credit';
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'requested' => 'Return Requested',
            'approved' => 'Return Approved',
            'rejected' => 'Return Rejected',
            'shipped' => 'Item Shipped',
            'received' => 'Item Received',
            'processed' => 'Refund Processed',
            'completed' => 'Return Completed',
            'cancelled' => 'Return Cancelled',
            default => ucfirst($this->status)
        };
    }

    public function getReturnTypeLabelAttribute()
    {
        return match($this->return_type) {
            'refund' => 'Refund',
            'exchange' => 'Exchange',
            'store_credit' => 'Store Credit',
            default => ucfirst($this->return_type)
        };
    }

    public function getReasonLabelAttribute()
    {
        return match($this->reason) {
            'defective' => 'Product is defective or damaged',
            'wrong_item' => 'Wrong item was delivered',
            'not_as_described' => 'Item not as described',
            'changed_mind' => 'Changed my mind',
            'damaged_packaging' => 'Damaged packaging',
            'size_issue' => 'Size does not fit',
            'color_issue' => 'Color is different than expected',
            default => $this->reason
        };
    }

    public function getDaysSinceRequestedAttribute()
    {
        return $this->requested_at ? $this->requested_at->diffInDays(now()) : 0;
    }

    // Helper methods
    public function canBeCancelled()
    {
        return $this->status === 'requested';
    }

    public function canBeApproved()
    {
        return $this->status === 'requested';
    }

    public function canBeRejected()
    {
        return $this->status === 'requested';
    }

    public function isInProgress()
    {
        return in_array($this->status, ['requested', 'approved', 'shipped', 'received', 'processed']);
    }

    public function isCompleted()
    {
        return in_array($this->status, ['completed', 'rejected', 'cancelled']);
    }

    public function getTotalItemsCount()
    {
        if (!$this->items) {
            return 0;
        }

        return collect($this->items)->sum('quantity');
    }

    public function getItemsDetails()
    {
        if (!$this->items) {
            return [];
        }

        return collect($this->items)->map(function ($item) {
            return [
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'product_name' => $item['product_name'] ?? 'Unknown Product',
                'unit_price' => $item['unit_price'] ?? 0,
                'total_price' => ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0)
            ];
        });
    }

    public function generateReturnLabel()
    {
        // In a real implementation, integrate with shipping service API
        // For now, return mock data
        return [
            'tracking_number' => 'RET' . $this->id . time(),
            'label_url' => url('/api/returns/' . $this->id . '/label'),
            'instructions' => [
                'Print the return label',
                'Pack items in original packaging if available',
                'Include all accessories and tags',
                'Attach the return label to the package',
                'Drop off at any authorized shipping location'
            ]
        ];
    }

    public function updateStatus($status, $adminNotes = null, $additionalData = [])
    {
        $updateData = array_merge([
            'status' => $status,
            'admin_notes' => $adminNotes
        ], $additionalData);

        // Add timestamp fields based on status
        switch ($status) {
            case 'approved':
                $updateData['approved_at'] = now();
                if (auth()->check()) {
                    $updateData['approved_by'] = auth()->id();
                }
                break;
            case 'shipped':
                $updateData['shipped_at'] = now();
                break;
            case 'received':
                $updateData['received_at'] = now();
                break;
            case 'processed':
                $updateData['processed_at'] = now();
                if (auth()->check()) {
                    $updateData['processed_by'] = auth()->id();
                }
                break;
            case 'completed':
                $updateData['completed_at'] = now();
                break;
        }

        $this->update($updateData);
        return $this;
    }
}