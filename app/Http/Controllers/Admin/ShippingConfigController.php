<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingWeightSlab;
use App\Models\ShippingZone;
use App\Models\PincodeZone;
use App\Models\InventoryLocation;
use App\Services\ShippingService;
use App\Services\ZoneCalculationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShippingConfigController extends Controller
{
    protected $shippingService;
    protected $zoneService;

    public function __construct(ShippingService $shippingService, ZoneCalculationService $zoneService)
    {
        $this->shippingService = $shippingService;
        $this->zoneService = $zoneService;
    }

    /**
     * Get shipping configuration overview
     */
    public function overview()
    {
        $overview = [
            'weight_slabs' => [
                'total' => ShippingWeightSlab::count(),
                'by_courier' => ShippingWeightSlab::selectRaw('courier_name, COUNT(*) as count')
                    ->groupBy('courier_name')
                    ->get(),
            ],
            'shipping_zones' => [
                'total' => ShippingZone::count(),
                'by_zone' => ShippingZone::selectRaw('zone, COUNT(*) as count')
                    ->groupBy('zone')
                    ->get(),
            ],
            'pincode_zones' => [
                'total' => PincodeZone::count(),
                'by_zone' => PincodeZone::selectRaw('zone, COUNT(*) as count')
                    ->groupBy('zone')
                    ->get(),
                'cod_enabled' => PincodeZone::where('cod_available', true)->count(),
            ],
            'zone_configuration' => $this->zoneService->getAllZones(),
        ];

        return response()->json([
            'success' => true,
            'overview' => $overview
        ]);
    }

    /**
     * Get all zones
     */
    public function getZones(Request $request)
    {
        $zones = ShippingZone::all();

        return response()->json([
            'success' => true,
            'zones' => $zones
        ]);
    }

    /**
     * Store a new zone
     */
    public function storeZone(Request $request)
    {
        $validated = $request->validate([
            'shipping_weight_slab_id' => 'required|exists:shipping_weight_slabs,id',
            'zone' => 'required|string|in:A,B,C,D,E',
            'fwd_rate' => 'required|numeric|min:0',
            'rto_rate' => 'required|numeric|min:0',
            'aw_rate' => 'required|numeric|min:0',
            'cod_charges' => 'required|numeric|min:0',
            'cod_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $zone = ShippingZone::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Zone created successfully',
            'zone' => $zone
        ], 201);
    }

    /**
     * Update a zone
     */
    public function updateZone(Request $request, $id)
    {
        $zone = ShippingZone::findOrFail($id);

        $validated = $request->validate([
            'shipping_weight_slab_id' => 'sometimes|required|exists:shipping_weight_slabs,id',
            'zone' => 'sometimes|required|string|in:A,B,C,D,E',
            'fwd_rate' => 'sometimes|required|numeric|min:0',
            'rto_rate' => 'sometimes|required|numeric|min:0',
            'aw_rate' => 'sometimes|required|numeric|min:0',
            'cod_charges' => 'sometimes|required|numeric|min:0',
            'cod_percentage' => 'sometimes|required|numeric|min:0|max:100',
        ]);

        $zone->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Zone updated successfully',
            'zone' => $zone->fresh()
        ]);
    }

    /**
     * Delete a zone
     */
    public function deleteZone($id)
    {
        $zone = ShippingZone::findOrFail($id);

        $zone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Zone deleted successfully'
        ]);
    }

    /**
     * Get all weight slabs with pagination
     */
    public function getWeightSlabs(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');

        $query = ShippingWeightSlab::with('shippingZones');

        if ($search) {
            $query->where('courier_name', 'LIKE', "%{$search}%");
        }

        $weightSlabs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'weight_slabs' => $weightSlabs
        ]);
    }

    /**
     * Store new weight slab (alias for createWeightSlab)
     */
    public function storeWeightSlab(Request $request)
    {
        return $this->createWeightSlab($request);
    }

    /**
     * Create new weight slab
     */
    public function createWeightSlab(Request $request)
    {
        $validated = $request->validate([
            'courier_name' => 'required|string|max:255',
            'base_weight' => 'required|numeric|min:0|max:100',
        ]);

        // Check for duplicate
        $existing = ShippingWeightSlab::where('courier_name', $validated['courier_name'])
            ->where('base_weight', $validated['base_weight'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Weight slab already exists for this courier and weight'
            ], 422);
        }

        $weightSlab = ShippingWeightSlab::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Weight slab created successfully',
            'weight_slab' => $weightSlab
        ], 201);
    }

    /**
     * Update weight slab
     */
    public function updateWeightSlab(Request $request, $id)
    {
        $weightSlab = ShippingWeightSlab::findOrFail($id);

        $validated = $request->validate([
            'courier_name' => 'required|string|max:255',
            'base_weight' => 'required|numeric|min:0|max:100',
        ]);

        // Check for duplicate (excluding current)
        $existing = ShippingWeightSlab::where('courier_name', $validated['courier_name'])
            ->where('base_weight', $validated['base_weight'])
            ->where('id', '!=', $weightSlab->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Weight slab already exists for this courier and weight'
            ], 422);
        }

        $weightSlab->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Weight slab updated successfully',
            'weight_slab' => $weightSlab->fresh()
        ]);
    }

    /**
     * Delete weight slab
     */
    public function deleteWeightSlab($id)
    {
        try {
            $weightSlab = ShippingWeightSlab::findOrFail($id);
            $weightSlab->delete();

            return response()->json([
                'success' => true,
                'message' => 'Weight slab deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete weight slab - it may be linked to shipping zones'
            ], 422);
        }
    }


    /**
     * Get pincode zones with pagination
     */
    public function getPincodeZones(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $zone = $request->input('zone');
        $state = $request->input('state');

        $query = PincodeZone::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pincode', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        if ($zone) {
            $query->where('zone', $zone);
        }

        if ($state) {
            $query->where('state', $state);
        }

        $pincodes = $query->orderBy('pincode')->paginate($perPage);

        return response()->json([
            'success' => true,
            'pincode_zones' => $pincodes
        ]);
    }

    /**
     * Store new pincode zone (alias for createPincodeZone)
     */
    public function storePincodeZone(Request $request)
    {
        return $this->createPincodeZone($request);
    }

    /**
     * Delete a pincode zone
     */
    public function deletePincodeZone($id)
    {
        $pincodeZone = PincodeZone::findOrFail($id);
        $pincodeZone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pincode zone deleted successfully'
        ]);
    }

    /**
     * Create pincode zone mapping
     */
    public function createPincodeZone(Request $request)
    {
        $validated = $request->validate([
            'pincode' => 'required|string|size:6|unique:pincode_zones,pincode',
            'zone' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'is_metro' => 'boolean',
            'is_remote' => 'boolean',
            'cod_available' => 'boolean',
            'expected_delivery_days' => 'required|integer|min:1|max:30',
            'zone_multiplier' => 'required|numeric|min:0.1|max:5.0',
        ]);

        $pincodeZone = PincodeZone::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pincode zone created successfully',
            'pincode_zone' => $pincodeZone
        ], 201);
    }

    /**
     * Update pincode zone
     */
    public function updatePincodeZone(Request $request, PincodeZone $pincodeZone)
    {
        $validated = $request->validate([
            'zone' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'is_metro' => 'boolean',
            'is_remote' => 'boolean',
            'cod_available' => 'boolean',
            'expected_delivery_days' => 'required|integer|min:1|max:30',
            'zone_multiplier' => 'required|numeric|min:0.1|max:5.0',
        ]);

        $pincodeZone->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pincode zone updated successfully',
            'pincode_zone' => $pincodeZone
        ]);
    }

    /**
     * Bulk import pincode zones
     */
    public function bulkImportPincodes(Request $request)
    {
        $request->validate([
            'pincodes' => 'required|array|min:1|max:1000',
            'pincodes.*.pincode' => 'required|string|size:6',
            'pincodes.*.zone' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
            'pincodes.*.city' => 'nullable|string|max:255',
            'pincodes.*.state' => 'nullable|string|max:255',
            'pincodes.*.region' => 'nullable|string|max:255',
            'pincodes.*.is_metro' => 'boolean',
            'pincodes.*.is_remote' => 'boolean',
            'pincodes.*.cod_available' => 'boolean',
            'pincodes.*.expected_delivery_days' => 'required|integer|min:1|max:30',
            'pincodes.*.zone_multiplier' => 'required|numeric|min:0.1|max:5.0',
        ]);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($request->pincodes as $index => $pincodeData) {
            try {
                PincodeZone::updateOrCreate(
                    ['pincode' => $pincodeData['pincode']],
                    $pincodeData
                );
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Row {$index}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Import completed: {$imported} imported, {$skipped} skipped",
            'stats' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors
            ]
        ]);
    }

    /**
     * Test shipping calculation
     */
    public function testCalculation(Request $request)
    {
        $validated = $request->validate([
            'pickup_pincode' => 'nullable|string|size:6',
            'delivery_pincode' => 'required|string|size:6',
            'weight' => 'required|numeric|min:0.1',
            'order_value' => 'required|numeric|min:1',
            'dimensions' => 'nullable|array',
            'dimensions.length' => 'nullable|numeric|min:1',
            'dimensions.width' => 'nullable|numeric|min:1',
            'dimensions.height' => 'nullable|numeric|min:1',
        ]);

        // Create test items
        $items = [
            [
                'product' => (object) [
                    'weight' => $validated['weight'],
                    'dimensions' => $validated['dimensions'] ?? ['length' => 20, 'width' => 14, 'height' => 2]
                ],
                'quantity' => 1
            ]
        ];

        try {
            // Use provided pickup pincode, or fall back to default warehouse
            $pickupPincode = $validated['pickup_pincode'] ?? $this->getDefaultPickupPincode();

            $shippingData = $this->shippingService->calculateShippingCharges(
                $pickupPincode,
                $validated['delivery_pincode'],
                $items,
                $validated['order_value']
            );

            return response()->json([
                'success' => true,
                'shipping_calculation' => $shippingData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Calculation failed: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get shipping analytics
     */
    public function getAnalytics(Request $request)
    {
        $period = $request->input('period', '30d');
        $days = (int) filter_var($period, FILTER_SANITIZE_NUMBER_INT);

        $analytics = [
            'zone_performance' => $this->getZonePerformance($days),
            'average_shipping_cost' => $this->getAverageShippingCost($days),
            'delivery_performance' => $this->getDeliveryPerformance($days),
            'cod_vs_prepaid' => $this->getCodVsPrepaidStats($days),
            'popular_zones' => $this->getPopularZones($days),
        ];

        return response()->json([
            'success' => true,
            'analytics' => $analytics,
            'period' => $period
        ]);
    }

    protected function getZonePerformance($days)
    {
        // This would query orders to get zone performance
        // For now, return sample data
        return [
            'A' => ['orders' => 150, 'revenue' => 25000, 'avg_delivery_days' => 1.2],
            'B' => ['orders' => 120, 'revenue' => 18000, 'avg_delivery_days' => 2.1],
            'C' => ['orders' => 90, 'revenue' => 15000, 'avg_delivery_days' => 3.2],
            'D' => ['orders' => 200, 'revenue' => 35000, 'avg_delivery_days' => 4.8],
            'E' => ['orders' => 40, 'revenue' => 8000, 'avg_delivery_days' => 7.5],
        ];
    }

    protected function getAverageShippingCost($days)
    {
        return [
            'overall_average' => 75.50,
            'by_zone' => [
                'A' => 35.00,
                'B' => 55.00,
                'C' => 75.00,
                'D' => 85.00,
                'E' => 125.00,
            ]
        ];
    }

    protected function getDeliveryPerformance($days)
    {
        return [
            'on_time_delivery_rate' => 92.5,
            'average_delivery_days' => 3.2,
            'fastest_zone' => 'A',
            'slowest_zone' => 'E',
        ];
    }

    protected function getCodVsPrepaidStats($days)
    {
        return [
            'cod_orders' => 60,
            'prepaid_orders' => 40,
            'cod_success_rate' => 85.5,
            'cod_average_value' => 1250.00,
            'prepaid_average_value' => 1850.00,
        ];
    }

    protected function getPopularZones($days)
    {
        return PincodeZone::selectRaw('zone, COUNT(*) as usage_count')
            ->groupBy('zone')
            ->orderBy('usage_count', 'desc')
            ->get();
    }

    /**
     * Get default pickup pincode from configured warehouse
     */
    protected function getDefaultPickupPincode(): string
    {
        $defaultWarehouse = InventoryLocation::where('is_default', true)
            ->where('is_active', true)
            ->first();

        return $defaultWarehouse ? $defaultWarehouse->postal_code : '110001'; // Fallback to Delhi
    }

    /**
     * Get all warehouses
     */
    public function getWarehouses(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');

        $query = InventoryLocation::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        $warehouses = $query->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'warehouses' => $warehouses
        ]);
    }

    /**
     * Store new warehouse
     */
    public function storeWarehouse(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:inventory_locations,code',
            'type' => 'required|string|in:warehouse,store,supplier',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|size:6',
            'country' => 'required|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // If this is being set as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            InventoryLocation::where('is_default', true)->update(['is_default' => false]);
        }

        $warehouse = InventoryLocation::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse created successfully',
            'warehouse' => $warehouse
        ], 201);
    }

    /**
     * Update warehouse
     */
    public function updateWarehouse(Request $request, $id)
    {
        $warehouse = InventoryLocation::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:inventory_locations,code,' . $id,
            'type' => 'required|string|in:warehouse,store,supplier',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|size:6',
            'country' => 'required|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // If this is being set as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            InventoryLocation::where('is_default', true)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $warehouse->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse updated successfully',
            'warehouse' => $warehouse->fresh()
        ]);
    }

    /**
     * Delete warehouse
     */
    public function deleteWarehouse($id)
    {
        $warehouse = InventoryLocation::findOrFail($id);

        if ($warehouse->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete default warehouse. Please set another warehouse as default first.'
            ], 422);
        }

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse deleted successfully'
        ]);
    }

    /**
     * Set warehouse as default
     */
    public function setDefaultWarehouse($id)
    {
        $warehouse = InventoryLocation::findOrFail($id);

        // Unset all other defaults
        InventoryLocation::where('is_default', true)->update(['is_default' => false]);

        // Set this one as default
        $warehouse->update(['is_default' => true, 'is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Default warehouse updated successfully',
            'warehouse' => $warehouse->fresh()
        ]);
    }

    /**
     * Get free shipping thresholds for all zones
     */
    public function getFreeShippingThresholds()
    {
        $zones = ['A', 'B', 'C', 'D', 'E'];
        $thresholds = [];

        foreach ($zones as $zone) {
            // Get the most recent config for each zone
            $config = \App\Models\ShippingZone::where('zone', $zone)
                ->orderBy('id', 'desc')
                ->first();

            $thresholds[] = [
                'zone' => $zone,
                'zone_name' => $this->getZoneName($zone),
                'threshold' => $config ? $config->free_shipping_threshold : $this->getDefaultThreshold($zone),
                'enabled' => $config ? (bool) $config->free_shipping_enabled : false,
                'has_custom_value' => $config && $config->free_shipping_threshold > 0,
            ];
        }

        return response()->json([
            'success' => true,
            'thresholds' => $thresholds
        ]);
    }

    /**
     * Update free shipping threshold for a zone
     */
    public function updateFreeShippingThreshold(Request $request)
    {
        $validated = $request->validate([
            'zone' => 'required|in:A,B,C,D,E',
            'threshold' => 'nullable|numeric|min:0|max:99999',
            'enabled' => 'nullable|boolean',
        ]);

        try {
            // Prepare update data
            $updateData = [];
            if (isset($validated['threshold'])) {
                $updateData['free_shipping_threshold'] = $validated['threshold'];
            }
            if (isset($validated['enabled'])) {
                $updateData['free_shipping_enabled'] = $validated['enabled'];
            }

            if (empty($updateData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data provided to update.'
                ], 400);
            }

            // Update all shipping zones with this zone code
            $updated = \App\Models\ShippingZone::where('zone', $validated['zone'])
                ->update($updateData);

            if ($updated === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No shipping zones found for this zone code. Please configure zone rates first.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Free shipping configuration updated successfully',
                'updated_count' => $updated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get zone name helper
     */
    private function getZoneName($zone)
    {
        $names = [
            'A' => 'Local (Within City)',
            'B' => 'Regional (Within State)',
            'C' => 'National (Metro to Metro)',
            'D' => 'National (Rest of India)',
            'E' => 'Special (Northeast/Islands)'
        ];

        return $names[$zone] ?? 'Unknown';
    }

    /**
     * Get default threshold helper
     */
    private function getDefaultThreshold($zone)
    {
        $defaults = [
            'A' => 499,
            'B' => 699,
            'C' => 999,
            'D' => 1499,
            'E' => 2499
        ];

        return $defaults[$zone] ?? 1499;
    }
}