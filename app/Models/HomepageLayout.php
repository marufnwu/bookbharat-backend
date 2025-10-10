<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomepageLayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
        'layout',
        'draft_layout',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'layout' => 'array',
        'draft_layout' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Get the active layout
     */
    public static function getActive()
    {
        return self::where('is_active', true)->first();
    }

    /**
     * Set this layout as active
     */
    public function setActive()
    {
        // Deactivate all other layouts
        self::where('is_active', true)->update(['is_active' => false]);

        // Activate this layout
        $this->update(['is_active' => true]);
    }
}

