<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductAssociation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'associated_product_id',
        'frequency',
        'confidence_score',
        'association_type',
        'last_purchased_together'
    ];

    protected $casts = [
        'frequency' => 'integer',
        'confidence_score' => 'float',
        'last_purchased_together' => 'datetime',
    ];

    /**
     * Get the main product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the associated product
     */
    public function associatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'associated_product_id');
    }

    /**
     * Scope for frequently bought together
     */
    public function scopeBoughtTogether($query)
    {
        return $query->where('association_type', 'bought_together');
    }

    /**
     * Scope for high confidence associations
     */
    public function scopeHighConfidence($query, $minScore = 0.5)
    {
        return $query->where('confidence_score', '>=', $minScore);
    }

    /**
     * Update confidence score based on frequency and recency
     */
    public function updateConfidenceScore()
    {
        // Calculate confidence based on frequency and recency
        $frequencyScore = min($this->frequency / 100, 0.5); // Max 50% weight from frequency
        
        // Recency score (purchases in last 30 days get higher score)
        $recencyScore = 0;
        if ($this->last_purchased_together) {
            $daysSinceLastPurchase = $this->last_purchased_together->diffInDays(now());
            if ($daysSinceLastPurchase <= 7) {
                $recencyScore = 0.3;
            } elseif ($daysSinceLastPurchase <= 30) {
                $recencyScore = 0.2;
            } elseif ($daysSinceLastPurchase <= 90) {
                $recencyScore = 0.1;
            }
        }
        
        // Base popularity score
        $popularityScore = 0.2;
        
        $this->confidence_score = min($frequencyScore + $recencyScore + $popularityScore, 1.0);
        $this->save();
    }
}