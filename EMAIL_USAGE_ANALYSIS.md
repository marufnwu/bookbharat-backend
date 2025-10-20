# Email Usage Analysis - BookBharat Backend

## 📧 **Complete Email System Overview**

This document provides a comprehensive analysis of all email sending functionality in the BookBharat backend application.

---

## 🏗️ **Core Email Services**

### **1. EmailService.php** (`app/Services/EmailService.php`)

**Primary email service handling all transactional emails**

#### **Methods:**

1. **`sendOrderConfirmation(Order $order)`**
   - Template: `emails.order.confirmation`
   - Triggered: When order is created
   - Recipient: Order owner
   - Data: Order details, items, total

2. **`sendOrderStatusUpdate(Order $order, $oldStatus, $newStatus)`**
   - Template: `emails.order.status_update`
   - Triggered: When order status changes
   - Recipient: Order owner
   - Data: Old/new status, status message

3. **`sendPaymentConfirmation(Payment $payment)`**
   - Template: `emails.payment.confirmation`
   - Triggered: When payment is successful
   - Recipient: Order owner
   - Data: Payment details, order info

4. **`sendInvoice(Invoice $invoice, $attachPdf = true)`**
   - Template: `emails.invoice.invoice`
   - Triggered: When invoice is generated
   - Recipient: Order owner
   - Data: Invoice details, PDF attachment
   - Special: Attaches PDF if exists at `storage/app/public/{pdf_path}`

5. **`sendReturnStatusUpdate(ReturnModel $return, $oldStatus, $newStatus)`**
   - Template: `emails.returns.status_update`
   - Triggered: When return status changes
   - Recipient: Return requester
   - Data: Return details, status message

6. **`sendWelcomeEmail(User $user)`**
   - Template: `emails.auth.welcome`
   - Triggered: After user registration
   - Recipient: New user
   - Data: User info, welcome message

7. **`sendPasswordResetEmail(User $user, $token)`**
   - Template: `emails.auth.password_reset`
   - Triggered: When password reset requested
   - Recipient: User
   - Data: Reset token, reset URL
   - Reset URL Format: `{frontend_url}/reset-password?token={token}&email={email}`

8. **`sendShippingNotification(Order $order)`**
   - Template: `emails.order.shipped`
   - Triggered: When order is shipped
   - Recipient: Order owner
   - Data: Tracking number, carrier, tracking URL

9. **`sendDeliveryConfirmation(Order $order)`**
   - Template: `emails.order.delivered`
   - Triggered: When order is delivered
   - Recipient: Order owner
   - Data: Order details, delivery confirmation

10. **`sendReviewRequest(Order $order)`**
    - Template: `emails.order.review_request`
    - Triggered: After delivery (automated)
    - Recipient: Order owner
    - Data: Order items, review URL
    - Review URL Format: `{frontend_url}/orders/{id}/review`

---

### **2. NotificationService.php** (`app/Services/NotificationService.php`)

**Multi-channel notification service (Email, SMS, Push, WhatsApp)**

#### **Email Methods:**

1. **`sendEmail(string $to, string $subject, string $template, array $data)`**
   - Generic email sending method
   - Uses Laravel Mail facade
   - From address: `config('mail.from.address')`
   - From name: `config('mail.from.name')`

2. **`notifyUser(User $user, string $type, array $data)`**
   - Sends notification via all enabled channels
   - Email enabled by default (`email_notifications_enabled`)
   - Template: `$data['email_template']` or `emails.default`
   - Subject: `$data['subject']` or 'Notification from BookBharat'

#### **Additional Features:**
- SMS notifications (MSG91, Twilio, TextLocal)
- Push notifications (FCM, OneSignal)
- WhatsApp notifications (Twilio, Wati)
- In-app notifications

---

### **3. InvoiceService.php** (`app/Services/InvoiceService.php`)

**Invoice generation and email sending**

#### **Method:**

**`sendInvoiceEmail(Invoice $invoice)`**
- Generates PDF if not exists
- Sends via EmailService
- Attaches PDF to email
- Returns success/failure boolean

---

## 📨 **Email Jobs (Queue)**

### **1. SendOrderNotification.php** (`app/Jobs/SendOrderNotification.php`)

