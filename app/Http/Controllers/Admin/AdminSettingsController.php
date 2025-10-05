<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class AdminSettingsController extends Controller
{
    /**
     * Get payment flow settings
     */
    public function getPaymentFlowSettings()
    {
        try {
            $flowType = AdminSetting::get('payment_flow_type', 'two_tier');
            $defaultType = AdminSetting::get('payment_default_type', 'none');

            return response()->json([
                'success' => true,
                'data' => [
                    'flow_type' => $flowType,
                    'default_type' => $defaultType
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment flow settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment flow settings
     */
    public function updatePaymentFlowSettings(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'flow_type' => 'sometimes|in:two_tier,single_list,cod_first',
                'default_type' => 'sometimes|in:none,online,cod'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updated = [];

            if ($request->has('flow_type')) {
                AdminSetting::set('payment_flow_type', $request->flow_type);
                $updated['flow_type'] = $request->flow_type;
            }

            if ($request->has('default_type')) {
                AdminSetting::set('payment_default_type', $request->default_type);
                $updated['default_type'] = $request->default_type;
            }

            // Clear cache
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Payment flow settings updated successfully',
                'data' => $updated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment flow settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
