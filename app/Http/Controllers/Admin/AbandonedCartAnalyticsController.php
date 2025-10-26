<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CartRecoveryAnalyticsService;
use Illuminate\Http\Request;

class AbandonedCartAnalyticsController extends Controller
{
    protected $analyticsService;

    public function __construct(CartRecoveryAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get comprehensive analytics
     */
    public function index(Request $request)
    {
        $dateFrom = $request->query('from');
        $dateTo = $request->query('to');

        $analytics = $this->analyticsService->getAnalytics($dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Get segment comparison
     */
    public function segmentComparison(Request $request)
    {
        $dateFrom = $request->query('from');
        $dateTo = $request->query('to');

        $data = $this->analyticsService->getSegmentComparison($dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get discount effectiveness
     */
    public function discountEffectiveness(Request $request)
    {
        $dateFrom = $request->query('from');
        $dateTo = $request->query('to');

        $data = $this->analyticsService->getDiscountEffectiveness($dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
