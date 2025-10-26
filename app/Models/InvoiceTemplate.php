<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'header_html',
        'footer_html',
        'styles_css',
        'thank_you_message',
        'legal_disclaimer',
        'logo_url',
        'show_company_address',
        'show_gst_number',
        'is_active',
        'is_default',
        'custom_fields',
    ];

    protected $casts = [
        'show_company_address' => 'boolean',
        'show_gst_number' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'custom_fields' => 'array',
    ];

    /**
     * Get the default template
     */
    public static function getDefault()
    {
        return self::where('is_default', true)->first() ?? self::first();
    }
}
