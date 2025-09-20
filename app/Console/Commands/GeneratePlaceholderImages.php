<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;

class GeneratePlaceholderImages extends Command
{
    protected $signature = 'images:generate-placeholders';
    protected $description = 'Generate placeholder book cover images';

    public function handle()
    {
        $this->info('Generating placeholder book cover images...');

        $manager = new ImageManager(new Driver());

        $categories = [
            'fiction' => ['bg' => '#4F46E5', 'titles' => ['Fiction Novel', 'Literary Fiction', 'Classic Fiction']],
            'nonfiction' => ['bg' => '#059669', 'titles' => ['Non-Fiction', 'Educational', 'Reference Book']],
            'mystery' => ['bg' => '#DC2626', 'titles' => ['Mystery', 'Thriller', 'Suspense']],
            'romance' => ['bg' => '#EC4899', 'titles' => ['Romance', 'Love Story', 'Romantic Novel']],
            'scifi' => ['bg' => '#6366F1', 'titles' => ['Science Fiction', 'Sci-Fi', 'Future Fiction']],
            'biography' => ['bg' => '#78716C', 'titles' => ['Biography', 'Life Story', 'Memoir']]
        ];

        foreach ($categories as $category => $data) {
            for ($i = 0; $i < 3; $i++) {
                $filename = "placeholder-{$category}-" . ($i + 1) . ".jpg";
                $title = $data['titles'][$i] ?? 'Book Cover';

                // Create a 600x900 image
                $image = $manager->create(600, 900)->fill($data['bg']);

                // Add white rectangle for text background
                $image->drawRectangle(50, 350, 550, 550, function($draw) {
                    $draw->background('#ffffff20');
                });

                // Add title text
                $words = explode(' ', $title);
                $y = 420;
                foreach ($words as $word) {
                    $image->text($word, 300, $y, function($font) {
                        $font->size(60);
                        $font->color('#ffffff');
                        $font->align('center');
                        $font->valign('middle');
                    });
                    $y += 80;
                }

                // Add decorative border
                $image->drawRectangle(30, 30, 570, 870, function($draw) {
                    $draw->border(3, '#ffffff40');
                });

                // Save to storage
                $path = storage_path('app/public/book-covers/' . $filename);
                $image->toJpeg(85)->save($path);

                $this->info("Generated: {$filename}");
            }
        }

        $this->info('All placeholder images generated successfully!');
        $this->info('Run "php artisan storage:link" if you haven\'t already.');
    }
}