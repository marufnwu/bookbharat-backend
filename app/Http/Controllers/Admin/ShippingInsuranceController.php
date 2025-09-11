<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingInsurance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShippingInsuranceController extends Controller
{
    /**
     * Get all insurance plans with pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $active = $request->input('active');

        $query = ShippingInsurance::query();

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($active !== null) {
            $query->where('is_active', $active);
        }

        $plans = $query->orderBy('min_order_value')->paginate($perPage);

        return response()->json([
            'success' => true,
            'insurance_plans' => $plans
        ]);
    }

    /**
     * Show specific insurance plan
     */
    public function show(ShippingInsurance $insurance)
    {
        return response()->json([
            'success' => true,
            'insurance_plan' => $insurance
        ]);
    }

    /**
     * Create new insurance plan
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:shipping_insurance,name',
            'description' => 'nullable|string|max:1000',
            'min_order_value' => 'required|numeric|min:0',
            'max_order_value' => 'nullable|numeric|gt:min_order_value',
            'coverage_percentage' => 'required|numeric|min:0|max:100',
            'premium_percentage' => 'required|numeric|min:0|max:50',
            'minimum_premium' => 'required|numeric|min:0',
            'maximum_premium' => 'nullable|numeric|gt:minimum_premium',
            'is_mandatory' => 'boolean',
            'conditions' => 'nullable|array',
            'claim_processing_days' => 'required|integer|min:1|max:30',
            'is_active' => 'boolean',
        ]);

        $plan = ShippingInsurance::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Insurance plan created successfully',
            'insurance_plan' => $plan
        ], 201);
    }

    /**
     * Update insurance plan
     */
    public function update(Request $request, ShippingInsurance $insurance)
    {
        $validated = $request->validate([
            'name' => [
                'required', 
                'string', 
                'max:255', 
                Rule::unique('shipping_insurance', 'name')->ignore($insurance->id)
            ],
            'description' => 'nullable|string|max:1000',
            'min_order_value' => 'required|numeric|min:0',
            'max_order_value' => 'nullable|numeric|gt:min_order_value',
            'coverage_percentage' => 'required|numeric|min:0|max:100',
            'premium_percentage' => 'required|numeric|min:0|max:50',
            'minimum_premium' => 'required|numeric|min:0',
            'maximum_premium' => 'nullable|numeric|gt:minimum_premium',
            'is_mandatory' => 'boolean',
            'conditions' => 'nullable|array',
            'claim_processing_days' => 'required|integer|min:1|max:30',
            'is_active' => 'boolean',
        ]);

        $insurance->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Insurance plan updated successfully',
            'insurance_plan' => $insurance
        ]);
    }

    /**
     * Test insurance calculation
     */
    public function testCalculation(Request $request)
    {
        $validated = $request->validate([
            'insurance_id' => 'required|exists:shipping_insurance,id',
            'order_value' => 'required|numeric|min:1',
            'zone' => 'nullable|string|in:A,B,C,D,E',
            'is_remote' => 'boolean',
            'has_fragile_items' => 'boolean',
            'has_electronics' => 'boolean',
        ]);

        $insurance = ShippingInsurance::findOrFail($validated['insurance_id']);

        $options = [
            'zone' => $validated['zone'] ?? 'D',
            'is_remote' => $validated['is_remote'] ?? false,
            'has_fragile_items' => $validated['has_fragile_items'] ?? false,
            'has_electronics' => $validated['has_electronics'] ?? false,
        ];

        try {
            $calculation = $insurance->calculatePremium($validated['order_value'], $options);

            return response()->json([
                'success' => true,
                'calculation' => $calculation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Calculation failed: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Toggle insurance plan status
     */
    public function toggleStatus(ShippingInsurance $insurance)
    {
        $insurance->update(['is_active' => !$insurance->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Insurance plan status updated',
            'is_active' => $insurance->is_active
        ]);
    }
}
