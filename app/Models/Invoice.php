<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'shipping_amount',
        'total_amount',
        'currency',
        'status',
        'notes',
        'invoice_data',
        'pdf_path'
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'invoice_data' => 'array'
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    // Scopes
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                    ->where('due_date', '<', now());
    }

    // Accessors
    public function getFormattedInvoiceNumberAttribute()
    {
        return 'INV-' . str_pad($this->invoice_number, 6, '0', STR_PAD_LEFT);
    }

    public function getIsPaidAttribute()
    {
        return $this->status === 'paid';
    }

    public function getIsOverdueAttribute()
    {
        return $this->status === 'pending' && $this->due_date < now();
    }

    // Helper methods
    public function markAsPaid()
    {
        $this->update(['status' => 'paid']);
        
        // Update order payment status if needed
        if ($this->order && $this->order->payment_status !== 'completed') {
            $this->order->update(['payment_status' => 'completed']);
        }
    }

    public function generatePDF()
    {
        // This method will generate PDF invoice
        // Implementation depends on chosen PDF library (TCPDF, DOMPDF, etc.)
        $invoiceService = app(\App\Services\InvoiceService::class);
        return $invoiceService->generatePDF($this);
    }
}