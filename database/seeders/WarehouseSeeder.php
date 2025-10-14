<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Warehouse;
use App\Models\ShippingCarrier;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default warehouse
        $defaultWarehouse = Warehouse::updateOrCreate(
            ['code' => 'WH-MAIN'],
            [
                'name' => 'Main Warehouse',
                'contact_person' => 'Warehouse Manager',
                'phone' => '9876543210',
                'email' => 'warehouse@bookbharat.com',
                'address_line_1' => '123, Book Street, Nehru Place',
                'address_line_2' => 'Near Metro Station',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'pincode' => '110001',
                'country' => 'India',
                'is_active' => true,
                'is_default' => true,
                'gst_number' => '07AAAAA0000A1Z5',
                'notes' => 'Main warehouse for book storage and dispatch',
            ]
        );

        $this->command->info('✅ Default warehouse created');

        // Associate warehouse with all active carriers
        $carriers = ShippingCarrier::where('is_active', true)->get();

        foreach ($carriers as $carrier) {
            // Check if association already exists
            $exists = $defaultWarehouse->carriers()->where('carrier_id', $carrier->id)->exists();

            if (!$exists) {
                $defaultWarehouse->carriers()->attach($carrier->id, [
                    'carrier_warehouse_name' => 'Main Warehouse',
                    'is_enabled' => true,
                ]);

                $this->command->info("✅ Associated warehouse with {$carrier->name}");
            }
        }

        $this->command->info('✅ Warehouse seeding completed');
    }
}