**Queued job for order-related notifications**

#### **Supported Templates:**
- `order_placed` → `App\Mail\OrderPlaced::class`
- `order_confirmed` → `App\Mail\OrderConfirmed::class`
- `order_shipped` → `App\Mail\OrderShipped::class`
- `order_delivered` → `App\Mail\OrderDelivered::class`
- `order_cancelled` → `App\Mail\OrderCancelled::class`
- `refund_processed` → `App\Mail\RefundProcessed::class`
- `payment_failed` → `App\Mail\PaymentFailed::class`

#### **Features:**
- Queued for async processing
- Multi-channel (Email, SMS, Push, In-app)
- SMS only for important events
- Respects user preferences

---

### **2. SendAbandonedCartEmail.php** (`app/Jobs/SendAbandonedCartEmail.php`)

**Abandoned cart recovery email system**

#### **Email Types:**
1. **First Reminder** - No discount
2. **Second Reminder** - 5% discount
3. **Final Reminder** - 10% discount

#### **Features:**
- Template: `EmailTemplate` with type `abandoned_cart`
- Checks if cart still abandoned
- Checks if user made recent purchase
- Generates recovery token
- Recovery URL: `/cart/recover/{token}`
- Tracks email count and last sent date
- Uses `App\Mail\AbandonedCartMail`

#### **Data Tracked:**
- `recovery_token`
- `recovery_email_count`
- `last_recovery_email_sent`

---

## 📝 **Email Templates (Blade Views)**

### **Location:** `resources/views/emails/`

### **Available Templates:**

#### **Order Emails:**
- `emails/order/confirmation.blade.php` - Order confirmation
- `emails/order/status_update.blade.php` - Status change notification
- `emails/order/shipped.blade.php` - Shipment notification
- `emails/order/delivered.blade.php` - Delivery confirmation
- `emails/order/review_request.blade.php` - Review request (missing - needs creation)

#### **Authentication Emails:**
- `emails/auth/welcome.blade.php` - Welcome email
- `emails/auth/password_reset.blade.php` - Password reset

#### **Payment Emails:**
- `emails/payment/confirmation.blade.php` - Payment confirmation

#### **Invoice Emails:**
- `emails/invoice/invoice.blade.php` - Invoice with PDF attachment

#### **Return Emails:**
- `emails/returns/status_update.blade.php` - Return status update

#### **Layout:**
- `emails/layout/app.blade.php` - Base email layout

---

## 🎯 **Email Triggers & Usage**

### **1. Order Flow**

| Event | Service | Method | Template | Queued |
|-------|---------|--------|----------|--------|
| Order Created | EmailService | sendOrderConfirmation | emails.order.confirmation | No |
| Order Status Changed | EmailService | sendOrderStatusUpdate | emails.order.status_update | No |
| Order Shipped | EmailService | sendShippingNotification | emails.order.shipped | No |
| Order Delivered | EmailService | sendDeliveryConfirmation | emails.order.delivered | No |
| Review Request | EmailService | sendReviewRequest | emails.order.review_request | No |

**Alternative (Queued):**
```php
SendOrderNotification::dispatch($order, 'order_placed');
SendOrderNotification::dispatch($order, 'order_shipped');
SendOrderNotification::dispatch($order, 'order_delivered');
```

### **2. Payment Flow**

| Event | Service | Method | Template | Queued |
|-------|---------|--------|----------|--------|
| Payment Success | EmailService | sendPaymentConfirmation | emails.payment.confirmation | No |
| Payment Failed | SendOrderNotification | Job | Mailable | Yes |

### **3. Authentication Flow**

| Event | Service | Method | Template | Queued |
|-------|---------|--------|----------|--------|
| User Registration | EmailService | sendWelcomeEmail | emails.auth.welcome | No |
| Password Reset | EmailService | sendPasswordResetEmail | emails.auth.password_reset | No |

### **4. Invoice Flow**

| Event | Service | Method | Template | Queued |
|-------|---------|--------|----------|--------|
| Invoice Generated | EmailService | sendInvoice | emails.invoice.invoice | No |
| Manual Send | InvoiceService | sendInvoiceEmail | emails.invoice.invoice | No |

### **5. Return Flow**

