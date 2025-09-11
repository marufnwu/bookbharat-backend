<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_method',
        'is_enabled',
        'configuration',
        'restrictions',
        'priority',
        'display_name',
        'description'
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'configuration' => 'array',
        'restrictions' => 'array',
        'priority' => 'integer'
    ];

    public static function getEnabledMethods($orderAmount = null, $orderItems = [])
    {
        $query = self::where('is_enabled', true)
            ->orderBy('priority', 'desc');

        $methods = $query->get();

        return $methods->filter(function ($method) use ($orderAmount, $orderItems) {
            return $method->isAvailableForOrder($orderAmount, $orderItems);
        });
    }

    public function isAvailableForOrder($orderAmount = null, $orderItems = [])
    {
        $restrictions = $this->restrictions ?? [];

        // Check minimum order amount
        if (isset($restrictions['min_order_amount']) && $orderAmount < $restrictions['min_order_amount']) {
            return false;
        }

        // Check maximum order amount
        if (isset($restrictions['max_order_amount']) && $orderAmount > $restrictions['max_order_amount']) {
            return false;
        }

        // Check product category restrictions
        if (isset($restrictions['excluded_categories']) && !empty($orderItems)) {
            $excludedCategories = $restrictions['excluded_categories'];
            foreach ($orderItems as $item) {
                if (isset($item['category_id']) && in_array($item['category_id'], $excludedCategories)) {
                    return false;
                }
            }
        }

        // Check customer group restrictions (can be extended)
        if (isset($restrictions['customer_groups'])) {
            // Implementation depends on your customer group system
        }

        return true;
    }

    public function getAdvancePaymentAmount($orderAmount)
    {
        $config = $this->configuration ?? [];
        
        if (str_contains($this->payment_method, 'cod') && isset($config['advance_payment'])) {
            $advance = $config['advance_payment'];
            
            if ($advance['type'] === 'percentage') {
                return ($orderAmount * $advance['value']) / 100;
            } elseif ($advance['type'] === 'fixed') {
                return min($advance['value'], $orderAmount);
            }
        }

        return 0;
    }

    public function requiresAdvancePayment()
    {
        $config = $this->configuration ?? [];
        return isset($config['advance_payment']) && $config['advance_payment']['required'];
    }
}