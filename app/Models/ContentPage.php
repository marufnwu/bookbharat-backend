<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AdminSetting;

class ContentPage extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'content',
        'excerpt',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_image',
        'is_active',
        'order',
        'metadata'
    ];

    protected $casts = [
        'meta_keywords' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer'
    ];

    /**
     * Get content with dynamic contact information
     */
    public function getContentWithDynamicInfo()
    {
        $content = $this->content;

        // Replace placeholders with dynamic values
        $replacements = [
            '{{support_email}}' => AdminSetting::get('support_email', 'support@bookbharat.com'),
            '{{contact_phone}}' => AdminSetting::get('contact_phone', '+91 12345 67890'),
            '{{company_city}}' => AdminSetting::get('company_city', 'Mumbai'),
            '{{company_state}}' => AdminSetting::get('company_state', 'Maharashtra'),
            '{{company_country}}' => AdminSetting::get('company_country', 'India'),
            '{{free_shipping_threshold}}' => AdminSetting::get('free_shipping_threshold', '500'),
        ];

        foreach ($replacements as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }
}
