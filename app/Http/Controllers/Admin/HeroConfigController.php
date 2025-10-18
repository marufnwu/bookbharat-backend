<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class HeroConfigController extends Controller
{
    /**
     * Get all hero configurations
     */
    public function index()
    {
        try {
            $heroConfigs = HeroConfiguration::all();

            return response()->json([
                'success' => true,
                'data' => $heroConfigs
            ], 200);

        } catch (\Exception $e) {
            Log::error('Hero config retrieval error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve hero configurations.',
            ], 500);
        }
    }

    /**
     * Get specific hero configuration
     */
    public function show($variant)
    {
        try {
            $config = HeroConfiguration::where('variant', $variant)->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hero configuration not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $config
            ], 200);

        } catch (\Exception $e) {
            Log::error('Hero config retrieval error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve hero configuration.',
            ], 500);
        }
    }

    /**
     * Create new hero configuration
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'variant' => 'required|string|unique:hero_configurations,variant|max:100',
                'title' => 'required|string|max:255',
                'subtitle' => 'nullable|string',
                'primaryCta' => 'nullable|array',
                'primaryCta.text' => 'required_with:primaryCta|string|max:100',
                'primaryCta.href' => 'required_with:primaryCta|string|max:255',
                'secondaryCta' => 'nullable|array',
                'secondaryCta.text' => 'required_with:secondaryCta|string|max:100',
                'secondaryCta.href' => 'required_with:secondaryCta|string|max:255',
                'stats' => 'nullable|array',
                'backgroundImage' => ['nullable', 'string', 'max:500', function ($attribute, $value, $fail) {
                    if ($value && !filter_var($value, FILTER_VALIDATE_URL) && !str_starts_with($value, '/')) {
                        $fail('The ' . $attribute . ' must be a valid URL or start with /');
                    }
                }],
                'categories' => 'nullable|array',
                'testimonials' => 'nullable|array',
                'features' => 'nullable|array',
                'campaignData' => 'nullable|array',
                'discountBadge' => 'nullable|array',
                'trustBadges' => 'nullable|array',
                'featuredProducts' => 'nullable|array',
                'videoUrl' => ['nullable', 'string', 'max:500', function ($attribute, $value, $fail) {
                    if ($value && !filter_var($value, FILTER_VALIDATE_URL) && !str_starts_with($value, '/')) {
                        $fail('The ' . $attribute . ' must be a valid URL or start with /');
                    }
                }],
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $heroConfig = HeroConfiguration::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Hero configuration created successfully.',
                'data' => $heroConfig
            ], 201);

        } catch (\Exception $e) {
            Log::error('Hero config creation error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to create hero configuration.',
            ], 500);
        }
    }

    /**
     * Update hero configuration
     */
    public function update(Request $request, $variant)
    {
        try {
            $config = HeroConfiguration::where('variant', $variant)->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hero configuration not found.',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:255',
                'subtitle' => 'nullable|string',
                'primaryCta' => 'nullable|array',
                'secondaryCta' => 'nullable|array',
                'stats' => 'nullable|array',
                'backgroundImage' => ['nullable', 'string', 'max:500', function ($attribute, $value, $fail) {
                    if ($value && !filter_var($value, FILTER_VALIDATE_URL) && !str_starts_with($value, '/')) {
                        $fail('The ' . $attribute . ' must be a valid URL or start with /');
                    }
                }],
                'categories' => 'nullable|array',
                'testimonials' => 'nullable|array',
                'features' => 'nullable|array',
                'campaignData' => 'nullable|array',
                'discountBadge' => 'nullable|array',
                'trustBadges' => 'nullable|array',
                'featuredProducts' => 'nullable|array',
                'videoUrl' => ['nullable', 'string', 'max:500', function ($attribute, $value, $fail) {
                    if ($value && !filter_var($value, FILTER_VALIDATE_URL) && !str_starts_with($value, '/')) {
                        $fail('The ' . $attribute . ' must be a valid URL or start with /');
                    }
                }],
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $config->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Hero configuration updated successfully.',
                'data' => $config->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Hero config update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to update hero configuration.',
            ], 500);
        }
    }

    /**
     * Delete hero configuration
     */
    public function destroy($variant)
    {
        try {
            $config = HeroConfiguration::where('variant', $variant)->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hero configuration not found.',
                ], 404);
            }

            // Check if it's the active variant
            if ($config->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the active hero configuration. Please set another variant as active first.',
                ], 422);
            }

            $config->delete();

            return response()->json([
                'success' => true,
                'message' => 'Hero configuration deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Hero config deletion error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete hero configuration.',
            ], 500);
        }
    }

    /**
     * Get active hero configuration
     */
    public function getActive()
    {
        try {
            $activeConfig = HeroConfiguration::where('is_active', true)->first();

            if (!$activeConfig) {
                // Fallback to first config
                $activeConfig = HeroConfiguration::first();
            }

            return response()->json([
                'success' => true,
                'data' => $activeConfig
            ], 200);

        } catch (\Exception $e) {
            Log::error('Active hero config retrieval error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve active hero configuration.',
            ], 500);
        }
    }

    /**
     * Set active hero configuration
     */
    public function setActive(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'variant' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $config = HeroConfiguration::where('variant', $request->variant)->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hero configuration not found.',
                ], 404);
            }

            // Deactivate all other configurations
            HeroConfiguration::where('is_active', true)->update(['is_active' => false]);

            // Activate the requested configuration
            $config->update(['is_active' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Active hero variant updated successfully.',
                'data' => $config->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Set active hero error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to set active hero variant.',
            ], 500);
        }
    }

}
