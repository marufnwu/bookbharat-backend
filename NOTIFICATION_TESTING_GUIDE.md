# Notification System Testing Guide

## 🧪 **Complete Testing Checklist**

This guide provides step-by-step instructions for testing the complete notification system.

---

## 📋 **Pre-Testing Setup**

### **1. Database Setup**

```bash
# Run migration
php artisan migrate

# Seed default notification settings
php artisan db:seed --class=NotificationSettingsSeeder
```

### **2. Queue Worker**

```bash
# Start queue worker in a separate terminal
php artisan queue:work
```

### **3. Schedule Runner (for testing cron jobs)**

```bash
# Start schedule worker
php artisan schedule:work
```

---

## 📧 **Phase 1: Email Testing**

### **Test 1: Order Confirmation Email**

```php
// Via Tinker
php artisan tinker

$order = App\Models\Order::first();
$emailService = app(App\Services\EmailService::class);
$emailService->sendOrderConfirmation($order);
```

**Expected:** Email sent to order user with order details

### **Test 2: Order Shipped Email**

```php
$order = App\Models\Order::first();
$order->update(['tracking_number' => 'TEST123', 'shipped_at' => now()]);
$emailService->sendShippingNotification($order);
```

**Expected:** Email with tracking information

### **Test 3: Order Delivered Email**

```php
$order = App\Models\Order::first();
$order->update(['delivered_at' => now()]);
$emailService->sendDeliveryConfirmation($order);
```

**Expected:** Delivery confirmation email

### **Test 4: Review Request Email**

```php
$order = App\Models\Order::first();
$emailService->sendReviewRequest($order);
```

**Expected:** Email requesting product review

### **Test 5: Abandoned Cart Email**

```php
use App\Jobs\SendAbandonedCartEmail;

$cart = App\Models\PersistentCart::first();
SendAbandonedCartEmail::dispatch($cart->id, 'first_reminder');
```

**Expected:** Abandoned cart email queued and sent

---

## 📱 **Phase 2: SMS Testing**

### **Prerequisites:**

1. Configure SMS gateway in admin panel:
   - Go to Settings → Notifications → SMS Gateway tab
   - Enter Gateway URL
   - Enter API Key
   - Set Sender ID
   - Select Request Format

2. Test connection:
   - Enter test phone number
   - Click "Test SMS"
   - Verify SMS received

### **Test 1: Order Placed SMS**

```php
php artisan tinker

$order = App\Models\Order::first();
$notificationService = app(App\Services\NotificationService::class);

$notificationService->notifyUser($order->user, 'order_placed', [
    'order' => $order,
    'order_number' => $order->order_number,
    'order_total' => $order->total_amount,
    'action_url' => config('app.frontend_url') . '/orders/' . $order->id,
]);
```

**Expected:** SMS sent to user's phone number

### **Test 2: Direct SMS Send**

```php
$smsService = app(App\Services\SMSGatewayService::class);
$result = $smsService->send(
    '9876543210', 
    'Test SMS from BookBharat notification system',
    'order_placed'
);

print_r($result);
```

**Expected:** 
```php
[
    'success' => true,
    'response' => [...],
    'message_id' => '...'
]
```

---

## 💬 **Phase 3: WhatsApp Testing**

### **Prerequisites:**

1. Set up WhatsApp Business Account at https://business.facebook.com
2. Get API credentials from Developer Console
3. Create and approve message templates
4. Configure in admin panel:
   - Go to Settings → Notifications → WhatsApp API tab
   - Enter API URL
   - Enter Access Token
   - Enter Phone Number ID
   - Enter Business Account ID
   - Click "Sync Templates"

### **Test 1: Sync WhatsApp Templates**

From Admin Panel:
1. Go to Settings → Notifications → WhatsApp API
2. Fill in all credentials
3. Click "Sync Templates"

**Expected:** List of approved templates displayed

### **Test 2: Test WhatsApp Connection**

From Admin Panel:
1. Enter test phone number
2. Click "Test WhatsApp"

**Expected:** Test message sent to WhatsApp

### **Test 3: Template Message**

```php
php artisan tinker

$whatsappService = app(App\Services\WhatsAppBusinessService::class);
$result = $whatsappService->send(
    '9876543210',
    'order_placed_notification',  // Template name
    [
        [
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => 'John Doe'],
                ['type' => 'text', 'text' => 'ORD-12345'],
                ['type' => 'text', 'text' => '₹499.00'],
            ]
        ]
    ],
    'order_placed'
);

print_r($result);
```

**Expected:** Template message sent via WhatsApp

---

## 🔔 **Phase 4: Multi-Channel Testing**

### **Test: Order Shipped - All Channels**

