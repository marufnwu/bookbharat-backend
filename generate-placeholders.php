<?php

$categories = [
    'fiction' => ['color' => [79, 70, 229], 'titles' => ['Fiction Novel', 'Literary Fiction', 'Classic Fiction']],
    'nonfiction' => ['color' => [5, 150, 105], 'titles' => ['Non-Fiction', 'Educational', 'Reference Book']],
    'mystery' => ['color' => [220, 38, 38], 'titles' => ['Mystery', 'Thriller', 'Suspense']],
    'romance' => ['color' => [236, 72, 153], 'titles' => ['Romance', 'Love Story', 'Romantic Novel']],
    'scifi' => ['color' => [99, 102, 241], 'titles' => ['Science Fiction', 'Sci-Fi', 'Future Fiction']],
    'biography' => ['color' => [120, 113, 108], 'titles' => ['Biography', 'Life Story', 'Memoir']]
];

$storageDir = __DIR__ . '/storage/app/public/book-covers';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

foreach ($categories as $category => $data) {
    for ($i = 0; $i < 3; $i++) {
        $filename = "placeholder-{$category}-" . ($i + 1) . ".jpg";
        $title = $data['titles'][$i] ?? 'Book Cover';

        // Create a 600x900 image
        $image = imagecreatetruecolor(600, 900);

        // Set background color
        $bgColor = imagecolorallocate($image, $data['color'][0], $data['color'][1], $data['color'][2]);
        imagefill($image, 0, 0, $bgColor);

        // Add white text
        $white = imagecolorallocate($image, 255, 255, 255);
        $fontSize = 5; // Built-in font size (1-5)

        // Calculate text position for centering
        $words = explode(' ', $title);
        $y = 400;

        foreach ($words as $word) {
            $textWidth = imagefontwidth($fontSize) * strlen($word);
            $x = (600 - $textWidth) / 2;
            imagestring($image, $fontSize, $x, $y, $word, $white);
            $y += 30;
        }

        // Add a simple border
        $borderColor = imagecolorallocatealpha($image, 255, 255, 255, 50);
        imagerectangle($image, 30, 30, 569, 869, $borderColor);

        // Save the image
        $path = $storageDir . '/' . $filename;
        imagejpeg($image, $path, 85);
        imagedestroy($image);

        echo "Generated: {$filename}\n";
    }
}

echo "All placeholder images generated successfully!\n";
echo "Run 'php artisan storage:link' if you haven't already.\n";