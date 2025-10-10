<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PromotionalBanner;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class PromotionalBannerController extends Controller
{
    /**
     * Get all promotional banners
     */
    public function index()
    {
        $banners = PromotionalBanner::ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $banners,
        ]);
    }

    /**
     * Get active promotional banners (public endpoint)
     */
    public function getActive()
    {
        $banners = Cache::remember('active_promotional_banners', 3600, function () {
            return PromotionalBanner::active()->ordered()->get();
        });

        return response()->json([
            'success' => true,
            'data' => $banners,
        ]);
    }

    /**
     * Get a single banner
     */
    public function show($id)
    {
        $banner = PromotionalBanner::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $banner,
        ]);
    }

    /**
     * Create a new banner
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'required|string|max:255',
            'icon_color' => 'nullable|string|max:7',
            'background_color' => 'nullable|string|max:7',
            'link_url' => 'nullable|string|max:255',
            'link_text' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $banner = PromotionalBanner::create($request->all());

        // Clear cache
        Cache::forget('active_promotional_banners');

        return response()->json([
            'success' => true,
            'message' => 'Promotional banner created successfully',
            'data' => $banner,
        ], 201);
    }

    /**
     * Update a banner
     */
    public function update(Request $request, $id)
    {
        $banner = PromotionalBanner::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'sometimes|required|string|max:255',
            'icon_color' => 'nullable|string|max:7',
            'background_color' => 'nullable|string|max:7',
            'link_url' => 'nullable|string|max:255',
            'link_text' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $banner->update($request->all());

        // Clear cache
        Cache::forget('active_promotional_banners');

        return response()->json([
            'success' => true,
            'message' => 'Promotional banner updated successfully',
            'data' => $banner,
        ]);
    }

    /**
     * Delete a banner
     */
    public function destroy($id)
    {
        $banner = PromotionalBanner::findOrFail($id);
        $banner->delete();

        // Clear cache
        Cache::forget('active_promotional_banners');

        return response()->json([
            'success' => true,
            'message' => 'Promotional banner deleted successfully',
        ]);
    }

    /**
     * Toggle banner active status
     */
    public function toggle($id)
    {
        $banner = PromotionalBanner::findOrFail($id);
        $banner->is_active = !$banner->is_active;
        $banner->save();

        // Clear cache
        Cache::forget('active_promotional_banners');

        return response()->json([
            'success' => true,
            'message' => 'Banner status updated successfully',
            'data' => $banner,
        ]);
    }

    /**
     * Update banner order
     */
    public function updateOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'banners' => 'required|array',
            'banners.*.id' => 'required|exists:promotional_banners,id',
            'banners.*.order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->banners as $bannerData) {
            PromotionalBanner::where('id', $bannerData['id'])
                ->update(['order' => $bannerData['order']]);
        }

        // Clear cache
        Cache::forget('active_promotional_banners');

        return response()->json([
            'success' => true,
            'message' => 'Banner order updated successfully',
        ]);
    }
}

