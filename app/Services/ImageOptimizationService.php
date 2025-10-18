<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageOptimizationService
{
    protected $disk;
    protected $sizes = [
        'thumbnail' => ['width' => 150, 'height' => 150],
        'small' => ['width' => 300, 'height' => 300],
        'medium' => ['width' => 600, 'height' => 600],
        'large' => ['width' => 1200, 'height' => 1200],
    ];

    protected $quality = [
        'jpeg' => 85,
        'webp' => 85,
        'png' => 9, // Compression level for PNG (0-9)
    ];

    protected $manager;

    public function __construct()
    {
        // Always use 'public' disk for media uploads to be web-accessible
        $this->disk = 'public';
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Optimize and create multiple sizes of an uploaded image
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $folder
     * @param array $sizes
     * @return array
     */
    public function processImage($file, $folder = 'images', $sizes = null)
    {
        if ($sizes === null) {
            $sizes = $this->sizes;
        }

        $conversions = [];
        $originalExtension = $file->getClientOriginalExtension();
        $filename = Str::random(40);
        $path = $folder . '/' . date('Y/m');

        // Create original image
        $image = $this->manager->read($file);

        // Store original (optimized)
        $originalPath = $path . '/' . $filename . '.' . $originalExtension;
        $this->saveOptimizedImage($image, $originalPath, $originalExtension);

        $conversions['original'] = [
            'path' => $originalPath,
            'url' => Storage::disk($this->disk)->url($originalPath),
            'width' => $image->width(),
            'height' => $image->height(),
            'size' => Storage::disk($this->disk)->size($originalPath),
        ];

        // Create different sizes
        foreach ($sizes as $sizeName => $dimensions) {
            $sizedImage = $this->manager->read($file);

            // Resize maintaining aspect ratio
            $sizedImage->scale(width: $dimensions['width'], height: $dimensions['height']);

            // Save as original format
            $sizePath = $path . '/' . $filename . '_' . $sizeName . '.' . $originalExtension;
            $this->saveOptimizedImage($sizedImage, $sizePath, $originalExtension);

            $conversions[$sizeName] = [
                'path' => $sizePath,
                'url' => Storage::disk($this->disk)->url($sizePath),
                'width' => $sizedImage->width(),
                'height' => $sizedImage->height(),
                'size' => Storage::disk($this->disk)->size($sizePath),
            ];

            // Also create WebP version
            if ($originalExtension !== 'webp') {
                $webpImage = $this->manager->read($file);
                $webpImage->scale(width: $dimensions['width'], height: $dimensions['height']);

                $webpPath = $path . '/' . $filename . '_' . $sizeName . '.webp';
                $this->saveOptimizedImage($webpImage, $webpPath, 'webp');

                $conversions[$sizeName . '_webp'] = [
                    'path' => $webpPath,
                    'url' => Storage::disk($this->disk)->url($webpPath),
                    'width' => $webpImage->width(),
                    'height' => $webpImage->height(),
                    'size' => Storage::disk($this->disk)->size($webpPath),
                ];
            }
        }

        // Create WebP version of original
        if ($originalExtension !== 'webp') {
            $webpImage = $this->manager->read($file);
            $webpPath = $path . '/' . $filename . '.webp';
            $this->saveOptimizedImage($webpImage, $webpPath, 'webp');

            $conversions['original_webp'] = [
                'path' => $webpPath,
                'url' => Storage::disk($this->disk)->url($webpPath),
                'width' => $webpImage->width(),
                'height' => $webpImage->height(),
                'size' => Storage::disk($this->disk)->size($webpPath),
            ];
        }

        return $conversions;
    }

    /**
     * Save optimized image to storage
     *
     * @param \Intervention\Image\Image $image
     * @param string $path
     * @param string $format
     * @return void
     */
    protected function saveOptimizedImage($image, $path, $format)
    {
        $format = strtolower($format);

        switch ($format) {
            case 'jpg':
            case 'jpeg':
                $encoded = $image->toJpeg($this->quality['jpeg']);
                break;
            case 'webp':
                $encoded = $image->toWebp($this->quality['webp']);
                break;
            case 'png':
                $encoded = $image->toPng();
                break;
            case 'gif':
                $encoded = $image->toGif();
                break;
            default:
                $encoded = $image->toJpeg($this->quality['jpeg']);
        }

        Storage::disk($this->disk)->put($path, (string) $encoded);
    }

    /**
     * Delete all conversions of an image
     *
     * @param array $conversions
     * @return void
     */
    public function deleteConversions($conversions)
    {
        if (!is_array($conversions)) {
            return;
        }

        foreach ($conversions as $conversion) {
            if (isset($conversion['path'])) {
                Storage::disk($this->disk)->delete($conversion['path']);
            }
        }
    }

    /**
     * Optimize existing image in storage
     *
     * @param string $path
     * @return array|null
     */
    public function optimizeExistingImage($path)
    {
        if (!Storage::disk($this->disk)->exists($path)) {
            return null;
        }

        try {
            $imageContents = Storage::disk($this->disk)->get($path);
            $image = Image::make($imageContents);

            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $this->saveOptimizedImage($image, $path, $extension);

            $image->destroy();

            return [
                'path' => $path,
                'url' => Storage::disk($this->disk)->url($path),
                'size' => Storage::disk($this->disk)->size($path),
            ];
        } catch (\Exception $e) {
            \Log::error('Image optimization failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get responsive image srcset
     *
     * @param array $conversions
     * @param string $sizeName
     * @return string
     */
    public function getSrcset($conversions, $sizeName = 'original')
    {
        $srcset = [];

        foreach ($this->sizes as $size => $dimensions) {
            if (isset($conversions[$size])) {
                $srcset[] = $conversions[$size]['url'] . ' ' . $conversions[$size]['width'] . 'w';
            }
        }

        if (isset($conversions['original'])) {
            $srcset[] = $conversions['original']['url'] . ' ' . $conversions['original']['width'] . 'w';
        }

        return implode(', ', $srcset);
    }

    /**
     * Get WebP version URL if available
     *
     * @param array $conversions
     * @param string $sizeName
     * @return string|null
     */
    public function getWebPUrl($conversions, $sizeName = 'original')
    {
        $webpKey = $sizeName . '_webp';
        return $conversions[$webpKey]['url'] ?? null;
    }

    /**
     * Check if file is an image
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return bool
     */
    public function isImage($file)
    {
        $mimeType = $file->getMimeType();
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Get image dimensions
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array
     */
    public function getDimensions($file)
    {
        $image = Image::make($file);
        $dimensions = [
            'width' => $image->width(),
            'height' => $image->height(),
        ];
        $image->destroy();

        return $dimensions;
    }
}

