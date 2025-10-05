# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BookBharat Backend - Laravel 12 REST API for a comprehensive e-commerce platform specializing in book sales. Provides API endpoints for customer-facing operations and admin management.

## Development Commands

```bash
# Install dependencies
composer install

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Seed database with test data
php artisan db:seed

# Run migrations and seed (fresh start)
php artisan migrate:fresh --seed

# Development server with all services (server, queue, logs, vite)
composer dev

# Run server only (port 8000)
php artisan serve

# Queue worker
php artisan queue:listen --tries=1

# View logs in real-time
php artisan pail --timeout=0

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run tests
composer test
# or
php artisan test

# Code formatting
./vendor/bin/pint

# Generate new model with migration
php artisan make:model ModelName -m

# Generate controller
php artisan make:controller Api/ControllerName
php artisan make:controller Admin/ControllerName

# Generate migration
php artisan make:migration create_table_name
```

## API Architecture

**Base URL:** `/api/v1`

**Authentication:** Laravel Sanctum with Bearer token
- Token returned on login/register
- Include in Authorization header: `Authorization: Bearer {token}`

**Route Files:**
- `routes/api.php`: Customer-facing API endpoints
- `routes/admin.php`: Admin-only endpoints with role middleware
- `routes/payment.php`: Payment gateway webhooks
- `routes/console.php`: Artisan commands
- `routes/web.php`: Minimal web routes

**API Response Format:**
```json
{
  "success": true,
  "data": {...},
  "message": "Success message"
}
```

**Error Response Format:**
```json
{
  "success": false,
  "message": "Error message",
  "errors": {...} // validation errors only
}
```

## Controller Organization

### API Controllers (`app/Http/Controllers/Api/`)
Customer-facing endpoints:
- **AuthController**: Registration, login, password reset, profile management
- **ProductController**: Product listing, search, filters, details, related products
- **CategoryController**: Category listing and details
- **CartController**: Cart management (add, update, remove, clear)
- **OrderController**: Order creation, listing, details, cancellation
- **ShippingController**: Shipping calculation, pincode check, zone lookup
- **PaymentController**: Payment initiation, verification
- **AddressController**: User address management
- **WishlistController**: Wishlist operations
- **ReviewController**: Product reviews and ratings
- **InvoiceController**: Invoice generation and download (PDF)
- **MultiCarrierShippingController**: Multi-carrier shipping integration
- **ContactController**: Contact form submissions
- **NewsletterController**: Newsletter subscriptions
- **StaticPageController**: CMS pages (About, Terms, Privacy)
- **FaqController**: FAQ management

### Admin Controllers (`app/Http/Controllers/Admin/`)
Admin-only endpoints with role-based access:
- **DashboardController**: Analytics, sales data, charts, KPIs
- **OrderController**: Order management, status updates, refunds
- **ProductController**: Product CRUD, bulk operations, image management
- **CategoryController**: Category hierarchy management
- **ShippingConfigController**: Shipping zones, rates, weight slabs, pincode management
- **DeliveryOptionController**: Delivery options (Standard, Express) with surcharges
- **ShippingInsuranceController**: Insurance plans configuration
- **BundleDiscountController**: Bundle discount rules (frequently bought together)
- **CouponController**: Coupon/promo code management
- **UserController**: User management, role assignment
- **SettingsController**: System settings, payment gateways, email templates
- **ConfigurationController**: General configurations (shipping, tax, etc.)
- **ReportController**: Sales reports, analytics exports
- **InventoryController**: Stock management, low stock alerts

## Key Models & Relationships

### Core Models (`app/Models/`)

**User**
- `hasMany` Orders, Addresses, Reviews, CartItems, Wishlists
- `belongsToMany` Roles (Spatie Permission)
- Fields: name, email, password, phone, email_verified_at

**Product**
- `belongsTo` Category
- `hasMany` ProductImages, ProductVariants, OrderItems, Reviews, Wishlists, CartItems
- `belongsToMany` RelatedProducts (through ProductAssociation)
- Fields: name, slug, description, price, sale_price, sku, isbn, author, publisher, stock_quantity, weight

**Category**
- `belongsTo` parent (self-referencing)
- `hasMany` children (self-referencing), products
- Fields: name, slug, description, parent_id, is_active

**Order**
- `belongsTo` User, DeliveryOption
- `hasMany` OrderItems, Payments, Shipments
- `hasOne` Invoice
- Fields: order_number, status, payment_status, total_amount, shipping_address, billing_address