```php
php artisan tinker

$order = App\Models\Order::first();
$order->update(['tracking_number' => 'TRACK123', 'shipped_at' => now()]);

$notificationService = app(App\Services\NotificationService::class);
$whatsappService = app(App\Services\WhatsAppBusinessService::class);

$data = [
    'user' => $order->user,
    'order' => $order,
    'order_number' => $order->order_number,
    'tracking_number' => $order->tracking_number,
    'order_total' => $order->total_amount,
    'action_url' => config('app.frontend_url') . '/orders/' . $order->id,
    'subject' => 'Your Order Has Shipped - #' . $order->order_number,
    'email_template' => 'emails.order.shipped',
    'whatsapp_components' => $whatsappService->buildOrderComponents($order, 'order_shipped'),
];

$results = $notificationService->notifyUser($order->user, 'order_shipped', $data);

print_r($results);
```

**Expected:** 
```php
[
    'email' => ['success' => true],
    'sms' => ['success' => true, 'message_id' => '...'],
    'whatsapp' => ['success' => true, 'message_id' => '...'],
    'in_app' => ['success' => true, ...]
]
```

---

## 📅 **Phase 5: Automation Testing**

### **Test 1: Abandoned Cart Command**

```bash
# Test first reminder
php artisan cart:send-abandoned-reminders --type=first

# Test second reminder
php artisan cart:send-abandoned-reminders --type=second

# Test final reminder
php artisan cart:send-abandoned-reminders --type=final

# Test all
php artisan cart:send-abandoned-reminders
```

**Expected:** Console output showing queued emails

### **Test 2: Verify Schedule**

```bash
# List all scheduled tasks
php artisan schedule:list
```

**Expected Output:**
```
0 * * * *  cart:send-abandoned-reminders --type=first ..... Next Due: ...
0 10 * * * cart:send-abandoned-reminders --type=second .... Next Due: ...
0 11 * * * cart:send-abandoned-reminders --type=final ..... Next Due: ...
0 9 * * *  Review Request Closure ........................ Next Due: ...
```

### **Test 3: Manual Schedule Run**

```bash
php artisan schedule:run
```

**Expected:** All scheduled tasks execute

---

## 🎛️ **Phase 6: Admin Panel Testing**

### **Access Notification Settings:**

1. Login to admin panel
2. Navigate to **Settings → Notifications**
3. Verify 4 tabs visible:
   - Event Channels
   - SMS Gateway
   - WhatsApp API
   - Email Config

### **Test: Event Channels Configuration**

1. Go to "Event Channels" tab
2. Toggle email/SMS/WhatsApp for different events
3. Toggle event enabled/disabled
4. Verify changes save automatically
5. Check database: `SELECT * FROM notification_settings;`

**Expected:** Changes reflected in database immediately

### **Test: SMS Gateway Configuration**

1. Go to "SMS Gateway" tab
2. Enter:
   - Gateway URL: `https://your-sms-api.com/send`
   - API Key: `test_key_12345`
   - Sender ID: `BKBHRT`
   - Request Format: `json`
3. Enter test phone number
4. Click "Test SMS"

**Expected:** 
- SMS sent to test number
- Success message displayed
- Configuration saved in database (API key encrypted)

### **Test: WhatsApp Business Configuration**

1. Go to "WhatsApp API" tab
2. Enter:
   - API URL: `https://graph.facebook.com/v18.0/YOUR_PHONE_ID`
   - Access Token: `your_token`
   - Phone Number ID: `123456`
   - Business Account ID: `123456`
3. Click "Sync Templates"

**Expected:** 
- Templates fetched from WhatsApp
- Template list displayed
- Templates saved in database

4. Enter test phone number
5. Click "Test WhatsApp"

**Expected:** Test message sent via WhatsApp

### **Test: Email Configuration**

1. Go to "Email Config" tab
2. Enter test email address
3. Click "Send Test Email"

**Expected:** Test email received at specified address

---

## 🔒 **Phase 7: Security Testing**

### **Test: Credential Encryption**

```php
php artisan tinker

$setting = App\Models\NotificationSetting::first();

// Set API key
$setting->sms_api_key = 'my_secret_key';
$setting->save();

// Check database - should be encrypted
\DB::table('notification_settings')->where('id', $setting->id)->first();
// sms_api_key should show encrypted string

// Retrieve - should be decrypted
$setting->fresh();
echo $setting->decrypted_sms_api_key; // Should show 'my_secret_key'
```

**Expected:** API keys stored encrypted, retrieved decrypted

---

## 🔄 **Phase 8: Retry Logic Testing**

### **Test: SMS Retry**

```php
php artisan tinker

$smsService = app(App\Services\SMSGatewayService::class);

// Use invalid endpoint to force retry
$result = $smsService->send(
    '9876543210',
    'Test message',
    'order_placed'
);

print_r($result);
```

**Expected:** 
```php
[
    'success' => false,
    'error' => '...',
    'attempts' => 3  // Should show 3 retry attempts
]
```

Check logs: `storage/logs/laravel.log` - Should show 3 attempts with exponential backoff

---

## 📊 **Phase 9: Integration Testing**

### **Full Order Flow Test:**

