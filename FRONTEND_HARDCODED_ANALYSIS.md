# Frontend Hardcoded Values Analysis

## ✅ What's Already Using Dynamic Config

### Components Using siteConfig via ConfigContext:
1. ✅ **Header.tsx** - Uses `siteConfig?.payment?.free_shipping_threshold`
2. ✅ **FooterClient.tsx** - Uses `payment.free_shipping_threshold`
3. ✅ **ProductInfo.tsx** - Uses `currencySymbol` from siteConfig
4. ✅ **CartSummary** - Uses currency symbol from siteConfig
5. ✅ **Checkout** - Uses currency from siteConfig
6. ✅ **Orders** - Uses currency from siteConfig

## ❌ Still Hardcoded in Frontend

### Critical Business Values:

1. **MobileCart.tsx** (Line 232)
   - `"Free delivery on orders above ₹499"`
   - Should use: `siteConfig?.payment?.free_shipping_threshold`

2. **ProductInfoCompact.tsx** (Line 377)
   - `"On orders above ₹499"`
   - Should use: `siteConfig?.payment?.free_shipping_threshold`

3. **cart/mobile-page.tsx** (Line 270)
   - `"On orders above ₹499 • Usually delivered in 2-3 days"`
   - Should use: `siteConfig?.payment?.free_shipping_threshold`

4. **ProductInfo.tsx** (Line 419)
   - `{ icon: Truck, text: 'Free Delivery', desc: 'On orders above ₹499' }`
   - Should use: `siteConfig?.payment?.free_shipping_threshold`

5. **Footer.tsx** (Line 100)
   - `description: 'Above ₹499'`
   - Should use: `siteConfig?.payment?.free_shipping_threshold`

### Currency Hardcoded:

1. **BookSchema.tsx** (Line 51)
   - `priceCurrency: 'INR'`
   - Should use: `siteConfig?.payment?.currency`

2. **ProductMeta.tsx** (Line 48, 82)
   - `priceCurrency: 'INR'`
   - Should use: `siteConfig?.payment?.currency`

3. **checkout/page.tsx** (Line 586)
   - `currency: 'INR'`
   - Should use: `siteConfig?.payment?.currency`

4. **settings/page.tsx** (Line 139)
   - `currency: 'INR'`
   - Should use: `siteConfig?.payment?.currency`

### FooterServer.tsx:
- Lines 120, 192: `freeShippingThreshold: 500` (hardcoded fallback)
- Should default but use ConfigContext

## ��� Summary

### Using Dynamic Config: 6 components ✅
- Header, Footer, ProductInfo, Cart, Checkout, Orders

### Still Hardcoded: 10+ locations ❌
- 5 instances of ₹499 threshold
- 4 instances of 'INR' currency
- Multiple min order amounts in admin

## ��� Recommendation

Frontend is **PARTIALLY** using AdminSetting:
- Core components (Header, Footer, Cart, Checkout) are dynamic ✅
- Some product info components still hardcoded ❌
- Currency mostly hardcoded ❌

**Need to update**: ~10 component files to use siteConfig fully
