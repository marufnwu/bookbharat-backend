<?php

namespace App\Services;

use App\Models\OrderCharge;
use App\Models\PaymentMethod;

class ChargeCalculationService
{
    /**
     * Calculate all applicable charges for an order
     */
    public function calculateCharges($orderContext)
    {
        $paymentMethod = $orderContext['payment_method'] ?? null;
        $orderValue = $orderContext['order_value'] ?? 0;
        $discountedValue = $orderContext['discounted_value'] ?? $orderValue;

        $charges = [];
        $totalCharges = 0;
        $advancePaymentConfig = null;

        // Get applicable order charges from order_charges table
        $orderCharges = OrderCharge::getApplicableCharges($paymentMethod, $orderContext);

        foreach ($orderCharges as $charge) {
            $baseValue = $charge->apply_after_discount ? $discountedValue : $orderValue;
            $chargeAmount = $charge->calculateCharge($baseValue);

            if ($chargeAmount > 0) {
                $charges[] = [
                    'code' => $charge->code,
                    'name' => $charge->name,
                    'display_label' => $charge->display_label,
                    'amount' => round($chargeAmount, 2),
                    'is_taxable' => $charge->is_taxable,
                    'type' => $charge->type,
                    'source' => 'order_charge'
                ];

                $totalCharges += $chargeAmount;
            }

            // Check for advance payment configuration (COD charges)
            if ($paymentMethod === 'cod' && $charge->apply_to === 'cod_only') {
                $config = $charge->getAdvancePaymentConfig();
                if ($config && !empty($config)) {
                    $advancePaymentConfig = $config;
                }
            }
        }

        // Get payment gateway specific charges
        if ($paymentMethod) {
            $gatewayCharge = $this->calculatePaymentGatewayCharge($paymentMethod, $discountedValue);

            if ($gatewayCharge) {
                $charges[] = $gatewayCharge;
                $totalCharges += $gatewayCharge['amount'];
            }

            // Check for advance payment from PaymentMethod configuration (especially for COD)
            if (!$advancePaymentConfig) {
                $advancePaymentConfig = $this->getAdvancePaymentFromPaymentMethod($paymentMethod, $discountedValue);
            }
        }

        $result = [
            'charges' => $charges,
            'total_charges' => round($totalCharges, 2),
            'taxable_charges' => round($this->getTaxableCharges($charges), 2),
            'non_taxable_charges' => round($this->getNonTaxableCharges($charges), 2),
        ];

        // Add advance payment info if available
        if ($advancePaymentConfig) {
            $result['advance_payment'] = $advancePaymentConfig;
        }

        return $result;
    }

    /**
     * Calculate payment gateway specific charges
     */
    protected function calculatePaymentGatewayCharge($paymentMethod, $orderValue)
    {
        $paymentConfig = PaymentMethod::where('payment_method', $paymentMethod)
            ->where('is_enabled', true)
            ->first();

        if (!$paymentConfig || !isset($paymentConfig->configuration['service_charges'])) {
            return null;
        }

        $serviceCharges = $paymentConfig->configuration['service_charges'];

        if (!($serviceCharges['enabled'] ?? false)) {
            return null;
        }

        $chargeAmount = 0;

        switch ($serviceCharges['type'] ?? 'fixed') {
            case 'fixed':
                $chargeAmount = $serviceCharges['value'] ?? 0;
                break;

            case 'percentage':
                $chargeAmount = ($orderValue * ($serviceCharges['value'] ?? 0)) / 100;
                
                // Apply min/max limits
                if (isset($serviceCharges['min_charge'])) {
                    $chargeAmount = max($chargeAmount, $serviceCharges['min_charge']);
                }
                if (isset($serviceCharges['max_charge'])) {
                    $chargeAmount = min($chargeAmount, $serviceCharges['max_charge']);
                }
                break;

            case 'tiered':
                $chargeAmount = $this->calculateTieredGatewayCharge($orderValue, $serviceCharges['tiers'] ?? []);
                break;
        }

        // Check if exempt above certain value
        if (isset($serviceCharges['conditions']['exempt_above']) && $orderValue >= $serviceCharges['conditions']['exempt_above']) {
            return null;
        }

        if ($chargeAmount > 0) {
            return [
                'code' => $paymentMethod . '_charge',
                'name' => $paymentConfig->display_name . ' Charge',
                'display_label' => $serviceCharges['description'] ?? 'Payment Gateway Charge',
                'amount' => round($chargeAmount, 2),
                'is_taxable' => $serviceCharges['is_taxable'] ?? false,
                'type' => $serviceCharges['type'] ?? 'fixed',
                'source' => 'payment_gateway'
            ];
        }

        return null;
    }

    /**
     * Calculate tiered gateway charge
     */
    protected function calculateTieredGatewayCharge($orderValue, $tiers)
    {
        foreach ($tiers as $tier) {
            $min = $tier['min'] ?? 0;
            $max = $tier['max'] ?? PHP_FLOAT_MAX;

            if ($orderValue >= $min && $orderValue <= $max) {
                $charge = $tier['charge'];
                
                if (is_string($charge) && str_ends_with($charge, '%')) {
                    $percentage = floatval(str_replace('%', '', $charge));
                    return ($orderValue * $percentage) / 100;
                }
                
                return floatval($charge);
            }
        }

        return 0;
    }

    /**
     * Get total taxable charges
     */
    protected function getTaxableCharges($charges)
    {
        return array_sum(array_column(array_filter($charges, function($charge) {
            return $charge['is_taxable'] ?? false;
        }), 'amount'));
    }

    /**
     * Get total non-taxable charges
     */
    protected function getNonTaxableCharges($charges)
    {
        return array_sum(array_column(array_filter($charges, function($charge) {
            return !($charge['is_taxable'] ?? false);
        }), 'amount'));
    }

    /**
     * Get advance payment configuration from PaymentMethod (for COD)
     */
    protected function getAdvancePaymentFromPaymentMethod($paymentMethod, $orderValue)
    {
        $paymentConfig = PaymentMethod::where('payment_method', $paymentMethod)
            ->where('is_enabled', true)
            ->first();

        if (!$paymentConfig || !isset($paymentConfig->configuration['advance_payment'])) {
            return null;
        }

        $advanceConfig = $paymentConfig->configuration['advance_payment'];

        // If advance payment is not required, return null
        if (!($advanceConfig['required'] ?? false)) {
            return null;
        }

        // Calculate advance amount
        $type = $advanceConfig['type'] ?? 'fixed';
        $value = $advanceConfig['value'] ?? 0;
        $amount = 0;

        switch ($type) {
            case 'percentage':
                $amount = round(($orderValue * $value) / 100, 2);
                break;
            case 'fixed':
                $amount = $value;
                break;
        }

        return [
            'required' => true,
            'type' => $type,
            'value' => $value,
            'amount' => $amount,
            'description' => $advanceConfig['description'] ?? 'Advance payment required',
            'payment_method' => $paymentMethod,
        ];
    }
}
