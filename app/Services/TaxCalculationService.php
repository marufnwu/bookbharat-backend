<?php

namespace App\Services;

use App\Models\TaxConfiguration;

class TaxCalculationService
{
    /**
     * Calculate all applicable taxes
     */
    public function calculateTaxes($orderContext, $chargesBreakdown = [])
    {
        $taxes = [];
        $totalTax = 0;

        // Get applicable taxes
        $taxConfigs = TaxConfiguration::getApplicableTaxes($orderContext);

        foreach ($taxConfigs as $taxConfig) {
            $taxableAmount = $this->getTaxableAmount($orderContext, $chargesBreakdown, $taxConfig);
            
            if ($taxableAmount > 0) {
                $taxAmount = $taxConfig->calculateTax($taxableAmount);

                $taxes[] = [
                    'code' => $taxConfig->code,
                    'name' => $taxConfig->name,
                    'display_label' => $taxConfig->display_label,
                    'rate' => $taxConfig->rate,
                    'amount' => round($taxAmount, 2),
                    'taxable_amount' => round($taxableAmount, 2),
                    'is_inclusive' => $taxConfig->is_inclusive,
                    'type' => $taxConfig->tax_type,
                ];

                $totalTax += $taxAmount;
            }
        }

        return [
            'taxes' => $taxes,
            'total_tax' => round($totalTax, 2),
        ];
    }

    /**
     * Get the taxable amount based on tax configuration
     */
    protected function getTaxableAmount($orderContext, $chargesBreakdown, $taxConfig)
    {
        $subtotal = $orderContext['discounted_value'] ?? $orderContext['order_value'] ?? 0;
        $shippingCost = $orderContext['shipping_cost'] ?? 0;
        $taxableCharges = $chargesBreakdown['taxable_charges'] ?? 0;
        $nonTaxableCharges = $chargesBreakdown['non_taxable_charges'] ?? 0;

        switch ($taxConfig->apply_on) {
            case 'subtotal':
                return $subtotal;

            case 'subtotal_with_charges':
                return $subtotal + $taxableCharges + $nonTaxableCharges;

            case 'subtotal_with_shipping':
                return $subtotal + $shippingCost;

            case 'subtotal_with_all':
                return $subtotal + $shippingCost + $taxableCharges + $nonTaxableCharges;

            default:
                return $subtotal;
        }
    }

    /**
     * Calculate tax breakdown for specific amount
     */
    public function getTaxBreakdown($amount, $taxRate)
    {
        $taxAmount = ($amount * $taxRate) / 100;
        
        return [
            'base_amount' => $amount,
            'tax_rate' => $taxRate,
            'tax_amount' => round($taxAmount, 2),
            'total_with_tax' => round($amount + $taxAmount, 2),
        ];
    }

    /**
     * Calculate reverse tax (extract tax from inclusive price)
     */
    public function extractTaxFromInclusive($inclusiveAmount, $taxRate)
    {
        $baseAmount = $inclusiveAmount / (1 + ($taxRate / 100));
        $taxAmount = $inclusiveAmount - $baseAmount;

        return [
            'inclusive_amount' => $inclusiveAmount,
            'base_amount' => round($baseAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'tax_rate' => $taxRate,
        ];
    }
}