**Cart**
- `belongsTo` User
- `hasMany` CartItems
- Fields: user_id, session_id (for guest carts)

**Address**
- `belongsTo` User
- Fields: type, name, phone, address_line_1, address_line_2, city, state, pincode, country, is_default

### Shipping Models

**ShippingZone**
- Zones: A (Same City), B (Within State), C (Metro), D (Rest of India), E (Northeast/Special)
- Fields: code, name, description

**PincodeZone**
- Maps pincodes to shipping zones
- `belongsTo` ShippingZone
- Fields: pincode, zone_code, state, city, is_serviceable, cod_available

**ShippingWeightSlab**
- Weight ranges for rate calculation
- Fields: min_weight, max_weight, zone, rate
- Example: 0-500g, 500g-1kg, 1kg-2kg

**DeliveryOption**
- Standard, Express, Same Day delivery
- Fields: name, description, delivery_window, base_cost, zone_based_pricing, is_active

**ShippingInsurance**
- Optional insurance based on order value
- Fields: name, min_order_value, max_order_value, cost_type, cost_value, coverage_percentage

### Payment Models

**Payment**
- `belongsTo` Order, User
- Fields: gateway, transaction_id, amount, status, payment_method

**PaymentConfiguration**
- Gateway configurations (Razorpay, Cashfree, PayU, PhonePe)
- Fields: gateway, is_enabled, credentials (encrypted), test_mode

### Other Important Models

**Review**
- `belongsTo` Product, User
- Fields: rating, title, comment, verified_purchase, helpful_count

**Wishlist**
- `belongsTo` Product, User
- Fields: priority, notes, added_at

**Coupon**
- Fields: code, type, value, min_order_value, max_discount, usage_limit, valid_from, valid_to

**BundleDiscountRule**
- Frequently bought together discounts
- `belongsToMany` Products
- Fields: name, discount_type, discount_value, min_products, max_products

**Shipment**
- `belongsTo` Order, ShippingCarrier
- `hasMany` ShipmentTrackingEvents
- Fields: tracking_number, carrier, status, estimated_delivery, actual_delivery

**Invoice**
- `belongsTo` Order
- Fields: invoice_number, invoice_date, due_date, status, pdf_path

## Services (`app/Services/`)

Encapsulate business logic separate from controllers:

- **ShippingService**: Complex shipping calculations, zone determination, rate lookup
- **PaymentService**: Payment gateway integrations, transaction processing
- **OrderService**: Order placement workflow, status management
- **ProductSearchService**: Advanced search with filters, sorting
- **BundleDiscountService**: Bundle discount calculations
- **EmailService**: Transactional email sending
- **InvoiceService**: PDF invoice generation using DomPDF
- **AnalyticsService**: Dashboard metrics and reporting

## Middleware (`app/Http/Middleware/`)

- **IsAdmin**: Verify admin role using Spatie Permission
- **Authenticate**: Sanctum token validation
- **Cors**: Handle CORS for frontend access
- **RateLimiter**: API rate limiting (60/min public, 120/min authenticated)

## Key Packages

- **laravel/sanctum**: API authentication
- **spatie/laravel-permission**: Role-based access control (roles: admin, customer)
- **spatie/laravel-activitylog**: Audit logging for admin actions
- **laravel/scout**: Product search (with Algolia)
- **intervention/image**: Image upload and manipulation
- **barryvdh/laravel-dompdf**: PDF generation for invoices
- **razorpay/razorpay**: Razorpay payment gateway
- **cashfree/cashfree-pg**: Cashfree payment gateway
- **pusher/pusher-php-server**: Real-time notifications

## Database

**Connection:** MySQL (production) / SQLite (default development)

**Migrations:** `database/migrations/`
- Naming: `YYYY_MM_DD_HHMMSS_create_table_name_table.php`
- Always create foreign key constraints with `->constrained()->cascadeOnDelete()`

**Seeders:** `database/seeders/`
- DatabaseSeeder: Main seeder
- Create test data for development

**Factories:** `database/factories/`
- Generate fake data for testing

## Shipping Calculation Flow

1. **Get cart items** → Calculate total weight
2. **Check delivery pincode** → Determine shipping zone (A-E)
3. **Find weight slab** → Based on total weight
4. **Get base rate** → From ShippingWeightSlab for zone + weight
5. **Apply delivery option surcharge** → If Express/Same Day selected
6. **Calculate insurance** → If opted, based on order value
7. **Check free shipping threshold** → Waive if order value exceeds threshold
8. **Return shipping cost** + estimated delivery days

