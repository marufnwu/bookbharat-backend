<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\ShippingCarrier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
{
    /**
     * Display a listing of warehouses
     */
    public function index(): JsonResponse
    {
        try {
            $warehouses = Warehouse::orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $warehouses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created warehouse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:warehouses,code',
            'contact_person' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address_line_1' => 'required|string',
            'address_line_2' => 'nullable|string',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'required|string|max:10',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'gst_number' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        try {
            // If this is set as default, remove default from others
            if ($validated['is_default'] ?? false) {
                Warehouse::where('is_default', true)->update(['is_default' => false]);
            }

            $warehouse = Warehouse::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Warehouse created successfully',
                'data' => $warehouse
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified warehouse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $warehouse = Warehouse::with('carriers')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $warehouse
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified warehouse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:warehouses,code,' . $id,
            'contact_person' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email|max:255',
            'address_line_1' => 'sometimes|string',
            'address_line_2' => 'nullable|string',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'pincode' => 'sometimes|string|max:10',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'gst_number' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        try {
            $warehouse = Warehouse::findOrFail($id);

            // If this is set as default, remove default from others
            if (($validated['is_default'] ?? false) && !$warehouse->is_default) {
                Warehouse::where('is_default', true)->update(['is_default' => false]);
            }

            $warehouse->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Warehouse updated successfully',
                'data' => $warehouse
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified warehouse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            if ($warehouse->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete default warehouse'
                ], 400);
            }

            $warehouse->delete();

            return response()->json([
                'success' => true,
                'message' => 'Warehouse deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get warehouse mappings for a specific carrier
     */
    public function getCarrierWarehouses(int $carrierId): JsonResponse
    {
        try {
            $carrier = \App\Models\ShippingCarrier::findOrFail($carrierId);
            $factory = app(\App\Services\Shipping\Carriers\CarrierFactory::class);
            $adapter = $factory->make($carrier);

            // Determine warehouse requirement type
            $requirementType = $adapter->getWarehouseRequirementType();

            Log::info('Fetching warehouses for carrier', [
                'carrier_code' => $carrier->code,
                'requirement_type' => $requirementType
            ]);

            switch ($requirementType) {
                case 'registered_id':
                case 'registered_alias':
                    // Fetch pre-registered warehouses from carrier API
                    $carrierService = app(\App\Services\Shipping\MultiCarrierShippingService::class);
                    $registeredLocations = $carrierService->getCarrierRegisteredPickupLocations($carrier);

                    return response()->json([
                        'success' => true,
                        'data' => $registeredLocations['warehouses'] ?? [],
                        'carrier_code' => $carrier->code,
                        'requirement_type' => $requirementType,
                        'source' => 'carrier_api',
                        'note' => 'These are pre-registered warehouses from ' . $carrier->name
                    ]);

                case 'full_address':
                default:
                    // Return site warehouses from database
                    $siteWarehouses = \App\Models\Warehouse::active()
                        ->orderBy('is_default', 'desc')
                        ->orderBy('name')
                        ->get()
                        ->map(function($warehouse) {
                            return [
                                'id' => $warehouse->id,
                                'name' => $warehouse->name,
                                'carrier_warehouse_name' => $warehouse->name,
                                'address' => $warehouse->address_line_1,
                                'city' => $warehouse->city,
                                'state' => $warehouse->state,
                                'pincode' => $warehouse->pincode,
                                'phone' => $warehouse->phone,
                                'is_default' => $warehouse->is_default,
                                'is_enabled' => $warehouse->is_active,
                                'is_registered' => true  // From database
                            ];
                        });

                    return response()->json([
                        'success' => true,
                        'data' => $siteWarehouses,
                        'carrier_code' => $carrier->code,
                        'requirement_type' => $requirementType,
                        'source' => 'database',
                        'note' => 'Select site warehouse. Full address will be sent to ' . $carrier->name
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch carrier warehouses', [
                'carrier_id' => $carrierId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch carrier registered pickup locations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update warehouse mapping for a carrier
     */
    public function updateCarrierWarehouse(Request $request, int $carrierId, int $warehouseId): JsonResponse
    {
        $validated = $request->validate([
            'carrier_warehouse_name' => 'required|string|max:255',
            'carrier_warehouse_id' => 'nullable|string|max:255',
            'is_enabled' => 'nullable|boolean',
        ]);

        try {
            $carrier = ShippingCarrier::findOrFail($carrierId);
            $warehouse = Warehouse::findOrFail($warehouseId);

            DB::table('carrier_warehouse')->updateOrInsert(
                [
                    'carrier_id' => $carrierId,
                    'warehouse_id' => $warehouseId
                ],
                [
                    'carrier_warehouse_name' => $validated['carrier_warehouse_name'],
                    'carrier_warehouse_id' => $validated['carrier_warehouse_id'] ?? null,
                    'is_enabled' => $validated['is_enabled'] ?? true,
                    'updated_at' => now()
                ]
            );

            Log::info('Warehouse mapping updated', [
                'carrier' => $carrier->name,
                'warehouse' => $warehouse->name,
                'alias' => $validated['carrier_warehouse_name']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Warehouse mapping updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update warehouse mapping',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get registered addresses from carrier (Ekart, Delhivery, etc.)
     */
    public function getRegisteredAddresses(int $carrierId): JsonResponse
    {
        try {
            $carrier = ShippingCarrier::findOrFail($carrierId);

            $factory = app(\App\Services\Shipping\Carriers\CarrierFactory::class);
            $adapter = $factory->make($carrier);

            // Check if adapter has getRegisteredAddresses method
            if (method_exists($adapter, 'getRegisteredAddresses')) {
                $result = $adapter->getRegisteredAddresses();
                return response()->json($result);
            }

            // Check for Delhivery-specific method
            if (method_exists($adapter, 'getRegisteredWarehouses')) {
                $result = $adapter->getRegisteredWarehouses();
                return response()->json($result);
            }

            return response()->json([
                'success' => false,
                'message' => 'This carrier does not support warehouse fetching via API',
                'note' => 'Please manage warehouses through the carrier\'s portal'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to fetch registered addresses', [
                'carrier_id' => $carrierId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch registered addresses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register a warehouse with a carrier
     */
    public function registerWarehouseWithCarrier(Request $request, int $carrierId, int $warehouseId): JsonResponse
    {
        $validated = $request->validate([
            'carrier_warehouse_name' => 'nullable|string|max:255',
        ]);

        try {
            $carrier = ShippingCarrier::findOrFail($carrierId);
            $warehouse = Warehouse::findOrFail($warehouseId);

            $factory = app(\App\Services\Shipping\Carriers\CarrierFactory::class);
            $adapter = $factory->make($carrier);

            // Prepare warehouse data
            $warehouseData = $warehouse->toPickupAddress();
            $warehouseData['alias'] = $validated['carrier_warehouse_name'] ?? $warehouse->name;

            // Try to register with carrier API
            $result = null;
            if (method_exists($adapter, 'registerAddress')) {
                // Ekart uses registerAddress
                $result = $adapter->registerAddress($warehouseData);
            } elseif (method_exists($adapter, 'registerWarehouse')) {
                // Delhivery uses registerWarehouse
                $result = $adapter->registerWarehouse($warehouseData);
            }

            if ($result && $result['success']) {
                // Update local mapping with carrier's response
                $aliasToStore = $result['alias'] ?? $result['warehouse_name'] ?? $validated['carrier_warehouse_name'] ?? $warehouse->name;

                DB::table('carrier_warehouse')->updateOrInsert(
                    [
                        'carrier_id' => $carrierId,
                        'warehouse_id' => $warehouseId
                    ],
                    [
                        'carrier_warehouse_name' => $aliasToStore,
                        'is_enabled' => true,
                        'updated_at' => now()
                    ]
                );

                return response()->json([
                    'success' => true,
                    'message' => "Warehouse registered with {$carrier->name} successfully",
                    'alias' => $aliasToStore
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Carrier does not support automatic warehouse registration',
                'note' => 'Please register warehouse manually through carrier portal'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to register warehouse with carrier', [
                'carrier_id' => $carrierId,
                'warehouse_id' => $warehouseId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register warehouse with carrier',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
