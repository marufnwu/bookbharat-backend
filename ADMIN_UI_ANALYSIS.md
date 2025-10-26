# Admin UI Hardcoded Values Analysis

## ‚úÖ Current Status

**Admin UI does NOT have a ConfigContext** - it needs one!

## ‚ùå Hardcoded Values Found in Admin UI

### Currency References (15+ files):
1. Dashboard/index.tsx - Line 102: `currency: 'INR'`
2. Analytics/PaymentAnalytics.tsx - Line 161: `currency: 'INR'`
3. Products/ProductList.tsx - Line 113: `currency: 'INR'`
4. Products/ProductDetail.tsx - Line 65: `currency: 'INR'`
5. Orders/OrderList.tsx - Line 121: `currency: 'INR'`
6. Orders/OrderDetail.tsx - Line 200: `currency: 'INR'`
7. Users/UserList.tsx - Line 80: `currency: 'INR'`
8. Customers/CustomerDetail.tsx - Line 118: `currency: 'INR'`
9. FrequentlyBoughtTogether/BundleAnalytics.tsx - Line 99: `currency: 'INR'`
10. Payments/Refunds.tsx - Line 52: `currency: 'INR'`
11. Payments/TransactionLog.tsx - Lines 41, 277: `currency: 'INR'`
12. Coupons/index.tsx - Line 283: `currency: 'INR'`
13. components/RefundModal.tsx - Line 37: `currency: 'INR'`

### Min Order Amount (3 files):
14. Settings/PaymentSettings.tsx - Lines 801, 1222, 1393, etc.

## Ì≥ã Implementation Plan

### Step 1: Create Admin ConfigContext
- Location: `bookbharat-admin/src/contexts/ConfigContext.tsx`
- Fetch from: `/admin/configuration/site-config` API
- Store: currency, currency_symbol, min_order_amount, free_shipping_threshold

### Step 2: Update App.tsx
- Wrap app with ConfigProvider
- Similar to frontend implementation

### Step 3: Update All Files
- Import `useConfig` from context
- Replace hardcoded 'INR' with dynamic currency
- Use currency_symbol from config

## ÌæØ Priority
- **High**: Dashboard, Order pages, Product pages (frequently viewed)
- **Medium**: Analytics, Payment pages
- **Low**: Other admin pages

## Ì¥ß Files to Create:
1. `bookbharat-admin/src/contexts/ConfigContext.tsx`
2. Update `bookbharat-admin/src/App.tsx`

## Ì≥ä Impact
- **13 files** need currency updates
- **1 file** needs min_order_amount
- **Total**: 14 admin UI files to update

## ‚è±Ô∏è Estimated Time
- Create context: 10 minutes
- Update files: 30 minutes
- Total: 40 minutes

