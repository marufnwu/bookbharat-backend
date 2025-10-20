<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationSetting;

class SMSGatewayService
{
    protected $config;
    protected $maxRetries = 3;

    public function __construct()
    {
        $this->config = config('notifications.sms', []);
    }

    /**
     * Send SMS via configured gateway
     */
    public function send($to, $message, $eventType = null)
    {
        // Get event-specific settings or use global config
        $settings = $eventType ? NotificationSetting::getForEvent($eventType) : null;

        $gatewayUrl = $settings->sms_gateway_url ?? $this->config['api_endpoint'] ?? null;
        $apiKey = $settings ? $settings->decrypted_sms_api_key : ($this->config['api_key'] ?? null);
        $senderId = $settings->sms_sender_id ?? $this->config['sender_id'] ?? 'BKBHRT';
        $requestFormat = $settings->sms_request_format ?? $this->config['request_format'] ?? 'json';

        if (!$gatewayUrl || !$apiKey) {
            Log::warning('SMS gateway not configured', [
                'event_type' => $eventType,
                'to' => $to
            ]);
            return ['success' => false, 'error' => 'SMS gateway not configured'];
        }

        // Clean phone number
        $to = $this->cleanPhoneNumber($to);

        return $this->sendWithRetry($gatewayUrl, $apiKey, $to, $message, $senderId, $requestFormat);
    }

    /**
     * Send SMS with retry logic
     */
    protected function sendWithRetry($url, $apiKey, $to, $message, $senderId, $format, $attempt = 1)
    {
        try {
            $response = $this->makeRequest($url, $apiKey, $to, $message, $senderId, $format);

            if ($response->successful()) {
                Log::info('SMS sent successfully', [
                    'to' => $to,
                    'attempt' => $attempt,
                    'response' => $response->json()
                ]);

                return [
                    'success' => true,
                    'response' => $response->json(),
                    'message_id' => $this->extractMessageId($response->json())
                ];
            }

            // Retry logic
            if ($attempt < $this->maxRetries) {
                $delay = pow(2, $attempt) * 1000; // Exponential backoff: 2s, 4s, 8s
                usleep($delay * 1000);
                return $this->sendWithRetry($url, $apiKey, $to, $message, $senderId, $format, $attempt + 1);
            }

            Log::error('SMS sending failed after retries', [
                'to' => $to,
                'attempts' => $attempt,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'SMS sending failed: ' . $response->body(),
                'attempts' => $attempt
            ];

        } catch (\Exception $e) {
            Log::error('SMS sending exception', [
                'to' => $to,
                'attempt' => $attempt,
                'error' => $e->getMessage()
            ]);

            // Retry on exception
            if ($attempt < $this->maxRetries) {
                $delay = pow(2, $attempt) * 1000;
                usleep($delay * 1000);
                return $this->sendWithRetry($url, $apiKey, $to, $message, $senderId, $format, $attempt + 1);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'attempts' => $attempt
            ];
        }
    }

    /**
     * Make HTTP request to SMS gateway
     */
    protected function makeRequest($url, $apiKey, $to, $message, $senderId, $format)
    {
        $payload = [
            'to' => $to,
            'message' => $message,
            'sender_id' => $senderId,
        ];

        if ($format === 'json') {
            return Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);
        } else {
            // Form-encoded request
            return Http::asForm()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->post($url, $payload);
        }
    }

    /**
     * Clean phone number
     */
    protected function cleanPhoneNumber($phone)
    {
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading +91 or 91
        $phone = preg_replace('/^(\+91|91)/', '', $phone);

        return $phone;
    }

    /**
     * Extract message ID from response (gateway-specific)
     */
    protected function extractMessageId($response)
    {
        if (isset($response['message_id'])) {
            return $response['message_id'];
        }
        if (isset($response['id'])) {
            return $response['id'];
        }
        if (isset($response['data']['message_id'])) {
            return $response['data']['message_id'];
        }

        return null;
    }

    /**
     * Test SMS gateway connection
     */
    public function testConnection($gatewayUrl, $apiKey, $testNumber, $format = 'json')
    {
        try {
            $testMessage = 'This is a test message from BookBharat notification system.';

            $response = $this->makeRequest(
                $gatewayUrl,
                $apiKey,
                $this->cleanPhoneNumber($testNumber),
                $testMessage,
                'BKBHRT',
                $format
            );

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->json(),
                'message' => $response->successful()
                    ? 'Test SMS sent successfully'
                    : 'Failed to send test SMS'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }
}

