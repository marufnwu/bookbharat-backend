<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;

class SimpleProductSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Creating categories...');

        // Create categories
        $categories = [
            ['name' => 'Fiction', 'slug' => 'fiction', 'description' => 'Fiction books'],
            ['name' => 'Non-Fiction', 'slug' => 'non-fiction', 'description' => 'Non-fiction books'],
            ['name' => 'Academic', 'slug' => 'academic', 'description' => 'Academic textbooks'],
            ['name' => 'Children', 'slug' => 'children', 'description' => 'Children\'s books'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        $this->command->info('Creating products...');

        // Get categories
        $fiction = Category::where('slug', 'fiction')->first();
        $nonFiction = Category::where('slug', 'non-fiction')->first();
        $academic = Category::where('slug', 'academic')->first();
        $children = Category::where('slug', 'children')->first();

        // Sample books - using correct column names
        $books = [
            [
                'name' => 'The Great Gatsby',
                'slug' => 'the-great-gatsby',
                'description' => 'A classic American novel set in the Jazz Age.',
                'price' => 299,
                'compare_price' => 399,
                'category_id' => $fiction->id,
                'sku' => 'BK001001',
                'stock_quantity' => 100,
                'is_active' => true,
                'in_stock' => true,
                'weight' => 300,
                'dimensions' => json_encode(['length' => 20, 'width' => 15, 'height' => 3]),
                'author' => 'F. Scott Fitzgerald',
                'publisher' => 'Scribner',
                'isbn' => '9780743273565',
                'language' => 'English',
                'pages' => 180,
                'status' => 'active',
                'is_featured' => true,
                'is_bestseller' => true,
            ],
            [
                'name' => 'To Kill a Mockingbird',
                'slug' => 'to-kill-a-mockingbird',
                'description' => 'A gripping tale of racial injustice and childhood innocence.',
                'price' => 350,
                'compare_price' => 450,
                'category_id' => $fiction->id,
                'sku' => 'BK001002',
                'stock_quantity' => 75,
                'is_active' => true,
                'in_stock' => true,
                'weight' => 350,
                'dimensions' => json_encode(['length' => 20, 'width' => 15, 'height' => 3]),
                'author' => 'Harper Lee',
                'publisher' => 'Harper',
                'isbn' => '9780061120084',
                'language' => 'English',
                'pages' => 324,
                'status' => 'active',
                'is_featured' => true,
                'is_bestseller' => false,
            ],
            [
                'name' => '1984',
                'slug' => '1984',
                'description' => 'A dystopian social science fiction novel.',
                'price' => 399,
                'compare_price' => 499,
                'category_id' => $fiction->id,
                'sku' => 'BK001003',
                'stock_quantity' => 150,
                'is_active' => true,
                'in_stock' => true,
                'weight' => 400,
                'dimensions' => json_encode(['length' => 20, 'width' => 15, 'height' => 3]),
                'author' => 'George Orwell',
                'publisher' => 'Signet Classic',
                'isbn' => '9780451524935',
                'language' => 'English',
                'pages' => 328,
                'status' => 'active',
                'is_featured' => true,
                'is_bestseller' => true,
            ],
            [
                'name' => 'Sapiens: A Brief History of Humankind',
                'slug' => 'sapiens',
                'description' => 'A narrative history of humanity\'s creation and evolution.',
                'price' => 599,
                'compare_price' => 799,
                'category_id' => $nonFiction->id,
                'sku' => 'BK002001',
                'stock_quantity' => 80,
                'is_active' => true,
                'in_stock' => true,
                'weight' => 500,
                'dimensions' => json_encode(['length' => 22, 'width' => 15, 'height' => 4]),
                'author' => 'Yuval Noah Harari',
                'publisher' => 'Harper',
                'isbn' => '9780062316097',
                'language' => 'English',
                'pages' => 464,
                'status' => 'active',
                'is_featured' => true,
                'is_bestseller' => true,
            ],
            [
                'name' => 'Atomic Habits',
                'slug' => 'atomic-habits',
                'description' => 'An easy and proven way to build good habits and break bad ones.',
                'price' => 649,
                'compare_price' => 849,
                'category_id' => $nonFiction->id,
                'sku' => 'BK002002',
                'stock_quantity' => 120,
                'is_active' => true,
                'in_stock' => true,
                'weight' => 350,
                'dimensions' => json_encode(['length' => 20, 'width' => 15, 'height' => 3]),
                'author' => 'James Clear',
                'publisher' => 'Avery',
                'isbn' => '9780735211292',
                'language' => 'English',
                'pages' => 320,
                'status' => 'active',
                'is_featured' => true,
                'is_bestseller' => true,
            ],
            [
                'name' => 'Harry Potter and the Sorcerer\'s Stone',
                'slug' => 'harry-potter-1',
                'description' => 'The first book in the Harry Potter series.',
                'price' => 499,
                'compare_price' => 699,
                'category_id' => $children->id,
                'sku' => 'BK003001',
                'stock_quantity' => 200,
                'is_active' => true,
                'in_stock' => true,
                'weight' => 400,
                'dimensions' => json_encode(['length' => 20, 'width' => 15, 'height' => 3]),
                'author' => 'J.K. Rowling',
                'publisher' => 'Scholastic',
                'isbn' => '9780439708180',
                'language' => 'English',
                'pages' => 309,
                'status' => 'active',
                'is_featured' => true,
                'is_bestseller' => true,
            ],
        ];

        foreach ($books as $book) {
            Product::create($book);
        }

        $this->command->info('✅ Products created successfully!');
    }
}