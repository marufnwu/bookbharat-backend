<?php

namespace App\Services;

use App\Models\TaxRate;
use App\Models\TaxCalculation;
use App\Models\Order;
use App\Models\Address;

class TaxCalculationService
{
    public function calculateTaxForOrder(Order $order, Address $billingAddress, Address $shippingAddress = null)
    {
        $shippingAddress = $shippingAddress ?? $billingAddress;
        
        // Get applicable tax rates for the shipping location
        $taxRates = $this->getApplicableTaxRates($shippingAddress, $order);
        
        $totalTax = 0;
        $taxBreakdown = [];
        
        foreach ($taxRates as $taxRate) {
            $calculation = $this->calculateIndividualTax($order, $taxRate);
            
            if ($calculation['tax_amount'] > 0) {
                // Store tax calculation
                TaxCalculation::create([
                    'order_id' => $order->id,
                    'tax_rate_id' => $taxRate->id,
                    'tax_type' => $taxRate->tax_type,
                    'tax_name' => $taxRate->name,
                    'taxable_amount' => $calculation['taxable_amount'],
                    'tax_rate' => $taxRate->rate,
                    'tax_amount' => $calculation['tax_amount'],
                    'calculation_method' => 'percentage',
                    'breakdown' => $calculation['breakdown'],
                    'jurisdiction' => $taxRate->jurisdiction,
                    'region_code' => $taxRate->region_code,
                ]);
                
                $totalTax += $calculation['tax_amount'];
                $taxBreakdown[] = $calculation;
            }
        }
        
        return [
            'total_tax' => $totalTax,
            'tax_breakdown' => $taxBreakdown,
            'tax_inclusive' => $this->hasTaxInclusivePricing($taxRates),
        ];
    }
    
