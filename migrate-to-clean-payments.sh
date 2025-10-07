#!/bin/bash

##
## CLEAN PAYMENT MIGRATION SCRIPT
## Migrates from messy dual-table to clean single-table system
##

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║  Clean Payment Gateway Migration                          ║"
echo "║  Single Table, Single Source of Truth                     ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Step 1: Confirm
echo -e "${YELLOW}⚠️  WARNING: This will create a new payment_methods table${NC}"
echo ""
echo "This script will:"
echo "  1. Create new payment_methods table"
echo "  2. Seed with default data (Razorpay, Cashfree, COD)"
echo "  3. Optionally migrate data from old tables"
echo ""
read -p "Continue? (y/N): " confirm

if [[ $confirm != [yY] ]]; then
    echo -e "${RED}❌ Migration cancelled${NC}"
    exit 1
fi

# Step 2: Run migration
echo ""
echo -e "${BLUE}📦 Running migration...${NC}"
php artisan migrate --path=database/migrations/2025_10_07_074250_create_payment_methods_table.php

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Migration failed${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Migration completed${NC}"

# Step 3: Seed data
echo ""
echo -e "${BLUE}🌱 Seeding payment methods...${NC}"
php artisan db:seed --class=PaymentMethodSeeder

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Seeding failed${NC}"
    exit 1
fi

# Step 4: Show results
echo ""
echo -e "${GREEN}✅ Setup complete!${NC}"
echo ""
echo "Payment methods created:"
echo "  • Razorpay: ENABLED (primary)"
echo "  • Cashfree: DISABLED (fallback)"
echo "  • COD: ENABLED"
echo ""
echo -e "${YELLOW}📝 Next steps:${NC}"
echo "  1. Update .env with your credentials:"
echo "     RAZORPAY_KEY_ID=your_key"
echo "     RAZORPAY_KEY_SECRET=your_secret"
echo ""
echo "  2. Test the API:"
echo "     curl http://localhost:8000/api/v1/payment/gateways"
echo ""
echo "  3. Update PaymentController to use PaymentMethod model"
echo ""
echo "  4. Update admin panel UI"
echo ""
echo -e "${GREEN}🎉 All done!${NC}"
