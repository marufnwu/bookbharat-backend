<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class PlaceholderImageController extends Controller
{
    public function generate(Request $request)
    {
        // Get parameters from request
        $width = $request->get('width', 600);
        $height = $request->get('height', 900);
        $text = $request->get('text', 'Book Cover');
        $bgColor = $request->get('bg', '#4F46E5');
        $textColor = $request->get('color', '#FFFFFF');

        // Validate dimensions
        $width = min(max($width, 50), 2000);
        $height = min(max($height, 50), 2000);

        // Create image manager with GD driver
        $manager = new ImageManager(new Driver());

        // Create blank image with background color
        $image = $manager->create($width, $height)->fill($bgColor);

        // Add text to the image
        $fontSize = min($width, $height) / 10;

        // Draw text with word wrapping
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine . ($currentLine ? ' ' : '') . $word;
            // Simple approximation for text width
            if (strlen($testLine) * $fontSize * 0.6 > $width * 0.8) {
                if ($currentLine) {
                    $lines[] = $currentLine;
                    $currentLine = $word;
                } else {
                    $lines[] = $word;
                }
            } else {
                $currentLine = $testLine;
            }
        }
        if ($currentLine) {
            $lines[] = $currentLine;
        }

        // Calculate starting Y position to center text vertically
        $lineHeight = $fontSize * 1.5;
        $totalHeight = count($lines) * $lineHeight;
        $startY = ($height - $totalHeight) / 2 + $fontSize;

        // Draw each line of text
        foreach ($lines as $index => $line) {
            $image->text($line, $width / 2, $startY + ($index * $lineHeight), function($font) use ($fontSize, $textColor) {
                $font->size($fontSize);
                $font->color($textColor);
                $font->align('center');
                $font->valign('middle');
                // Use a default font - you might need to specify a path to a TTF font file
                // $font->file(public_path('fonts/arial.ttf'));
            });
        }

        // Return image response
        return $image->toPng()->response();
    }
}