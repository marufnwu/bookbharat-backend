<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromotionalCampaign;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PromotionalCampaignController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage-campaigns');
    }

    public function index(Request $request)
    {
        $query = PromotionalCampaign::with(['creator:id,name', 'coupons'])
            ->withCount('coupons');

        // Filters
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Date filters
        if ($request->date_from) {
            $query->where('starts_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('ends_at', '<=', $request->date_to);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $campaigns = $query->paginate($request->input('per_page', 20));

        // Add calculated fields
        $campaigns->getCollection()->transform(function ($campaign) {
            return $campaign->append([
                'is_active',
                'is_expired',
                'progress_percentage',
                'participation_rate',
                'budget_utilization',
                'estimated_roi',
                'days_remaining'
            ]);
        });

        return response()->json([
            'success' => true,
            'campaigns' => $campaigns,
            'filters' => $this->getCampaignFilters(),
            'stats' => $this->getCampaignStats()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:promotional_campaigns,slug',
            'description' => 'nullable|string|max:2000',
            'type' => 'required|in:flash_sale,seasonal_offer,clearance,bundle_deal,loyalty_bonus,referral_bonus',
            'starts_at' => 'required|date|after_or_equal:now',
            'ends_at' => 'required|date|after:starts_at',
            'campaign_rules' => 'required|array',
            'target_audience' => 'nullable|array',
            'banner_config' => 'nullable|array',
            'email_config' => 'nullable|array',
            'notification_config' => 'nullable|array',
            'budget_limit' => 'nullable|numeric|min:0',
            'target_participants' => 'nullable|integer|min:1',
            'target_revenue' => 'nullable|numeric|min:0',
            'priority' => 'nullable|integer|min:0|max:100',
            'auto_apply' => 'nullable|boolean',
            'analytics_config' => 'nullable|array',
        ]);

        // Generate slug if not provided
        if (!$validated['slug']) {
            $validated['slug'] = Str::slug($validated['name']);
            
            // Ensure uniqueness
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (PromotionalCampaign::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        $validated['status'] = 'draft';
        $validated['created_by'] = Auth::id();

        $campaign = PromotionalCampaign::create($validated);

        // Auto-generate coupons if specified in rules
        if (!empty($validated['campaign_rules']['auto_generate_coupons'])) {
            $this->generateCampaignCoupons($campaign, $validated['campaign_rules']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaign created successfully',
            'campaign' => $campaign->load('creator:id,name')
        ]);
    }

    public function show(PromotionalCampaign $campaign)
    {
        $campaign->load([
            'creator:id,name',
            'coupons.usages',
            'coupons' => function ($query) {
                $query->withCount('usages');
            }
        ]);

        $analytics = [
            'performance_metrics' => $campaign->getPerformanceMetrics(),
            'timeline_data' => $this->getCampaignTimelineData($campaign),
            'audience_analysis' => $this->getCampaignAudienceAnalysis($campaign),
            'revenue_impact' => $this->getCampaignRevenueImpact($campaign),
            'engagement_metrics' => $this->getCampaignEngagementMetrics($campaign),
        ];

        return response()->json([
            'success' => true,
            'campaign' => $campaign->append([
                'is_active',
                'is_expired',
                'progress_percentage',
                'participation_rate',
                'budget_utilization',
                'estimated_roi',
                'days_remaining'
            ]),
            'analytics' => $analytics
        ]);
    }

    public function update(Request $request, PromotionalCampaign $campaign)
    {
        // Restrict editing of running campaigns
        if ($campaign->status === 'active' && $campaign->is_active) {
            $restrictedFields = ['starts_at', 'campaign_rules', 'type'];
            $allowedFields = array_diff(array_keys($request->all()), $restrictedFields);
            $request = $request->only($allowedFields);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'ends_at' => 'sometimes|required|date|after:starts_at',
            'target_audience' => 'nullable|array',
            'banner_config' => 'nullable|array',
            'email_config' => 'nullable|array',
            'notification_config' => 'nullable|array',
            'budget_limit' => 'nullable|numeric|min:' . $campaign->current_spend,
            'target_participants' => 'nullable|integer|min:' . $campaign->actual_participants,
            'target_revenue' => 'nullable|numeric|min:0',
            'priority' => 'nullable|integer|min:0|max:100',
            'auto_apply' => 'nullable|boolean',
            'analytics_config' => 'nullable|array',
        ]);

        // Only allow certain status transitions
        if ($request->has('status')) {
            $allowedTransitions = $this->getAllowedStatusTransitions($campaign->status);
            if (in_array($request->status, $allowedTransitions)) {
                $validated['status'] = $request->status;
            }
        }

        $campaign->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Campaign updated successfully',
            'campaign' => $campaign->fresh()->load('creator:id,name')
        ]);
    }

    public function destroy(PromotionalCampaign $campaign)
    {
        if ($campaign->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete active campaign'
            ], 400);
        }

        if ($campaign->coupons()->whereHas('usages')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete campaign with used coupons'
            ], 400);
        }

        // Delete associated coupons
        $campaign->coupons()->delete();
        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully'
        ]);
    }

    public function activate(PromotionalCampaign $campaign)
    {
        if ($campaign->status !== 'scheduled' && $campaign->status !== 'paused') {
            return response()->json([
                'success' => false,
                'message' => 'Campaign cannot be activated from current status'
            ], 400);
        }

        if ($campaign->starts_at->isFuture()) {
            $campaign->update(['status' => 'scheduled']);
            $message = 'Campaign scheduled for activation';
        } else {
            $campaign->update(['status' => 'active']);
            $message = 'Campaign activated successfully';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'campaign' => $campaign
        ]);
    }

    public function pause(PromotionalCampaign $campaign)
    {
        if ($campaign->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Only active campaigns can be paused'
            ], 400);
        }

        $campaign->update(['status' => 'paused']);

        return response()->json([
            'success' => true,
            'message' => 'Campaign paused successfully',
            'campaign' => $campaign
        ]);
    }

    public function end(PromotionalCampaign $campaign)
    {
        if (!in_array($campaign->status, ['active', 'paused', 'scheduled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign cannot be ended from current status'
            ], 400);
        }

        $campaign->update([
            'status' => 'ended',
            'ends_at' => now()
        ]);

        // Deactivate associated coupons
        $campaign->coupons()->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Campaign ended successfully',
            'campaign' => $campaign
        ]);
    }

    public function duplicate(PromotionalCampaign $campaign)
    {
        $newCampaign = $campaign->replicate();
        $newCampaign->name = $campaign->name . ' (Copy)';
        $newCampaign->slug = $campaign->slug . '-copy-' . now()->format('Ymd-His');
        $newCampaign->status = 'draft';
        $newCampaign->starts_at = now()->addDay();
        $newCampaign->ends_at = now()->addWeek();
        $newCampaign->actual_participants = 0;
        $newCampaign->actual_revenue = 0;
        $newCampaign->current_spend = 0;
        $newCampaign->created_by = Auth::id();
        $newCampaign->save();

        return response()->json([
            'success' => true,
            'message' => 'Campaign duplicated successfully',
            'campaign' => $newCampaign
        ]);
    }

    public function generateCoupons(PromotionalCampaign $campaign, Request $request)
    {
        $validated = $request->validate([
            'count' => 'required|integer|min:1|max:1000',
            'coupon_config' => 'required|array',
        ]);

        $coupons = [];
        for ($i = 0; $i < $validated['count']; $i++) {
            $coupon = $campaign->generateCouponForCampaign($validated['coupon_config']);
            $coupons[] = $coupon;
        }

        return response()->json([
            'success' => true,
            'message' => "Generated {$validated['count']} coupons for campaign",
            'coupons' => $coupons
        ]);
    }

    public function getEligibleUsers(PromotionalCampaign $campaign, Request $request)
    {
        $query = User::query();

        // Apply campaign target audience filters
        if (!empty($campaign->target_audience)) {
            $query = $this->applyAudienceFilters($query, $campaign->target_audience);
        }

        $users = $query->select('id', 'name', 'email', 'created_at')
                      ->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'eligible_users' => $users,
            'total_eligible' => $users->total()
        ]);
    }

    public function getPerformanceReport(PromotionalCampaign $campaign, Request $request)
    {
        $period = $request->input('period', 'campaign'); // 'campaign', '7d', '30d'

        if ($period === 'campaign') {
            $startDate = $campaign->starts_at;
            $endDate = $campaign->ends_at ?? now();
        } else {
            $days = (int) filter_var($period, FILTER_SANITIZE_NUMBER_INT);
            $startDate = now()->subDays($days);
            $endDate = now();
        }

        $report = [
            'summary' => $campaign->getPerformanceMetrics(),
            'daily_metrics' => $this->getDailyMetrics($campaign, $startDate, $endDate),
            'top_performing_coupons' => $this->getTopPerformingCoupons($campaign),
            'customer_segments' => $this->getCustomerSegmentPerformance($campaign),
            'revenue_breakdown' => $this->getRevenueBreakdown($campaign, $startDate, $endDate),
        ];

        return response()->json([
            'success' => true,
            'performance_report' => $report,
            'period' => $period
        ]);
    }

    protected function getCampaignFilters(): array
    {
        return [
            'types' => [
                ['value' => 'flash_sale', 'label' => 'Flash Sale'],
                ['value' => 'seasonal_offer', 'label' => 'Seasonal Offer'],
                ['value' => 'clearance', 'label' => 'Clearance'],
                ['value' => 'bundle_deal', 'label' => 'Bundle Deal'],
                ['value' => 'loyalty_bonus', 'label' => 'Loyalty Bonus'],
                ['value' => 'referral_bonus', 'label' => 'Referral Bonus'],
            ],
            'statuses' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'paused', 'label' => 'Paused'],
                ['value' => 'ended', 'label' => 'Ended'],
            ],
        ];
    }

    protected function getCampaignStats(): array
    {
        return [
            'total_campaigns' => PromotionalCampaign::count(),
            'active_campaigns' => PromotionalCampaign::active()->count(),
            'scheduled_campaigns' => PromotionalCampaign::where('status', 'scheduled')->count(),
            'total_revenue_generated' => PromotionalCampaign::sum('actual_revenue'),
            'average_campaign_duration' => PromotionalCampaign::selectRaw('AVG(DATEDIFF(ends_at, starts_at)) as avg_duration')->first()->avg_duration ?? 0,
        ];
    }

    protected function getAllowedStatusTransitions(string $currentStatus): array
    {
        return match($currentStatus) {
            'draft' => ['scheduled', 'active'],
            'scheduled' => ['active', 'paused', 'ended'],
            'active' => ['paused', 'ended'],
            'paused' => ['active', 'ended'],
            'ended' => [],
            default => []
        };
    }

    protected function generateCampaignCoupons(PromotionalCampaign $campaign, array $rules): void
    {
        $count = $rules['coupon_count'] ?? 1;
        
        for ($i = 0; $i < $count; $i++) {
            $campaign->generateCouponForCampaign([
                'usage_limit' => $rules['usage_limit'] ?? null,
                'usage_limit_per_customer' => $rules['usage_limit_per_customer'] ?? 1,
            ]);
        }
    }

    protected function getCampaignTimelineData(PromotionalCampaign $campaign): array
    {
        return [
            'creation_date' => $campaign->created_at,
            'scheduled_start' => $campaign->starts_at,
            'actual_start' => $campaign->starts_at, // This could be different if manually activated
            'scheduled_end' => $campaign->ends_at,
            'milestones' => $this->getCampaignMilestones($campaign),
        ];
    }

    protected function getCampaignAudienceAnalysis(PromotionalCampaign $campaign): array
    {
        // Analyze the audience that participated in the campaign
        return [
            'total_reached' => $campaign->actual_participants,
            'conversion_rate' => $campaign->calculateConversionRate(),
            'demographic_breakdown' => [], // Would require more detailed user data
        ];
    }

    protected function getCampaignRevenueImpact(PromotionalCampaign $campaign): array
    {
        return [
            'direct_revenue' => $campaign->actual_revenue,
            'discount_given' => $campaign->current_spend,
            'net_revenue' => $campaign->actual_revenue - $campaign->current_spend,
            'roi_percentage' => $campaign->estimated_roi,
        ];
    }

    protected function getCampaignEngagementMetrics(PromotionalCampaign $campaign): array
    {
        return array_merge([
            'total_participants' => $campaign->actual_participants,
            'participation_rate' => $campaign->participation_rate,
        ], $campaign->getEngagementMetrics());
    }

    protected function getCampaignMilestones(PromotionalCampaign $campaign): array
    {
        // This would track key events in the campaign lifecycle
        return [
            ['event' => 'Created', 'date' => $campaign->created_at, 'status' => 'completed'],
            ['event' => 'Scheduled', 'date' => $campaign->starts_at, 'status' => $campaign->starts_at->isPast() ? 'completed' : 'pending'],
            ['event' => 'Ends', 'date' => $campaign->ends_at, 'status' => $campaign->ends_at->isPast() ? 'completed' : 'pending'],
        ];
    }

    protected function applyAudienceFilters($query, array $audience)
    {
        // Apply various audience filters based on the target_audience configuration
        if (!empty($audience['customer_groups'])) {
            $query->whereHas('customerGroups', function ($q) use ($audience) {
                $q->whereIn('customer_groups.id', $audience['customer_groups']);
            });
        }

        if (!empty($audience['order_criteria'])) {
            $criteria = $audience['order_criteria'];
            
            if (isset($criteria['min_orders'])) {
                $query->whereHas('orders', function ($q) use ($criteria) {
                    $q->havingRaw('COUNT(*) >= ?', [$criteria['min_orders']]);
                });
            }
        }

        return $query;
    }

    protected function getDailyMetrics(PromotionalCampaign $campaign, $startDate, $endDate): array
    {
        // Return daily performance metrics for the campaign
        return $campaign->coupons()
            ->join('coupon_usages', 'coupons.id', '=', 'coupon_usages.coupon_id')
            ->selectRaw('DATE(coupon_usages.created_at) as date, COUNT(*) as usage_count, SUM(discount_amount) as total_discount')
            ->whereBetween('coupon_usages.created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    protected function getTopPerformingCoupons(PromotionalCampaign $campaign): array
    {
        return $campaign->coupons()
            ->withCount('usages')
            ->withSum('usages', 'discount_amount')
            ->orderBy('usages_count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    protected function getCustomerSegmentPerformance(PromotionalCampaign $campaign): array
    {
        // Analyze performance across different customer segments
        return [];
    }

    protected function getRevenueBreakdown(PromotionalCampaign $campaign, $startDate, $endDate): array
    {
        return [
            'gross_revenue' => $campaign->actual_revenue + $campaign->current_spend,
            'discounts_given' => $campaign->current_spend,
            'net_revenue' => $campaign->actual_revenue,
            'average_order_value' => $campaign->actual_participants > 0 ? $campaign->actual_revenue / $campaign->actual_participants : 0,
        ];
    }
}