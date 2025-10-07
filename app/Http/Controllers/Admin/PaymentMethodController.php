<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin Payment Method Controller
 *
 * Manages predefined payment gateways:
 * - List all gateways
 * - Update credentials (with validation based on gateway schema)
 * - Toggle is_enabled
 * - Set default/fallback
 * - CANNOT delete system gateways (only edit)
 */
class PaymentMethodController extends Controller
{
    /**
     * List all payment methods
     * GET /api/v1/admin/payment-methods
     */
    public function index()
    {
        $methods = PaymentMethod::orderBy('priority', 'desc')->get();

        $methods->transform(function ($method) {
            $method->masked_credentials = $method->getMaskedCredentials();
            $method->credentials_valid = $method->hasValidCredentials();
            $method->can_delete = $method->canBeDeleted();
            return $method;
        });

        return response()->json([
            'success' => true,
            'payment_methods' => $methods,
        ]);
    }

    /**
     * Get single payment method
     * GET /api/v1/admin/payment-methods/{id}
     */
    public function show($id)
    {
        $method = PaymentMethod::findOrFail($id);

        return response()->json([
            'success' => true,
            'payment_method' => $method,
            'masked_credentials' => $method->getMaskedCredentials(),
            'credential_schema' => $method->getCredentialSchema(),
            'credentials_valid' => $method->hasValidCredentials(),
            'can_delete' => $method->canBeDeleted(),
        ]);
    }

    /**
     * Update payment method credentials and configuration
     * PUT /api/v1/admin/payment-methods/{id}
     */
    public function update(Request $request, $id)
    {
        $method = PaymentMethod::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'credentials' => 'nullable|array',
            'configuration' => 'nullable|array',
            'restrictions' => 'nullable|array',
            'is_production' => 'nullable|boolean',
            'display_name' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate credentials against schema
        if ($request->has('credentials')) {
            $validation = $method->validateCredentials($request->credentials);

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'errors' => $validation['errors']
                ], 422);
            }
        }

        // Update fields
        $updateData = $request->only(['credentials', 'configuration', 'restrictions', 'is_production', 'display_name', 'description']);
        $method->update(array_filter($updateData, fn($value) => $value !== null));

        return response()->json([
            'success' => true,
            'message' => 'Payment method updated successfully',
            'payment_method' => $method,
            'credentials_valid' => $method->hasValidCredentials(),
        ]);
    }

    /**
     * Toggle is_enabled
     * POST /api/v1/admin/payment-methods/{id}/toggle
     */
    public function toggle($id)
    {
        $method = PaymentMethod::findOrFail($id);
        $method->is_enabled = !$method->is_enabled;
        $method->save();

        return response()->json([
            'success' => true,
            'message' => $method->is_enabled ? 'Payment method enabled' : 'Payment method disabled',
            'payment_method' => $method,
        ]);
    }

    /**
     * Set as default gateway
     * POST /api/v1/admin/payment-methods/{id}/set-default
     */
    public function setDefault($id)
    {
        $method = PaymentMethod::findOrFail($id);

        if ($method->isCod()) {
            return response()->json([
                'success' => false,
                'message' => 'COD cannot be set as default. Only online payment gateways can be default.',
            ], 400);
        }

        $method->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => 'Payment method set as default',
            'payment_method' => $method,
        ]);
    }

    /**
     * Set as fallback gateway
     * POST /api/v1/admin/payment-methods/{id}/set-fallback
     */
    public function setFallback($id)
    {
        $method = PaymentMethod::findOrFail($id);

        if ($method->isCod()) {
            return response()->json([
                'success' => false,
                'message' => 'COD cannot be set as fallback. Only online payment gateways can be fallback.',
            ], 400);
        }

        $method->setAsFallback();

        return response()->json([
            'success' => true,
            'message' => 'Payment method set as fallback',
            'payment_method' => $method,
        ]);
    }

    /**
     * Delete payment method (only if not system)
     * DELETE /api/v1/admin/payment-methods/{id}
     */
    public function destroy($id)
    {
        $method = PaymentMethod::findOrFail($id);

        if (!$method->canBeDeleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system payment method. System gateways are predefined and can only be edited.',
            ], 403);
        }

        $method->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully',
        ]);
    }

    /**
     * Get credential schemas
     * GET /api/v1/admin/payment-methods/schemas
     */
    public function getSchemas()
    {
        return response()->json([
            'success' => true,
            'schemas' => PaymentMethod::getCredentialSchemas(),
        ]);
    }

    /**
     * Get gateway status (default/fallback)
     * GET /api/v1/admin/payment-methods/gateway-status
     */
    public function getGatewayStatus()
    {
        $default = PaymentMethod::getDefault();
        $fallback = PaymentMethod::getFallback();

        return response()->json([
            'success' => true,
            'default' => $default ? [
                'id' => $default->id,
                'payment_method' => $default->payment_method,
                'display_name' => $default->display_name,
                'is_enabled' => $default->is_enabled,
                'credentials_valid' => $default->hasValidCredentials(),
            ] : null,
            'fallback' => $fallback ? [
                'id' => $fallback->id,
                'payment_method' => $fallback->payment_method,
                'display_name' => $fallback->display_name,
                'is_enabled' => $fallback->is_enabled,
                'credentials_valid' => $fallback->hasValidCredentials(),
            ] : null,
        ]);
    }
}
