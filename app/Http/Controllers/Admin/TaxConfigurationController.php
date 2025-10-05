<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxConfiguration;
use Illuminate\Http\Request;

class TaxConfigurationController extends Controller
{
    public function index()
    {
        $taxes = TaxConfiguration::orderBy('priority', 'asc')->get();
        
        return response()->json([
            'success' => true,
            'taxes' => $taxes
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:tax_configurations,code',
            'tax_type' => 'required|in:gst,igst,cgst_sgst,vat,sales_tax,custom',
            'rate' => 'required|numeric|min:0|max:100',
            'is_enabled' => 'boolean',
            'apply_on' => 'required|in:subtotal,subtotal_with_charges,subtotal_with_shipping',
            'conditions' => 'nullable|array',
            'is_inclusive' => 'boolean',
            'priority' => 'integer|min:0',
            'description' => 'nullable|string',
            'display_label' => 'required|string|max:255',
        ]);

        $tax = TaxConfiguration::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tax configuration created successfully',
            'tax' => $tax
        ], 201);
    }

    public function show(TaxConfiguration $taxConfiguration)
    {
        return response()->json([
            'success' => true,
            'tax' => $taxConfiguration
        ]);
    }

    public function update(Request $request, TaxConfiguration $taxConfiguration)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:tax_configurations,code,' . $taxConfiguration->id,
            'tax_type' => 'sometimes|required|in:gst,igst,cgst_sgst,vat,sales_tax,custom',
            'rate' => 'sometimes|required|numeric|min:0|max:100',
            'is_enabled' => 'boolean',
            'apply_on' => 'sometimes|required|in:subtotal,subtotal_with_charges,subtotal_with_shipping',
            'conditions' => 'nullable|array',
            'is_inclusive' => 'boolean',
            'priority' => 'integer|min:0',
            'description' => 'nullable|string',
            'display_label' => 'sometimes|required|string|max:255',
        ]);

        $taxConfiguration->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tax configuration updated successfully',
            'tax' => $taxConfiguration
        ]);
    }

    public function destroy(TaxConfiguration $taxConfiguration)
    {
        $taxConfiguration->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tax configuration deleted successfully'
        ]);
    }

    public function toggleStatus(TaxConfiguration $taxConfiguration)
    {
        $taxConfiguration->update(['is_enabled' => !$taxConfiguration->is_enabled]);

        return response()->json([
            'success' => true,
            'message' => 'Tax status updated',
            'tax' => $taxConfiguration
        ]);
    }
}
