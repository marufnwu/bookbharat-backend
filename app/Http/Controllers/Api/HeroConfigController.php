<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HeroConfigController extends Controller
{
    /**
     * Get hero configuration
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

}
