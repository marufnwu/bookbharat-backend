<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'content',
        'language',
        'category',
        'description',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get content block by key and language with fallback
     */
    public static function getByKey(string $key, string $language = 'en', string $fallbackLanguage = 'en'): ?string
    {
        // Try to get content in requested language
        $block = self::where('key', $key)
            ->where('language', $language)
            ->where('is_active', true)
            ->first();

        // Fallback to default language if not found
        if (!$block && $language !== $fallbackLanguage) {
            $block = self::where('key', $key)
                ->where('language', $fallbackLanguage)
                ->where('is_active', true)
                ->first();
        }

        return $block?->content;
    }

    /**
     * Get all blocks by category
     */
    public static function getByCategory(string $category, string $language = 'en')
    {
        return self::where('category', $category)
            ->where('language', $language)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }
}
