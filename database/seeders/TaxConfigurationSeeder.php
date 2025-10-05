<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaxConfiguration;

class TaxConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $taxes = [
            [
                'name' => 'GST (Goods & Services Tax)',
                'code' => 'gst',
                'tax_type' => 'gst',
                'rate' => 18.00,
                'is_enabled' => true,
                'apply_on' => 'subtotal',
                'conditions' => null,
                'is_inclusive' => false,
                'priority' => 1,
                'description' => 'Goods and Services Tax - 18%',
                'display_label' => 'GST (18%)',
            ],
            [
                'name' => 'IGST (Interstate)',
                'code' => 'igst',
                'tax_type' => 'igst',
                'rate' => 18.00,
                'is_enabled' => false, // Enable based on state logic
                'apply_on' => 'subtotal',
                'conditions' => [
                    'state_based' => true,
                    'states' => [], // Configure for interstate orders
                ],
                'is_inclusive' => false,
                'priority' => 2,
                'description' => 'Integrated GST for interstate orders',
                'display_label' => 'IGST (18%)',
            ],
            [
                'name' => 'CGST + SGST',
                'code' => 'cgst_sgst',
                'tax_type' => 'cgst_sgst',
                'rate' => 18.00,
                'is_enabled' => false, // Enable based on state logic
                'apply_on' => 'subtotal',
                'conditions' => [
                    'state_based' => true,
                    'states' => ['DL', 'MH', 'KA'], // Same state orders
                ],
                'is_inclusive' => false,
                'priority' => 3,
                'description' => 'Combined CGST (9%) + SGST (9%)',
                'display_label' => 'CGST + SGST (18%)',
            ],
        ];

        foreach ($taxes as $tax) {
            TaxConfiguration::updateOrCreate(
                ['code' => $tax['code']],
                $tax
            );
        }
    }
}
