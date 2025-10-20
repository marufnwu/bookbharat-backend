<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class NotificationSetting extends Model
{
    protected $fillable = [
        'event_type',
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
    ];

    protected $casts = [
        'channels' => 'array',
        'whatsapp_templates' => 'array',
        'enabled' => 'boolean',
    ];

    /**
     * Get decrypted SMS API key
     */
    public function getDecryptedSmsApiKeyAttribute()
    {
        if (!$this->sms_api_key) {
            return null;
        }

        try {
            return Crypt::decryptString($this->sms_api_key);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted SMS API key
     */
    public function setSmsApiKeyAttribute($value)
    {
        if ($value) {
            $this->attributes['sms_api_key'] = Crypt::encryptString($value);
        } else {
            $this->attributes['sms_api_key'] = null;
        }
    }

    /**
     * Get decrypted WhatsApp access token
     */
    public function getDecryptedWhatsappAccessTokenAttribute()
    {
        if (!$this->whatsapp_access_token) {
            return null;
        }

        try {
            return Crypt::decryptString($this->whatsapp_access_token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted WhatsApp access token
     */
    public function setWhatsappAccessTokenAttribute($value)
    {
        if ($value) {
            $this->attributes['whatsapp_access_token'] = Crypt::encryptString($value);
        } else {
            $this->attributes['whatsapp_access_token'] = null;
        }
    }

    /**
     * Check if a specific channel is enabled for this event
     */
    public function hasChannel($channel)
    {
        return $this->enabled && in_array($channel, $this->channels ?? []);
    }

    /**
     * Get event-specific setting or default
     */
    public static function getForEvent($eventType)
    {
        return self::where('event_type', $eventType)->first()
            ?? self::getDefault($eventType);
    }

    /**
     * Get default settings for event type
     */
    private static function getDefault($eventType)
    {
        return new self([
            'event_type' => $eventType,
            'channels' => ['email'], // Default to email only
            'enabled' => true,
        ]);
    }

    /**
     * Get all event types
     */
    public static function getEventTypes()
    {
        return [
            'order_placed' => 'Order Placed',
            'order_confirmed' => 'Order Confirmed',
            'order_shipped' => 'Order Shipped',
            'order_delivered' => 'Order Delivered',
            'order_cancelled' => 'Order Cancelled',
            'payment_success' => 'Payment Success',
            'payment_failed' => 'Payment Failed',
            'return_requested' => 'Return Requested',
            'return_approved' => 'Return Approved',
            'return_completed' => 'Return Completed',
            'abandoned_cart' => 'Abandoned Cart',
            'review_request' => 'Review Request',
            'password_reset' => 'Password Reset',
            'welcome_email' => 'Welcome Email',
        ];
    }
}

