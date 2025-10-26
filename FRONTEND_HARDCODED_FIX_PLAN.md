# Frontend Hardcoded Values - Fix Plan

## Current Status

### ✅ Already Using Dynamic Config (6 files)
- Header.tsx
- FooterClient.tsx  
- ProductInfo.tsx
- Cart Summary
- Checkout page
- Orders page

### ❌ Still Needs Updates (10 files)

## Files to Update:

1. **MobileCart.tsx** - ✅ COMPLETED
   - Added useConfig hook
   - Updated line 232 to use dynamic threshold

2. **cart/mobile-page.tsx** (Line 270)
   ```tsx
   // Before
   "On orders above ₹499 • Usually delivered in 2-3 days"
   
   // After
   `On orders above ₹${siteConfig?.payment?.free_shipping_threshold || 499} • Usually delivered in 2-3 days`
   ```

3. **ProductInfoCompact.tsx** (Line 377)
   ```tsx
   // Before
   <div className="text-[9px] sm:text-xs text-muted-foreground">On orders above ₹499</div>
   
   // After
   <div className="text-[9px] sm:text-xs text-muted-foreground">
     On orders above ₹{siteConfig?.payment?.free_shipping_threshold || 499}
   </div>
   ```

4. **ProductInfo.tsx** (Line 419)
   ```tsx
   // Before
   { icon: Truck, text: 'Free Delivery', desc: 'On orders above ₹499' }
   
   // After  
   { icon: Truck, text: 'Free Delivery', desc: `On orders above ₹${siteConfig?.payment?.free_shipping_threshold || 499}` }
   ```

5. **Footer.tsx** (Line 100)
   ```tsx
   // Before
   description: 'Above ₹499'
   
   // After
   description: `Above ₹${siteConfig?.payment?.free_shipping_threshold || 499}`
   ```

6. **BookSchema.tsx** (Line 51)
   ```tsx
   // Before
   priceCurrency: 'INR'
   
   // After - Need to pass currency as prop
   priceCurrency: currency || 'INR'
   ```

7. **ProductMeta.tsx** (Lines 48, 82)
   ```tsx
   // Before
   priceCurrency: 'INR'
   
   // After - Need to pass currency as prop
   priceCurrency: currency || 'INR'
   ```

8. **checkout/page.tsx** (Line 586)
   ```tsx
   // Before
   currency: 'INR'
   
   // After
   currency: siteConfig?.payment?.currency || 'INR'
   ```

9. **settings/page.tsx** (Line 139)
   ```tsx
   // Before
   currency: 'INR'
   
   // After
   currency: siteConfig?.payment?.currency || 'INR'
   ```

10. **FooterServer.tsx** (Lines 120, 192)
    - Already has fallback value
    - Should add ConfigContext for consistency

## Implementation Steps

1. ✅ Import useConfig in each component
2. ✅ Add const { siteConfig } = useConfig();
3. ✅ Replace hardcoded values with siteConfig
4. ✅ Add fallback values for safety

## Priority

**High Priority** (User-facing):
- MobileCart.tsx ✅ DONE
- cart/mobile-page.tsx
- ProductInfo.tsx
- ProductInfoCompact.tsx
- Footer.tsx

**Medium Priority** (SEO/Meta):
- BookSchema.tsx
- ProductMeta.tsx

**Low Priority** (Internal):
- checkout/page.tsx
- settings/page.tsx
- FooterServer.tsx

## Completion Status

- Files Updated: 1/10 ✅
- Remaining: 9 files
- Estimated Time: 15-20 minutes
