<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\ImageOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class MediaLibraryController extends Controller
{
    protected $imageOptimizationService;

    public function __construct(ImageOptimizationService $imageOptimizationService)
    {
        $this->imageOptimizationService = $imageOptimizationService;
    }

    /**
     * Get all media files with pagination and filters
     */
    public function index(Request $request)
    {
        $query = Media::with('uploader:id,name,email');

        // Filter by type
        if ($request->has('type') && $request->type !== 'all') {
            $query->ofType($request->type);
        }

        // Filter by folder
        if ($request->has('folder')) {
            $query->inFolder($request->folder);
        }

        // Search by filename
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                  ->orWhere('filename', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 24);
        $media = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $media->items(),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
                'from' => $media->firstItem(),
                'to' => $media->lastItem(),
            ]
        ]);
    }

    /**
     * Upload new media file
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'folder' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $folder = $request->get('folder', 'media');

        // Validate file type
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'application/pdf',
            'video/mp4', 'video/webm',
        ];

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return response()->json([
                'success' => false,
                'message' => 'File type not allowed'
            ], 422);
        }

        try {
            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '-' . time() . '.' . $extension;

            $conversions = null;
            $width = null;
            $height = null;

            // Check if it's an image and optimize it
            if ($this->imageOptimizationService->isImage($file) && $extension !== 'svg') {
                try {
                    // Process image with optimization and create multiple sizes
                    $conversions = $this->imageOptimizationService->processImage($file, $folder);

                    // Use original conversion for main media record
                    $path = $conversions['original']['path'];
                    $url = $conversions['original']['url'];
                    $width = $conversions['original']['width'];
                    $height = $conversions['original']['height'];
                    $filename = basename($path);
                } catch (\Exception $e) {
                    \Log::error('Image optimization failed, falling back to standard upload: ' . $e->getMessage());

                    // Fallback to standard upload if optimization fails
                    $path = $file->storeAs($folder, $filename, 'public');
                    $url = Storage::disk('public')->url($path);

                    $dimensions = $this->imageOptimizationService->getDimensions($file);
                    $width = $dimensions['width'];
                    $height = $dimensions['height'];
                }
            } else {
                // Non-image files, upload as-is
                $path = $file->storeAs($folder, $filename, 'public');
                $url = Storage::disk('public')->url($path);
            }

            // Get file size safely
            $fileSize = null;
            try {
                if ($conversions && isset($conversions['original']['size'])) {
                    $fileSize = $conversions['original']['size'];
                } else {
                    $fileSize = Storage::disk('public')->size($path);
                }
            } catch (\Exception $e) {
                \Log::warning('Could not retrieve file size: ' . $e->getMessage());
                $fileSize = $file->getSize(); // Use original file size as fallback
            }

            // Create media record
            $media = Media::create([
                'filename' => $filename,
                'original_filename' => $originalName,
                'path' => $path,
                'url' => $url,
                'mime_type' => $file->getMimeType(),
                'file_size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'disk' => 'public',
                'folder' => $folder,
                'uploaded_by' => auth()->id(),
                'conversions' => $conversions,
                'metadata' => [
                    'uploaded_at' => now()->toISOString(),
                    'optimized' => $conversions !== null,
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully' . ($conversions ? ' and optimized with ' . count($conversions) . ' versions' : ''),
                'data' => $media
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array',
            'files.*' => 'required|file|max:10240',
            'folder' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $uploadedMedia = [];
        $errors = [];

        foreach ($request->file('files') as $index => $file) {
            try {
                $folder = $request->get('folder', 'media');

                // Generate unique filename
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '-' . time() . '-' . $index . '.' . $extension;

                // Store file
                $path = $file->storeAs($folder, $filename, 'public');
                $url = Storage::disk('public')->url($path);

                // Get image dimensions if it's an image
                $width = null;
                $height = null;
                if (str_starts_with($file->getMimeType(), 'image/') && $extension !== 'svg') {
                    try {
                        $image = Image::read($file->getRealPath());
                        $width = $image->width();
                        $height = $image->height();
                    } catch (\Exception $e) {
                        // Continue without dimensions
                    }
                }

                // Create media record
                $media = Media::create([
                    'filename' => $filename,
                    'original_filename' => $originalName,
                    'path' => $path,
                    'url' => $url,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'width' => $width,
                    'height' => $height,
                    'disk' => 'public',
                    'folder' => $folder,
                    'uploaded_by' => auth()->id(),
                ]);

                $uploadedMedia[] = $media;

            } catch (\Exception $e) {
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => count($uploadedMedia) . ' file(s) uploaded successfully',
            'data' => $uploadedMedia,
            'errors' => $errors
        ], count($errors) > 0 ? 207 : 201);
    }

    /**
     * Get single media file
     */
    public function show($id)
    {
        $media = Media::with('uploader:id,name,email')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $media
        ]);
    }

    /**
     * Update media metadata
     */
    public function update(Request $request, $id)
    {
        $media = Media::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'original_filename' => 'nullable|string|max:255',
            'folder' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $media->update($request->only(['original_filename', 'folder', 'metadata']));

        return response()->json([
            'success' => true,
            'message' => 'Media updated successfully',
            'data' => $media
        ]);
    }

    /**
     * Delete media file
     */
    public function destroy($id)
    {
        $media = Media::findOrFail($id);

        // Delete file from storage
        $media->deleteFile();

        // Delete database record
        $media->delete();

        return response()->json([
            'success' => true,
            'message' => 'Media deleted successfully'
        ]);
    }

    /**
     * Bulk delete media files
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|exists:media_library,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $media = Media::whereIn('id', $request->ids)->get();

        foreach ($media as $item) {
            $item->deleteFile();
            $item->delete();
        }

        return response()->json([
            'success' => true,
            'message' => count($media) . ' media file(s) deleted successfully'
        ]);
    }

    /**
     * Get available folders
     */
    public function getFolders()
    {
        $folders = Media::distinct()->pluck('folder');

        return response()->json([
            'success' => true,
            'data' => $folders
        ]);
    }

    /**
     * Get media statistics
     */
    public function getStats()
    {
        $stats = [
            'total_files' => Media::count(),
            'total_size' => Media::sum('file_size'),
            'by_type' => [
                'images' => Media::where('mime_type', 'like', 'image/%')->count(),
                'videos' => Media::where('mime_type', 'like', 'video/%')->count(),
                'documents' => Media::whereIn('mime_type', ['application/pdf'])->count(),
            ],
            'recent_uploads' => Media::with('uploader:id,name')
                ->latest()
                ->take(5)
                ->get(),
        ];

        // Format total size
        $bytes = $stats['total_size'];
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        $stats['total_size_formatted'] = round($bytes, 2) . ' ' . $units[$i];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}

