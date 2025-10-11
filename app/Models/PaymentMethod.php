<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * CLEAN SINGLE TABLE MODEL - SINGLE SOURCE OF TRUTH
 *
 * Replaces the messy PaymentConfiguration + PaymentSetting dual-table architecture.
 * One row = one payment method (e.g., razorpay_upi, cod, cashfree_card)
 *
 * VISIBILITY RULE: is_enabled = THE ONLY SWITCH
 * - No hierarchies
 * - No foreign keys
 * - No cascade issues
 * - Simple query: WHERE is_enabled = true
 */
class PaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'payment_methods';

    protected $fillable = [
        'payment_method',      // Unique identifier (razorpay, cod, etc.)
        'display_name',        // User-friendly name
        'description',         // Description for customers
        'is_enabled',          // THE ONLY SWITCH - SINGLE SOURCE OF TRUTH
        'gateway_type',        // For grouping in admin UI (razorpay, cashfree, cod)
        'is_system',           // Predefined gateway (cannot be deleted)
        'is_default',          // Default gateway for online payments
        'is_fallback',         // Fallback gateway if default fails
        'credentials',         // API keys, secrets, merchant IDs
        'credential_schema',   // Schema definition for credential validation
        'configuration',       // advance_payment, service_charges, etc.
        'restrictions',        // min/max amounts, excluded categories
        'priority',            // Display order (higher = shown first)
        'is_production',       // Test vs Production mode
        'supported_currencies',// Supported currencies (JSON array)
        'webhook_config',      // Webhook settings
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_system' => 'boolean',
        'is_default' => 'boolean',
        'is_fallback' => 'boolean',
        'is_production' => 'boolean',
        'priority' => 'integer',
        'credentials' => 'array',
        'credential_schema' => 'array',
        'configuration' => 'array',
        'restrictions' => 'array',
        'supported_currencies' => 'array',
        'webhook_config' => 'array',
    ];

    protected $attributes = [
        'is_enabled' => true,
        'is_system' => false,
        'is_default' => false,
        'is_fallback' => false,
        'is_production' => false,
        'priority' => 0,
        'supported_currencies' => '["INR"]',
    ];

    /**
     * Credential schemas for each gateway type
     * Defines required and optional fields for gateway configuration
     */
    public static function getCredentialSchemas()
    {
        return [
            'razorpay' => [
                'required' => ['key_id', 'key_secret'],
                'optional' => ['webhook_secret'],
                'fields' => [
                    'key_id' => ['type' => 'string', 'label' => 'Razorpay Key ID', 'placeholder' => 'rzp_test_xxxxx'],
                    'key_secret' => ['type' => 'password', 'label' => 'Razorpay Key Secret', 'placeholder' => 'Enter secret key'],
                    'webhook_secret' => ['type' => 'password', 'label' => 'Webhook Secret', 'placeholder' => 'Optional webhook secret'],
                ]
            ],
            'cashfree' => [
                'required' => ['app_id', 'secret_key'],
                'optional' => [],
                'fields' => [
                    'app_id' => ['type' => 'string', 'label' => 'Cashfree App ID', 'placeholder' => 'Enter App ID'],
                    'secret_key' => ['type' => 'password', 'label' => 'Secret Key', 'placeholder' => 'Enter secret key'],
                ]
            ],
            'payu' => [
                'required' => ['merchant_key', 'merchant_salt'],
                'optional' => [],
                'fields' => [
                    'merchant_key' => ['type' => 'string', 'label' => 'Merchant Key', 'placeholder' => 'Enter merchant key'],
                    'merchant_salt' => ['type' => 'password', 'label' => 'Merchant Salt', 'placeholder' => 'Enter merchant salt'],
                ]
            ],
            'phonepe' => [
                'required' => ['merchant_id', 'salt_key', 'salt_index'],
                'optional' => [],
                'fields' => [
                    'merchant_id' => ['type' => 'string', 'label' => 'Merchant ID', 'placeholder' => 'Enter merchant ID'],
                    'salt_key' => ['type' => 'password', 'label' => 'Salt Key', 'placeholder' => 'Enter salt key'],
                    'salt_index' => ['type' => 'number', 'label' => 'Salt Index', 'placeholder' => '1'],
                ]
            ],
            'cod' => [
                'required' => [],
                'optional' => [],
                'fields' => [] // COD doesn't need credentials
            ],
        ];
    }

    /**
     * CLEAN & SIMPLE: Get enabled methods for customers
     *
     * @param float|null $orderAmount
     * @param array $orderItems
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getEnabledMethods($orderAmount = null, $orderItems = [])
    {
        $query = self::where('is_enabled', true)
            ->orderBy('priority', 'desc');

        $methods = $query->get();

        // Apply business rules
        return $methods->filter(function ($method) use ($orderAmount, $orderItems) {
            return $method->isAvailableForOrder($orderAmount, $orderItems);
        });
    }

    /**
     * Check if this method is available for the given order
     *
     * @param float|null $orderAmount
     * @param array $orderItems
     * @return bool
     */
    public function isAvailableForOrder($orderAmount = null, $orderItems = [])
    {
        $restrictions = $this->restrictions ?? [];

        // Skip amount restrictions when amount is not provided or is 0
        // This allows showing all available methods before order is finalized
        if ($orderAmount === null || $orderAmount <= 0) {
            return true;
        }

        // Check minimum order amount
        if (isset($restrictions['min_order_amount']) && $orderAmount < $restrictions['min_order_amount']) {
            return false;
        }

        // Check maximum order amount
        if (isset($restrictions['max_order_amount']) && $orderAmount > $restrictions['max_order_amount']) {
            return false;
        }

        // Check excluded categories
        if (isset($restrictions['excluded_categories']) && !empty($orderItems)) {
            $excludedCategories = $restrictions['excluded_categories'];
            foreach ($orderItems as $item) {
                if (isset($item['category_id']) && in_array($item['category_id'], $excludedCategories)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if this is a COD method
     *
     * @return bool
     */
    public function isCod()
    {
        return str_starts_with($this->payment_method, 'cod') || $this->gateway_type === 'cod';
    }

    /**
     * Check if this is an online payment method
     *
     * @return bool
     */
    public function isOnline()
    {
        return !$this->isCod();
    }

    /**
     * Get advance payment amount for COD orders
     *
     * @param float $orderAmount
     * @return float
     */
    public function getAdvancePaymentAmount($orderAmount)
    {
        if (!$this->isCod()) {
            return 0;
        }

        $config = $this->configuration ?? [];
        if (!isset($config['advance_payment']) || !$config['advance_payment']['required']) {
            return 0;
        }

        $advance = $config['advance_payment'];
        if ($advance['type'] === 'percentage') {
            return ($orderAmount * $advance['value']) / 100;
        } elseif ($advance['type'] === 'fixed') {
            return min($advance['value'], $orderAmount);
        }

        return 0;
    }

    /**
     * Check if advance payment is required
     *
     * @return bool
     */
    public function requiresAdvancePayment()
    {
        $config = $this->configuration ?? [];
        return isset($config['advance_payment']) && $config['advance_payment']['required'];
    }

    /**
     * Scope: Only online payment methods
     */
    public function scopeOnline($query)
    {
        return $query->where(function($q) {
            $q->where('gateway_type', '!=', 'cod')
              ->orWhereNotLike('payment_method', 'cod%');
        });
    }

    /**
     * Scope: Only COD methods
     */
    public function scopeCod($query)
    {
        return $query->where(function($q) {
            $q->where('gateway_type', 'cod')
              ->orWhere('payment_method', 'like', 'cod%');
        });
    }

    /**
     * Scope: Enabled methods
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope: Production mode only
     */
    public function scopeProduction($query)
    {
        return $query->where('is_production', true);
    }

    /**
     * Scope: System gateways (predefined, cannot be deleted)
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Get default payment gateway
     */
    public static function getDefault()
    {
        return self::where('is_default', true)
            ->where('is_enabled', true)
            ->first();
    }

    /**
     * Get fallback payment gateway
     */
    public static function getFallback()
    {
        return self::where('is_fallback', true)
            ->where('is_enabled', true)
            ->first();
    }

    /**
     * Set this gateway as default (removes default from others)
     */
    public function setAsDefault()
    {
        // Remove default from all other gateways
        self::where('id', '!=', $this->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);

        return $this;
    }

    /**
     * Set this gateway as fallback (removes fallback from others)
     */
    public function setAsFallback()
    {
        // Remove fallback from all other gateways
        self::where('id', '!=', $this->id)
            ->where('is_fallback', true)
            ->update(['is_fallback' => false]);

        // Set this as fallback
        $this->update(['is_fallback' => true]);

        return $this;
    }

    /**
     * Check if this gateway can be deleted
     */
    public function canBeDeleted()
    {
        return !$this->is_system;
    }

    /**
     * Validate credentials against schema
     *
     * @param array $credentials
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateCredentials(array $credentials)
    {
        $schema = $this->credential_schema ?? self::getCredentialSchemas()[$this->gateway_type] ?? null;

        if (!$schema) {
            return ['valid' => true, 'errors' => []];
        }

        $errors = [];

        // Check required fields
        foreach ($schema['required'] ?? [] as $field) {
            if (empty($credentials[$field])) {
                $errors[$field] = "The {$field} field is required.";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Check if credentials are configured
     */
    public function hasValidCredentials()
    {
        if ($this->gateway_type === 'cod') {
            return true; // COD doesn't need credentials
        }

        $result = $this->validateCredentials($this->credentials ?? []);
        return $result['valid'];
    }

    /**
     * Get credential schema for this gateway
     */
    public function getCredentialSchema()
    {
        return $this->credential_schema ?? self::getCredentialSchemas()[$this->gateway_type] ?? null;
    }

    /**
     * Mask sensitive credential values for display
     */
    public function getMaskedCredentials()
    {
        if (!$this->credentials) {
            return [];
        }

        $masked = [];
        foreach ($this->credentials as $key => $value) {
            if (empty($value)) {
                $masked[$key] = '';
            } else {
                // Mask password/secret fields
                if (str_contains($key, 'secret') || str_contains($key, 'password') || str_contains($key, 'salt')) {
                    $masked[$key] = '••••••••' . substr($value, -4);
                } else {
                    $masked[$key] = $value;
                }
            }
        }

        return $masked;
    }
}