| Event | Service | Method | Template | Queued |
|-------|---------|--------|----------|--------|
| Return Status Changed | EmailService | sendReturnStatusUpdate | emails.returns.status_update | No |

### **6. Abandoned Cart Flow**

| Event | Job | Email Type | Discount | Queued |
|-------|-----|-----------|----------|--------|
| 1 hour after abandon | SendAbandonedCartEmail | first_reminder | None | Yes |
| 24 hours after abandon | SendAbandonedCartEmail | second_reminder | 5% | Yes |
| 48 hours after abandon | SendAbandonedCartEmail | final_reminder | 10% | Yes |

---

## 🔧 **Controllers Using Email**

### **1. Admin Controllers**

#### **`OrderController.php`**
```php
use App\Services\EmailService;

// Order status update
$emailService->sendOrderStatusUpdate($order, $oldStatus, $order->status);

// Shipment notification
$emailService->sendShippingNotification($order);
```

#### **`UserController.php`**
```php
public function sendEmail(Request $request, User $user)
{
    // Admin can send custom emails to users
    Mail::to($user->email)->send(new CustomMail($data));
}
```

#### **`NotificationController.php`**
```php
use App\Services\NotificationService;

// Send notifications via all channels
$notificationService->notifyUser($user, $type, $data);
```

### **2. API Controllers**

#### **`NewsletterController.php`**
```php
// Newsletter subscription confirmation
private function sendWelcomeEmail($email, $name)
{
    Mail::send('emails.newsletter.welcome', $data, function($message) use ($email) {
        $message->to($email)->subject('Welcome to BookBharat Newsletter');
    });
}

// Newsletter unsubscribe confirmation
private function sendUnsubscribeConfirmation($email)
{
    Mail::send('emails.newsletter.unsubscribe', $data, function($message) use ($email) {
        $message->to($email)->subject('You have been unsubscribed');
    });
}
```

#### **`ContactController.php`**
```php
// Admin notification
private function sendAdminNotification($contactData)
{
    Mail::to(config('mail.admin_email'))->send(new ContactFormMail($contactData));
}

// User confirmation
private function sendUserConfirmation($contactData)
{
    Mail::to($contactData['email'])->send(new ContactConfirmationMail($contactData));
}
```

#### **`InvoiceController.php`**
```php
public function sendEmail($id)
{
    $invoice = Invoice::findOrFail($id);
    $success = $this->invoiceService->sendInvoiceEmail($invoice);
    
    return response()->json([
        'success' => $success,
        'message' => $success ? 'Invoice sent successfully' : 'Failed to send invoice'
    ]);
}
```

---

## ⚙️ **Mail Configuration**

### **Config File:** `config/mail.php`

```php
'default' => env('MAIL_MAILER', 'smtp'),

'from' => [
    'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    'name' => env('MAIL_FROM_NAME', 'Example'),
],

'mailers' => [
    'smtp' => [
        'transport' => 'smtp',
        'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
        'port' => env('MAIL_PORT', 587),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
    ],
    // ... other mailers
],
```

### **Required Environment Variables:**

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io  # or your SMTP server
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@bookbharat.com
MAIL_FROM_NAME="${APP_NAME}"
```

---

## 📊 **Email Tracking & Logging**

### **All email services log:**
- Success/failure status
- Recipient email
- Order/User ID
- Email type/template
- Error messages (if failed)

### **Log Examples:**

```php
Log::info('Order confirmation email sent', [
    'order_id' => $order->id,
    'user_id' => $order->user_id,
    'email' => $order->user->email
]);

