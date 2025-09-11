<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryOptionController extends Controller
{
    /**
     * Get all delivery options with pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $active = $request->input('active');

        $query = DeliveryOption::query();

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
        }

        if ($active !== null) {
            $query->where('is_active', $active);
        }

        $options = $query->orderBy('sort_order')->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'delivery_options' => $options
        ]);
    }

    /**
     * Show specific delivery option
     */
    public function show(DeliveryOption $deliveryOption)
    {
        return response()->json([
            'success' => true,
            'delivery_option' => $deliveryOption
        ]);
    }

    /**
     * Create new delivery option
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:delivery_options,code',
            'description' => 'nullable|string|max:1000',
            'delivery_days_min' => 'required|integer|min:1|max:30',
            'delivery_days_max' => 'required|integer|gte:delivery_days_min|max:30',
            'price_multiplier' => 'required|numeric|min:0.1|max:10',
            'fixed_surcharge' => 'nullable|numeric|min:0',
            'availability_zones' => 'nullable|array',
            'availability_zones.*' => 'string|in:A,B,C,D,E',
            'availability_conditions' => 'nullable|array',
            'cutoff_time' => 'nullable|date_format:H:i:s',
            'restricted_days' => 'nullable|array',
            'restricted_days.*' => 'integer|min:0|max:6',
            'min_order_value' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $option = DeliveryOption::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Delivery option created successfully',
            'delivery_option' => $option
        ], 201);
    }

    /**
     * Update delivery option
     */
    public function update(Request $request, DeliveryOption $deliveryOption)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('delivery_options', 'code')->ignore($deliveryOption->id)
            ],
            'description' => 'nullable|string|max:1000',
            'delivery_days_min' => 'required|integer|min:1|max:30',
            'delivery_days_max' => 'required|integer|gte:delivery_days_min|max:30',
            'price_multiplier' => 'required|numeric|min:0.1|max:10',
            'fixed_surcharge' => 'nullable|numeric|min:0',
            'availability_zones' => 'nullable|array',
            'availability_zones.*' => 'string|in:A,B,C,D,E',
            'availability_conditions' => 'nullable|array',
            'cutoff_time' => 'nullable|date_format:H:i:s',
            'restricted_days' => 'nullable|array',
            'restricted_days.*' => 'integer|min:0|max:6',
            'min_order_value' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $deliveryOption->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Delivery option updated successfully',
            'delivery_option' => $deliveryOption
        ]);
    }

    /**
     * Toggle delivery option status
     */
    public function toggleStatus(DeliveryOption $deliveryOption)
    {
        $deliveryOption->update(['is_active' => !$deliveryOption->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Delivery option status updated',
            'is_active' => $deliveryOption->is_active
        ]);
    }

    /**
     * Delete delivery option
     */
    public function destroy(DeliveryOption $deliveryOption)
    {
        try {
            $deliveryOption->delete();

            return response()->json([
                'success' => true,
                'message' => 'Delivery option deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete delivery option - it may be in use'
            ], 422);
        }
    }

    /**
     * Test delivery option availability and cost
     */
    public function testAvailability(Request $request)
    {
        $validated = $request->validate([
            'option_id' => 'required|exists:delivery_options,id',
            'zone' => 'required|string|in:A,B,C,D,E',
            'order_value' => 'required|numeric|min:1',
            'base_shipping_cost' => 'required|numeric|min:0',
            'is_metro' => 'boolean',
            'is_remote' => 'boolean',
            'order_date' => 'nullable|date',
            'order_time' => 'nullable|date_format:H:i:s',
        ]);

        $option = DeliveryOption::findOrFail($validated['option_id']);

        $testOptions = [
            'is_metro' => $validated['is_metro'] ?? false,
            'is_remote' => $validated['is_remote'] ?? false,
            'order_date' => $validated['order_date'] ?? now()->toDateString(),
            'order_time' => $validated['order_time'] ?? now()->format('H:i:s'),
        ];

        try {
            $isAvailable = $option->isAvailable(
                $validated['zone'],
                $validated['order_value'],
                $testOptions
            );

            $result = [
                'available' => $isAvailable,
                'option_details' => [
                    'name' => $option->name,
                    'code' => $option->code,
                    'delivery_window' => $option->getDeliveryWindow(),
                ]
            ];

            if ($isAvailable) {
                $cost = $option->calculateCost(
                    $validated['base_shipping_cost'],
                    $validated['order_value'],
                    $testOptions
                );
                $result['cost_calculation'] = $cost;
            } else {
                $result['reason'] = 'Option not available for given conditions';
            }

            return response()->json([
                'success' => true,
                'test_result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get available delivery options for conditions
     */
    public function getAvailableForConditions(Request $request)
    {
        $validated = $request->validate([
            'zone' => 'required|string|in:A,B,C,D,E',
            'order_value' => 'required|numeric|min:1',
            'base_shipping_cost' => 'required|numeric|min:0',
            'is_metro' => 'boolean',
            'is_remote' => 'boolean',
            'order_date' => 'nullable|date',
            'order_time' => 'nullable|date_format:H:i:s',
        ]);

        $options = [
            'is_metro' => $validated['is_metro'] ?? false,
            'is_remote' => $validated['is_remote'] ?? false,
            'order_date' => $validated['order_date'] ?? now()->toDateString(),
            'order_time' => $validated['order_time'] ?? now()->format('H:i:s'),
        ];

        try {
            $availableOptions = DeliveryOption::getAvailableOptions(
                $validated['zone'],
                $validated['order_value'],
                $validated['base_shipping_cost'],
                $options
            );

            return response()->json([
                'success' => true,
                'available_options' => $availableOptions,
                'test_conditions' => $validated + $options
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get options: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Update sort order for delivery options
     */
    public function updateSortOrder(Request $request)
    {
        $validated = $request->validate([
            'options' => 'required|array|min:1',
            'options.*.id' => 'required|exists:delivery_options,id',
            'options.*.sort_order' => 'required|integer|min:0',
        ]);

        $updated = 0;
        $errors = [];

        foreach ($validated['options'] as $optionData) {
            try {
                $option = DeliveryOption::findOrFail($optionData['id']);
                $option->update(['sort_order' => $optionData['sort_order']]);
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Option ID {$optionData['id']}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$updated} delivery options updated",
            'stats' => [
                'updated' => $updated,
                'errors' => $errors
            ]
        ]);
    }

    /**
     * Get delivery analytics
     */
    public function analytics(Request $request)
    {
        $period = $request->input('period', '30d');
        $days = (int) filter_var($period, FILTER_SANITIZE_NUMBER_INT);

        $analytics = [
            'total_options' => DeliveryOption::count(),
            'active_options' => DeliveryOption::where('is_active', true)->count(),
            'option_usage' => $this->getOptionUsageStats($days),
            'average_delivery_days' => $this->getAverageDeliveryDays($days),
            'premium_service_adoption' => $this->getPremiumServiceAdoption($days),
        ];

        return response()->json([
            'success' => true,
            'analytics' => $analytics,
            'period' => $period
        ]);
    }

    protected function getOptionUsageStats($days)
    {
        // This would query actual usage data
        // For now, return sample data
        return [
            'standard' => ['usage_count' => 450, 'success_rate' => 96.5],
            'express' => ['usage_count' => 180, 'success_rate' => 94.8],
            'same_day' => ['usage_count' => 45, 'success_rate' => 91.2],
            'next_day' => ['usage_count' => 120, 'success_rate' => 93.6],
        ];
    }

    protected function getAverageDeliveryDays($days)
    {
        return [
            'overall_average' => 3.2,
            'by_option' => [
                'standard' => 4.1,
                'express' => 2.8,
                'same_day' => 1.0,
                'next_day' => 1.5,
            ]
        ];
    }

    protected function getPremiumServiceAdoption($days)
    {
        return [
            'premium_percentage' => 28.5, // % of orders using premium services
            'revenue_contribution' => 45.2, // % of shipping revenue from premium
            'growth_rate' => 12.8, // % growth in premium adoption
        ];
    }
}
