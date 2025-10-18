# Storage Path Fix

## Issue
Images were being uploaded to `storage/app/private/media/` (not web-accessible) instead of `storage/app/public/media/` (web-accessible via symlink).

## Root Cause
`ImageOptimizationService` was using `config('filesystems.default')` which returns `'local'` disk, pointing to `storage/app/private/`.

## Fix Applied

### 1. Updated ImageOptimizationService.php
**Changed:**
```php
// Before
$this->disk = config('filesystems.default', 'public');

// After
$this->disk = 'public'; // Always use public disk for web-accessible media
```

### 2. Moved Existing Files
```bash
mv storage/app/private/media/* storage/app/public/media/
```

### 3. Set Permissions
```bash
chmod -R 755 storage/app/public/media
chmod -R 775 storage/app/public
```

## Result
✅ New uploads will go to `storage/app/public/media/`
✅ Files are web-accessible via `/storage/media/` URL
✅ Existing files moved to correct location
✅ Proper permissions set

## Verification
Images should now be accessible at:
- `http://localhost:8000/storage/media/2025/10/filename.png`
- Via symlink: `public/storage -> storage/app/public`

## Future Uploads
All new image uploads will automatically go to the correct `public` disk and be immediately accessible via the web.

