# Storage Image CORS Configuration Guide

## Problem: 400 Bad Request / CORS Issues with Images

When Next.js Image Optimizer tries to fetch images from `https://v2s.bookbharat.com/storage/`, it needs proper CORS headers and the images must be accessible.

## Solutions Implemented

### 1. Apache Configuration (.htaccess)

Created `public/storage/.htaccess` with CORS headers for all image file types.

**File Location:** `public/storage/.htaccess`

```apache
<IfModule mod_headers.c>
    # CORS Headers for Storage Assets
    SetEnvIf Origin "^https?://(v2\.bookbharat\.com|v2a\.bookbharat\.com|v2s\.bookbharat\.com|localhost:[0-9]+|127\.0\.0\.1:[0-9]+)$" CORS_ORIGIN=$0
    
    Header always set Access-Control-Allow-Origin "%{CORS_ORIGIN}e" env=CORS_ORIGIN
    Header always set Access-Control-Allow-Methods "GET, OPTIONS"
    Header always set Access-Control-Allow-Credentials "true"
    Header always set Access-Control-Max-Age "86400"
    
    Header always set Vary "Origin"
    Header always set Cache-Control "public, max-age=31536000"
</IfModule>
```

### 2. Nginx Configuration (Alternative)

If your production server uses Nginx, add this to your site configuration:

**File:** `/etc/nginx/sites-available/v2s.bookbharat.com`

```nginx
server {
    listen 443 ssl http2;
    server_name v2s.bookbharat.com;
    
    root /path/to/bookbharat-backend/public;
    index index.php;

    # CORS Configuration for Storage Assets
    location /storage/ {
        # Allow CORS from specific origins
        if ($http_origin ~* "^https?://(v2\.bookbharat\.com|v2a\.bookbharat\.com|v2s\.bookbharat\.com|localhost:[0-9]+)$") {
            add_header Access-Control-Allow-Origin $http_origin always;
            add_header Access-Control-Allow-Methods "GET, OPTIONS" always;
            add_header Access-Control-Allow-Credentials "true" always;
            add_header Access-Control-Max-Age "86400" always;
        }
        
        # Handle preflight requests
        if ($request_method = OPTIONS) {
            add_header Access-Control-Allow-Origin $http_origin;
            add_header Access-Control-Allow-Methods "GET, OPTIONS";
            add_header Access-Control-Allow-Credentials "true";
            add_header Access-Control-Max-Age "86400";
            add_header Content-Length 0;
            add_header Content-Type text/plain;
            return 204;
        }
        
        # Cache control for assets
        add_header Cache-Control "public, max-age=31536000" always;
        add_header Vary "Origin" always;
        
        # Try to serve file directly
        try_files $uri =404;
    }

    # Laravel application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # SSL configuration
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;
}
```

## Troubleshooting 400 Bad Request

### Issue 1: Filenames with Spaces
**Problem:** Files like `Screen Shot 2025-10-11 at 18.49.02_68f5ef34b3353.png` have spaces.

**Solution:** The ImageUploadService should sanitize filenames. Check:

```php
// In app/Services/ImageUploadService.php
$filename = str_replace(' ', '_', $originalName);
$filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $filename);
```

### Issue 2: Storage Link Not Working
**Problem:** Symlink from `public/storage` to `storage/app/public` is broken.

**Solution:**
```bash
# Remove old link if exists
rm -rf public/storage

# Create fresh symlink
php artisan storage:link

# Verify
ls -la public/storage
# Should show: public/storage -> ../storage/app/public
```

### Issue 3: File Permissions
**Problem:** Web server cannot read files in storage.

**Solution:**
```bash
# Set correct ownership
sudo chown -R www-data:www-data storage/app/public

# Set correct permissions
chmod -R 755 storage/app/public
```

### Issue 4: Next.js Image Domains Not Configured
**Problem:** Next.js doesn't allow external images without configuration.

**Solution:** In your frontend `next.config.js`:

```javascript
module.exports = {
  images: {
    domains: ['v2s.bookbharat.com'],
    // Or for more control:
    remotePatterns: [
      {
        protocol: 'https',
        hostname: 'v2s.bookbharat.com',
        pathname: '/storage/**',
      },
    ],
  },
}
```

## Deployment Checklist

### Backend (v2s.bookbharat.com)

1. **Upload Storage CORS Configuration**
   ```bash
   # For Apache
   scp public/storage/.htaccess user@server:/path/to/public/storage/
   
   # Verify mod_headers is enabled
   sudo a2enmod headers
   sudo systemctl restart apache2
   ```

2. **Verify Storage Link**
   ```bash
   cd /path/to/bookbharat-backend
   php artisan storage:link
   ls -la public/storage
   ```

