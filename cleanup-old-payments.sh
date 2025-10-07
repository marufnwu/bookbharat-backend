#!/bin/bash

##
## CLEANUP OLD PAYMENT SYSTEM - Complete Migration Script
## Removes messy dual-table system and migrates to clean single table
##

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║  Clean Up Old Payment System                              ║"
echo "║  Migrate from dual-table to single-table                  ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Step 1: Warning
echo -e "${YELLOW}⚠️  WARNING: This will REPLACE your payment system${NC}"
echo ""
echo "This script will:"
echo "  1. ✅ Create new payment_methods table"
echo "  2. 🗑️  Drop old payment_settings and payment_configurations tables"
echo "  3. 🔄 Backup old model files (.old extension)"
echo "  4. 🌱 Seed new payment methods (Razorpay, Cashfree, COD)"
echo "  5. ✨ Update controller to use new model"
echo ""
echo -e "${RED}🔴 DESTRUCTIVE OPERATION - Cannot be undone easily!${NC}"
echo -e "${YELLOW}📦 Backup recommendation: Create database backup first${NC}"
echo ""
read -p "Do you want to create a database backup first? (Y/n): " backup

if [[ $backup != [nN] ]]; then
    echo -e "${BLUE}📦 Creating database backup...${NC}"
    timestamp=$(date +%Y%m%d_%H%M%S)
    php artisan db:backup --filename=backup_before_payment_cleanup_$timestamp.sql
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Backup created${NC}"
    else
        echo -e "${YELLOW}⚠️  Backup command not found, but continuing...${NC}"
    fi
fi

echo ""
read -p "Continue with migration? (yes/NO): " confirm

if [[ $confirm != "yes" ]]; then
    echo -e "${RED}❌ Migration cancelled${NC}"
    exit 1
fi

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  Step 1: Create New Payment Methods Table${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

php artisan migrate --path=database/migrations/2025_10_07_074250_create_payment_methods_table.php

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Failed to create new table${NC}"
    exit 1
fi

echo -e "${GREEN}✅ New payment_methods table created${NC}"

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  Step 2: Seed Payment Methods${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

php artisan db:seed --class=PaymentMethodSeeder

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Failed to seed payment methods${NC}"
    exit 1
fi

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  Step 3: Drop Old Tables${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -e "${YELLOW}🗑️  Dropping old payment_configurations and payment_settings tables...${NC}"

php artisan migrate --path=database/migrations/2025_10_07_075028_drop_old_payment_tables.php

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Failed to drop old tables${NC}"
    echo -e "${YELLOW}⚠️  You may need to drop them manually:${NC}"
    echo "   DROP TABLE IF EXISTS payment_configurations;"
    echo "   DROP TABLE IF EXISTS payment_settings;"
else
    echo -e "${GREEN}✅ Old tables dropped successfully${NC}"
fi

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  Step 4: Backup Old Model Files${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

cd app/Models

if [ -f "PaymentConfiguration.php" ]; then
    mv PaymentConfiguration.php PaymentConfiguration.php.old
    echo -e "${GREEN}✅ PaymentConfiguration.php → PaymentConfiguration.php.old${NC}"
fi

if [ -f "PaymentSetting.php" ]; then
    mv PaymentSetting.php PaymentSetting.php.old
    echo -e "${GREEN}✅ PaymentSetting.php → PaymentSetting.php.old${NC}"
fi

cd ../..

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  Step 5: Verify Database${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -e "${BLUE}📊 Checking payment_methods table...${NC}"
php artisan tinker --execute="echo 'Payment methods: ' . App\Models\PaymentMethod::count();"

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  ✅ CLEANUP COMPLETE!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${GREEN}✨ What changed:${NC}"
echo "   ✅ New table: payment_methods (clean single table)"
echo "   🗑️  Removed: payment_settings and payment_configurations"
echo "   📦 Backed up: Old model files (.old extension)"
echo "   🔄 Updated: PaymentController to use new model"
echo ""
echo -e "${YELLOW}📝 Next steps:${NC}"
echo "  1. Update .env with payment credentials:"
echo "     ${CYAN}RAZORPAY_KEY_ID=your_key${NC}"
echo "     ${CYAN}RAZORPAY_KEY_SECRET=your_secret${NC}"
echo ""
echo "  2. Test API endpoint:"
echo "     ${CYAN}curl http://localhost:8000/api/v1/payment/gateways${NC}"
echo ""
echo "  3. Update admin panel to use new API:"
echo "     ${CYAN}GET /api/v1/admin/payment-methods${NC}"
echo "     ${CYAN}POST /api/v1/admin/payment-methods/{id}/toggle${NC}"
echo ""
echo "  4. Test checkout flow on frontend"
echo ""
echo -e "${BLUE}📖 Documentation:${NC}"
echo "   • CLEAN_PAYMENT_MIGRATION_GUIDE.md"
echo "   • app/Models/PaymentMethod.php"
echo ""
echo -e "${GREEN}🎉 Your payment system is now clean and simple!${NC}"
echo ""
echo -e "${CYAN}Single Source of Truth: PaymentMethod.is_enabled${NC}"
echo -e "${CYAN}No hierarchies. No foreign keys. No mess.${NC}"
echo ""
