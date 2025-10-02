# Admin Guide: Frequently Bought Together Management

**Date:** October 1, 2025
**Version:** 1.0
**Status:** ✅ Complete

---

## Table of Contents

1. [Overview](#overview)
2. [Product Associations Management](#product-associations-management)
3. [Bundle Discount Rules](#bundle-discount-rules)
4. [Bundle Analytics](#bundle-analytics)
5. [API Reference](#api-reference)
6. [Common Workflows](#common-workflows)
7. [Troubleshooting](#troubleshooting)

---

## Overview

The Frequently Bought Together (FBT) system gives admins complete control over product associations, discount rules, and performance analytics. This guide covers all administrative features.

### Key Features

- **Product Association Management** - View, create, edit, and delete product associations
- **Discount Rule Configuration** - Set up dynamic bundle discount rules with conditions
- **Analytics Dashboard** - Track bundle performance, conversion rates, and revenue
- **Automated Generation** - Generate associations from order history automatically
- **Manual Curation** - Override automated associations with manual selections
- **A/B Testing** - Compare bundle performance across different configurations

### Access Requirements

- **Role:** Admin
- **Authentication:** Laravel Sanctum token required
- **Base URL:** `http://localhost:8000/api/v1/admin`

---

## Product Associations Management

Product associations define which products are shown together in the "Frequently Bought Together" section.

### List All Associations

**Endpoint:** `GET /admin/product-associations`

**Query Parameters:**
- `product_id` - Filter by main product
- `associated_product_id` - Filter by associated product
- `min_confidence` - Minimum confidence score (0.0-1.0)
- `min_frequency` - Minimum purchase frequency
- `search` - Search by product name or SKU
- `sort_by` - Sort field (default: confidence_score)
- `sort_order` - asc or desc (default: desc)
- `per_page` - Results per page (default: 50)

**Example:**
```bash
curl -X GET "http://localhost:8000/api/v1/admin/product-associations?min_confidence=0.5&per_page=20" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "associations": {
    "data": [
      {
        "id": 1,
        "product_id": 5,
        "associated_product_id": 12,
        "frequency": 15,
        "confidence_score": 0.75,
        "association_type": "bought_together",
        "last_purchased_together": "2025-09-25 14:30:00",
        "product": {
          "id": 5,
          "name": "Harry Potter and the Philosopher's Stone",
          "sku": "HP001"
        },
        "associated_product": {
          "id": 12,
          "name": "Harry Potter and the Chamber of Secrets",
          "sku": "HP002"
        }
      }
    ],
    "total": 150,
    "per_page": 20,
    "current_page": 1
  },
  "statistics": {
    "total_associations": 150,
    "high_confidence": 45,
    "medium_confidence": 75,
    "low_confidence": 30,
    "average_confidence": 0.58,
    "average_frequency": 8.5
  }
}
```

### Get Association Statistics

**Endpoint:** `GET /admin/product-associations/statistics`

Returns overview statistics for all associations.

**Response:**
```json
{
  "success": true,
  "statistics": {
    "total_associations": 150,
    "high_confidence": 45,
    "medium_confidence": 75,
    "low_confidence": 30,
    "average_confidence": 0.58,
    "average_frequency": 8.5,
    "products_with_associations": 75,
    "last_generated": "2025-10-01 02:00:00"
  },
  "top_associations": [
    {
      "id": 1,
      "product_id": 5,
      "associated_product_id": 12,
      "confidence_score": 0.85,
      "frequency": 20
    }
  ],
  "products_without_associations": 25
}
```

### Get Product Associations

**Endpoint:** `GET /admin/product-associations/product/{productId}`

Get all associations for a specific product.

**Example:**
```bash
curl -X GET "http://localhost:8000/api/v1/admin/product-associations/product/5" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Create Manual Association

**Endpoint:** `POST /admin/product-associations`

Manually create a new product association.

**Body:**
```json
{
  "product_id": 5,
  "associated_product_id": 12,
  "frequency": 10,
  "confidence_score": 0.7,
  "create_bidirectional": true
}
```

**Parameters:**
- `product_id` (required) - Main product ID
- `associated_product_id` (required) - Associated product ID (must be different from product_id)
- `frequency` (optional) - Purchase frequency (default: 1)
- `confidence_score` (optional) - Confidence score 0.0-1.0 (default: 0.5)
- `create_bidirectional` (optional) - Create reverse association (default: true)

**Response:**
```json
{
  "success": true,
  "message": "Association created successfully",
  "association": {
    "id": 151,
    "product_id": 5,
    "associated_product_id": 12,
    "frequency": 10,
    "confidence_score": 0.7
  }
}
```

### Update Association

**Endpoint:** `PUT /admin/product-associations/{id}`

Update frequency and confidence score of an existing association.

**Body:**
```json
{
  "frequency": 15,
  "confidence_score": 0.8
}
```

### Delete Association

**Endpoint:** `DELETE /admin/product-associations/{id}`

Delete a single association.

**Example:**
```bash
curl -X DELETE "http://localhost:8000/api/v1/admin/product-associations/151" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Bulk Delete

**Endpoint:** `POST /admin/product-associations/bulk-delete`

Delete multiple associations at once.

**Body:**
```json
{
  "ids": [1, 2, 3, 5, 8]
}
```

### Generate Associations

**Endpoint:** `POST /admin/product-associations/generate`

Trigger automated generation of associations from order history.

**Body:**
```json
{
  "months": 6,
  "min_orders": 2,
  "async": true
}
```

**Parameters:**
- `months` (optional) - Number of months to look back (default: 6)
- `min_orders` (optional) - Minimum orders threshold (default: 2)
- `async` (optional) - Run in background queue (default: true)

**Response (async):**
```json
{
  "success": true,
  "message": "Association generation job queued. This may take a few minutes.",
  "async": true
}
```

**Response (sync):**
```json
{
  "success": true,
  "message": "Associations generated successfully",
  "statistics": {
    "total": 150,
    "high_confidence": 45
  },
  "async": false
}
```

### Clear All Associations

**Endpoint:** `DELETE /admin/product-associations/clear-all`

⚠️ **Warning:** This deletes ALL associations. Use with caution!

---

## Bundle Discount Rules

Configure dynamic discount rules for bundles based on product count, category, customer tier, and more.

### List All Rules

**Endpoint:** `GET /admin/bundle-discount-rules`

**Query Parameters:**
- `is_active` - Filter by active status (true/false)
- `category_id` - Filter by category
- `min_products` - Filter by minimum products
- `discount_type` - Filter by type (percentage/fixed)
- `search` - Search by name
- `sort_by` - Sort field (default: priority)
- `sort_order` - asc or desc (default: desc)
- `per_page` - Results per page (default: 20)

**Example Response:**
```json
{
  "success": true,
  "rules": {
    "data": [
      {
        "id": 1,
        "name": "Buy 2 Get 10% Off",
        "description": "Save 10% when buying 2 or more books",
        "discount_type": "percentage",
        "discount_percentage": 10.0,
        "min_products": 2,
        "max_products": null,
        "category_id": null,
        "customer_tier": null,
        "priority": 80,
        "is_active": true,
        "valid_from": null,
        "valid_until": null
      }
    ]
  }
}
```

### Create Discount Rule

**Endpoint:** `POST /admin/bundle-discount-rules`

**Body:**
```json
{
  "name": "Premium Bundle Discount",
  "description": "15% off for VIP customers buying 3+ books",
  "discount_type": "percentage",
  "discount_percentage": 15.0,
  "min_products": 3,
  "max_products": null,
  "category_id": 5,
  "customer_tier": "vip",
  "min_order_value": 500,
  "max_discount_cap": 200,
  "priority": 90,
  "conditions": {
    "min_book_price": 100,
    "exclude_sale_items": false
  },
  "valid_from": "2025-10-01 00:00:00",
  "valid_until": "2025-12-31 23:59:59",
  "is_active": true
}
```

**Field Descriptions:**

- **name** (required) - Rule name
- **description** (optional) - Rule description shown to customers
- **discount_type** (required) - "percentage" or "fixed"
- **discount_percentage** (required if percentage) - Discount % (0-100)
- **discount_amount** (required if fixed) - Fixed discount amount
- **min_products** (optional) - Minimum products (default: 2)
- **max_products** (optional) - Maximum products (null = unlimited)
- **category_id** (optional) - Restrict to specific category
- **customer_tier** (optional) - bronze, silver, gold, platinum, vip
- **min_order_value** (optional) - Minimum bundle value
- **max_discount_cap** (optional) - Maximum discount amount
- **priority** (optional) - Rule priority 0-100 (default: 50)
- **conditions** (optional) - Additional JSON conditions
- **valid_from** (optional) - Start date/time
- **valid_until** (optional) - End date/time
- **is_active** (optional) - Active status (default: true)

### Update Discount Rule

**Endpoint:** `PUT /admin/bundle-discount-rules/{id}`

Same body structure as create, all fields optional.

### Toggle Rule Status

**Endpoint:** `POST /admin/bundle-discount-rules/{id}/toggle-active`

Quickly enable/disable a rule.

### Test Rule

**Endpoint:** `POST /admin/bundle-discount-rules/{id}/test`

Test if a rule applies to sample data.

**Body:**
```json
{
  "product_count": 3,
  "total_amount": 1500,
  "category_id": 5,
  "customer_tier": "gold"
}
```

**Response:**
```json
{
  "success": true,
  "applies": true,
  "reasons": [],
  "discount_amount": 150.00,
  "final_amount": 1350.00,
  "savings": 150.00,
  "discount_percentage": 10.0
}
```

### Duplicate Rule

**Endpoint:** `POST /admin/bundle-discount-rules/{id}/duplicate`

Create a copy of an existing rule (deactivated by default).

### Delete Rule

**Endpoint:** `DELETE /admin/bundle-discount-rules/{id}`

### Get Available Categories

**Endpoint:** `GET /admin/bundle-discount-rules/categories`

Returns list of categories for rule creation.

### Get Customer Tiers

**Endpoint:** `GET /admin/bundle-discount-rules/customer-tiers`

Returns available customer tier options.

---

## Bundle Analytics

Track bundle performance, conversion rates, revenue, and customer behavior.

### Get Analytics Overview

**Endpoint:** `GET /admin/bundle-analytics`

**Query Parameters:**
- `product_id` - Filter by product ID
- `min_views` - Minimum views
- `min_conversion_rate` - Minimum conversion rate
- `sort_by` - Sort field (default: views)
- `sort_order` - asc or desc (default: desc)
- `per_page` - Results per page (default: 20)

**Response:**
```json
{
  "success": true,
  "bundles": {
    "data": [
      {
        "bundle_id": "bundle_5_12",
        "product_ids_array": [5, 12],
        "views": 450,
        "clicks": 0,
        "add_to_cart": 78,
        "purchases": 35,
        "total_revenue": 52500.50,
        "conversion_rate": 7.78,
        "products": [
          {
            "id": 5,
            "name": "Harry Potter Book 1",
            "sku": "HP001",
            "price": 499.00
          }
        ]
      }
    ]
  }
}
```

### Get Overall Statistics

**Endpoint:** `GET /admin/bundle-analytics/statistics`

**Response:**
```json
{
  "success": true,
  "statistics": {
    "total_bundles": 125,
    "total_views": 15800,
    "total_add_to_cart": 2850,
    "total_purchases": 890,
    "total_revenue": 1245800.00,
    "average_conversion_rate": 5.63,
    "view_to_cart_rate": 18.04,
    "overall_conversion_rate": 5.63,
    "cart_to_purchase_rate": 31.23,
    "average_order_value": 1400.22
  }
}
```

### Get Top Performing Bundles

**Endpoint:** `GET /admin/bundle-analytics/top-bundles?metric=conversion_rate&limit=10`

**Metrics:**
- `views` - Most viewed bundles
- `add_to_cart` - Most added to cart
- `purchases` - Most purchased
- `conversion_rate` - Highest conversion rate
- `total_revenue` - Highest revenue

### Get Bundle Performance

**Endpoint:** `GET /admin/bundle-analytics/performance?bundle_id=bundle_5_12`

Detailed performance metrics for a specific bundle.

**Response:**
```json
{
  "success": true,
  "bundle_id": "bundle_5_12",
  "products": [...],
  "metrics": {
    "views": 450,
    "clicks": 0,
    "add_to_cart": 78,
    "purchases": 35,
    "total_revenue": 52500.50,
    "conversion_rate": 7.78,
    "view_to_cart_rate": 17.33,
    "cart_to_purchase_rate": 44.87,
    "average_order_value": 1500.01
  },
  "last_updated": "2025-10-01 10:30:00"
}
```

### Get Funnel Analysis

**Endpoint:** `GET /admin/bundle-analytics/funnel`

Analyze the complete conversion funnel for all bundles.

**Response:**
```json
{
  "success": true,
  "funnel": {
    "total_bundles": 125,
    "bundles_with_views": 125,
    "bundles_with_add_to_cart": 98,
    "bundles_with_purchases": 72,
    "view_to_cart_drop_rate": 21.60,
    "cart_to_purchase_drop_rate": 26.53,
    "metrics": {
      "total_views": 15800,
      "total_add_to_cart": 2850,
      "total_purchases": 890,
      "view_to_cart_rate": 18.04,
      "overall_conversion_rate": 5.63
    }
  }
}
```

### Get Product Participation

**Endpoint:** `GET /admin/bundle-analytics/product/{productId}/participation`

See all bundles a specific product participates in and their performance.

**Response:**
```json
{
  "success": true,
  "product": {
    "id": 5,
    "name": "Harry Potter Book 1"
  },
  "bundle_count": 8,
  "total_views": 1250,
  "total_add_to_cart": 225,
  "total_purchases": 85,
  "total_revenue": 127500.00,
  "bundles": [...]
}
```

### Export Analytics Data

**Endpoint:** `GET /admin/bundle-analytics/export?format=csv`

Export bundle analytics data.

**Formats:**
- `json` - JSON format (default)
- `csv` - CSV file download

### Compare Bundles

**Endpoint:** `POST /admin/bundle-analytics/compare`

**Body:**
```json
{
  "bundle_ids": ["bundle_5_12", "bundle_8_15", "bundle_3_7"]
}
```

Compare performance across multiple bundles side-by-side.

### Clear Analytics Data

**Endpoint:** `DELETE /admin/bundle-analytics/clear`

**Body (optional):**
```json
{
  "bundle_id": "bundle_5_12"
}
```

Clear all analytics or specific bundle analytics.

---

## API Reference

### Base URLs

- **Admin API:** `http://localhost:8000/api/v1/admin`
- **Public API:** `http://localhost:8000/api/v1`

### Authentication

All admin endpoints require authentication:

```bash
curl -X GET "http://localhost:8000/api/v1/admin/product-associations" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json"
```

### Standard Response Format

**Success:**
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {...}
}
```

**Error:**
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### HTTP Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Common Workflows

### 1. Initial Setup

**Step 1:** Generate associations from order history
```bash
POST /admin/product-associations/generate
{
  "months": 6,
  "min_orders": 2,
  "async": true
}
```

**Step 2:** Create default discount rules
```bash
POST /admin/bundle-discount-rules
{
  "name": "Buy 2 Get 5% Off",
  "discount_type": "percentage",
  "discount_percentage": 5,
  "min_products": 2,
  "is_active": true
}
```

**Step 3:** Check statistics
```bash
GET /admin/product-associations/statistics
GET /admin/bundle-analytics/statistics
```

### 2. Manual Association Curation

**Step 1:** Find products without associations
```bash
GET /admin/product-associations/statistics
# Check "products_without_associations" value
```

**Step 2:** Manually create associations
```bash
POST /admin/product-associations
{
  "product_id": 25,
  "associated_product_id": 30,
  "confidence_score": 0.8,
  "create_bidirectional": true
}
```

**Step 3:** Clear cache
```bash
POST /admin/system/cache/clear
```

### 3. A/B Testing Discount Rules

**Step 1:** Create rule variant A
```bash
POST /admin/bundle-discount-rules
{
  "name": "Test A - 10% Off",
  "discount_percentage": 10,
  "min_products": 2
}
```

**Step 2:** Create rule variant B
```bash
POST /admin/bundle-discount-rules
{
  "name": "Test B - 15% Off",
  "discount_percentage": 15,
  "min_products": 3
}
```

**Step 3:** Monitor analytics
```bash
GET /admin/bundle-analytics/statistics
GET /admin/bundle-analytics/funnel
```

**Step 4:** Disable losing variant
```bash
POST /admin/bundle-discount-rules/{id}/toggle-active
```

### 4. Performance Optimization

**Step 1:** Find low-performing bundles
```bash
GET /admin/bundle-analytics?min_views=100&sort_by=conversion_rate&sort_order=asc
```

**Step 2:** Check product associations
```bash
GET /admin/product-associations/product/{productId}
```

**Step 3:** Update confidence scores
```bash
PUT /admin/product-associations/{id}
{
  "confidence_score": 0.3
}
```

Or delete poor associations:
```bash
DELETE /admin/product-associations/{id}
```

### 5. Category-Specific Bundles

**Step 1:** Create category rule
```bash
POST /admin/bundle-discount-rules
{
  "name": "Fiction Bundle Discount",
  "discount_percentage": 12,
  "min_products": 2,
  "category_id": 5,
  "priority": 90
}
```

**Step 2:** Filter associations by category products
```bash
GET /admin/product-associations?search=fiction
```

**Step 3:** Monitor category bundle performance
```bash
GET /admin/bundle-analytics?product_id=5
```

---

## Troubleshooting

### No Associations Generated

**Problem:** Running generate command produces 0 associations

**Causes:**
1. Not enough order history
2. No delivered orders
3. Orders don't have 2+ products

**Solution:**
```bash
# Check order count
php artisan tinker
>>> App\Models\Order::where('status', 'delivered')->count()

# Lower threshold
POST /admin/product-associations/generate
{
  "months": 12,
  "min_orders": 1
}

# Or use test seeder
php artisan db:seed --class=ProductAssociationsSeeder
```

### Discount Not Applied

**Problem:** Bundle discount not showing in cart

**Causes:**
1. No active discount rules
2. Rule conditions not met
3. Cache not cleared

**Solution:**
```bash
# Check active rules
GET /admin/bundle-discount-rules?is_active=true

# Test rule
POST /admin/bundle-discount-rules/{id}/test
{
  "product_count": 2,
  "total_amount": 1000
}

# Clear cache
POST /admin/system/cache/clear
```

### Analytics Not Tracking

**Problem:** Bundle views/purchases not tracked

**Causes:**
1. Database table missing
2. Frontend not calling correct endpoint
3. Error logs show failures

**Solution:**
```bash
# Check table exists
php artisan tinker
>>> DB::table('bundle_analytics')->count()

# Check logs
tail -f storage/logs/laravel.log | grep bundle

# Manual test
php artisan tinker
>>> DB::table('bundle_analytics')->insert([
    'bundle_id' => 'test',
    'product_ids' => '[1,2]',
    'views' => 1,
    'created_at' => now(),
    'updated_at' => now()
])
```

### Low Conversion Rate

**Problem:** Bundles have high views but low purchases

**Possible Actions:**

1. **Increase Discount**
```bash
PUT /admin/bundle-discount-rules/{id}
{
  "discount_percentage": 15
}
```

2. **Improve Associations**
```bash
# Find better product matches
POST /admin/product-associations/generate
{
  "months": 3
}
```

3. **Analyze Funnel**
```bash
GET /admin/bundle-analytics/funnel
# Check where drop-off occurs
```

### Cache Issues

**Problem:** Changes not reflecting on frontend

**Solution:**
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Or via API
POST /admin/system/cache/clear
```

---

## Best Practices

### 1. Association Management

✅ **DO:**
- Run generation weekly or monthly
- Review and curate top associations manually
- Set confidence threshold >= 0.3
- Remove low-performing associations

❌ **DON'T:**
- Clear all associations without backup
- Set confidence too high (filters out valid matches)
- Ignore products without associations

### 2. Discount Rules

✅ **DO:**
- Start with conservative discounts (5-10%)
- Use priority to control rule application order
- Set expiration dates for promotional rules
- Test rules before activating

❌ **DON'T:**
- Create conflicting rules with same priority
- Offer discounts higher than margin
- Forget to set max_discount_cap for percentage rules

### 3. Analytics Monitoring

✅ **DO:**
- Check funnel weekly
- Export data monthly for reports
- Monitor top bundles for insights
- Compare variants in A/B tests

❌ **DON'T:**
- Clear analytics without export
- Ignore low conversion signals
- Make decisions without data

### 4. Performance

✅ **DO:**
- Use async generation for large datasets
- Paginate large result sets
- Cache frequently accessed data
- Schedule generation during low-traffic hours

❌ **DON'T:**
- Run sync generation on live site
- Fetch all associations at once
- Skip cache clearing after changes

---

## Command Reference

### Artisan Commands

```bash
# Generate associations
php artisan associations:generate

# With options
php artisan associations:generate --months=12 --min-orders=3 --async

# Seed test data
php artisan db:seed --class=ProductAssociationsSeeder

# Check scheduled tasks
php artisan schedule:list

# Run schedule manually
php artisan schedule:run

# Clear cache
php artisan cache:clear
```

### Database Queries

```bash
php artisan tinker

# Count associations
>>> App\Models\ProductAssociation::where('association_type', 'bought_together')->count()

# Count high confidence
>>> App\Models\ProductAssociation::where('confidence_score', '>=', 0.5)->count()

# Get bundle analytics
>>> DB::table('bundle_analytics')->orderBy('purchases', 'desc')->take(10)->get()

# Check discount rules
>>> App\Models\BundleDiscountRule::where('is_active', true)->get()
```

---

## Support & Resources

### Documentation Files
- `FREQUENTLY_BOUGHT_TOGETHER_ANALYSIS.md` - Complete system analysis
- `FREQUENTLY_BOUGHT_TOGETHER_FIXES.md` - Implementation guide
- `ADMIN_FBT_GUIDE.md` - This file

### Related Code Files
- `app/Http/Controllers/Admin/ProductAssociationController.php`
- `app/Http/Controllers/Admin/BundleDiscountRuleController.php`
- `app/Http/Controllers/Admin/BundleAnalyticsController.php`
- `app/Jobs/GenerateProductAssociations.php`
- `app/Services/ProductRecommendationService.php`
- `routes/admin.php`

### Database Tables
- `product_associations` - Product relationship data
- `bundle_discount_rules` - Discount configuration
- `bundle_analytics` - Performance metrics

---

**Last Updated:** October 1, 2025
**Maintained By:** Development Team
**Version:** 1.0
