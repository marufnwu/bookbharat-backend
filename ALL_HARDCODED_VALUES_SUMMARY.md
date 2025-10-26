# All Hardcoded Values Summary - Backend + Frontend

**Date**: 2025-10-26  
**Status**: Comprehensive Search Complete

---

## Critical Hardcoded Values Found

### 1. Email Addresses ���

**Files with hardcoded emails**:
- `OrderController.php` - Line 415: `'support@bookbharat.com'`
- `ContactController.php` - Line 80: `'admin@bookbharat.com'`
- `FAQController.php` - Line 387: `'support@bookbharat.com'`  
- `StaticPageController.php` - Multiple lines: `'support@bookbharat.com'` (Terms, Privacy, etc.)
- `ReportController.php` - Line 485: `'admin@bookbharat.com'`

**Email Templates** (Already Fixed ✅):
- `password_reset.blade.php` ✅
- `welcome.blade.php` ✅
- `app.blade.php` ✅

**PDF Templates** (Need Fix ⚠️):
- `invoice.blade.php` - Line 106, 259
- `receipt.blade.php` - Line 196, 372

---

### 2. Phone Numbers ���

**Files with hardcoded phones**:
- `ConfigurationController.php` - Line 42: `'+91 9876543210'`
- `ContentController.php` - Line 33: `'+91 9876543210'`
- `OrderController.php` - Line 416: `'+91 9876543210'`
- `NotificationController.php` - Lines 42, 405: `'+91-9876543210'`
- `ContactController.php` - Line 181: `'+91 12345 67890'`
- `FAQController.php` - Line 387: `'+91 12345 67890'`
- `StaticPageController.php` - Line 325: `'+91 12345 67890'`

**Email Templates** (Placeholder ⚠️):
- `password_reset.blade.php` - Line 52: `'+91-XXXX-XXXXXX'`
- `welcome.blade.php` - Line 43: `'+91-XXXX-XXXXXX'`

**PDF Templates** (Placeholder ⚠️):
- `invoice.blade.php` - Line 106: `'+91-XXXX-XXXXXX'`
- `receipt.blade.php` - Line 196: `'+91-XXXX-XXXXXX'`

---

### 3. Social Media URLs ���

**Files with hardcoded URLs**:
- `ConfigurationController.php` - Lines 100-104:
  - `'https://facebook.com/bookbharat'`
  - `'https://twitter.com/bookbharat'`
  - `'https://instagram.com/bookbharat'`
  - `'https://linkedin.com/company/bookbharat'`

- `ContentController.php` - Lines 91-95:
  - Same URLs duplicated

---

### 4. Other Hardcoded Values

**Free Shipping Threshold** (Already Fixed ✅):
- `ShippingService.php` - ✅ Fixed
- `ShippingController.php` - ✅ Fixed
- `ConfigurationController.php` - ✅ Fixed
- `ContentController.php` - ⚠️ Still hardcoded `499`

**Currency**:
- Most places now use `AdminSetting` ✅

---

## Priority Fix List

### Priority 1: Email Addresses (Critical) ���

| File | Line | Current Value | Should Use |
|------|------|---------------|------------|
| `OrderController.php` | 415 | `support@bookbharat.com` | `AdminSetting::get('support_email')` |
| `ReportController.php` | 485 | `admin@bookbharat.com` | `AdminSetting::get('support_email')` |
| `ContactController.php` | 80 | `admin@bookbharat.com` | `AdminSetting::get('support_email')` |

### Priority 2: Phone Numbers (Critical) ���

| File | Line | Current Value | Should Use |
|------|------|---------------|------------|
| `ConfigurationController.php` | 42 | `+91 9876543210` | `AdminSetting::get('contact_phone')` |
| `ContentController.php` | 33 | `+91 9876543210` | `AdminSetting::get('contact_phone')` |
| `OrderController.php` | 416 | `+91 9876543210` | `AdminSetting::get('contact_phone')` |
| `NotificationController.php` | 42, 405 | `+91-9876543210` | `AdminSetting::get('contact_phone')` |

### Priority 3: Social Media URLs (Medium) ⚠️

| File | Line | Should Use |
|------|------|------------|
| `ConfigurationController.php` | 100-104 | `SiteConfiguration` (already implemented) |
| `ContentController.php` | 91-95 | Remove duplicate, use same as above |

### Priority 4: Static Content (Medium) ⚠️

| File | Lines | Content |
|------|-------|---------|
| `StaticPageController.php` | Multiple | Hardcoded emails/phones in HTML content |
| `FAQController.php` | 387 | Hardcoded contact info in answer |
| `ContactController.php` | 181 | Hardcoded phone number |

### Priority 5: PDF Templates (Low) ✅

| File | Lines | Already Using | Needed |
|------|-------|---------------|--------|
| `invoice.blade.php` | 106 | `AdminSetting` | ✅ Just use it |
| `receipt.blade.php` | 196 | `AdminSetting` | ✅ Just use it |

---

## Files Already Fixed ✅

1. ✅ Email templates (`welcome.blade.php`, `password_reset.blade.php`, `app.blade.php`)
2. ✅ Order/PDF templates (`invoice.blade.php`, `receipt.blade.php`) - Use AdminSetting
3. ✅ Free shipping thresholds - Most files
4. ✅ Currency and payment configs - Most files

---

## Remaining Work

### Backend Files to Fix:
1. ⚠️ `OrderController.php` - Email & Phone (Lines 415-416)
2. ⚠️ `NotificationController.php` - Phone (Lines 42, 405)
3. ⚠️ `ReportController.php` - Email (Line 485)
4. ⚠️ `ContactController.php` - Email & Phone (Lines 80, 181)
5. ⚠️ `FAQController.php` - Contact info in answer (Line 387)
6. ⚠️ `StaticPageController.php` - Multiple hardcoded values
7. ⚠️ `ContentController.php` - Free shipping threshold (Line 79)
8. ⚠️ `SocialCommerceService.php` - Instagram API URL (Line 18)

### Frontend to Check:
- Need to search frontend for hardcoded values too

---

## Estimated Work

**Backend**: ~8 files to fix  
**Frontend**: To be determined  
**Time**: 2-3 hours for all fixes

---

**Next Step**: Start fixing these files systematically, starting with Priority 1.
