<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class ErrorLoggingService
{
    /**
     * Log error with comprehensive context
     */
    public function logError(\Throwable $exception, array $context = []): void
    {
        $correlationId = Request::id();
        $userId = auth()->id();
        
        Log::error('Application Error', [
            'correlation_id' => $correlationId,
            'message' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'user_id' => $userId,
            'ip_address' => Request::ip(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'request_data' => $this->sanitizeRequestData(),
            'context' => $context,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Log API error with request details
     */
    public function logApiError(\Throwable $exception, string $endpoint, array $requestData = []): void
    {
        $correlationId = Request::id();
        
        Log::error('API Error', [
            'correlation_id' => $correlationId,
            'endpoint' => $endpoint,
            'message' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'status_code' => method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500,
            'request_data' => $this->sanitizeArray($requestData),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Log payment error with transaction details
     */
    public function logPaymentError(\Throwable $exception, string $gateway, array $transactionData): void
    {
        $correlationId = Request::id();
        $userId = auth()->id();
        
        Log::error('Payment Error', [
            'correlation_id' => $correlationId,
            'gateway' => $gateway,
            'message' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'order_id' => $transactionData['order_id'] ?? null,
            'transaction_id' => $transactionData['transaction_id'] ?? null,
            'amount' => $transactionData['amount'] ?? null,
            'user_id' => $userId,
            'payment_method' => $transactionData['method'] ?? null,
            'trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Sanitize request data to remove sensitive information
     */
    protected function sanitizeRequestData(): array
    {
        $data = Request::except(['password', 'password_confirmation', 'card_number', 'cvv', 'token']);
        return $this->sanitizeArray($data);
    }
    
    /**
     * Recursively sanitize array to remove sensitive data
     */
    protected function sanitizeArray(array $data): array
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'card_number', 'cvv', 'token', 'api_key', 'secret'];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            } elseif (in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '***HIDDEN***';
            }
        }
        
        return $data;
    }
}
