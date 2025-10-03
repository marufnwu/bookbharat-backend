# Database Seeding Guide

This guide explains how to seed the BookBharat database for different environments.

## Overview

The BookBharat backend provides two distinct seeding strategies:

1. **Production Seeding** - Essential data only (roles, permissions, configurations)
2. **Development Seeding** - Full test data (users, products, orders, etc.)

## Available Commands

### 1. Development Seeding

```bash
php artisan db:seed-dev
```

**Options:**
- `--fresh` : Wipe the database before seeding (optional)
- `--force` : Force in production environment (not recommended)

**What it includes:**
- All essential configurations
- 6+ test user accounts
- 10+ sample products with images
- 7+ categories with hierarchy
- Sample orders and cart items
- Test coupons and discounts
- Product associations for recommendations
- Complete shipping configurations

**Test Accounts Created:**
| Email | Password | Role |
|-------|----------|------|
| admin@example.com | password | Admin |
| customer@example.com | password | Customer |
| test@example.com | password | Customer |
| demo@example.com | password | Customer |
| john@example.com | password | Customer |
| jane@example.com | password | Customer |

### 2. Production Seeding

```bash
php artisan db:seed-prod
```

**Options:**
- `--fresh` : Wipe the database before seeding (requires triple confirmation in production)

**What it includes:**
- Roles & Permissions
- Payment gateway configurations
- Shipping zones and carriers
- Admin settings
- Hero configuration
- Super admin account only
- NO test data

**Default Admin Account:**
- Email: Set via `ADMIN_EMAIL` env variable (default: admin@bookbharat.com)
- Password: Set via `ADMIN_PASSWORD` env variable (default: ChangeMe@123!)

### 3. Environment-Based Auto-Detection

```bash
php artisan db:seed
```

This command automatically detects your environment:
- **Production**: Runs ProductionSeeder
- **Local/Development**: Runs DevelopmentSeeder

## Fresh Migrations with Seeding

### Development Environment

```bash
# Complete reset with test data
php artisan db:seed-dev --fresh

# Or using migrate:fresh
php artisan migrate:fresh --seed
```

### Production Environment

```bash
# DANGER: This will DELETE all data!
php artisan db:seed-prod --fresh
# Requires typing: "DELETE PRODUCTION DATABASE" to confirm
```

## Seeders Directory Structure

```
database/seeders/
├── DatabaseSeeder.php           # Main seeder with environment detection
├── DevelopmentSeeder.php        # Development environment orchestrator
├── ProductionSeeder.php         # Production environment orchestrator
│
├── Core System/
│   ├── RolePermissionSeeder.php # Roles and permissions
│   └── AdminSettingsSeeder.php  # Admin configurations
│
├── Payment/
│   ├── PaymentConfigurationSeeder.php
│   ├── PaymentSettingSeeder.php
│   ├── PaymentAdminSettingsSeeder.php
│   └── EnablePaymentGatewaysSeeder.php
│
├── Shipping/
│   ├── DefaultWarehouseSeeder.php
│   ├── ShippingWeightSlabSeeder.php
│   ├── ShippingZoneSeeder.php
│   ├── ShippingCarrierSeeder.php
│   ├── ShippingInsuranceSeeder.php
│   ├── PinCodeSeeder.php
│   └── PincodeZoneSeeder.php
│
├── Marketing/
│   ├── BundleDiscountRuleSeeder.php
│   ├── CouponsTableSeeder.php
│   └── ProductAssociationsSeeder.php
│
├── Content/
│   └── HeroConfigurationSeeder.php
│
└── Test Data/
    └── SystemTestSeeder.php     # Complete test data
```

## Individual Seeders

### Essential Seeders (Production)

1. **RolePermissionSeeder**
   - Creates: admin, customer, vendor roles
   - Sets up all permissions

2. **PaymentConfigurationSeeder**
   - Configures: Razorpay, Cashfree, PhonePe, PayU
   - Sets test/production modes

3. **ShippingWeightSlabSeeder**
   - Creates weight-based shipping tiers
   - Must run before ShippingZoneSeeder

4. **ShippingZoneSeeder**
   - Defines shipping zones (A, B, C, D)
   - Sets rates per weight slab

5. **AdminSettingsSeeder**
   - Site configuration
   - Email settings
   - Default values

### Test Data Seeders (Development Only)

1. **SystemTestSeeder**
   - Creates categories (Fiction, Non-Fiction, etc.)
   - Adds 10+ sample products
   - Creates customer groups
   - Adds test coupons

2. **CouponsTableSeeder**
   - WELCOME10 - 10% off for new customers
   - SAVE20 - ₹20 off on ₹100+
   - FREESHIP - Free shipping

3. **ProductAssociationsSeeder**
   - Creates "frequently bought together" relationships
   - Sets up product recommendations

## Production Deployment Checklist

After running production seeding:

- [ ] Change super admin password immediately
- [ ] Update payment gateway API keys in admin panel
- [ ] Configure shipping carrier accounts
- [ ] Set up SMTP in .env file
- [ ] Import product catalog
- [ ] Import complete pincode database
- [ ] Configure SSL certificate
- [ ] Set APP_DEBUG=false in .env
- [ ] Enable rate limiting
- [ ] Configure CORS settings
- [ ] Set up automated backups
- [ ] Configure error tracking (Sentry, etc.)
- [ ] Set up log rotation

## Environment Variables for Production

Add to your `.env` file:

```env
# Admin Account
ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=SecurePassword123!

# Set to production
APP_ENV=production
APP_DEBUG=false

# Database
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password
```

## Troubleshooting

### Common Issues

1. **Migration fails with duplicate table error**
   ```bash
   php artisan db:wipe --force
   php artisan migrate --seed
   ```

2. **Seeder class not found**
   ```bash
   composer dump-autoload
   ```

3. **Permission denied errors**
   - Ensure database user has CREATE, DROP, ALTER permissions
   - Check file permissions for storage/ and bootstrap/cache/

4. **Out of memory during seeding**
   ```bash
   php -d memory_limit=512M artisan db:seed
   ```

### Quick Commands

```bash
# Development quick start
php artisan migrate:fresh --seed

# Production minimal setup
php artisan migrate
php artisan db:seed-prod

# Reset everything in development
php artisan db:seed-dev --fresh

# Check current environment
php artisan tinker
>>> app()->environment()
```

## Safety Features

### Production Safeguards

1. **Triple confirmation** for fresh migrations in production
2. **Environment warnings** when using dev commands in production
3. **Typed confirmation** required ("DELETE PRODUCTION DATABASE")
4. **Force flags** required for dangerous operations

### Development Features

1. **Quick reset** with --fresh flag
2. **Automatic test data** creation
3. **Sample orders** for testing checkout flow
4. **Multiple test accounts** with different roles

## Best Practices

1. **Never use development seeders in production**
2. **Always backup before running fresh migrations**
3. **Test migrations in staging first**
4. **Use environment-specific .env files**
5. **Keep production passwords in secure vault**
6. **Run seeders in transaction when possible**
7. **Monitor seeding execution time**
8. **Log seeding operations in production**

## Support

For issues or questions about seeding:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Run with verbose output: `php artisan db:seed -vvv`
3. Test database connection: `php artisan tinker` then `DB::connection()->getPdo();`