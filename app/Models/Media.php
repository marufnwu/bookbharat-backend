<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'media_library';

    protected $fillable = [
        'filename',
        'original_filename',
        'path',
        'url',
        'mime_type',
        'file_size',
        'width',
        'height',
        'disk',
        'folder',
        'metadata',
        'conversions',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'metadata' => 'array',
        'conversions' => 'array',
    ];

    protected $appends = ['full_url', 'size_formatted', 'type'];

    /**
     * Get the user who uploaded the media
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the full URL attribute
     */
    public function getFullUrlAttribute()
    {
        return $this->url;
    }

    /**
     * Get formatted file size
     */
    public function getSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get media type
     */
    public function getTypeAttribute()
    {
        if (str_starts_with($this->mime_type, 'image/')) {
            return 'image';
        } elseif (str_starts_with($this->mime_type, 'video/')) {
            return 'video';
        } elseif (str_starts_with($this->mime_type, 'audio/')) {
            return 'audio';
        } elseif (in_array($this->mime_type, ['application/pdf'])) {
            return 'document';
        }
        return 'file';
    }

    /**
     * Delete media file from storage
     */
    public function deleteFile()
    {
        if (Storage::disk($this->disk)->exists($this->path)) {
            Storage::disk($this->disk)->delete($this->path);
        }
    }

    /**
     * Scope to filter by folder
     */
    public function scopeInFolder($query, $folder)
    {
        return $query->where('folder', $folder);
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, $type)
    {
        if ($type === 'image') {
            return $query->where('mime_type', 'like', 'image/%');
        } elseif ($type === 'video') {
            return $query->where('mime_type', 'like', 'video/%');
        } elseif ($type === 'document') {
            return $query->whereIn('mime_type', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
        }
        return $query;
    }
}

