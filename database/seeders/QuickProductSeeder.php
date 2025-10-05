<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;

class QuickProductSeeder extends Seeder
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

        // Sample books data
        $books = [
            // Fiction
            [
                'name' => 'The Great Gatsby',
                'author' => 'F. Scott Fitzgerald',
                'description' => 'A classic American novel set in the Jazz Age.',
                'price' => 299,
                'sale_price' => 249,
                'category_id' => $fiction->id,
                'isbn' => '9780743273565',
                'pages' => 180,
                'publisher' => 'Scribner',
                'language' => 'English',
                'featured' => true,
            ],
            [
                'name' => 'To Kill a Mockingbird',
                'author' => 'Harper Lee',
                'description' => 'A gripping tale of racial injustice and childhood innocence.',
                'price' => 350,
                'sale_price' => 299,
                'category_id' => $fiction->id,
                'isbn' => '9780061120084',
                'pages' => 324,
                'publisher' => 'Harper',
                'language' => 'English',
                'featured' => true,
            ],
            [
                'name' => '1984',
                'author' => 'George Orwell',
                'description' => 'A dystopian social science fiction novel.',
                'price' => 399,
                'sale_price' => 349,
                'category_id' => $fiction->id,
                'isbn' => '9780451524935',
                'pages' => 328,
                'publisher' => 'Signet Classic',
                'language' => 'English',
                'featured' => true,
            ],

            // Non-Fiction
            [
                'name' => 'Sapiens: A Brief History of Humankind',
                'author' => 'Yuval Noah Harari',
                'description' => 'A narrative history of humanity\'s creation and evolution.',
                'price' => 599,
                'sale_price' => 499,
                'category_id' => $nonFiction->id,
                'isbn' => '9780062316097',
                'pages' => 464,
                'publisher' => 'Harper',
                'language' => 'English',
                'featured' => true,
            ],
            [
                'name' => 'Atomic Habits',
                'author' => 'James Clear',
                'description' => 'An easy and proven way to build good habits and break bad ones.',
                'price' => 649,
                'sale_price' => 549,
                'category_id' => $nonFiction->id,
                'isbn' => '9780735211292',
                'pages' => 320,
                'publisher' => 'Avery',
                'language' => 'English',
                'featured' => true,
            ],

            // Academic
            [
                'name' => 'Introduction to Algorithms',
                'author' => 'Thomas H. Cormen',
                'description' => 'Comprehensive textbook on algorithms.',
                'price' => 1299,
                'sale_price' => 1099,
                'category_id' => $academic->id,
                'isbn' => '9780262033848',
                'pages' => 1292,
                'publisher' => 'MIT Press',
                'language' => 'English',
                'featured' => false,
            ],
            [
                'name' => 'Physics for Scientists and Engineers',
                'author' => 'Raymond A. Serway',
                'description' => 'A comprehensive physics textbook.',
                'price' => 1599,
                'sale_price' => 1399,
                'category_id' => $academic->id,
                'isbn' => '9781337553292',
                'pages' => 1484,
                'publisher' => 'Cengage',
                'language' => 'English',
                'featured' => false,
            ],

            // Children
            [
                'name' => 'Harry Potter and the Sorcerer\'s Stone',
                'author' => 'J.K. Rowling',
                'description' => 'The first book in the Harry Potter series.',
                'price' => 499,
                'sale_price' => 399,
                'category_id' => $children->id,
                'isbn' => '9780439708180',
                'pages' => 309,
                'publisher' => 'Scholastic',
                'language' => 'English',
                'featured' => true,
            ],
            [
                'name' => 'The Cat in the Hat',
                'author' => 'Dr. Seuss',
                'description' => 'A classic children\'s book.',
                'price' => 299,
                'sale_price' => 249,
                'category_id' => $children->id,
                'isbn' => '9780394800011',
                'pages' => 61,
                'publisher' => 'Random House',
                'language' => 'English',
                'featured' => true,
            ],
        ];

        foreach ($books as $book) {
            // Remove fields that don't exist in current schema
            unset($book['sale_price']);
            unset($book['featured']);
            unset($book['author']);
            unset($book['isbn']);
            unset($book['pages']);
            unset($book['publisher']);
            unset($book['language']);

            $book['slug'] = Str::slug($book['name']);
            $book['sku'] = 'BK' . str_pad(rand(1000, 9999), 6, '0', STR_PAD_LEFT);
            $book['quantity'] = rand(50, 200);
            $book['status'] = 'active';
            $book['weight'] = rand(200, 800); // in grams
            $book['dimensions'] = json_encode(['length' => 20, 'width' => 15, 'height' => 3]);

            Product::create($book);
        }

        $this->command->info('✅ Products created successfully!');
    }
}