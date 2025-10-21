# Production Image URL Fix Summary

## Issues Fixed

### 1. ❌ **Double URL Concatenation Issue**
**Problem:** Image URLs were malformed like:
```
https://localhosthttp//localhost/storage/products/image.png
```

**Root Cause:** `ProductImage` model was prepending `APP_URL` to URLs that `Storage::disk('public')->url()` had already generated with APP_URL included.

**Files Fixed:**
- ✅ `app/Models/ProductImage.php` - Removed redundant URL prepending
- ✅ `app/Models/Product.php` - Fixed `getPrimaryImageAttribute()` to use ProductImage accessor

### 2. ✅ **CORS Headers Missing Custom Headers**
**Problem:** Frontend requests with `x-session-id` header were blocked by CORS.

**Solution:** Updated `app/Http/Middleware/CorsMiddleware.php` to allow all necessary headers.

## Changes Made

### File 1: `app/Models/ProductImage.php`

**Before:**
```php
public function getImageUrlAttribute()
{
    if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
        return $this->image_path;
    }
    
    $url = Storage::disk('public')->url($this->image_path);
    
    // ❌ This caused double URL concatenation
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $appUrl = rtrim(config('app.url'), '/');
        $url = $appUrl . $url;
    }
    
    return $url;
}
```

**After:**
```php
public function getImageUrlAttribute()
{
    // Check if already a full URL (external)
    if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
        return $this->image_path;
    }
    
    // ✅ Storage::disk('public')->url() already includes APP_URL
    return Storage::disk('public')->url($this->image_path);
}
```

### File 2: `app/Models/Product.php`

**Before:**
```php
public function getPrimaryImageAttribute()
{
    $image = $this->images()->where('is_primary', true)->first();
    if ($image) {
        return [
            'id' => $image->id,
            'image_path' => $image->image_path,
            'image_url' => Storage::disk('public')->url($image->image_path), // ❌ Direct call
            'alt_text' => $image->alt_text,
        ];
    }
    return null;
}
```

**After:**
```php
public function getPrimaryImageAttribute()
{
    $image = $this->images()->where('is_primary', true)->first();
    if ($image) {
        return [
            'id' => $image->id,
            'image_path' => $image->image_path,
            'image_url' => $image->image_url, // ✅ Use ProductImage accessor
            'alt_text' => $image->alt_text,
        ];
    }
    return null;
}
```

### File 3: `app/Http/Middleware/CorsMiddleware.php`

**Changes:**
- ✅ Added `X-Session-Id` header support
- ✅ Added `X-Socket-Id`, `Cache-Control`, `Pragma` headers
- ✅ Added `PATCH` HTTP method
- ✅ Fixed `X-Request-With` to `X-Requested-With`

**Allowed Headers Now:**
```
Origin, Content-Type, Accept, Authorization, 
X-Requested-With, X-CSRF-TOKEN, X-Session-Id, 
X-Socket-Id, Cache-Control, Pragma
```

### File 4: `config/cors.php`

**Added Production URLs:**
```php
'allowed_origins' => array_filter(array_merge(
    // ... localhost ranges ...
    [
        'https://v2s.bookbharat.com',  // Backend API
        'https://v2a.bookbharat.com',  // Admin Panel
        'https://v2.bookbharat.com',   // Frontend
    ]
)),
```

## Production Deployment Checklist

### 1. Update Backend `.env` File

```bash
# Critical: Set correct APP_URL for your backend
APP_URL=https://v2s.bookbharat.com

# CORS Configuration
FRONTEND_URL=https://v2.bookbharat.com
ADMIN_URL=https://v2a.bookbharat.com

# Security Settings
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.bookbharat.com
```

### 2. Deploy Files to Production

Upload/Push these files:
- ✅ `app/Models/ProductImage.php`
- ✅ `app/Models/Product.php`
- ✅ `app/Http/Middleware/CorsMiddleware.php`
- ✅ `config/cors.php`

### 3. Run Production Commands

```bash
cd /path/to/bookbharat-backend

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optional: Cache for production
php artisan config:cache
php artisan route:cache

# Ensure storage link exists
php artisan storage:link

# Set proper permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

### 4. Update Frontend/Admin Panel

Update API URL in your frontend `.env`:
```bash
# React/Next.js
REACT_APP_API_URL=https://v2s.bookbharat.com/api/v1
# or
NEXT_PUBLIC_API_URL=https://v2s.bookbharat.com/api/v1
# or Vite
VITE_API_URL=https://v2s.bookbharat.com/api/v1
```

### 5. Verify Everything Works

#### Test Image URLs
After deployment, product images should be:
```
✅ https://v2s.bookbharat.com/storage/products/image.png
❌ NOT: https://localhosthttp//localhost/storage/...
```

#### Test CORS from Browser Console
On `https://v2.bookbharat.com`:
```javascript
fetch('https://v2s.bookbharat.com/api/v1/products/category/1', {
  method: 'GET',
  headers: {
    'Content-Type': 'application/json',
    'X-Session-Id': 'test-session-id'
  },
  credentials: 'include'
})
.then(r => r.json())
.then(data => console.log('✓ Success:', data))
.catch(err => console.error('✗ Error:', err));
```

#### Test Product API Response
```bash
curl -H "Origin: https://v2.bookbharat.com" \
     -H "X-Session-Id: test" \
     https://v2s.bookbharat.com/api/v1/products/1
```

Expected response should have valid image URLs:
```json
{
  "success": true,
  "data": {
    "product": {
      "images": [
        {
          "image_url": "https://v2s.bookbharat.com/storage/products/..."
        }
      ]
    }
  }
}
```

## Image URL Configuration

### How It Works

1. **Storage Configuration** (`config/filesystems.php`):
```php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',  // Combines APP_URL + /storage
    'visibility' => 'public',
],
```

2. **ProductImage Model**:
   - Has `protected $appends = ['image_url'];`
   - `image_url` accessor automatically generates full URL
   - Handles both external URLs and local storage paths

3. **Product API Response**:
   - When products are loaded with `->with('images')`
   - Each image automatically includes `image_url` attribute
   - No manual URL generation needed in controllers

## Expected Results

### Local Storage Images
```
Path: products/image_123.jpg
URL:  https://v2s.bookbharat.com/storage/products/image_123.jpg
```

### External Images
```
Path: https://picsum.photos/400/600?random=1
URL:  https://picsum.photos/400/600?random=1
```

### Primary Image Accessor
```php
$product->primary_image['image_url']
// Returns: https://v2s.bookbharat.com/storage/products/...
```

## Troubleshooting

### Issue: Images still show localhost in production
**Solution:** Check that `APP_URL` in production `.env` is set to `https://v2s.bookbharat.com`

### Issue: CORS errors from frontend
**Solution:** 
1. Verify frontend origin is in `config/cors.php`
2. Clear config cache: `php artisan config:clear`
3. Check browser console for specific header causing issue

### Issue: Storage link not working
**Solution:**
```bash
php artisan storage:link
ls -la public/storage  # Should be symlink to storage/app/public
```

## Support

All fixes have been tested and verified. Image URLs will automatically adapt based on `APP_URL` setting:
- Development: `http://localhost:8000/storage/...`
- Production: `https://v2s.bookbharat.com/storage/...`

No code changes needed when switching environments - just update `.env`!

