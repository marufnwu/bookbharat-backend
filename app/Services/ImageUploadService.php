<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageUploadService
{
    private ImageManager $imageManager;

    public function __construct()
    {
        // Initialize ImageManager with GD driver
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Upload and optimize an image
     */
    public function uploadImage(UploadedFile $file, string $directory = 'products', array $options = []): array
    {
        // Default options
        $options = array_merge([
            'max_width' => 1920,
            'max_height' => 1920,
            'quality' => 85,
            'generate_thumbnails' => true,
            'thumbnail_sizes' => [
                'small' => ['width' => 300, 'height' => 300],
                'medium' => ['width' => 600, 'height' => 600],
            ]
        ], $options);

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $fileName = $originalName . '_' . uniqid() . '.' . $extension;

        // Store original file temporarily
        $tempPath = $file->store('temp', 'public');
        $fullTempPath = Storage::disk('public')->path($tempPath);

        try {
            // Load and optimize image
            $image = $this->imageManager->read($fullTempPath);

            // Resize if needed while maintaining aspect ratio
            if ($image->width() > $options['max_width'] || $image->height() > $options['max_height']) {
                $image->scaleDown($options['max_width'], $options['max_height']);
            }

            // Save optimized main image
            $mainPath = $directory . '/' . $fileName;
            $fullMainPath = Storage::disk('public')->path($mainPath);

            // Ensure directory exists
            Storage::disk('public')->makeDirectory($directory);

            $image->save($fullMainPath, $options['quality']);

            $result = [
                'path' => $mainPath,
                'url' => Storage::disk('public')->url($mainPath),
                'size' => Storage::disk('public')->size($mainPath),
                'thumbnails' => []
            ];

            // Generate thumbnails if requested
            if ($options['generate_thumbnails']) {
                foreach ($options['thumbnail_sizes'] as $size => $dimensions) {
                    $thumbFileName = $originalName . '_' . $size . '_' . uniqid() . '.' . $extension;
                    $thumbPath = $directory . '/thumbs/' . $thumbFileName;
                    $fullThumbPath = Storage::disk('public')->path($thumbPath);

                    // Ensure thumbnails directory exists
                    Storage::disk('public')->makeDirectory($directory . '/thumbs');

                    $thumbnail = $this->imageManager->read($fullTempPath);
                    $thumbnail->cover($dimensions['width'], $dimensions['height']);
                    $thumbnail->save($fullThumbPath, $options['quality']);

                    $result['thumbnails'][$size] = [
                        'path' => $thumbPath,
                        'url' => Storage::disk('public')->url($thumbPath),
                        'size' => Storage::disk('public')->size($thumbPath)
                    ];
                }
            }

            // Clean up temp file
            Storage::disk('public')->delete($tempPath);

            return $result;

        } catch (\Exception $e) {
            // Clean up temp file on error
            Storage::disk('public')->delete($tempPath);
            throw $e;
        }
    }

    /**
     * Delete an image and its thumbnails
     */
    public function deleteImage(string $imagePath): bool
    {
        try {
            // Delete main image
            if (Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            // Delete thumbnails
            $directory = dirname($imagePath);
            $filename = pathinfo($imagePath, PATHINFO_FILENAME);
            $extension = pathinfo($imagePath, PATHINFO_EXTENSION);

            // Look for thumbnails with pattern: originalname_size_uniqueid.ext
            $thumbDirectory = $directory . '/thumbs';
            if (Storage::disk('public')->exists($thumbDirectory)) {
                $thumbnails = Storage::disk('public')->files($thumbDirectory);
                foreach ($thumbnails as $thumbnail) {
                    $thumbFilename = pathinfo($thumbnail, PATHINFO_FILENAME);
                    // Check if this thumbnail belongs to our image (rough match)
                    if (strpos($thumbFilename, explode('_', $filename)[0]) === 0) {
                        Storage::disk('public')->delete($thumbnail);
                    }
                }
            }

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate image dimensions and file size
     */
    public function validateImage(UploadedFile $file, array $rules = []): array
    {
        $rules = array_merge([
            'max_size' => 5120, // KB
            'min_width' => 300,
            'min_height' => 300,
            'max_width' => 4000,
            'max_height' => 4000,
            'allowed_formats' => ['jpeg', 'jpg', 'png', 'webp']
        ], $rules);

        $errors = [];

        // Check file size
        if ($file->getSize() > ($rules['max_size'] * 1024)) {
            $errors[] = "Image size must be less than {$rules['max_size']}KB";
        }

        // Check format
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $rules['allowed_formats'])) {
            $errors[] = "Image must be one of: " . implode(', ', $rules['allowed_formats']);
        }

        // Check dimensions
        try {
            $imageInfo = getimagesize($file->getPathname());
            if ($imageInfo) {
                [$width, $height] = $imageInfo;

                if ($width < $rules['min_width'] || $height < $rules['min_height']) {
                    $errors[] = "Image dimensions must be at least {$rules['min_width']}x{$rules['min_height']}px";
                }

                if ($width > $rules['max_width'] || $height > $rules['max_height']) {
                    $errors[] = "Image dimensions must not exceed {$rules['max_width']}x{$rules['max_height']}px";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Unable to read image dimensions";
        }

        return $errors;
    }
}
