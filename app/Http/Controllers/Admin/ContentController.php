<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Models\SiteConfiguration;

class ContentController extends Controller
{
    /**
     * Update site configuration
     */
    public function updateSiteConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site.name' => 'required|string|max:255',
            'site.description' => 'required|string|max:500',
            'site.contact_email' => 'required|email',
            'site.contact_phone' => 'required|string',
            'theme.primary_color' => 'required|string',
            'theme.secondary_color' => 'required|string',
            'theme.accent_color' => 'required|string',
            'features' => 'required|array',
            'payment' => 'required|array',
            'shipping' => 'required|array',
            'social' => 'required|array',
            'seo' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update configuration
        $configData = $request->all();

        // Store in database or config file
        // For now, we'll use cache and assume config is stored elsewhere
        Cache::forget('site_config');
        Cache::put('site_config', $configData, 3600);

        return response()->json([
            'success' => true,
            'message' => 'Site configuration updated successfully',
            'data' => $configData
        ]);
    }

    /**
     * Update homepage configuration
     */
    public function updateHomepageConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hero_section' => 'required|array',
            'hero_section.title' => 'required|string|max:255',
            'hero_section.subtitle' => 'required|string|max:500',
            'featured_sections' => 'required|array',
            'promotional_banners' => 'required|array',
            'newsletter' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $configData = $request->all();

        Cache::forget('homepage_config');
        Cache::put('homepage_config', $configData, 1800);

        return response()->json([
            'success' => true,
            'message' => 'Homepage configuration updated successfully',
            'data' => $configData
        ]);
    }

    /**
     * Update navigation configuration
     */
    public function updateNavigationConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'header_menu' => 'required|array',
            'footer_menu' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $configData = $request->all();

        Cache::forget('navigation_config');
        Cache::put('navigation_config', $configData, 3600);

        return response()->json([
            'success' => true,
            'message' => 'Navigation configuration updated successfully',
            'data' => $configData
        ]);
    }

    /**
     * Update content page
     */
    public function updateContentPage(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'meta_title' => 'required|string|max:255',
            'meta_description' => 'required|string|max:160'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $pageData = $request->all();

        // In a real implementation, this would be stored in database
        Cache::forget("content_page_{$slug}");
        Cache::put("content_page_{$slug}", $pageData, 3600);

        return response()->json([
            'success' => true,
            'message' => 'Content page updated successfully',
            'data' => $pageData
        ]);
    }

    /**
     * Upload media files
     */
    public function uploadMedia(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'type' => 'required|string|in:image,video,document'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $type = $request->input('type');

        // Store file
        $path = $file->store("media/{$type}", 'public');
        $url = asset("storage/{$path}");

        return response()->json([
            'success' => true,
            'message' => 'Media uploaded successfully',
            'data' => [
                'url' => $url,
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType()
            ]
        ]);
    }

    /**
     * Get media library
     */
    public function getMediaLibrary(Request $request)
    {
        $type = $request->input('type', 'all');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        // In a real implementation, this would query the database
        // For now, return mock data
        $media = [
            [
                'id' => 1,
                'url' => asset('storage/media/image/hero-bg.jpg'),
                'name' => 'hero-bg.jpg',
                'type' => 'image/jpeg',
                'size' => 245760,
                'created_at' => now()->subDays(5)->toISOString()
            ],
            [
                'id' => 2,
                'url' => asset('storage/media/image/logo.png'),
                'name' => 'logo.png',
                'type' => 'image/png',
                'size' => 12840,
                'created_at' => now()->subDays(3)->toISOString()
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $media,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => count($media),
                'last_page' => 1
            ]
        ]);
    }

    /**
     * Delete media file
     */
    public function deleteMedia($id)
    {
        // In a real implementation, this would delete from storage and database
        return response()->json([
            'success' => true,
            'message' => 'Media deleted successfully'
        ]);
    }

    /**
     * Get theme presets
     */
    public function getThemePresets()
    {
        $presets = [
            'default' => [
                'name' => 'Default',
                'primary_color' => '#1e40af',
                'secondary_color' => '#f59e0b',
                'accent_color' => '#10b981',
                'success_color' => '#10b981',
                'warning_color' => '#f59e0b',
                'error_color' => '#ef4444'
            ],
            'dark' => [
                'name' => 'Dark Mode',
                'primary_color' => '#3b82f6',
                'secondary_color' => '#fbbf24',
                'accent_color' => '#34d399',
                'success_color' => '#34d399',
                'warning_color' => '#fbbf24',
                'error_color' => '#f87171'
            ],
            'elegant' => [
                'name' => 'Elegant',
                'primary_color' => '#6366f1',
                'secondary_color' => '#f43f5e',
                'accent_color' => '#06b6d4',
                'success_color' => '#10b981',
                'warning_color' => '#f59e0b',
                'error_color' => '#ef4444'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $presets
        ]);
    }
}
