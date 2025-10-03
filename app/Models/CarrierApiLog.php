<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'carrier_id',
        'order_id',
        'api_method',
        'endpoint',
        'request_data',
        'response_data',
        'response_code',
        'response_time_ms',
        'status',
        'error_message',
        'tracking_number',
        'quoted_price'
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'quoted_price' => 'decimal:2'
    ];

    /**
     * Get the carrier that owns this log
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }

    /**
     * Get the order associated with this log
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Check if the API call was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the API call failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the API call timed out
     */
    public function isTimeout(): bool
    {
        return $this->status === 'timeout';
    }

    /**
     * Get masked request data (hide sensitive info)
     */
    public function getMaskedRequestData(): array
    {
        $data = $this->request_data ?? [];

        // Mask sensitive fields
        $sensitiveFields = ['api_key', 'api_secret', 'password', 'token', 'secret'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***MASKED***';
            }
        }

        return $data;
    }

    /**
     * Get masked response data (hide sensitive info)
     */
    public function getMaskedResponseData(): array
    {
        $data = $this->response_data ?? [];

        // Mask sensitive fields
        $sensitiveFields = ['api_key', 'api_secret', 'password', 'token', 'secret'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***MASKED***';
            }
        }

        return $data;
    }

    /**
     * Scope for successful API calls
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed API calls
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for timeout API calls
     */
    public function scopeTimeout($query)
    {
        return $query->where('status', 'timeout');
    }

    /**
     * Scope by API method
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('api_method', $method);
    }

    /**
     * Scope for recent logs
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get average response time for a carrier
     */
    public static function getAverageResponseTime(int $carrierId, string $method = null): ?float
    {
        $query = static::where('carrier_id', $carrierId)
            ->whereNotNull('response_time_ms')
            ->successful();

        if ($method) {
            $query->where('api_method', $method);
        }

        return $query->avg('response_time_ms');
    }

    /**
     * Get success rate for a carrier
     */
    public static function getSuccessRate(int $carrierId, string $method = null): float
    {
        $query = static::where('carrier_id', $carrierId);

        if ($method) {
            $query->where('api_method', $method);
        }

        $total = $query->count();

        if ($total === 0) {
            return 0;
        }

        $successful = $query->successful()->count();

        return ($successful / $total) * 100;
    }
}