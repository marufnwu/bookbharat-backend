<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductAssociation;
use Carbon\Carbon;

class ProductAssociationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating sample product associations for testing...');

        // Get some products
        $products = Product::where('status', 'active')->limit(20)->get();

        if ($products->count() < 5) {
            $this->command->warn('Not enough products to create associations. Please seed products first.');
            return;
        }

        $associationsCreated = 0;

        // Create some realistic associations
        $associations = [
            // Fiction books often bought together
            [$products[0]->id, $products[1]->id, 15, 0.75],
            [$products[1]->id, $products[0]->id, 15, 0.75],
            [$products[0]->id, $products[2]->id, 10, 0.65],
            [$products[2]->id, $products[0]->id, 10, 0.65],

            // Non-fiction combo
            [$products[3]->id, $products[4]->id, 12, 0.70],
            [$products[4]->id, $products[3]->id, 12, 0.70],

            // Same author series
            [$products[5]->id, $products[6]->id, 20, 0.85],
            [$products[6]->id, $products[5]->id, 20, 0.85],

            // Related topics
            [$products[7]->id, $products[8]->id, 8, 0.55],
            [$products[8]->id, $products[7]->id, 8, 0.55],
        ];

        foreach ($associations as $assoc) {
            if (count($assoc) !== 4) continue;

            [$productId, $associatedId, $frequency, $confidence] = $assoc;

            // Check if products exist
            if (!$products->contains('id', $productId) || !$products->contains('id', $associatedId)) {
                continue;
            }

            ProductAssociation::create([
                'product_id' => $productId,
                'associated_product_id' => $associatedId,
                'frequency' => $frequency,
                'confidence_score' => $confidence,
                'association_type' => 'bought_together',
                'last_purchased_together' => Carbon::now()->subDays(rand(1, 30)),
            ]);

            $associationsCreated++;
        }

        // Create some random associations for additional products
        if ($products->count() >= 10) {
            for ($i = 0; $i < 20; $i++) {
                $product1 = $products->random();
                $product2 = $products->where('id', '!=', $product1->id)->random();

                // Check if association already exists
                $exists = ProductAssociation::where('product_id', $product1->id)
                    ->where('associated_product_id', $product2->id)
                    ->where('association_type', 'bought_together')
                    ->exists();

                if ($exists) {
                    continue;
                }

                ProductAssociation::create([
                    'product_id' => $product1->id,
                    'associated_product_id' => $product2->id,
                    'frequency' => rand(3, 15),
                    'confidence_score' => rand(30, 80) / 100,
                    'association_type' => 'bought_together',
                    'last_purchased_together' => Carbon::now()->subDays(rand(1, 60)),
                ]);

                $associationsCreated++;
            }
        }

        $this->command->info("✅ Created {$associationsCreated} product associations");
        $this->command->newLine();

        // Show statistics
        $stats = [
            'Total' => ProductAssociation::where('association_type', 'bought_together')->count(),
            'High Confidence (≥0.5)' => ProductAssociation::where('association_type', 'bought_together')
                ->where('confidence_score', '>=', 0.5)->count(),
            'Medium Confidence (0.3-0.5)' => ProductAssociation::where('association_type', 'bought_together')
                ->whereBetween('confidence_score', [0.3, 0.5])->count(),
        ];

        $this->command->table(['Metric', 'Count'],
            collect($stats)->map(fn($v, $k) => [$k, $v])->values()->toArray()
        );
    }
}
