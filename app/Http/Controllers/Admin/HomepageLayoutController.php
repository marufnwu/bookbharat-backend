<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use App\Models\HomepageLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class HomepageLayoutController extends Controller
{
    /**
     * Get all homepage sections
     */
    public function getSections()
    {
        $sections = HomepageSection::getAllOrdered();

        return response()->json([
            'success' => true,
            'data' => $sections
        ]);
    }

    /**
     * Get enabled sections (for frontend)
     */
    public function getEnabledSections()
    {
        $sections = Cache::remember('homepage_sections_enabled', 1800, function () {
            return HomepageSection::getEnabledSections();
        });

        return response()->json([
            'success' => true,
            'data' => $sections
        ]);
    }

    /**
     * Create a new section
     */
    public function createSection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'section_id' => 'required|string|unique:homepage_sections,section_id',
            'section_type' => 'required|string',
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'enabled' => 'boolean',
            'order' => 'integer',
            'settings' => 'nullable|array',
            'styles' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $section = HomepageSection::create($request->all());

        Cache::forget('homepage_sections_enabled');

        return response()->json([
            'success' => true,
            'message' => 'Section created successfully',
            'data' => $section
        ], 201);
    }

    /**
     * Update a section
     */
    public function updateSection(Request $request, $id)
    {
        $section = HomepageSection::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'enabled' => 'boolean',
            'order' => 'integer',
            'settings' => 'nullable|array',
            'styles' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $section->update($request->all());

        Cache::forget('homepage_sections_enabled');

        return response()->json([
            'success' => true,
            'message' => 'Section updated successfully',
            'data' => $section
        ]);
    }

    /**
     * Delete a section
     */
    public function deleteSection($id)
    {
        $section = HomepageSection::findOrFail($id);
        $section->delete();

        Cache::forget('homepage_sections_enabled');

        return response()->json([
            'success' => true,
            'message' => 'Section deleted successfully'
        ]);
    }

    /**
     * Update section order
     */
    public function updateSectionOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sections' => 'required|array',
            'sections.*.id' => 'required|exists:homepage_sections,id',
            'sections.*.order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->sections as $sectionData) {
            HomepageSection::where('id', $sectionData['id'])
                ->update(['order' => $sectionData['order']]);
        }

        Cache::forget('homepage_sections_enabled');

        return response()->json([
            'success' => true,
            'message' => 'Section order updated successfully'
        ]);
    }

    /**
     * Toggle section visibility
     */
    public function toggleSection(Request $request, $id)
    {
        $section = HomepageSection::findOrFail($id);

        $section->update([
            'enabled' => !$section->enabled
        ]);

        Cache::forget('homepage_sections_enabled');

        return response()->json([
            'success' => true,
            'message' => 'Section visibility updated',
            'data' => $section
        ]);
    }

    /**
     * Get all layouts
     */
    public function getLayouts()
    {
        $layouts = HomepageLayout::all();

        return response()->json([
            'success' => true,
            'data' => $layouts
        ]);
    }

    /**
     * Get active layout
     */
    public function getActiveLayout()
    {
        $layout = HomepageLayout::getActive();

        return response()->json([
            'success' => true,
            'data' => $layout
        ]);
    }

    /**
     * Create a new layout
     */
    public function createLayout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:homepage_layouts,name',
            'description' => 'nullable|string',
            'section_order' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $layout = HomepageLayout::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Layout created successfully',
            'data' => $layout
        ], 201);
    }

    /**
     * Set active layout
     */
    public function setActiveLayout($id)
    {
        $layout = HomepageLayout::findOrFail($id);
        $layout->setActive();

        Cache::forget('homepage_sections_enabled');

        return response()->json([
            'success' => true,
            'message' => 'Layout activated successfully',
            'data' => $layout
        ]);
    }

    /**
     * Delete a layout
     */
    public function deleteLayout($id)
    {
        $layout = HomepageLayout::findOrFail($id);

        if ($layout->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete active layout'
            ], 400);
        }

        $layout->delete();

        return response()->json([
            'success' => true,
            'message' => 'Layout deleted successfully'
        ]);
    }

    /**
     * Get default section templates
     */
    public function getSectionTemplates()
    {
        $templates = [
            [
                'section_type' => 'hero',
                'title' => 'Hero Section',
                'description' => 'Main hero banner with CTA buttons',
                'icon' => 'star',
                'default_settings' => [
                    'variant' => 'minimal-product',
                    'show_stats' => true,
                    'show_cta' => true,
                ]
            ],
            [
                'section_type' => 'featured-products',
                'title' => 'Featured Products',
                'description' => 'Grid of featured/bestselling products',
                'icon' => 'package',
                'default_settings' => [
                    'limit' => 8,
                    'layout' => 'grid',
                    'columns' => 4,
                    'show_rating' => true,
                    'show_discount' => true,
                ]
            ],
            [
                'section_type' => 'categories',
                'title' => 'Categories',
                'description' => 'Browse by category section',
                'icon' => 'grid',
                'default_settings' => [
                    'limit' => 8,
                    'layout' => 'grid',
                    'show_count' => true,
                    'show_icons' => true,
                ]
            ],
            [
                'section_type' => 'category-products',
                'title' => 'Category Products',
                'description' => 'Products grouped by categories',
                'icon' => 'layers',
                'default_settings' => [
                    'products_per_category' => 4,
                    'show_see_all' => true,
                    'lazy_load' => true,
                ]
            ],
            [
                'section_type' => 'promotional-banners',
                'title' => 'Promotional Banners',
                'description' => 'Feature highlights (shipping, returns, etc)',
                'icon' => 'badge',
                'default_settings' => [
                    'layout' => 'horizontal',
                    'show_icons' => true,
                ]
            ],
            [
                'section_type' => 'newsletter',
                'title' => 'Newsletter Signup',
                'description' => 'Email subscription form',
                'icon' => 'mail',
                'default_settings' => [
                    'show_privacy_text' => true,
                    'background_style' => 'gradient',
                ]
            ],
            [
                'section_type' => 'testimonials',
                'title' => 'Customer Testimonials',
                'description' => 'Customer reviews and ratings',
                'icon' => 'message-square',
                'default_settings' => [
                    'limit' => 6,
                    'layout' => 'carousel',
                    'auto_rotate' => true,
                ]
            ],
            [
                'section_type' => 'cta-banner',
                'title' => 'Call-to-Action Banner',
                'description' => 'Large promotional CTA section',
                'icon' => 'megaphone',
                'default_settings' => [
                    'background_color' => 'primary',
                    'show_button' => true,
                ]
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }
}

