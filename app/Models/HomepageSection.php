<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomepageSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'section_type',
        'title',
        'subtitle',
        'enabled',
        'order',
        'settings',
        'styles',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'order' => 'integer',
        'settings' => 'array',
        'styles' => 'array',
    ];

    /**
     * Get enabled sections ordered by order column
     */
    public static function getEnabledSections()
    {
        return self::where('enabled', true)
            ->orderBy('order')
            ->get();
    }

    /**
     * Get all sections with their current order
     */
    public static function getAllOrdered()
    {
        return self::orderBy('order')->get();
    }

    /**
     * Update section order
     */
    public static function updateOrder(array $sectionOrders)
    {
        foreach ($sectionOrders as $order => $sectionId) {
            self::where('section_id', $sectionId)
                ->update(['order' => $order]);
        }
    }
}

