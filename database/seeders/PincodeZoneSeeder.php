<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PincodeZone;

class PincodeZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pincodeMappings = [
            // Zone A - Major Metro Cities (samples)
            ['pincode' => '110001', 'zone' => 'A', 'city' => 'New Delhi', 'state' => 'Delhi', 'region' => 'North', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 1, 'zone_multiplier' => 1.0],
            ['pincode' => '400001', 'zone' => 'A', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'region' => 'West', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 1, 'zone_multiplier' => 1.0],
            ['pincode' => '560001', 'zone' => 'A', 'city' => 'Bangalore', 'state' => 'Karnataka', 'region' => 'South', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 1, 'zone_multiplier' => 1.0],
            ['pincode' => '600001', 'zone' => 'A', 'city' => 'Chennai', 'state' => 'Tamil Nadu', 'region' => 'South', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 1, 'zone_multiplier' => 1.0],
            ['pincode' => '700001', 'zone' => 'A', 'city' => 'Kolkata', 'state' => 'West Bengal', 'region' => 'East', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 1, 'zone_multiplier' => 1.0],
            ['pincode' => '500001', 'zone' => 'A', 'city' => 'Hyderabad', 'state' => 'Telangana', 'region' => 'South', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 1, 'zone_multiplier' => 1.0],
            ['pincode' => '411001', 'zone' => 'A', 'city' => 'Pune', 'state' => 'Maharashtra', 'region' => 'West', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 1, 'zone_multiplier' => 1.0],
            ['pincode' => '380001', 'zone' => 'A', 'city' => 'Ahmedabad', 'state' => 'Gujarat', 'region' => 'West', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 1, 'zone_multiplier' => 1.0],

            // Zone B - Same State Examples
            ['pincode' => '110020', 'zone' => 'B', 'city' => 'New Delhi', 'state' => 'Delhi', 'region' => 'North', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 2, 'zone_multiplier' => 1.0],
            ['pincode' => '400020', 'zone' => 'B', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'region' => 'West', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 2, 'zone_multiplier' => 1.0],
            ['pincode' => '121001', 'zone' => 'B', 'city' => 'Faridabad', 'state' => 'Haryana', 'region' => 'North', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 2, 'zone_multiplier' => 1.0],
            ['pincode' => '201001', 'zone' => 'B', 'city' => 'Ghaziabad', 'state' => 'Uttar Pradesh', 'region' => 'North', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 2, 'zone_multiplier' => 1.0],
            ['pincode' => '422001', 'zone' => 'B', 'city' => 'Nashik', 'state' => 'Maharashtra', 'region' => 'West', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 2, 'zone_multiplier' => 1.0],
            ['pincode' => '562001', 'zone' => 'B', 'city' => 'Tumkur', 'state' => 'Karnataka', 'region' => 'South', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 2, 'zone_multiplier' => 1.0],

            // Zone C - Metro to Metro
            ['pincode' => '302001', 'zone' => 'C', 'city' => 'Jaipur', 'state' => 'Rajasthan', 'region' => 'North', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 3, 'zone_multiplier' => 1.0],
            ['pincode' => '395001', 'zone' => 'C', 'city' => 'Surat', 'state' => 'Gujarat', 'region' => 'West', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 3, 'zone_multiplier' => 1.0],
            ['pincode' => '641001', 'zone' => 'C', 'city' => 'Coimbatore', 'state' => 'Tamil Nadu', 'region' => 'South', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 3, 'zone_multiplier' => 1.0],
            ['pincode' => '682001', 'zone' => 'C', 'city' => 'Kochi', 'state' => 'Kerala', 'region' => 'South', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 3, 'zone_multiplier' => 1.0],
            ['pincode' => '462001', 'zone' => 'C', 'city' => 'Bhopal', 'state' => 'Madhya Pradesh', 'region' => 'Central', 'is_metro' => true, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 3, 'zone_multiplier' => 1.0],

            // Zone D - Rest of India
            ['pincode' => '226001', 'zone' => 'D', 'city' => 'Lucknow', 'state' => 'Uttar Pradesh', 'region' => 'North', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 4, 'zone_multiplier' => 1.1],
            ['pincode' => '751001', 'zone' => 'D', 'city' => 'Bhubaneswar', 'state' => 'Odisha', 'region' => 'East', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 4, 'zone_multiplier' => 1.1],
            ['pincode' => '803001', 'zone' => 'D', 'city' => 'Patna', 'state' => 'Bihar', 'region' => 'East', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 5, 'zone_multiplier' => 1.1],
            ['pincode' => '533001', 'zone' => 'D', 'city' => 'Rajahmundry', 'state' => 'Andhra Pradesh', 'region' => 'South', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 4, 'zone_multiplier' => 1.1],
            ['pincode' => '364001', 'zone' => 'D', 'city' => 'Bhavnagar', 'state' => 'Gujarat', 'region' => 'West', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 4, 'zone_multiplier' => 1.1],
            ['pincode' => '530001', 'zone' => 'D', 'city' => 'Visakhapatnam', 'state' => 'Andhra Pradesh', 'region' => 'South', 'is_metro' => false, 'is_remote' => false, 'cod_available' => true, 'expected_delivery_days' => 4, 'zone_multiplier' => 1.1],

            // Zone E - Northeast & J&K
            ['pincode' => '781001', 'zone' => 'E', 'city' => 'Guwahati', 'state' => 'Assam', 'region' => 'Northeast', 'is_metro' => false, 'is_remote' => true, 'cod_available' => false, 'expected_delivery_days' => 7, 'zone_multiplier' => 1.5],
            ['pincode' => '793001', 'zone' => 'E', 'city' => 'Shillong', 'state' => 'Meghalaya', 'region' => 'Northeast', 'is_metro' => false, 'is_remote' => true, 'cod_available' => false, 'expected_delivery_days' => 8, 'zone_multiplier' => 1.6],
            ['pincode' => '797001', 'zone' => 'E', 'city' => 'Aizawl', 'state' => 'Mizoram', 'region' => 'Northeast', 'is_metro' => false, 'is_remote' => true, 'cod_available' => false, 'expected_delivery_days' => 9, 'zone_multiplier' => 1.7],
            ['pincode' => '798001', 'zone' => 'E', 'city' => 'Imphal', 'state' => 'Manipur', 'region' => 'Northeast', 'is_metro' => false, 'is_remote' => true, 'cod_available' => false, 'expected_delivery_days' => 8, 'zone_multiplier' => 1.6],
            ['pincode' => '799001', 'zone' => 'E', 'city' => 'Agartala', 'state' => 'Tripura', 'region' => 'Northeast', 'is_metro' => false, 'is_remote' => true, 'cod_available' => false, 'expected_delivery_days' => 8, 'zone_multiplier' => 1.6],
            ['pincode' => '790001', 'zone' => 'E', 'city' => 'Itanagar', 'state' => 'Arunachal Pradesh', 'region' => 'Northeast', 'is_metro' => false, 'is_remote' => true, 'cod_available' => false, 'expected_delivery_days' => 9, 'zone_multiplier' => 1.7],
            ['pincode' => '795001', 'zone' => 'E', 'city' => 'Dimapur', 'state' => 'Nagaland', 'region' => 'Northeast', 'is_metro' => false, 'is_remote' => true, 'cod_available' => false, 'expected_delivery_days' => 8, 'zone_multiplier' => 1.6],
            ['pincode' => '737001', 'zone' => 'E', 'city' => 'Gangtok', 'state' => 'Sikkim', 'region' => 'Northeast', 'is_metro' => false, 'is_remote' => true, 'cod_available' => false, 'expected_delivery_days' => 7, 'zone_multiplier' => 1.5],
            ['pincode' => '190001', 'zone' => 'E', 'city' => 'Srinagar', 'state' => 'Jammu & Kashmir', 'region' => 'J&K', 'is_metro' => false, 'is_remote' => true, 'cod_available' => false, 'expected_delivery_days' => 7, 'zone_multiplier' => 1.5],
            ['pincode' => '180001', 'zone' => 'E', 'city' => 'Jammu', 'state' => 'Jammu & Kashmir', 'region' => 'J&K', 'is_metro' => false, 'is_remote' => true, 'cod_available' => true, 'expected_delivery_days' => 6, 'zone_multiplier' => 1.4],
        ];

        foreach ($pincodeMappings as $mapping) {
            PincodeZone::updateOrCreate(
                ['pincode' => $mapping['pincode']],
                $mapping
            );
        }

        // Add some additional pincodes for testing different zones
        $this->addBulkPincodes();
    }

    private function addBulkPincodes()
    {
        // Add more Delhi pincodes (Zone A/B)
        $delhiPincodes = ['110002', '110003', '110004', '110005', '110006', '110007', '110008', '110009', '110010'];
        foreach ($delhiPincodes as $pincode) {
            PincodeZone::updateOrCreate(
                ['pincode' => $pincode],
                [
                    'pincode' => $pincode,
                    'zone' => 'A',
                    'city' => 'New Delhi',
                    'state' => 'Delhi',
                    'region' => 'North',
                    'is_metro' => true,
                    'is_remote' => false,
                    'cod_available' => true,
                    'expected_delivery_days' => 1,
                    'zone_multiplier' => 1.0
                ]
            );
        }

        // Add more Mumbai pincodes (Zone A/B)
        $mumbaiPincodes = ['400002', '400003', '400004', '400005', '400006', '400007', '400008', '400009', '400010'];
        foreach ($mumbaiPincodes as $pincode) {
            PincodeZone::updateOrCreate(
                ['pincode' => $pincode],
                [
                    'pincode' => $pincode,
                    'zone' => 'A',
                    'city' => 'Mumbai',
                    'state' => 'Maharashtra',
                    'region' => 'West',
                    'is_metro' => true,
                    'is_remote' => false,
                    'cod_available' => true,
                    'expected_delivery_days' => 1,
                    'zone_multiplier' => 1.0
                ]
            );
        }

        // Add some random Zone D pincodes for testing
        $zoneDPincodes = [
            ['321001', 'Bharatpur', 'Rajasthan'],
            ['331001', 'Churu', 'Rajasthan'],
            ['335001', 'Sri Ganganagar', 'Rajasthan'],
            ['281001', 'Mathura', 'Uttar Pradesh'],
            ['284001', 'Jhansi', 'Uttar Pradesh'],
        ];

        foreach ($zoneDPincodes as [$pincode, $city, $state]) {
            PincodeZone::updateOrCreate(
                ['pincode' => $pincode],
                [
                    'pincode' => $pincode,
                    'zone' => 'D',
                    'city' => $city,
                    'state' => $state,
                    'region' => 'North',
                    'is_metro' => false,
                    'is_remote' => false,
                    'cod_available' => true,
                    'expected_delivery_days' => 5,
                    'zone_multiplier' => 1.2
                ]
            );
        }
    }
}
