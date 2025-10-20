<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationSetting;

class WhatsAppBusinessService
{
    protected $config;
    protected $maxRetries = 3;

    public function __construct()
    {
        $this->config = config('notifications.whatsapp', []);
    }

    /**
     * Send WhatsApp message via Meta Graph API
     */
    public function send($to, $templateName, $components = [], $eventType = null)
    {
        // Get event-specific settings or use global config
        $settings = $eventType ? NotificationSetting::getForEvent($eventType) : null;

        $apiUrl = $settings->whatsapp_api_url ?? $this->config['api_url'] ?? null;
        $accessToken = $settings ? $settings->decrypted_whatsapp_access_token : ($this->config['access_token'] ?? null);
        $phoneNumberId = $settings->whatsapp_phone_number_id ?? $this->config['phone_number_id'] ?? null;

        if (!$apiUrl || !$accessToken || !$phoneNumberId) {
            Log::warning('WhatsApp API not configured', [
                'event_type' => $eventType,
                'to' => $to
            ]);
            return ['success' => false, 'error' => 'WhatsApp API not configured'];
        }

        // Clean phone number
        $to = $this->cleanPhoneNumber($to);

        return $this->sendWithRetry($apiUrl, $accessToken, $phoneNumberId, $to, $templateName, $components);
    }

    /**
     * Send WhatsApp message with retry logic
     */
    protected function sendWithRetry($apiUrl, $accessToken, $phoneNumberId, $to, $templateName, $components, $attempt = 1)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($apiUrl . '/messages', [
                'messaging_product' => 'whatsapp',
                'to' => '91' . $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => 'en'
                    ],
                    'components' => $components
                ]
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp message sent successfully', [
                    'to' => $to,
                    'template' => $templateName,
                    'attempt' => $attempt,
                    'response' => $response->json()
                ]);

                return [
                    'success' => true,
                    'response' => $response->json(),
                    'message_id' => $response->json()['messages'][0]['id'] ?? null
                ];
            }

            // Retry logic
            if ($attempt < $this->maxRetries) {
                $delay = pow(2, $attempt) * 1000;
                usleep($delay * 1000);
                return $this->sendWithRetry($apiUrl, $accessToken, $phoneNumberId, $to, $templateName, $components, $attempt + 1);
            }

            Log::error('WhatsApp sending failed after retries', [
                'to' => $to,
                'template' => $templateName,
                'attempts' => $attempt,
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => $response->json()['error']['message'] ?? 'WhatsApp sending failed',
                'attempts' => $attempt
            ];

        } catch (\Exception $e) {
            Log::error('WhatsApp sending exception', [
                'to' => $to,
                'template' => $templateName,
                'attempt' => $attempt,
                'error' => $e->getMessage()
            ]);

            if ($attempt < $this->maxRetries) {
                $delay = pow(2, $attempt) * 1000;
                usleep($delay * 1000);
                return $this->sendWithRetry($apiUrl, $accessToken, $phoneNumberId, $to, $templateName, $components, $attempt + 1);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'attempts' => $attempt
            ];
        }
    }

    /**
     * Send text message (non-template)
     */
    public function sendText($to, $message, $eventType = null)
    {
        $settings = $eventType ? NotificationSetting::getForEvent($eventType) : null;

        $apiUrl = $settings->whatsapp_api_url ?? $this->config['api_url'] ?? null;
        $accessToken = $settings ? $settings->decrypted_whatsapp_access_token : ($this->config['access_token'] ?? null);

        if (!$apiUrl || !$accessToken) {
            return ['success' => false, 'error' => 'WhatsApp API not configured'];
        }

        $to = $this->cleanPhoneNumber($to);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($apiUrl . '/messages', [
                'messaging_product' => 'whatsapp',
                'to' => '91' . $to,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ]
            ]);

            return [
                'success' => $response->successful(),
                'response' => $response->json(),
                'message_id' => $response->json()['messages'][0]['id'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('WhatsApp text message failed', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fetch templates from WhatsApp Business API
     */
    public function fetchTemplates($apiUrl = null, $accessToken = null, $businessAccountId = null)
    {
        $apiUrl = $apiUrl ?? $this->config['api_url'] ?? null;
        $accessToken = $accessToken ?? $this->config['access_token'] ?? null;
        $businessAccountId = $businessAccountId ?? $this->config['business_account_id'] ?? null;

        if (!$apiUrl || !$accessToken || !$businessAccountId) {
            return ['success' => false, 'error' => 'WhatsApp API credentials missing'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get("https://graph.facebook.com/v18.0/{$businessAccountId}/message_templates", [
                'fields' => 'name,status,components,language',
                'limit' => 100
            ]);

            if ($response->successful()) {
                $templates = $response->json()['data'] ?? [];

                // Filter only approved templates
                $approvedTemplates = array_filter($templates, function($template) {
                    return $template['status'] === 'APPROVED';
                });

                return [
                    'success' => true,
                    'templates' => array_values($approvedTemplates),
                    'count' => count($approvedTemplates)
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['error']['message'] ?? 'Failed to fetch templates'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to fetch WhatsApp templates', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test WhatsApp connection
     */
    public function testConnection($apiUrl, $accessToken, $phoneNumberId, $testNumber)
    {
        try {
            $to = $this->cleanPhoneNumber($testNumber);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($apiUrl . '/messages', [
                'messaging_product' => 'whatsapp',
                'to' => '91' . $to,
                'type' => 'text',
                'text' => [
                    'body' => 'This is a test message from BookBharat notification system.'
                ]
            ]);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->json(),
                'message' => $response->successful()
                    ? 'Test WhatsApp message sent successfully'
                    : 'Failed to send test message'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clean phone number
     */
    protected function cleanPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phone = preg_replace('/^(\+91|91)/', '', $phone);
        return $phone;
    }

    /**
     * Build template components for common use cases
     */
    public function buildOrderComponents($order, $templateType)
    {
        return match($templateType) {
            'order_placed' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $order->user->name],
                        ['type' => 'text', 'text' => $order->order_number],
                        ['type' => 'text', 'text' => '₹' . number_format($order->total_amount, 2)],
                    ]
                ]
            ],
            'order_shipped' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $order->user->name],
                        ['type' => 'text', 'text' => $order->order_number],
                        ['type' => 'text', 'text' => $order->tracking_number ?? 'N/A'],
                    ]
                ]
            ],
            'order_delivered' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $order->user->name],
                        ['type' => 'text', 'text' => $order->order_number],
                    ]
                ]
            ],
            default => []
        };
    }
}

