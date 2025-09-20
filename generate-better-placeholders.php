<?php

// Create higher quality book cover placeholders
$categories = [
    'fiction' => [
        'color' => [79, 70, 229],
        'titles' => [
            ['The Great', 'Indian Novel'],
            ["Midnight's", 'Children'],
            ['The God of', 'Small Things']
        ]
    ],
    'nonfiction' => [
        'color' => [5, 150, 105],
        'titles' => [
            ['SAPIENS', 'A Brief History'],
            ['Educational', 'Reference'],
            ['Knowledge', 'Book']
        ]
    ],
    'mystery' => [
        'color' => [220, 38, 38],
        'titles' => [
            ['The Da Vinci', 'Code'],
            ['Mystery', 'Thriller'],
            ['Dark', 'Suspense']
        ]
    ],
    'romance' => [
        'color' => [236, 72, 153],
        'titles' => [
            ['Pride and', 'Prejudice'],
            ['Love', 'Story'],
            ['Romantic', 'Novel']
        ]
    ],
    'scifi' => [
        'color' => [99, 102, 241],
        'titles' => [
            ['DUNE', 'Frank Herbert'],
            ['Science', 'Fiction'],
            ['Future', 'World']
        ]
    ],
    'biography' => [
        'color' => [120, 113, 108],
        'titles' => [
            ['Steve Jobs', 'Biography'],
            ['Life', 'Story'],
            ['Personal', 'Memoir']
        ]
    ]
];

$storageDir = __DIR__ . '/storage/app/public/book-covers';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

foreach ($categories as $category => $data) {
    for ($i = 0; $i < 3; $i++) {
        $filename = "placeholder-{$category}-" . ($i + 1) . ".jpg";
        $title = $data['titles'][$i] ?? ['Book', 'Cover'];

        // Create a 600x900 image with better quality
        $image = imagecreatetruecolor(600, 900);

        // Enable better rendering
        imageantialias($image, true);

        // Create gradient background
        $r = $data['color'][0];
        $g = $data['color'][1];
        $b = $data['color'][2];

        // Fill with gradient effect
        for ($y = 0; $y < 900; $y++) {
            $factor = 1 - ($y / 900) * 0.3; // Darken towards bottom
            $currentR = min(255, $r * $factor);
            $currentG = min(255, $g * $factor);
            $currentB = min(255, $b * $factor);
            $lineColor = imagecolorallocate($image, $currentR, $currentG, $currentB);
            imageline($image, 0, $y, 599, $y, $lineColor);
        }

        // Add decorative elements
        $white = imagecolorallocate($image, 255, 255, 255);
        $whiteAlpha = imagecolorallocatealpha($image, 255, 255, 255, 100);
        $darkAlpha = imagecolorallocatealpha($image, 0, 0, 0, 100);

        // Top ornament
        imagefilledrectangle($image, 0, 0, 600, 5, $whiteAlpha);
        imagefilledrectangle($image, 0, 895, 600, 900, $whiteAlpha);

        // Create central text area
        imagefilledrectangle($image, 50, 350, 550, 550, $darkAlpha);

        // Add border
        imagerectangle($image, 40, 340, 560, 560, $white);
        imagerectangle($image, 30, 30, 569, 869, $whiteAlpha);

        // Add text
        $fontSize = 5; // Maximum built-in font

        // Title - Line 1
        if (isset($title[0])) {
            $text = strtoupper($title[0]);
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $x = (600 - $textWidth) / 2;
            imagestring($image, $fontSize, $x, 420, $text, $white);
        }

        // Title - Line 2
        if (isset($title[1])) {
            $text = strtoupper($title[1]);
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $x = (600 - $textWidth) / 2;
            imagestring($image, $fontSize, $x, 460, $text, $white);
        }

        // Add fake publisher text at bottom
        $publisherText = "BOOKBHARAT CLASSICS";
        $textWidth = imagefontwidth(3) * strlen($publisherText);
        $x = (600 - $textWidth) / 2;
        imagestring($image, 3, $x, 820, $publisherText, $whiteAlpha);

        // Add some visual noise for texture
        for ($j = 0; $j < 50; $j++) {
            $px = rand(40, 560);
            $py = rand(40, 860);
            $dotAlpha = imagecolorallocatealpha($image, 255, 255, 255, rand(110, 125));
            imagesetpixel($image, $px, $py, $dotAlpha);
        }

        // Save with better quality
        $path = $storageDir . '/' . $filename;
        imagejpeg($image, $path, 95); // Higher quality
        imagedestroy($image);

        echo "Generated improved: {$filename}\n";
    }
}

echo "All placeholder images regenerated with better quality!\n";