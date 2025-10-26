# Hardcoded Values Refactoring Plan

## Phase 1: Database Schema & Seeder Updates

### 1.1 Update AdminSetting Seeder
Add new configuration keys:
- `min_order_amount` (default: 99)
- `currency` (default: 'INR')
- `currency_symbol` (default: '₹')
- `max_cart_quantity` (default: 99)
- `max_file_size` (default: 5120 KB)
- `max_image_width` (default: 1920)
- `max_image_height` (default: 1920)
- `max_stock_level` (default: 1000)
- `max_discount_amount_coupon` (default: 1000)
- `cod_max_order_amount` (default: 50000)

### 1.2 Create New Seeder for Zone Thresholds
Create `ZoneThresholdsSeeder.php` to populate `shipping_zones` table with:
- Zone A: threshold 499, enabled true
- Zone B: threshold 699, enabled true
- Zone C: threshold 999, enabled true
- Zone D: threshold 1499, enabled true
- Zone E: threshold 2499, enabled true

## Phase 2: Backend Refactoring

### 2.1 ConfigurationController.php
Update `getSiteConfig()` to read from database:

```php
// Before
'min_order_amount' => 99,
'free_shipping_threshold' => 499

// After
'min_order_amount' => AdminSetting::get('min_order_amount', 99),
'free_shipping_threshold' => AdminSetting::get('free_shipping_threshold', 500),
'currency' => AdminSetting::get('currency', 'INR'),
'currency_symbol' => AdminSetting::get('currency_symbol', '₹'),
```

### 2.2 ShippingService.php
Update `getFreeShippingConfig()` to:
1. First try ShippingZone table
2. Fallback to AdminSetting
3. Last resort: hardcoded defaults

### 2.3 ShippingConfigController.php
Remove duplicate threshold definitions, use ShippingService methods

### 2.4 FAQController.php
Update FAQ answer to read from AdminSetting:
```php
$threshold = AdminSetting::get('free_shipping_threshold', 499);
'answer' => "We offer free shipping on orders above ₹{$threshold}..."
```

### 2.5 ImageUploadService.php
Read limits from AdminSetting:
```php
$maxWidth = AdminSetting::get('max_image_width', 1920);
$maxHeight = AdminSetting::get('max_image_height', 1920);
$maxSize = AdminSetting::get('max_file_size', 5120);
```

### 2.6 CartController.php
Validate against database value:
```php
$maxQuantity = AdminSetting::get('max_cart_quantity', 99);
'quantity' => "required|integer|min:1|max:{$maxQuantity}"
```

## Phase 3: Admin UI Components

### 3.1 Create GeneralSettings.tsx
Fields for:
- Min Order Amount
- Currency Settings
- Max Cart Quantity
- File Upload Limits
- Stock Management Settings

### 3.2 Create ShippingSettings.tsx (if not exists)
Fields for:
- Zone-based thresholds (A-E)
- Free shipping enable/disable per zone

### 3.3 Create ProductSettings.tsx (if not exists)
Fields for:
- Max Stock Level
- Image Upload Limits
- Product Limits

### 3.4 Create PaymentSettings.tsx (if not exists)
Fields for:
- COD Max Order Amount
- Payment Gateway Limits

## Phase 4: Content Management

### 4.1 Create ContentManagement.tsx
Editable fields for:
- Hero Title
- Hero Subtitle
- CTA Buttons Text
- Trust Badges
- Footer Text

### 4.2 Update HomepageConfig to read from database
Store hero content in `site_configurations` table

### 4.3 Update FAQController
Store FAQ questions/answers in database

## Phase 5: Implementation Steps

### Step 1: Database & Seeds
1. Update AdminSettingsSeeder
2. Create ZoneThresholdsSeeder
3. Run seeders
4. Verify data in database

### Step 2: Backend Refactoring
1. Update ConfigurationController
2. Update ShippingService
3. Update all service classes
4. Update controllers to use dynamic values
5. Test all endpoints

### Step 3: Admin UI
1. Create settings components
2. Add routes to settings page
3. Implement CRUD operations
4. Add validation
5. Test admin UI

### Step 4: Frontend Integration
1. Update frontend to consume new API
2. Test dynamic content display
3. Verify all hardcoded values removed

### Step 5: Documentation
1. Document new settings
2. Create migration guide
3. Update API documentation
4. Create admin user guide

## Testing Checklist

- [ ] All seeders run successfully
- [ ] Database contains all new settings
- [ ] ConfigurationController returns dynamic values
- [ ] ShippingService reads from database
- [ ] Admin UI loads settings
- [ ] Admin UI saves settings
- [ ] Frontend displays dynamic values
- [ ] Cart validation uses dynamic max quantity
- [ ] Image upload uses dynamic limits
- [ ] Shipping calculation uses zone thresholds
- [ ] No hardcoded values remain in code

## Migration Path

1. Add new settings to database
2. Deploy backend with backward compatibility
3. Test on staging
4. Deploy to production
5. Monitor for 1 week
6. Remove old hardcoded fallbacks

## Rollback Plan

- Keep hardcoded values as fallbacks initially
- If issues arise, can revert to previous version
- Database changes are additive (no deletions)
- Can disable new features via feature flags