Log::error('Failed to send order confirmation email', [
    'order_id' => $order->id,
    'error' => $e->getMessage()
]);
```

---

## 🚨 **Missing/Incomplete Features**

### **1. Missing Email Templates:**
- ❌ `emails/order/review_request.blade.php` - Referenced but doesn't exist
- ❌ `emails/newsletter/welcome.blade.php` - Referenced but doesn't exist
- ❌ `emails/newsletter/unsubscribe.blade.php` - Referenced but doesn't exist
- ❌ `emails/default.blade.php` - Used as fallback but doesn't exist

### **2. Missing Mailable Classes:**
- ❌ `App\Mail\OrderConfirmed`
- ❌ `App\Mail\OrderShipped`
- ❌ `App\Mail\OrderDelivered`
- ❌ `App\Mail\OrderCancelled`
- ❌ `App\Mail\RefundProcessed`
- ❌ `App\Mail\PaymentFailed`
- ❌ `App\Mail\AbandonedCartMail`
- ❌ `App\Mail\ContactFormMail`
- ❌ `App\Mail\ContactConfirmationMail`

### **3. Incomplete Integrations:**
- ⚠️ SMS sending (TODO comments present)
- ⚠️ Push notifications (TODO comments present)
- ⚠️ WhatsApp integration (partial)

---

## 🔄 **Email Flow Architecture**

```
┌─────────────────────────────────────────────────────────────┐
│                    Email Trigger Points                      │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Controllers → Services → EmailService/NotificationService   │
│                                    ↓                          │
│                          ┌────────────────┐                  │
│                          │  Queue (Jobs)  │                  │
│                          └────────────────┘                  │
│                                    ↓                          │
│                          ┌────────────────┐                  │
│                          │  Mail Facade   │                  │
│                          └────────────────┘                  │
│                                    ↓                          │
│                          ┌────────────────┐                  │
│                          │  SMTP Server   │                  │
│                          └────────────────┘                  │
│                                    ↓                          │
│                          ┌────────────────┐                  │
│                          │   Recipient    │                  │
│                          └────────────────┘                  │
└─────────────────────────────────────────────────────────────┘
```

---

## 📈 **Email Statistics**

### **Total Email Types:** 10+
### **Total Templates:** 10 (7 existing, 3+ missing)
### **Total Services:** 3 (EmailService, NotificationService, InvoiceService)
### **Total Jobs:** 2 (SendOrderNotification, SendAbandonedCartEmail)
### **Total Mailable Classes:** 1 existing (OrderPlaced), 8+ referenced but missing

---

## ✅ **Recommendations**

### **1. High Priority:**
1. ✅ Create all missing Mailable classes
2. ✅ Create missing email templates
3. ✅ Implement review request email automation
4. ✅ Set up abandoned cart email scheduling
5. ✅ Configure SMTP credentials

### **2. Medium Priority:**
1. ⚠️ Add email open/click tracking
2. ⚠️ Implement email preferences management
3. ⚠️ Add unsubscribe functionality to all emails
4. ⚠️ Create email template builder in admin
5. ⚠️ Add email queue monitoring

### **3. Low Priority:**
1. 📝 Complete SMS integration
2. 📝 Complete push notification integration
3. 📝 Add email A/B testing
4. 📝 Implement transactional email analytics
5. 📝 Add email localization support

---

## 🔗 **Related Files**

### **Services:**
- `app/Services/EmailService.php`
- `app/Services/NotificationService.php`
- `app/Services/InvoiceService.php`
- `app/Services/OrderAutomationService.php`

### **Jobs:**
- `app/Jobs/SendOrderNotification.php`
- `app/Jobs/SendAbandonedCartEmail.php`

### **Mail Classes:**
- `app/Mail/OrderPlaced.php`

### **Controllers:**
- `app/Http/Controllers/Admin/OrderController.php`
- `app/Http/Controllers/Admin/UserController.php`
- `app/Http/Controllers/Admin/NotificationController.php`
- `app/Http/Controllers/Api/NewsletterController.php`
- `app/Http/Controllers/Api/ContactController.php`
- `app/Http/Controllers/Api/InvoiceController.php`

### **Templates:**
- `resources/views/emails/**/*.blade.php`

### **Config:**
- `config/mail.php`
- `config/services.php`

---

## 📞 **Support & Configuration**

### **To enable email functionality:**

1. Configure `.env` with SMTP credentials
2. Test email configuration: `php artisan tinker` → `Mail::raw('Test', function($msg) { $msg->to('test@example.com')->subject('Test'); });`
3. Create missing Mailable classes: `php artisan make:mail OrderConfirmed`
4. Create missing email templates in `resources/views/emails/`
5. Set up queue worker for async emails: `php artisan queue:work`
6. Monitor email logs: `storage/logs/laravel.log`

---

**Generated:** 2025-01-20
**Version:** 1.0
**Status:** Complete Analysis

