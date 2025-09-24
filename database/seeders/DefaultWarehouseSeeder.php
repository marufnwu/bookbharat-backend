<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\InventoryLocation;

class DefaultWarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default warehouse if none exists
        if (!InventoryLocation::where('is_default', true)->exists()) {
            InventoryLocation::create([
                'name' => 'Main Warehouse - Delhi',
                'code' => 'WH-DEL-001',
                'type' => 'warehouse',
                'address' => 'Block A, Sector 63, Noida',
                'city' => 'Noida',
                'state' => 'Uttar Pradesh',
                'postal_code' => '110001',
                'country' => 'India',
                'contact_person' => 'Warehouse Manager',
                'contact_phone' => '+91-9876543210',
                'contact_email' => 'warehouse@bookbharat.com',
                'is_active' => true,
                'is_default' => true,
                'settings' => [
                    'operating_hours' => '9:00 AM - 6:00 PM',
                    'capacity' => 'Large',
                    'services' => ['pickup', 'packaging', 'storage']
                ]
            ]);
        }
    }
}