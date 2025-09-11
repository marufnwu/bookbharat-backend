<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Product;
use App\Models\Category;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CouponController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage-coupons');
    }

    public function index(Request $request)
    {
        $query = Coupon::with(['creator:id,name', 'usages'])
            ->withCount('usages');

        // Filters
        if ($request->status) {
            match($request->status) {
                'active' => $query->active(),
                'expired' => $query->where('expires_at', '<', now()),
                'inactive' => $query->where('is_active', false),
                'valid' => $query->valid(),
                default => null
            };
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('code', 'like', '%' . $request->search . '%')
                  ->orWhere('name', 'like', '%' . $request->search . '%');
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $coupons = $query->paginate($request->input('per_page', 20));

        // Add calculated fields
        $coupons->getCollection()->transform(function ($coupon) {
            return $coupon->append([
                'formatted_value',
                'is_expired',
                'is_valid',
                'remaining_uses',
                'usage_percentage',
                'total_discount_given',
                'average_discount'
            ]);
        });

        return response()->json([
            'success' => true,
            'coupons' => $coupons,
            'filters' => $this->getCouponFilters(),
            'stats' => $this->getCouponStats()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:percentage,fixed_amount,free_shipping,buy_x_get_y',
            'value' => 'required_unless:type,free_shipping|numeric|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'maximum_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'starts_at' => 'required|date|after_or_equal:today',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_active' => 'nullable|boolean',
            'is_stackable' => 'nullable|boolean',
            'applicable_products' => 'nullable|array',
            'applicable_products.*' => 'exists:products,id',
            'applicable_categories' => 'nullable|array',
            'applicable_categories.*' => 'exists:categories,id',
            'applicable_customer_groups' => 'nullable|array',
            'applicable_customer_groups.*' => 'exists:customer_groups,id',
            'excluded_products' => 'nullable|array',
            'excluded_products.*' => 'exists:products,id',
            'excluded_categories' => 'nullable|array',
            'excluded_categories.*' => 'exists:categories,id',
            'first_order_only' => 'nullable|in:yes,no',
            'buy_x_get_y_config' => 'required_if:type,buy_x_get_y|array',
            'buy_x_get_y_config.buy_quantity' => 'required_if:type,buy_x_get_y|integer|min:1',
            'buy_x_get_y_config.get_quantity' => 'required_if:type,buy_x_get_y|integer|min:1',
            'buy_x_get_y_config.product_id' => 'nullable|exists:products,id',
            'geographic_restrictions' => 'nullable|array',
            'day_time_restrictions' => 'nullable|array',
        ]);

        // Auto-generate code if not provided or make it uppercase
        if (!$validated['code']) {
            $validated['code'] = $this->generateCouponCode();
        } else {
            $validated['code'] = strtoupper($validated['code']);
        }

        $validated['created_by'] = Auth::id();

        $coupon = Coupon::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully',
            'coupon' => $coupon->load('creator:id,name')
        ]);
    }

    public function show(Coupon $coupon)
    {
        $coupon->load(['creator:id,name', 'usages.user:id,name,email', 'usages.order:id,order_number']);
        
        $analytics = [
            'usage_analytics' => $this->getCouponUsageAnalytics($coupon),
            'performance_metrics' => $this->getCouponPerformanceMetrics($coupon),
            'user_demographics' => $this->getCouponUserDemographics($coupon),
            'product_impact' => $this->getCouponProductImpact($coupon),
        ];

        return response()->json([
            'success' => true,
            'coupon' => $coupon->append([
                'formatted_value',
                'is_expired',
                'is_valid',
                'remaining_uses',
                'usage_percentage',
                'total_discount_given',
                'average_discount'
            ]),
            'analytics' => $analytics
        ]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        // Prevent editing of certain fields if coupon has been used
        $hasUsages = $coupon->usages()->exists();

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'maximum_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:' . $coupon->usage_count,
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_active' => 'nullable|boolean',
            'is_stackable' => 'nullable|boolean',
            'applicable_products' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'applicable_customer_groups' => 'nullable|array',
            'excluded_products' => 'nullable|array',
            'excluded_categories' => 'nullable|array',
            'geographic_restrictions' => 'nullable|array',
            'day_time_restrictions' => 'nullable|array',
        ];

        // Allow editing core fields only if no usages
        if (!$hasUsages) {
            $rules = array_merge($rules, [
                'code' => 'required|string|max:50|unique:coupons,code,' . $coupon->id,
                'type' => 'required|in:percentage,fixed_amount,free_shipping,buy_x_get_y',
                'value' => 'required_unless:type,free_shipping|numeric|min:0',
                'starts_at' => 'required|date',
                'first_order_only' => 'nullable|in:yes,no',
                'buy_x_get_y_config' => 'required_if:type,buy_x_get_y|array',
            ]);
        }

        $validated = $request->validate($rules);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $coupon->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully',
            'coupon' => $coupon->fresh()->load('creator:id,name')
        ]);
    }

    public function destroy(Coupon $coupon)
    {
        if ($coupon->usages()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete coupon that has been used'
            ], 400);
        }

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully'
        ]);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,delete,extend_expiry',
            'coupon_ids' => 'required|array|min:1',
            'coupon_ids.*' => 'exists:coupons,id',
            'extend_days' => 'required_if:action,extend_expiry|integer|min:1|max:365',
        ]);

        $coupons = Coupon::whereIn('id', $request->coupon_ids);
        $action = $request->action;
        $count = 0;

        switch ($action) {
            case 'activate':
                $count = $coupons->update(['is_active' => true]);
                break;

            case 'deactivate':
                $count = $coupons->update(['is_active' => false]);
                break;

            case 'delete':
                $deletableCoupons = $coupons->whereDoesntHave('usages')->get();
                $count = $deletableCoupons->count();
                Coupon::whereIn('id', $deletableCoupons->pluck('id'))->delete();
                break;

            case 'extend_expiry':
                $count = $coupons->whereNotNull('expires_at')
                              ->update([
                                  'expires_at' => DB::raw("DATE_ADD(expires_at, INTERVAL {$request->extend_days} DAY)")
                              ]);
                break;
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk {$action} completed for {$count} coupons"
        ]);
    }

    public function generateCode(Request $request)
    {
        $request->validate([
            'prefix' => 'nullable|string|max:10',
            'length' => 'nullable|integer|min:4|max:20',
            'type' => 'nullable|in:random,readable,numeric',
        ]);

        $prefix = strtoupper($request->input('prefix', ''));
        $length = $request->input('length', 8);
        $type = $request->input('type', 'random');

        $code = $this->generateCouponCode($prefix, $length, $type);

        return response()->json([
            'success' => true,
            'generated_code' => $code
        ]);
    }

    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'user_id' => 'nullable|exists:users,id',
            'order_total' => 'nullable|numeric|min:0',
            'products' => 'nullable|array',
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found'
            ], 404);
        }

        $validation = [
            'is_valid' => $coupon->is_valid,
            'messages' => [],
            'discount_preview' => null,
        ];

        if (!$coupon->is_active) {
            $validation['messages'][] = 'Coupon is inactive';
        }

        if ($coupon->starts_at->isFuture()) {
            $validation['messages'][] = 'Coupon is not yet active';
        }

        if ($coupon->is_expired) {
            $validation['messages'][] = 'Coupon has expired';
        }

        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit) {
            $validation['messages'][] = 'Coupon usage limit reached';
        }

        if ($request->user_id && $coupon->usage_limit_per_customer) {
            $userUsage = $coupon->usages()->where('user_id', $request->user_id)->count();
            if ($userUsage >= $coupon->usage_limit_per_customer) {
                $validation['messages'][] = 'Customer usage limit reached';
            }
        }

        if ($request->order_total && $request->order_total < $coupon->minimum_order_amount) {
            $validation['messages'][] = "Minimum order amount of ₹{$coupon->minimum_order_amount} required";
        }

        if (empty($validation['messages']) && $request->order_total) {
            $validation['discount_preview'] = $coupon->calculateDiscount(
                $request->order_total,
                $request->products ?? []
            );
        }

        return response()->json([
            'success' => true,
            'validation' => $validation,
            'coupon' => $coupon->only(['code', 'name', 'type', 'formatted_value'])
        ]);
    }

    public function getUsageReport(Coupon $coupon, Request $request)
    {
        $period = $request->input('period', '30d');
        $days = (int) filter_var($period, FILTER_SANITIZE_NUMBER_INT);

        $usages = $coupon->usages()
            ->with(['user:id,name,email', 'order:id,order_number,status'])
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'usage_report' => $usages,
            'summary' => [
                'total_usages' => $usages->total(),
                'total_discount_given' => $coupon->usages()
                    ->where('created_at', '>=', now()->subDays($days))
                    ->sum('discount_amount'),
                'average_discount' => $coupon->usages()
                    ->where('created_at', '>=', now()->subDays($days))
                    ->avg('discount_amount'),
            ]
        ]);
    }

    protected function getCouponFilters(): array
    {
        return [
            'types' => [
                ['value' => 'percentage', 'label' => 'Percentage'],
                ['value' => 'fixed_amount', 'label' => 'Fixed Amount'],
                ['value' => 'free_shipping', 'label' => 'Free Shipping'],
                ['value' => 'buy_x_get_y', 'label' => 'Buy X Get Y'],
            ],
            'statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'expired', 'label' => 'Expired'],
                ['value' => 'valid', 'label' => 'Valid'],
            ],
            'products' => Product::select('id', 'name')->limit(100)->get(),
            'categories' => Category::select('id', 'name')->get(),
            'customer_groups' => CustomerGroup::select('id', 'name')->get(),
        ];
    }

    protected function getCouponStats(): array
    {
        return [
            'total_coupons' => Coupon::count(),
            'active_coupons' => Coupon::active()->count(),
            'expired_coupons' => Coupon::where('expires_at', '<', now())->count(),
            'total_discounts_given' => CouponUsage::sum('discount_amount'),
            'most_used_type' => Coupon::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->first()?->type,
        ];
    }

    protected function generateCouponCode(string $prefix = '', int $length = 8, string $type = 'random'): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $code = $prefix;
            $remainingLength = $length - strlen($prefix);

            switch ($type) {
                case 'numeric':
                    $code .= str_pad(random_int(0, pow(10, $remainingLength) - 1), $remainingLength, '0', STR_PAD_LEFT);
                    break;
                case 'readable':
                    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Excluding similar characters
                    $code .= substr(str_shuffle(str_repeat($chars, ceil($remainingLength / strlen($chars)))), 0, $remainingLength);
                    break;
                default: // random
                    $code .= strtoupper(Str::random($remainingLength));
            }

            $attempt++;
        } while (Coupon::where('code', $code)->exists() && $attempt < $maxAttempts);

        if (Coupon::where('code', $code)->exists()) {
            // Fallback: append timestamp
            $code .= now()->format('His');
        }

        return $code;
    }

    protected function getCouponUsageAnalytics(Coupon $coupon): array
    {
        $usages = $coupon->usages();

        return [
            'daily_usage' => $usages->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'monthly_usage' => $usages->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get(),
        ];
    }

    protected function getCouponPerformanceMetrics(Coupon $coupon): array
    {
        $usages = $coupon->usages();
        
        return [
            'conversion_rate' => $this->calculateCouponConversionRate($coupon),
            'average_cart_value' => $usages->avg('order_total_before_discount'),
            'total_revenue_impact' => $usages->sum('order_total_after_discount'),
            'discount_efficiency' => $this->calculateDiscountEfficiency($coupon),
        ];
    }

    protected function getCouponUserDemographics(Coupon $coupon): array
    {
        // This would analyze the users who used the coupon
        return [
            'new_vs_returning' => $coupon->usages()
                ->join('users', 'coupon_usages.user_id', '=', 'users.id')
                ->selectRaw('
                    SUM(CASE WHEN (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.created_at < coupon_usages.created_at) = 0 THEN 1 ELSE 0 END) as new_customers,
                    SUM(CASE WHEN (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.created_at < coupon_usages.created_at) > 0 THEN 1 ELSE 0 END) as returning_customers
                ')
                ->first(),
        ];
    }

    protected function getCouponProductImpact(Coupon $coupon): array
    {
        // Analyze which products were most affected by this coupon
        return [
            'top_products' => $coupon->usages()
                ->join('orders', 'coupon_usages.order_id', '=', 'orders.id')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->select('products.name', DB::raw('SUM(order_items.quantity) as quantity_sold'))
                ->groupBy('products.id', 'products.name')
                ->orderBy('quantity_sold', 'desc')
                ->limit(10)
                ->get(),
        ];
    }

    protected function calculateCouponConversionRate(Coupon $coupon): float
    {
        // This is a simplified calculation - you might want to track coupon views separately
        return 0; // Placeholder
    }

    protected function calculateDiscountEfficiency(Coupon $coupon): float
    {
        $usages = $coupon->usages();
        $totalDiscount = $usages->sum('discount_amount');
        $totalRevenue = $usages->sum('order_total_after_discount');

        if ($totalRevenue == 0) {
            return 0;
        }

        return ($totalDiscount / ($totalRevenue + $totalDiscount)) * 100;
    }
}