<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    protected $smsConfig;
    protected $pushConfig;
    protected $emailConfig;

    public function __construct()
    {
        $this->smsConfig = config('services.sms');
        $this->pushConfig = config('services.push');
        $this->emailConfig = config('mail');
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
        // Remove country code if present
        $to = preg_replace('/^\+91/', '', $to);
        
        try {
            // Example implementation for MSG91
            if ($this->smsConfig['provider'] === 'msg91') {
                return $this->sendViaMsg91($to, $message, $options);
            }
            
            // Example implementation for Twilio
            if ($this->smsConfig['provider'] === 'twilio') {
                return $this->sendViaTwilio($to, $message, $options);
            }
            
            // Example implementation for TextLocal
            if ($this->smsConfig['provider'] === 'textlocal') {
                return $this->sendViaTextLocal($to, $message, $options);
            }
            
            Log::warning('No SMS provider configured');
            return ['success' => false, 'error' => 'No SMS provider configured'];
            
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
            // WhatsApp Business API implementation
            if ($this->smsConfig['whatsapp_provider'] === 'twilio') {
                return $this->sendWhatsAppViaTwilio($to, $message, $options);
            }
            
            // Other WhatsApp providers
            if ($this->smsConfig['whatsapp_provider'] === 'wati') {
                return $this->sendWhatsAppViaWati($to, $message, $options);
            }
            
            Log::warning('No WhatsApp provider configured');
            return ['success' => false, 'error' => 'No WhatsApp provider configured'];
            
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
        
        // Email notification
        if ($user->email_notifications_enabled ?? true) {
            $results['email'] = $this->sendEmail(
                $user->email,
                $data['subject'] ?? 'Notification from BookBharat',
                $data['email_template'] ?? 'emails.default',
                $data
            );
        }
        
        // SMS notification
        if (($user->sms_notifications_enabled ?? false) && $user->phone) {
            $results['sms'] = $this->sendSMS(
                $user->phone,
                $data['sms_message'] ?? $data['message'] ?? ''
            );
        }
        
        // Push notification
        if ($user->push_token) {
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

    // Provider-specific implementations

    protected function sendViaMsg91($to, $message, $options)
    {
        $response = Http::post('https://api.msg91.com/api/v5/flow/', [
            'authkey' => $this->smsConfig['msg91_auth_key'],
            'mobiles' => '91' . $to,
            'country' => '91',
            'message' => $message,
            'route' => $options['route'] ?? '4',
            'sender' => $this->smsConfig['sender_id'] ?? 'BKBHRT',
        ]);
        
        return ['success' => $response->successful(), 'response' => $response->json()];
    }

    protected function sendViaTwilio($to, $message, $options)
    {
        $client = new \Twilio\Rest\Client(
            $this->smsConfig['twilio_sid'],
            $this->smsConfig['twilio_token']
        );
        
        $result = $client->messages->create(
            '+91' . $to,
            [
                'from' => $this->smsConfig['twilio_from'],
                'body' => $message
            ]
        );
        
        return ['success' => true, 'sid' => $result->sid];
    }

    protected function sendViaTextLocal($to, $message, $options)
    {
        $response = Http::post('https://api.textlocal.in/send/', [
            'apikey' => $this->smsConfig['textlocal_api_key'],
            'numbers' => $to,
            'message' => $message,
            'sender' => $this->smsConfig['sender_id'] ?? 'BKBHRT',
        ]);
        
        return ['success' => $response->successful(), 'response' => $response->json()];
    }

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

    protected function sendWhatsAppViaTwilio($to, $message, $options)
    {
        $client = new \Twilio\Rest\Client(
            $this->smsConfig['twilio_sid'],
            $this->smsConfig['twilio_token']
        );
        
        $result = $client->messages->create(
            'whatsapp:+91' . $to,
            [
                'from' => 'whatsapp:' . $this->smsConfig['twilio_whatsapp_from'],
                'body' => $message
            ]
        );
        
        return ['success' => true, 'sid' => $result->sid];
    }

    protected function sendWhatsAppViaWati($to, $message, $options)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->smsConfig['wati_api_key'],
            'Content-Type' => 'application/json',
        ])->post($this->smsConfig['wati_base_url'] . '/api/v1/sendTemplateMessage', [
            'whatsappNumber' => '91' . $to,
            'template_name' => $options['template'] ?? 'default',
            'broadcast_name' => $options['broadcast'] ?? 'default',
            'parameters' => $options['parameters'] ?? [$message],
        ]);
        
        return ['success' => $response->successful(), 'response' => $response->json()];
    }
}