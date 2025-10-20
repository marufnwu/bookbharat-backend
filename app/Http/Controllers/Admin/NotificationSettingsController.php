<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationSetting;
use App\Services\SMSGatewayService;
use App\Services\WhatsAppBusinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationSettingsController extends Controller
{
    protected $smsGatewayService;
    protected $whatsappService;

    public function __construct(
        SMSGatewayService $smsGatewayService,
        WhatsAppBusinessService $whatsappService
    ) {
        $this->smsGatewayService = $smsGatewayService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Get all notification settings
     */
    public function index()
    {
        try {
            $settings = NotificationSetting::all();
            $eventTypes = NotificationSetting::getEventTypes();

            // Ensure all event types have settings
            $settingsMap = [];
            foreach ($eventTypes as $key => $label) {
                $setting = $settings->firstWhere('event_type', $key);
                if (!$setting) {
                    $setting = NotificationSetting::getForEvent($key);
                }
                $settingsMap[$key] = $setting;
            }

            return response()->json([
                'success' => true,
                'settings' => $settingsMap,
                'event_types' => $eventTypes,
                'available_channels' => config('notifications.channels'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update notification settings
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string',
            'channels' => 'nullable|array',
            'channels.*' => 'in:email,sms,whatsapp,push',
            'enabled' => 'boolean',
            'sms_gateway_url' => 'nullable|url',
            'sms_api_key' => 'nullable|string',
            'sms_sender_id' => 'nullable|string|max:11',
            'sms_request_format' => 'nullable|in:json,form',
            'whatsapp_api_url' => 'nullable|url',
            'whatsapp_access_token' => 'nullable|string',
            'whatsapp_phone_number_id' => 'nullable|string',
            'whatsapp_business_account_id' => 'nullable|string',
            'whatsapp_templates' => 'nullable|array',
            'email_from' => 'nullable|email',
            'email_from_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $setting = NotificationSetting::updateOrCreate(
                ['event_type' => $request->event_type],
                $request->only([
                    'channels',
                    'enabled',
                    'sms_gateway_url',
                    'sms_api_key',
                    'sms_sender_id',
                    'sms_request_format',
                    'whatsapp_api_url',
                    'whatsapp_access_token',
                    'whatsapp_phone_number_id',
                    'whatsapp_business_account_id',
                    'whatsapp_templates',
                    'email_from',
                    'email_from_name',
                ])
            );

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully',
                'setting' => $setting
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available channels
     */
    public function getChannels()
    {
        return response()->json([
            'success' => true,
            'channels' => config('notifications.channels'),
            'default_channels' => config('notifications.default_channels'),
            'event_channels' => config('notifications.event_channels'),
        ]);
    }

    /**
     * Test SMS gateway connection
     */
    public function testSMS(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gateway_url' => 'required|url',
            'api_key' => 'required|string',
            'test_number' => 'required|string',
            'request_format' => 'required|in:json,form',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->smsGatewayService->testConnection(
                $request->gateway_url,
                $request->api_key,
                $request->test_number,
                $request->request_format
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'SMS test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test WhatsApp connection
     */
    public function testWhatsApp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_url' => 'required|url',
            'access_token' => 'required|string',
            'phone_number_id' => 'required|string',
            'test_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->whatsappService->testConnection(
                $request->api_url,
                $request->access_token,
                $request->phone_number_id,
                $request->test_number
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync WhatsApp templates
     */
    public function syncWhatsAppTemplates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_url' => 'nullable|url',
            'access_token' => 'nullable|string',
            'business_account_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->whatsappService->fetchTemplates(
                $request->api_url,
                $request->access_token,
                $request->business_account_id
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "Successfully synced {$result['count']} WhatsApp templates",
                    'templates' => $result['templates']
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync templates',
                'error' => $result['error']
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Template sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get email configuration
     */
    public function getEmailConfig()
    {
        try {
            return response()->json([
                'success' => true,
                'config' => [
                    'mailer' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'encryption' => config('mail.mailers.smtp.encryption'),
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get email configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update email configuration
     */
    public function updateEmailConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'host' => 'required|string',
            'port' => 'required|integer',
            'encryption' => 'required|in:tls,ssl',
            'username' => 'required|string',
            'password' => 'required|string',
            'from_address' => 'required|email',
            'from_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update .env file
            $this->updateEnvFile([
                'MAIL_HOST' => $request->host,
                'MAIL_PORT' => $request->port,
                'MAIL_ENCRYPTION' => $request->encryption,
                'MAIL_USERNAME' => $request->username,
                'MAIL_PASSWORD' => $request->password,
                'MAIL_FROM_ADDRESS' => $request->from_address,
                'MAIL_FROM_NAME' => $request->from_name,
            ]);

            // Clear config cache
            \Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Email configuration updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update .env file
     */
    protected function updateEnvFile($data)
    {
        $envFile = base_path('.env');
        $str = file_get_contents($envFile);

        foreach ($data as $key => $value) {
            $keyPosition = strpos($str, "{$key}=");
            $endOfLinePosition = strpos($str, PHP_EOL, $keyPosition);
            $oldLine = substr($str, $keyPosition, $endOfLinePosition - $keyPosition);

            // Wrap value in quotes if it contains spaces
            if (strpos($value, ' ') !== false) {
                $value = '"' . $value . '"';
            }

            $newLine = "{$key}={$value}";
            $str = str_replace($oldLine, $newLine, $str);
        }

        file_put_contents($envFile, $str);
    }

    /**
     * Send test notification
     */
    public function sendTestNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'required|in:email,sms,whatsapp',
            'recipient' => 'required|string',
            'event_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $channel = $request->channel;
            $recipient = $request->recipient;
            $eventType = $request->event_type ?? 'test_notification';

            $testData = [
                'subject' => 'Test Notification from BookBharat',
                'message' => 'This is a test notification from the BookBharat notification system.',
                'name' => 'Test User',
                'order_number' => 'TEST-12345',
                'amount' => '499.00',
            ];

            $result = null;

            switch ($channel) {
                case 'email':
                    $notificationService = app(NotificationService::class);
                    $result = $notificationService->sendEmail(
                        $recipient,
                        $testData['subject'],
                        'emails.default',
                        $testData
                    );
                    break;

                case 'sms':
                    $result = $this->smsGatewayService->send(
                        $recipient,
                        $testData['message'],
                        $eventType
                    );
                    break;

                case 'whatsapp':
                    $result = $this->whatsappService->sendText(
                        $recipient,
                        $testData['message'],
                        $eventType
                    );
                    break;
            }

            if ($result && $result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "Test {$channel} notification sent successfully",
                    'result' => $result
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Failed to send test {$channel} notification",
                'error' => $result['error'] ?? 'Unknown error'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test notification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