**Admin CSV Bulk Import:**
- Pincodes: pincode, state, city, zone_code, is_serviceable, cod_available
- Weight Slabs: min_weight, max_weight, zone_a, zone_b, zone_c, zone_d, zone_e
- Shipping Rates: Similar structure with zone-wise pricing

## Payment Gateway Integration

**Supported Gateways:**
- Razorpay (Primary)
- Cashfree
- PayU
- PhonePe
- COD (Cash on Delivery)

**Payment Flow:**
1. Frontend initiates payment → `POST /payment/initiate`
2. Backend creates order in gateway → Returns payment URL
3. User completes payment on gateway
4. Gateway redirects to callback URL → `GET /payment/callback/{gateway}`
5. Webhook verification → `POST /payment/webhook/{gateway}`
6. Update order payment status
7. Send confirmation email

**Webhook Security:**
- Verify signature using gateway secret
- Prevent replay attacks with timestamp check
- Log all webhook events to CarrierApiLog

## Environment Configuration

**Required .env variables:**
```bash
APP_URL=http://localhost:8000
APP_FRONTEND_URL=http://localhost:3000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bookbharat
DB_USERNAME=root
DB_PASSWORD=

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:3000

# Razorpay
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=

# Cashfree
CASHFREE_APP_ID=
CASHFREE_SECRET_KEY=
CASHFREE_MODE=test

# Scout (Algolia)
SCOUT_DRIVER=algolia
ALGOLIA_APP_ID=
ALGOLIA_SECRET=

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525

# Queue
QUEUE_CONNECTION=database

# Pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
```

## Testing

**Test Structure:** `tests/Feature/` and `tests/Unit/`

**Run tests:**
```bash
php artisan test
php artisan test --filter ProductTest
```

**Factory usage in tests:**
```php
$user = User::factory()->create();
$product = Product::factory()->create();
```

## Common Development Tasks

### Adding a new API endpoint:

1. Create controller method or use existing controller
2. Add route to `routes/api.php` or `routes/admin.php`
3. Add validation rules using FormRequest or inline validation
4. Return standardized JSON response
5. Test with Postman or write feature test

### Creating a new model relationship:

1. Add migration for foreign key
2. Define relationship methods in both models
3. Use eager loading in queries: `Product::with('category')->get()`

### Implementing a new payment gateway:

1. Add gateway config to PaymentConfiguration model
2. Create service class in `app/Services/Payment/`
3. Add routes to `routes/payment.php` for callback and webhook
4. Implement signature verification
5. Handle payment status updates
6. Add gateway to PaymentController

### Bulk import data:

1. Create CSV with required columns
2. Use admin endpoint: `POST /admin/shipping/pincodes/bulk-import`
3. Parse CSV, validate rows
4. Use DB transactions for atomicity
5. Return success/error summary

### Debugging:

```bash
# View logs
php artisan pail

# Tail log file
tail -f storage/logs/laravel.log

# Database queries
DB::enableQueryLog();
// ... queries ...
dd(DB::getQueryLog());

# Dump and die
dd($variable);

# Dump without dying
dump($variable);
```

## API Documentation

See `../API_DOCUMENTATION.md` for complete endpoint reference.

**Key endpoints:**
- `POST /api/v1/auth/login` - User login
- `GET /api/v1/products` - List products with filters
- `POST /api/v1/cart/add` - Add to cart
- `POST /api/v1/orders` - Create order
- `POST /api/v1/shipping/calculate` - Calculate shipping
- `GET /api/v1/admin/dashboard/overview` - Admin dashboard

## Security Best Practices

- Always use `auth:sanctum` middleware for protected routes
- Validate all user input with FormRequest or inline validation
- Use `Hash::make()` for passwords, never store plain text
- Rate limit sensitive endpoints (login, registration)
- Verify payment webhook signatures
- Sanitize user-generated content (reviews, addresses)
- Use HTTPS in production
- Keep `.env` file secure, never commit to git
- Regularly update dependencies: `composer update`

## Performance Optimization

- Use eager loading to prevent N+1 queries: `Product::with(['category', 'images'])`
- Cache expensive queries: `Cache::remember('products.featured', 3600, fn() => Product::featured()->get())`
- Index frequently queried columns (sku, slug, email)
- Queue heavy jobs: invoice generation, email sending
- Optimize images before storage
- Use pagination for large result sets
- Enable Redis for session and cache in production