    protected function getApplicableTaxRates(Address $address, Order $order)
    {
        $country = $address->country;
        $state = $address->state;
        
        // Build region codes for lookup
        $regionCodes = [
            $country, // IN
            "{$country}-{$state}", // IN-MH
        ];
        
        return TaxRate::whereIn('region_code', $regionCodes)
            ->where('is_active', true)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', now()->toDateString());
            })
            ->orderBy('priority', 'desc')
            ->get()
            ->filter(function ($taxRate) use ($order) {
                return $this->isTaxRateApplicable($taxRate, $order);
            });
    }
    
    protected function isTaxRateApplicable(TaxRate $taxRate, Order $order)
    {
        // Check category applicability
        if ($taxRate->applicable_categories) {
            $orderCategories = $order->items->pluck('product.category_id')->unique();
            $applicableCategories = $taxRate->applicable_categories;
            
            if (!$orderCategories->intersect($applicableCategories)->count()) {
                return false;
            }
        }
        
        // Check minimum amount
        if ($taxRate->min_amount && $order->subtotal < $taxRate->min_amount) {
            return false;
        }
        
        // Check maximum amount
        if ($taxRate->max_amount && $order->subtotal > $taxRate->max_amount) {
            return false;
        }
        
        // Check additional conditions
        if ($taxRate->conditions) {
            return $this->evaluateTaxConditions($taxRate->conditions, $order);
        }
        
        return true;
    }
    
    protected function calculateIndividualTax(Order $order, TaxRate $taxRate)
    {
        $taxableAmount = $this->calculateTaxableAmount($order, $taxRate);
        $taxAmount = 0;
        $breakdown = [];
        
        if ($taxRate->tax_type === 'gst' && $taxRate->region_code === 'IN') {
            // Indian GST calculation (CGST + SGST or IGST)
            $gstCalculation = $this->calculateIndianGST($taxableAmount, $taxRate, $order);
            $taxAmount = $gstCalculation['total_tax'];
            $breakdown = $gstCalculation['breakdown'];
            
        } elseif ($taxRate->tax_type === 'vat') {
            // VAT calculation
            $taxAmount = $taxableAmount * $taxRate->rate;
            $breakdown = [
                'type' => 'VAT',
                'rate' => $taxRate->rate * 100 . '%',
                'amount' => $taxAmount,
            ];
            
        } else {
            // Standard percentage tax
            $taxAmount = $taxableAmount * $taxRate->rate;
            $breakdown = [
                'type' => $taxRate->tax_type,
                'rate' => $taxRate->rate * 100 . '%',
                'amount' => $taxAmount,
            ];
        }
        
        return [
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxAmount,
            'breakdown' => $breakdown,
        ];
    }
    
    protected function calculateIndianGST($taxableAmount, TaxRate $taxRate, Order $order)
    {
        $gstRate = $taxRate->rate; // Total GST rate (e.g., 0.18 for 18%)
        $totalGst = $taxableAmount * $gstRate;
        
        // Check if it's interstate or intrastate
        $isInterstate = $this->isInterstateTransaction($order);
        
        if ($isInterstate) {
            // IGST (Integrated GST) = Full rate
            $breakdown = [
                'IGST' => [
                    'rate' => $gstRate * 100 . '%',
                    'amount' => $totalGst,
                ]
            ];
        } else {
            // CGST + SGST (Central + State GST) = Half rate each
            $cgstRate = $gstRate / 2;
            $sgstRate = $gstRate / 2;
            $cgstAmount = $taxableAmount * $cgstRate;
            $sgstAmount = $taxableAmount * $sgstRate;
            
            $breakdown = [
                'CGST' => [
                    'rate' => $cgstRate * 100 . '%',
                    'amount' => $cgstAmount,
                ],
                'SGST' => [
                    'rate' => $sgstRate * 100 . '%',
                    'amount' => $sgstAmount,
                ]
            ];
        }
        
        return [
            'total_tax' => $totalGst,
            'breakdown' => $breakdown,
        ];
    }
    
    protected function calculateTaxableAmount(Order $order, TaxRate $taxRate)
    {
        $taxableAmount = 0;
        
        foreach ($order->items as $item) {
            $itemAmount = $item->unit_price * $item->quantity;
            
            // Check if this item's category is taxable under this rate
            if ($taxRate->applicable_categories) {
                if (!in_array($item->product->category_id, $taxRate->applicable_categories)) {
                    continue;
                }
            }
            
            // Apply discounts if any
            $itemAmount -= $item->discount_amount ?? 0;
            
            $taxableAmount += $itemAmount;
        }
        
        // Include shipping in taxable amount if applicable
        if ($this->isShippingTaxable($taxRate)) {
            $taxableAmount += $order->shipping_cost ?? 0;
        }
        
        return max(0, $taxableAmount);
    }
    
    protected function isInterstateTransaction(Order $order)
    {
        // Get business address (seller) and shipping address (buyer)
        $businessState = config('business.state', 'Maharashtra'); // Business state
        $shippingState = $order->shippingAddress->state ?? $order->billingAddress->state;
        
        return $businessState !== $shippingState;
    }
    
    protected function isShippingTaxable(TaxRate $taxRate)
    {
        // Check if shipping is included in taxable amount for this tax type
        $conditions = $taxRate->conditions ?? [];
        return $conditions['include_shipping'] ?? false;
    }
    
    protected function hasTaxInclusivePricing($taxRates)
    {
        return $taxRates->where('is_inclusive', true)->count() > 0;
    }
    
    protected function evaluateTaxConditions(array $conditions, Order $order)
    {
        foreach ($conditions as $condition => $value) {
            switch ($condition) {
                case 'customer_type':
                    if ($order->user && $order->user->customer_type !== $value) {
                        return false;
                    }
                    break;
                    
                case 'order_type':
                    if ($order->order_type !== $value) {
                        return false;
                    }
                    break;
                    
                case 'exclude_categories':
                    $orderCategories = $order->items->pluck('product.category_id');
                    if ($orderCategories->intersect($value)->count() > 0) {
                        return false;
                    }
                    break;
            }
        }
        
        return true;
    }
    
    public function getTaxSummaryForOrder(Order $order)
    {
        $calculations = TaxCalculation::where('order_id', $order->id)->get();
        
        $summary = [
            'total_tax' => $calculations->sum('tax_amount'),
            'tax_types' => [],
            'breakdown' => [],
        ];
        
        foreach ($calculations->groupBy('tax_type') as $taxType => $typeCalculations) {
            $summary['tax_types'][$taxType] = $typeCalculations->sum('tax_amount');
            
            foreach ($typeCalculations as $calc) {
                $summary['breakdown'][] = [
                    'name' => $calc->tax_name,
                    'rate' => $calc->tax_rate * 100 . '%',
                    'taxable_amount' => $calc->taxable_amount,
                    'tax_amount' => $calc->tax_amount,
                    'jurisdiction' => $calc->jurisdiction,
                ];
            }
        }
        
        return $summary;
    }
    
    public function calculateQuickTaxEstimate($subtotal, $country, $state = null, $categoryIds = [])
    {
        // Quick tax estimation for cart/checkout preview
        $regionCode = $state ? "{$country}-{$state}" : $country;
        
        $taxRate = TaxRate::where('region_code', $regionCode)
            ->where('is_active', true)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', now()->toDateString());
            })
            ->orderBy('priority', 'desc')
            ->first();
            
        if (!$taxRate) {
            // Fallback to country-level tax
            $taxRate = TaxRate::where('region_code', $country)
                ->where('is_active', true)
                ->orderBy('priority', 'desc')
                ->first();
        }
        
        if (!$taxRate) {
            return ['tax_amount' => 0, 'tax_rate' => 0];
        }
        
        $taxAmount = $subtotal * $taxRate->rate;
        
        return [
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate->rate * 100,
            'tax_name' => $taxRate->name,
        ];
    }
}