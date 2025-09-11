<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ShippingWeightSlab;

class ShippingWeightSlabSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $weightSlabs = [
            ['courier_name' => 'Standard Courier', 'base_weight' => 0.5],
            ['courier_name' => 'Standard Courier', 'base_weight' => 1.0],
            ['courier_name' => 'Standard Courier', 'base_weight' => 2.0],
            ['courier_name' => 'Standard Courier', 'base_weight' => 5.0],
            ['courier_name' => 'Standard Courier', 'base_weight' => 10.0],
            ['courier_name' => 'Express Courier', 'base_weight' => 0.5],
            ['courier_name' => 'Express Courier', 'base_weight' => 1.0],
            ['courier_name' => 'Express Courier', 'base_weight' => 2.0],
            ['courier_name' => 'Express Courier', 'base_weight' => 5.0],
            ['courier_name' => 'Express Courier', 'base_weight' => 10.0],
            ['courier_name' => 'Premium Courier', 'base_weight' => 0.5],
            ['courier_name' => 'Premium Courier', 'base_weight' => 1.0],
            ['courier_name' => 'Premium Courier', 'base_weight' => 2.0],
            ['courier_name' => 'Premium Courier', 'base_weight' => 5.0],
            ['courier_name' => 'Premium Courier', 'base_weight' => 10.0],
        ];

        foreach ($weightSlabs as $slab) {
            ShippingWeightSlab::updateOrCreate(
                [
                    'courier_name' => $slab['courier_name'],
                    'base_weight' => $slab['base_weight']
                ],
                $slab
            );
        }
    }
}
