<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Pincode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /**
     * Display a listing of user's addresses.
     */
    public function index(Request $request)
    {
        try {
            $addresses = Address::where('user_id', auth()->id())
                ->when($request->type, function ($query) use ($request) {
                    return $query->where('type', $request->type);
                })
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $addresses
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve addresses'
            ], 500);
        }
    }

    /**
     * Store a newly created address.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:shipping,billing',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|min:10|max:20',
            'whatsapp_number' => 'required|string|min:10|max:20',
            'village_city_area' => 'required|string|max:255',
            'house_number' => 'sometimes|nullable|string|max:255',
            'landmark' => 'required|string|max:255',
            'pincode' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/', function ($attribute, $value, $fail) {
                if (!Pincode::isServiceable($value)) {
                    $fail('This pincode is not serviceable.');
                }
            }],
            'zila' => 'required|string|max:255',
            'state' => 'required|string|max:255', 
            'post_name' => 'required|string|max:255',
            'country' => 'sometimes|nullable|string|max:255',
            'is_default' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // If this is being set as default, unset other defaults of the same type
            if ($request->boolean('is_default')) {
                Address::where('user_id', auth()->id())
                    ->where('type', $request->type)
                    ->update(['is_default' => false]);
            }

            $address = Address::create([
                'user_id' => auth()->id(),
                'type' => $request->type,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'whatsapp_number' => $request->whatsapp_number,
                'village_city_area' => $request->village_city_area,
                'house_number' => $request->house_number,
                'landmark' => $request->landmark,
                'pincode' => $request->pincode,
                'zila' => $request->zila,
                'state' => $request->state,
                'post_name' => $request->post_name,
                'country' => $request->country ?? 'India',
                'is_default' => $request->boolean('is_default'),
                // Map new fields to old schema for backward compatibility
                'address_line_1' => $request->village_city_area ?? '', // Required field
                'address_line_2' => $request->house_number, // Optional
                'city' => $request->zila, // Map zila to city
                'postal_code' => $request->pincode, // Map pincode to postal_code
            ]);

            // If no default is set and this is the first address of this type, make it default
            if (!$request->boolean('is_default')) {
                $existingDefaults = Address::where('user_id', auth()->id())
                    ->where('type', $request->type)
                    ->where('is_default', true)
                    ->count();

                if ($existingDefaults === 0) {
                    $address->update(['is_default' => true]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Address created successfully',
                'data' => $address
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Address creation failed: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create address',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified address.
     */
    public function show($id)
    {
        try {
            $address = Address::where('user_id', auth()->id())
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $address
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Address not found'
            ], 404);
        }
    }

    /**
     * Update the specified address.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:shipping,billing',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'company' => 'sometimes|nullable|string|max:255',
            'address_line_1' => 'sometimes|string|max:255',
            'address_line_2' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|string|max:255',
            'state' => 'sometimes|string|max:255',
            'postal_code' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:2',
            'phone' => 'sometimes|nullable|string|max:20',
            'is_default' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $address = Address::where('user_id', auth()->id())
                ->findOrFail($id);

            DB::beginTransaction();

            // If this is being set as default, unset other defaults of the same type
            if ($request->boolean('is_default')) {
                $addressType = $request->type ?? $address->type;
                Address::where('user_id', auth()->id())
                    ->where('type', $addressType)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            $address->update($request->only([
                'type', 'first_name', 'last_name', 'company',
                'address_line_1', 'address_line_2', 'city', 'state',
                'postal_code', 'country', 'phone', 'is_default'
            ]));

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Address updated successfully',
                'data' => $address->fresh()
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update address'
            ], 500);
        }
    }

    /**
     * Remove the specified address.
     */
    public function destroy($id)
    {
        try {
            $address = Address::where('user_id', auth()->id())
                ->findOrFail($id);

            DB::beginTransaction();

            $wasDefault = $address->is_default;
            $type = $address->type;

            $address->delete();

            // If deleted address was default, set another address of same type as default
            if ($wasDefault) {
                $nextAddress = Address::where('user_id', auth()->id())
                    ->where('type', $type)
                    ->first();

                if ($nextAddress) {
                    $nextAddress->update(['is_default' => true]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Address deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete address'
            ], 500);
        }
    }

    /**
     * Set an address as default for its type.
     */
    public function setDefault($id)
    {
        try {
            $address = Address::where('user_id', auth()->id())
                ->findOrFail($id);

            DB::beginTransaction();

            // Unset other defaults of the same type
            Address::where('user_id', auth()->id())
                ->where('type', $address->type)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);

            // Set this address as default
            $address->update(['is_default' => true]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Address set as default successfully',
                'data' => $address->fresh()
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to set address as default'
            ], 500);
        }
    }

    /**
     * Get default addresses for the user.
     */
    public function getDefaults()
    {
        try {
            $defaults = Address::where('user_id', auth()->id())
                ->where('is_default', true)
                ->get()
                ->groupBy('type');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'shipping' => $defaults->get('shipping', collect())->first(),
                    'billing' => $defaults->get('billing', collect())->first()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve default addresses'
            ], 500);
        }
    }

    /**
     * Validate address format (can be used for address verification services)
     */
    public function validateAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'sometimes|nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Basic validation - in production, integrate with address verification service
            $isValid = true;
            $suggestions = [];
            
            // Basic postal code validation for India (6 digits)
            if ($request->country === 'IN' && !preg_match('/^\d{6}$/', $request->postal_code)) {
                $isValid = false;
                $suggestions[] = 'Postal code should be 6 digits for India';
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'is_valid' => $isValid,
                    'suggestions' => $suggestions,
                    'formatted_address' => $this->formatAddress($request->all())
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Address validation failed'
            ], 500);
        }
    }

    /**
     * Format address into a standardized string
     */
    private function formatAddress($addressData)
    {
        $parts = [
            $addressData['address_line_1'],
            $addressData['address_line_2'] ?? null,
            $addressData['city'],
            $addressData['state'],
            $addressData['postal_code']
        ];

        return implode(', ', array_filter($parts));
    }
}