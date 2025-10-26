# Comprehensive Hardcoded Values - Fix Plan

**Date**: 2025-10-26  
**Total Files to Fix**: Backend (8) + Frontend (7) = **15 files**

---

## Backend Files (8 files)

### Priority 1: Critical - Email & Phone

1. **OrderController.php** - Lines 415-416
   - Email: `support@bookbharat.com`
   - Phone: `+91 9876543210`
   - Fix: Use `AdminSetting::get()`

2. **NotificationController.php** - Lines 42, 405
   - Phone: `+91-9876543210`
   - Fix: Use `AdminSetting::get('contact_phone')`

3. **ReportController.php** - Line 485
   - Email: `admin@bookbharat.com`
   - Fix: Use `AdminSetting::get('support_email')`

4. **ContactController.php** - Lines 80, 181
   - Email: `admin@bookbharat.com`
   - Phone: `+91 12345 67890`
   - Fix: Use `AdminSetting::get()`

5. **ConfigurationController.php** - Line 42
   - Phone: `+91 9876543210` (fallback)
   - Status: This is already fixed by using `AdminSetting`

6. **ContentController.php** - Line 33
   - Phone: `+91 9876543210` (fallback)
   - Status: Need to verify fix

### Priority 2: Static Content

7. **FAQController.php** - Line 387
   - Hardcoded contact info in answer
   - Fix: Use dynamic contact info

8. **StaticPageController.php** - Multiple lines
   - Hardcoded emails and phones in HTML
   - Fix: Use dynamic values

---

## Frontend Files (7 files)

### Priority 1: Contact Information

1. **contact/page.tsx** - Lines 42-43, 134, 143
   - Email: `support@bookbharat.com`
   - Phone: `+91 12345 67890`
   - Fix: Use `useConfig()` hook

2. **help/page.tsx** - Lines 357, 364
   - Phone: `+911234567890`
   - Email: `support@bookbharat.com`
   - Fix: Use `useConfig()` hook

3. **settings/page.tsx** - Lines 119-122
   - Multiple hardcoded values
   - Fix: Remove hardcoded defaults

4. **layout/Footer.tsx** - Lines 205, 207, 288
   - Phone: `+91 12345 67890`
   - Fix: Use `useConfig()` hook

5. **layout/FooterServer.tsx** - Lines 101, 173
   - Phone: `+91 12345 67890`
   - Fix: Use siteInfo from API

6. **layout/Header.tsx** - Line 165
   - Phone: `+91 12345 67890`
   - Fix: Already uses dynamic? Check

7. **layout/HeaderDynamic.tsx** - Line 192
   - Phone: `+91 12345 67890` (fallback)
   - Fix: Remove or update fallback

---

## Implementation Plan

### Phase 1: Backend Fixes (30 min)

```bash
# Fix email and phone in controllers
1. OrderController.php
2. NotificationController.php
3. ReportController.php
4. ContactController.php
5. FAQController.php (dynamic contact)
6. StaticPageController.php (dynamic contact)
7. ContentController.php (verify free shipping fix)
```

### Phase 2: Frontend Fixes (45 min)

```bash
# Fix contact info in components
1. contact/page.tsx
2. help/page.tsx
3. settings/page.tsx
4. Footer.tsx
5. FooterServer.tsx
6. Header.tsx (verify)
7. HeaderDynamic.tsx
```

### Phase 3: Testing (15 min)

```bash
# Test all contact info displays correctly
- Check all pages showing contact info
- Verify dynamic values loading from API
- Test admin can change values
```

---

## Quick Fix Commands

### Backend Pattern:
```php
// Before
$phone = '+91 9876543210';

// After
use App\Models\AdminSetting;
$phone = AdminSetting::get('contact_phone', '+91 9876543210');
```

### Frontend Pattern:
```typescript
// Before
const phone = '+91 12345 67890';

// After
const { siteConfig } = useConfig();
const phone = siteConfig?.site.contact_phone || '+91 12345 67890';
```

---

## Estimated Time

- **Backend**: 30-45 minutes
- **Frontend**: 45-60 minutes
- **Testing**: 15 minutes
- **Total**: ~2 hours

---

## Success Criteria

âœ… All email addresses use `AdminSetting::get('support_email')`  
âœ… All phone numbers use `AdminSetting::get('contact_phone')`  
âœ… Frontend components use `useConfig()` hook  
âœ… Admin can change contact info and it reflects everywhere  
âœ… No hardcoded contact information remains  

---

**Ready to start fixing?** íº€
