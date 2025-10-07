<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\PaymentConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class AdminSettingsController extends Controller
{
    /**
     * Get payment flow settings (UI behavior only)
     * Note: Payment method visibility is controlled via PaymentConfiguration.is_enabled
     */
    public function getPaymentFlowSettings()
    {
        try {
            $flowType = AdminSetting::get('payment_flow_type', 'two_tier');
            $defaultType = AdminSetting::get('payment_default_type', 'none');

            // Check if any COD payment methods are enabled
            $codEnabled = PaymentConfiguration::whereIn('payment_method', ['cod', 'cod_with_advance', 'cod_percentage_advance'])
                ->where('is_enabled', true)
                ->exists();

            // Check if any online payment methods are enabled
            $onlinePaymentEnabled = PaymentConfiguration::whereNotIn('payment_method', ['cod', 'cod_with_advance', 'cod_percentage_advance'])
                ->where('is_enabled', true)
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'flow_type' => $flowType,
                    'default_type' => $defaultType,
                    'cod_enabled' => $codEnabled,
                    'online_payment_enabled' => $onlinePaymentEnabled,
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
     * Update payment flow settings (UI behavior and COD visibility)
     */
    public function updatePaymentFlowSettings(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'flow_type' => 'sometimes|in:two_tier,single_list,cod_first',
                'default_type' => 'sometimes|in:none,online,cod',
                'cod_enabled' => 'sometimes|in:0,1,true,false',
                'online_payment_enabled' => 'sometimes|in:0,1,true,false',
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

            // Handle COD enable/disable for all COD payment methods
            if ($request->has('cod_enabled')) {
                $codEnabled = filter_var($request->cod_enabled, FILTER_VALIDATE_BOOLEAN);

                // Update all COD payment configurations
                PaymentConfiguration::whereIn('payment_method', ['cod', 'cod_with_advance', 'cod_percentage_advance'])
                    ->update(['is_enabled' => $codEnabled]);

                $updated['cod_enabled'] = $codEnabled;
            }

            // Handle online payment enable/disable (Razorpay, Cashfree, etc.)
            if ($request->has('online_payment_enabled')) {
                $onlineEnabled = filter_var($request->online_payment_enabled, FILTER_VALIDATE_BOOLEAN);

                // Update all online payment configurations (excluding COD methods)
                PaymentConfiguration::whereNotIn('payment_method', ['cod', 'cod_with_advance', 'cod_percentage_advance'])
                    ->update(['is_enabled' => $onlineEnabled]);

                $updated['online_payment_enabled'] = $onlineEnabled;
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
