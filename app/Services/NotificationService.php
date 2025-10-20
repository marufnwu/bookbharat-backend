<?php

namespace App\Services;

use App\Models\User;
use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    protected $smsConfig;
    protected $pushConfig;
    protected $emailConfig;
    protected $smsGatewayService;
    protected $whatsappService;

    public function __construct(
        SMSGatewayService $smsGatewayService,
        WhatsAppBusinessService $whatsappService
    ) {
        $this->smsConfig = config('notifications.sms', []);
        $this->pushConfig = config('notifications.push', []);
        $this->emailConfig = config('mail');
        $this->smsGatewayService = $smsGatewayService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Send email notification
     */
    public function sendEmail(string $to, string $subject, string $template, array $data = [])
    {
        try {
            Mail::send($template, $data, function ($message) use ($to, $subject) {
                $message->to($to)
                       ->subject($subject)
                       ->from(config('mail.from.address'), config('mail.from.name'));
            });
            
            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Email sending failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send SMS notification
     */
    public function sendSMS(string $to, string $message, array $options = [])
    {
        try {
            $eventType = $options['event_type'] ?? null;
            return $this->smsGatewayService->send($to, $message, $eventType);
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'to' => $to,
                'message' => $message,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send push notification
     */
    public function sendPush(string $token, string $title, string $body, array $data = [])
    {
        try {
            // Firebase Cloud Messaging implementation
            if ($this->pushConfig['provider'] === 'fcm') {
                return $this->sendViaFCM($token, $title, $body, $data);
            }
            
            // OneSignal implementation
            if ($this->pushConfig['provider'] === 'onesignal') {
                return $this->sendViaOneSignal($token, $title, $body, $data);
            }
            
            Log::warning('No push provider configured');
            return ['success' => false, 'error' => 'No push provider configured'];
            
        } catch (\Exception $e) {
            Log::error('Push notification failed', [
                'token' => $token,
                'title' => $title,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send WhatsApp notification
     */
    public function sendWhatsApp(string $to, string $message, array $options = [])
    {
        try {
            $eventType = $options['event_type'] ?? null;
            $templateName = $options['template_name'] ?? null;
            $components = $options['components'] ?? [];

            // Use template if provided, otherwise send as text
            if ($templateName) {
                return $this->whatsappService->send($to, $templateName, $components, $eventType);
            } else {
                return $this->whatsappService->sendText($to, $message, $eventType);
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp sending failed', [
                'to' => $to,
                'message' => $message,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send notification to user via preferred channels
     */
    public function notifyUser(User $user, string $type, array $data)
    {
        $results = [];
        
        // Get notification settings for this event type
        $settings = NotificationSetting::getForEvent($type);
        $enabledChannels = $settings->channels ?? ['email'];

        // Email notification
        if (in_array('email', $enabledChannels) && ($user->email_notifications_enabled ?? true)) {
            $results['email'] = $this->sendEmail(
                $user->email,
                $data['subject'] ?? 'Notification from BookBharat',
                $data['email_template'] ?? 'emails.default',
                $data
            );
        }
        
        // SMS notification
        if (in_array('sms', $enabledChannels) && ($user->sms_notifications_enabled ?? false) && $user->phone) {
            $smsMessage = $this->buildSMSMessage($type, $data);
            $results['sms'] = $this->sendSMS(
                $user->phone,
                $smsMessage,
                ['event_type' => $type]
            );
        }
        
        // WhatsApp notification
        if (in_array('whatsapp', $enabledChannels) && ($user->whatsapp_notifications_enabled ?? false) && $user->phone) {
            $whatsappTemplate = $this->getWhatsAppTemplate($type);
            $whatsappComponents = $data['whatsapp_components'] ?? [];
            
            $results['whatsapp'] = $this->sendWhatsApp(
                $user->phone,
                $data['message'] ?? '',
                [
                    'event_type' => $type,
                    'template_name' => $whatsappTemplate,
                    'components' => $whatsappComponents
                ]
            );
        }
        
        // Push notification
        if (in_array('push', $enabledChannels) && $user->push_token) {
            $results['push'] = $this->sendPush(
                $user->push_token,
                $data['push_title'] ?? $data['title'] ?? 'BookBharat',
                $data['push_body'] ?? $data['message'] ?? '',
                $data['push_data'] ?? []
            );
        }
        
        // In-app notification
        $results['in_app'] = $this->createInAppNotification($user, $type, $data);
        
        return $results;
    }

    /**
     * Build SMS message from template
     */
    protected function buildSMSMessage($eventType, $data)
    {
        $template = config("notifications.sms_templates.{$eventType}");
        
        if (!$template) {
            return $data['sms_message'] ?? $data['message'] ?? 'Notification from BookBharat';
        }

        // Replace variables in template
        $replacements = [
            '{name}' => $data['user']->name ?? $data['name'] ?? 'Customer',
            '{order_number}' => $data['order_number'] ?? '',
            '{amount}' => $data['order_total'] ?? $data['amount'] ?? '',
            '{tracking_number}' => $data['tracking_number'] ?? '',
            '{url}' => $data['action_url'] ?? config('app.frontend_url'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Get WhatsApp template name for event type
     */
    protected function getWhatsAppTemplate($eventType)
    {
        return config("notifications.whatsapp_template_mappings.{$eventType}");
    }

    /**
     * Create in-app notification
     */
    public function createInAppNotification(User $user, string $type, array $data)
    {
        try {
            $notification = $user->notifications()->create([
                'type' => $type,
                'title' => $data['title'] ?? 'Notification',
                'message' => $data['message'] ?? '',
                'data' => $data['notification_data'] ?? [],
                'is_read' => false,
                'action_url' => $data['action_url'] ?? null,
            ]);
            
            return ['success' => true, 'notification' => $notification];
        } catch (\Exception $e) {
            Log::error('In-app notification creation failed', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Provider-specific implementations removed - now using direct API services

    protected function sendViaFCM($token, $title, $body, $data)
    {
        $response = Http::withHeaders([
            'Authorization' => 'key=' . $this->pushConfig['fcm_server_key'],
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => $data['icon'] ?? '/logo.png',
                'click_action' => $data['click_action'] ?? null,
            ],
            'data' => $data,
        ]);
        
        return ['success' => $response->successful(), 'response' => $response->json()];
    }

    protected function sendViaOneSignal($playerId, $title, $body, $data)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $this->pushConfig['onesignal_rest_api_key'],
            'Content-Type' => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', [
            'app_id' => $this->pushConfig['onesignal_app_id'],
            'include_player_ids' => [$playerId],
            'headings' => ['en' => $title],
            'contents' => ['en' => $body],
            'data' => $data,
        ]);
        
        return ['success' => $response->successful(), 'response' => $response->json()];
    }

}