3. **Test Image Access**
   ```bash
   # Should return 200 OK
   curl -I https://v2s.bookbharat.com/storage/products/test.png
   
   # Should include CORS headers
   curl -H "Origin: https://v2.bookbharat.com" -I \
     https://v2s.bookbharat.com/storage/products/test.png
   ```

4. **Check Permissions**
   ```bash
   ls -la storage/app/public/products/
   # Should be readable by web server user (www-data)
   ```

### Frontend (v2.bookbharat.com)

1. **Update next.config.js**
   ```javascript
   images: {
     domains: ['v2s.bookbharat.com'],
   }
   ```

2. **Rebuild and Deploy**
   ```bash
   npm run build
   # Deploy to production
   ```

3. **Test Image Loading**
   ```javascript
   // In browser console on v2.bookbharat.com
   fetch('https://v2s.bookbharat.com/storage/products/test.png', {
     method: 'GET',
     headers: { 'Origin': 'https://v2.bookbharat.com' }
   })
   .then(r => {
     console.log('Status:', r.status);
     console.log('CORS:', r.headers.get('access-control-allow-origin'));
   });
   ```

## Testing CORS Headers

### Test with cURL
```bash
# Test CORS headers
curl -H "Origin: https://v2.bookbharat.com" \
     -H "Access-Control-Request-Method: GET" \
     -X OPTIONS \
     -I https://v2s.bookbharat.com/storage/products/image.png

# Expected response headers:
# Access-Control-Allow-Origin: https://v2.bookbharat.com
# Access-Control-Allow-Methods: GET, OPTIONS
# Access-Control-Allow-Credentials: true
```

### Test from Browser
```javascript
// Open browser console on https://v2.bookbharat.com
fetch('https://v2s.bookbharat.com/storage/products/Screen%20Shot%202025-10-11%20at%2018.49.02_68f5ef34b3353.png')
  .then(response => {
    console.log('✓ Status:', response.status);
    console.log('✓ CORS Origin:', response.headers.get('access-control-allow-origin'));
    return response.blob();
  })
  .then(blob => {
    console.log('✓ Image loaded, size:', blob.size, 'bytes');
  })
  .catch(error => {
    console.error('✗ Error:', error);
  });
```

## Common Errors and Solutions

### Error: "Failed to load resource: 400 Bad Request"

**Possible Causes:**
1. Filename has special characters causing encoding issues
2. File doesn't exist at that path
3. Storage link is broken
4. Permissions issue

**Debug Steps:**
```bash
# 1. Check if file exists
ls -la storage/app/public/products/ | grep "Screen Shot"

# 2. Check storage link
ls -la public/storage
# Should show symlink: storage -> ../../storage/app/public

# 3. Test direct file access
curl -I https://v2s.bookbharat.com/storage/products/filename.png

# 4. Check Laravel logs
tail -f storage/logs/laravel.log

# 5. Check web server error logs
sudo tail -f /var/log/nginx/error.log
# or
sudo tail -f /var/log/apache2/error.log
```

### Error: "Access-Control-Allow-Origin header is missing"

**Solution:** Ensure the storage .htaccess or nginx config is properly configured.

### Error: "Cross-Origin Request Blocked"

**Solution:** Check that:
1. Origin is in the allowed origins regex
2. mod_headers is enabled (Apache)
3. Nginx configuration is reloaded
4. Headers are being added to actual file responses (not just preflight)

## Expected Results After Fix

### 1. Image URLs are correct
```
✓ https://v2s.bookbharat.com/storage/products/image.png
```

### 2. CORS headers present
```
Access-Control-Allow-Origin: https://v2.bookbharat.com
Access-Control-Allow-Credentials: true
```

### 3. Next.js Image Optimizer works
```
https://v2.bookbharat.com/_next/image?url=https%3A%2F%2Fv2s.bookbharat.com%2Fstorage%2Fproducts%2Fimage.png
```

### 4. Images display correctly
All product images load without errors in the frontend UI.

## Quick Fix Commands

```bash
# On production server
cd /path/to/bookbharat-backend

# Recreate storage link
rm -rf public/storage
php artisan storage:link

# Fix permissions
sudo chown -R www-data:www-data storage/app/public
chmod -R 755 storage/app/public

# For Apache: enable headers module
sudo a2enmod headers
sudo systemctl restart apache2

# For Nginx: test and reload config
sudo nginx -t
sudo systemctl reload nginx

# Test image access
curl -I https://v2s.bookbharat.com/storage/products/test.png
```

## Support

If images still don't load after these fixes:
1. Check browser console for specific error
2. Check network tab for HTTP status codes
3. Verify storage link exists and points to correct directory
4. Ensure files have correct permissions
5. Check web server error logs