```php
php artisan tinker

// 1. Create order
$order = App\Models\Order::factory()->create();

// 2. Send order placed notification
$notificationService = app(App\Services\NotificationService::class);
$notificationService->notifyUser($order->user, 'order_placed', [
    'order' => $order,
    'order_number' => $order->order_number,
    'order_total' => $order->total_amount,
]);

// 3. Update to shipped
$order->update(['status' => 'shipped', 'tracking_number' => 'SHIP123', 'shipped_at' => now()]);
$notificationService->notifyUser($order->user, 'order_shipped', [
    'order' => $order,
    'order_number' => $order->order_number,
    'tracking_number' => 'SHIP123',
]);

// 4. Update to delivered
$order->update(['status' => 'delivered', 'delivered_at' => now()]);
$notificationService->notifyUser($order->user, 'order_delivered', [
    'order' => $order,
    'order_number' => $order->order_number,
]);

// 5. Send review request
sleep(2);
$emailService = app(App\Services\EmailService::class);
$emailService->sendReviewRequest($order);
```

**Expected:** User receives 4 notifications (order placed, shipped, delivered, review request) via all enabled channels

---

## 🐛 **Troubleshooting**

### **Issue: Email not sending**

**Check:**
1. `.env` file has correct SMTP credentials
2. `php artisan config:clear`
3. Test SMTP: `php artisan tinker` → `Mail::raw('Test', fn($m) => $m->to('test@example.com')->subject('Test'));`
4. Check logs: `storage/logs/laravel.log`

### **Issue: SMS not sending**

**Check:**
1. SMS gateway URL is correct
2. API key is valid
3. Phone number format is correct (10 digits without country code)
4. Check NotificationSetting has `sms` in channels array
5. Check logs for API response

### **Issue: WhatsApp not sending**

**Check:**
1. WhatsApp Business Account is approved
2. Access token is valid (regenerate if expired)
3. Phone Number ID is correct
4. Template names match approved templates
5. Template status is "APPROVED"
6. Check logs for Graph API error responses

### **Issue: Notifications not scheduled**

**Check:**
1. Schedule worker is running: `php artisan schedule:work`
2. Or cron job is configured
3. Run manually: `php artisan schedule:run`
4. Check task list: `php artisan schedule:list`

---

## ✅ **Success Criteria**

### **Email System:**
- ✅ All 9 Mailable classes working
- ✅ All 5 email templates rendering correctly
- ✅ Emails sent successfully
- ✅ PDF attachments working (invoices)

### **SMS System:**
- ✅ Direct API integration working
- ✅ Messages sent successfully
- ✅ Retry logic functioning
- ✅ Test connection successful
- ✅ Credentials encrypted in database

### **WhatsApp System:**
- ✅ Direct Meta Graph API integration working
- ✅ Template messages sent successfully
- ✅ Template sync working
- ✅ Test connection successful
- ✅ Credentials encrypted in database

### **Admin Panel:**
- ✅ All tabs accessible
- ✅ Channel toggles working
- ✅ SMS configuration saving
- ✅ WhatsApp configuration saving
- ✅ Test buttons functional
- ✅ Template sync working

### **Automation:**
- ✅ Abandoned cart emails scheduled
- ✅ Review request emails scheduled
- ✅ Commands executing correctly
- ✅ Jobs processing from queue

---

## 📈 **Monitoring**

### **Check Logs:**

```bash
# View latest logs
tail -f storage/logs/laravel.log

# Search for notification logs
grep "notification" storage/logs/laravel.log

# Search for SMS logs
grep "SMS" storage/logs/laravel.log

# Search for WhatsApp logs
grep "WhatsApp" storage/logs/laravel.log
```

### **Check Queue Jobs:**

```bash
# View failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {job-id}

# Retry all failed jobs
php artisan queue:retry all
```

### **Check Database:**

```sql
-- Check notification settings
SELECT * FROM notification_settings;

-- Check notification logs (if implemented)
SELECT * FROM notification_logs ORDER BY created_at DESC LIMIT 10;

-- Check abandoned carts
SELECT * FROM persistent_carts WHERE is_abandoned = 1;
```

---

## 🎯 **Performance Testing**

### **Test: Bulk Notifications**

```php
php artisan tinker

$orders = App\Models\Order::take(10)->get();

foreach ($orders as $order) {
    App\Jobs\SendOrderNotification::dispatch($order, 'order_placed');
}

// Check queue jobs processing
```

**Expected:** All 10 notifications queued and processed

### **Test: Retry Performance**

Monitor logs during failed API calls to verify:
- First retry after 2 seconds
- Second retry after 4 seconds
- Third retry after 8 seconds
- Exponential backoff working correctly

---

## 📞 **Support & Next Steps**

### **Production Deployment:**

1. ✅ Set up cron job for schedule:run
2. ✅ Configure supervisor for queue:work
3. ✅ Set up log rotation for laravel.log
4. ✅ Configure real SMS gateway credentials
5. ✅ Configure real WhatsApp Business API credentials
6. ✅ Test all notification flows in production
7. ✅ Monitor delivery rates
8. ✅ Set up alerts for failed notifications

### **Additional Enhancements:**

1. Add delivery status webhooks
2. Implement notification analytics dashboard
3. Add user notification preferences in frontend
4. Create admin email template builder
5. Add A/B testing for notification content

---

**Testing Date:** 2025-10-20
**Version:** 1.0
**Status:** Ready for Testing ✅

