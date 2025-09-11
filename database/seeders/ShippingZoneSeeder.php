<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ShippingZone;
use App\Models\ShippingWeightSlab;

class ShippingZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zones = ['A', 'B', 'C', 'D', 'E'];
        $courierServices = ['Standard Courier', 'Express Courier', 'Premium Courier'];
        
        // Zone-specific rates (FWD rates in INR)
        $zoneRates = [
            'A' => ['fwd_rate' => 30, 'rto_rate' => 25, 'aw_rate' => 20, 'cod_charges' => 15, 'cod_percentage' => 2.0],
            'B' => ['fwd_rate' => 50, 'rto_rate' => 40, 'aw_rate' => 30, 'cod_charges' => 20, 'cod_percentage' => 2.5],
            'C' => ['fwd_rate' => 70, 'rto_rate' => 55, 'aw_rate' => 40, 'cod_charges' => 25, 'cod_percentage' => 2.5],
            'D' => ['fwd_rate' => 80, 'rto_rate' => 65, 'aw_rate' => 50, 'cod_charges' => 30, 'cod_percentage' => 3.0],
            'E' => ['fwd_rate' => 120, 'rto_rate' => 95, 'aw_rate' => 70, 'cod_charges' => 40, 'cod_percentage' => 3.5],
        ];

        $weightSlabs = ShippingWeightSlab::all();

        foreach ($weightSlabs as $slab) {
            foreach ($zones as $zone) {
                $baseRates = $zoneRates[$zone];
                
                // Adjust rates based on courier service type
                $multiplier = 1.0;
                if ($slab->courier_name === 'Express Courier') {
                    $multiplier = 1.3; // 30% premium
                } elseif ($slab->courier_name === 'Premium Courier') {
                    $multiplier = 1.6; // 60% premium
                }

                // Adjust rates based on weight slab
                $weightMultiplier = 1.0;
                if ($slab->base_weight >= 5.0) {
                    $weightMultiplier = 1.2; // 20% more for heavier items
                } elseif ($slab->base_weight >= 10.0) {
                    $weightMultiplier = 1.5; // 50% more for very heavy items
                }

                $finalMultiplier = $multiplier * $weightMultiplier;

                ShippingZone::updateOrCreate(
                    [
                        'shipping_weight_slab_id' => $slab->id,
                        'zone' => $zone
                    ],
                    [
                        'fwd_rate' => round($baseRates['fwd_rate'] * $finalMultiplier, 2),
                        'rto_rate' => round($baseRates['rto_rate'] * $finalMultiplier, 2),
                        'aw_rate' => round($baseRates['aw_rate'] * $finalMultiplier, 2),
                        'cod_charges' => round($baseRates['cod_charges'] * $finalMultiplier, 2),
                        'cod_percentage' => $baseRates['cod_percentage'],
                    ]
                );
            }
        }
    }
}
