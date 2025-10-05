<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderCharge;
use Illuminate\Http\Request;

class OrderChargeController extends Controller
{
    public function index()
    {
        $charges = OrderCharge::orderBy('priority', 'asc')->get();
        
        return response()->json([
            'success' => true,
            'charges' => $charges
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:order_charges,code',
            'type' => 'required|in:fixed,percentage,tiered',
            'amount' => 'required_if:type,fixed|nullable|numeric|min:0',
            'percentage' => 'required_if:type,percentage|nullable|numeric|min:0|max:100',
            'tiers' => 'required_if:type,tiered|nullable|array',
            'is_enabled' => 'boolean',
            'apply_to' => 'required|in:all,cod_only,online_only,specific_payment_methods,conditional',
            'payment_methods' => 'nullable|array',
            'conditions' => 'nullable|array',
            'priority' => 'integer|min:0',
            'description' => 'nullable|string',
            'display_label' => 'required|string|max:255',
            'is_taxable' => 'boolean',
            'apply_after_discount' => 'boolean',
            'is_refundable' => 'boolean',
        ]);

        $charge = OrderCharge::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Charge created successfully',
            'charge' => $charge
        ], 201);
    }

    public function show(OrderCharge $orderCharge)
    {
        return response()->json([
            'success' => true,
            'charge' => $orderCharge
        ]);
    }

    public function update(Request $request, OrderCharge $orderCharge)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:order_charges,code,' . $orderCharge->id,
            'type' => 'sometimes|required|in:fixed,percentage,tiered',
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'tiers' => 'nullable|array',
            'is_enabled' => 'boolean',
            'apply_to' => 'sometimes|required|in:all,cod_only,online_only,specific_payment_methods,conditional',
            'payment_methods' => 'nullable|array',
            'conditions' => 'nullable|array',
            'priority' => 'integer|min:0',
            'description' => 'nullable|string',
            'display_label' => 'sometimes|required|string|max:255',
            'is_taxable' => 'boolean',
            'apply_after_discount' => 'boolean',
            'is_refundable' => 'boolean',
        ]);

        $orderCharge->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Charge updated successfully',
            'charge' => $orderCharge
        ]);
    }

    public function destroy(OrderCharge $orderCharge)
    {
        $orderCharge->delete();

        return response()->json([
            'success' => true,
            'message' => 'Charge deleted successfully'
        ]);
    }

    public function toggleStatus(OrderCharge $orderCharge)
    {
        $orderCharge->update(['is_enabled' => !$orderCharge->is_enabled]);

        return response()->json([
            'success' => true,
            'message' => 'Charge status updated',
            'charge' => $orderCharge
        ]);
    }

    public function updatePriority(Request $request)
    {
        $request->validate([
            'charges' => 'required|array',
            'charges.*.id' => 'required|exists:order_charges,id',
            'charges.*.priority' => 'required|integer|min:0',
        ]);

        foreach ($request->charges as $chargeData) {
            OrderCharge::where('id', $chargeData['id'])
                ->update(['priority' => $chargeData['priority']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Priorities updated successfully'
        ]);
    }
}
