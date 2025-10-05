<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrderCharge;

class OrderChargeSeeder extends Seeder
{
    public function run(): void
    {
        $charges = [
            [
                'name' => 'COD Service Charge',
                'code' => 'cod_service_charge',
                'type' => 'tiered',
                'amount' => 0,
                'percentage' => 0,
                'tiers' => [
                    ['min' => 0, 'max' => 500, 'charge' => 30],
                    ['min' => 501, 'max' => 2000, 'charge' => 50],
                    ['min' => 2001, 'max' => 999999, 'charge' => '2%'],
                ],
                'is_enabled' => true,
                'apply_to' => 'cod_only',
                'payment_methods' => null,
                'conditions' => [
                    'exempt_above_value' => 5000,
                ],
                'priority' => 1,
                'description' => 'Service charge for Cash on Delivery orders',
                'display_label' => 'COD Charges',
                'is_taxable' => false,
                'apply_after_discount' => true,
                'is_refundable' => false,
            ],
            [
                'name' => 'Handling Fee',
                'code' => 'handling_fee',
                'type' => 'fixed',
                'amount' => 25,
                'percentage' => 0,
                'tiers' => null,
                'is_enabled' => false, // Disabled by default
                'apply_to' => 'all',
                'payment_methods' => null,
                'conditions' => [
                    'min_order_value' => 0,
                    'max_order_value' => 1000,
                ],
                'priority' => 2,
                'description' => 'Small order handling fee',
                'display_label' => 'Handling Fee',
                'is_taxable' => true,
                'apply_after_discount' => true,
                'is_refundable' => true,
            ],
            [
                'name' => 'Gift Wrapping Charge',
                'code' => 'gift_wrapping',
                'type' => 'fixed',
                'amount' => 50,
                'percentage' => 0,
                'tiers' => null,
                'is_enabled' => false, // Disabled - for future use
                'apply_to' => 'conditional',
                'payment_methods' => null,
                'conditions' => [],
                'priority' => 3,
                'description' => 'Gift wrapping service charge',
                'display_label' => 'Gift Wrapping',
                'is_taxable' => true,
                'apply_after_discount' => false,
                'is_refundable' => true,
            ],
        ];

        foreach ($charges as $charge) {
            OrderCharge::updateOrCreate(
                ['code' => $charge['code']],
                $charge
            );
        }
    }
}
