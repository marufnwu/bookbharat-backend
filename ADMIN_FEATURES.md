# BookBharat Admin Panel - Complete Feature Specification

## Overview
This document provides a comprehensive specification of all admin features required for the BookBharat e-commerce platform. Each feature is mapped to existing backend endpoints and controllers.

---

## 1. Dashboard & Analytics

### 1.1 Main Dashboard
**Endpoint**: `GET /api/v1/admin/dashboard/overview`
**Controller**: `AdminDashboardController::overview()`

- **Total Revenue** - All-time, today, with growth percentage
- **Total Orders** - Count, pending, processing, delivered
- **Total Customers** - Count, new today, active this month
- **Total Products** - In stock, low stock, out of stock
- **Conversion Rate** - Percentage of users who made purchases
- **Average Order Value** - AOV metrics
- **Sales Chart** - Time-series revenue and order data
- **Recent Orders** - Latest 10 orders with details
- **Top Products** - Best selling products (last 30 days)
- **Low Stock Alerts** - Products with quantity ≤ 10
- **Customer Insights** - New vs repeat customers
- **Campaign Performance** - Active campaigns and coupon usage

### 1.2 Sales Analytics
**Endpoint**: `GET /api/v1/admin/dashboard/sales-analytics`
**Controller**: `AdminDashboardController::salesAnalytics()`

- **Period Selection** - 7d, 30d, 90d, 1y
- **Comparison View** - Current vs previous period
- **Sales Metrics** - Revenue, orders, AOV, unique customers
- **Product Performance** - Top 20 products by revenue
- **Category Performance** - Sales by category
- **Geographic Sales** - Top 10 cities by revenue
- **Customer Segments** - Sales by customer groups
- **Growth Metrics** - Revenue, order, AOV, customer growth %

### 1.3 Customer Analytics
**Endpoint**: `GET /api/v1/admin/dashboard/customer-analytics`
**Controller**: `AdminDashboardController::customerAnalytics()`

- **Customer Metrics** - Total, active, inactive counts
- **Acquisition Channels** - Source of customers
- **Customer Lifetime Value** - CLV analysis
- **Retention Analysis** - Cohort retention rates
- **Customer Segments** - Detailed segmentation
- **Churn Analysis** - Customer loss patterns

### 1.4 Inventory Overview
**Endpoint**: `GET /api/v1/admin/dashboard/inventory-overview`
**Controller**: `AdminDashboardController::inventoryOverview()`

- **Stock Value Report** - Total inventory value
- **Low Stock Products** - Products below threshold
- **Out of Stock Products** - Zero quantity products

### 1.5 Order Insights
**Endpoint**: `GET /api/v1/admin/dashboard/order-insights`
**Controller**: `AdminDashboardController::orderInsights()`

- **Order Statistics** - Total, average value, frequency
- **Status Breakdown** - Orders by status
- **Fulfillment Metrics** - Processing time, delivery time
- **Payment Method Analysis** - Popular payment methods
- **Return Analysis** - Return rates and reasons
- **Shipping Performance** - Delivery success rates

### 1.6 Marketing Performance
**Endpoint**: `GET /api/v1/admin/dashboard/marketing-performance`
**Controller**: `AdminDashboardController::marketingPerformance()`

- **Campaign Overview** - Active campaigns and ROI
- **Coupon Performance** - Usage rates and revenue impact
- **Email Marketing Stats** - Open rates, click rates
- **Social Commerce Metrics** - Social media performance
- **Referral Program Stats** - Referral success rates
- **Customer Acquisition Cost** - CAC by channel

---

## 2. Product Management

### 2.1 Product List
**Endpoint**: `GET /api/v1/admin/products`
**Controller**: `AdminProductController::index()`

- **Search & Filter**
  - Search by name, SKU, description, author, ISBN
  - Filter by category, status, stock level, featured
  - Sort by name, price, stock, sales, created date
- **Bulk Operations**
  - Activate/Deactivate multiple products
  - Delete multiple products
  - Update prices in bulk
  - Export to CSV/Excel
- **Quick Actions**
  - Toggle active status
  - Toggle featured status
  - Quick edit price and stock
  - Duplicate product

### 2.2 Create/Edit Product
**Endpoints**:
- `POST /api/v1/admin/products`
- `PUT /api/v1/admin/products/{id}`
**Controller**: `AdminProductController::store/update()`

- **Basic Information**
  - Name, SKU, Barcode, ISBN
  - Category selection
  - Brand, Author, Publisher
  - Short description, Full description
- **Pricing**
  - Regular price, Sale price
  - Cost price (for margin calculation)
  - Tax class selection
- **Inventory**
  - Stock quantity
  - Stock management settings
  - Low stock threshold
  - Allow backorders
- **Product Attributes**
  - Custom attributes (color, size, etc.)
  - Product specifications
- **Product Variants**
  - Multiple variants with individual SKUs
  - Variant-specific pricing
  - Variant-specific stock
- **Images**
  - Primary image
  - Gallery images (multiple)
  - Alt text for SEO
- **SEO Settings**
  - Meta title, Meta description
  - URL slug
  - Keywords
- **Shipping**
  - Weight, Dimensions
  - Shipping class
  - Free shipping toggle
- **Related Products**
  - Manual product associations
  - Cross-sell products
  - Upsell products

### 2.3 Product Images
**Endpoint**: `POST /api/v1/admin/products/{id}/images`
**Controller**: `AdminProductController::uploadImages()`

- Upload multiple images
- Set primary image
- Reorder images
- Delete images
- Image optimization

### 2.4 Product Analytics
**Endpoint**: `GET /api/v1/admin/products/{id}/analytics`
**Controller**: `AdminProductController::analytics()`

- Sales performance
- View statistics
- Conversion rates
- Customer demographics
- Return rates

---

## 3. Order Management

### 3.1 Order List
**Endpoint**: `GET /api/v1/admin/orders`
**Controller**: `AdminOrderController::index()`

- **Search & Filter**
  - Search by order number, customer name, email
  - Filter by status, payment status, date range
  - Filter by payment method, shipping method
  - Filter by order value range
- **Bulk Actions**
  - Update status for multiple orders
  - Print invoices/labels in bulk
  - Export orders to CSV
- **Quick Actions**
  - View order details
  - Update status
  - Print invoice
  - Send email to customer

### 3.2 Order Details
**Endpoint**: `GET /api/v1/admin/orders/{id}`
**Controller**: `AdminOrderController::show()`

- **Order Information**
  - Order number, Date, Status
  - Customer details
  - Billing address
  - Shipping address
- **Order Items**
  - Product list with quantities
  - Price breakdown
  - Applied discounts
- **Payment Information**
  - Payment method
  - Transaction ID
  - Payment status
- **Shipping Information**
  - Shipping method
  - Tracking number
  - Delivery status
- **Order Timeline**
  - Status change history
  - Notes and comments
  - Activity log

### 3.3 Order Actions
**Endpoints**:
- `PUT /api/v1/admin/orders/{id}/status`
- `PUT /api/v1/admin/orders/{id}/payment-status`
- `POST /api/v1/admin/orders/{id}/cancel`
- `POST /api/v1/admin/orders/{id}/refund`

- Update order status
- Update payment status
- Cancel order with reason
- Process refund (partial/full)
- Add tracking information
- Send notification email
- Add internal notes

### 3.4 Order Timeline
**Endpoint**: `GET /api/v1/admin/orders/{id}/timeline`
**Controller**: `AdminOrderController::getTimeline()`

- Complete order history
- Status changes
- Payment updates
- Shipping updates
- Customer communications

---

## 4. User Management

### 4.1 User List
**Endpoint**: `GET /api/v1/admin/users`
**Controller**: `AdminUserController::index()`

- **Search & Filter**
  - Search by name, email, phone
  - Filter by status, customer group
  - Filter by registration date
  - Filter by order history
- **User Metrics**
  - Total spent
  - Order count
  - Last order date
  - Account status
- **Bulk Actions**
  - Export users
  - Send bulk emails
  - Assign to groups

### 4.2 User Details
**Endpoint**: `GET /api/v1/admin/users/{id}`
**Controller**: `AdminUserController::show()`

- **Profile Information**
  - Personal details
  - Contact information
  - Account status
- **Analytics**
  - Lifetime value
  - Order history
  - Average order value
  - Product preferences
- **Addresses**
  - Saved addresses
  - Default addresses
- **Activity**
  - Login history
  - Order history
  - Review history
  - Wishlist items

### 4.3 User Actions
**Endpoints**:
- `PUT /api/v1/admin/users/{id}`
- `POST /api/v1/admin/users/{id}/reset-password`
- `POST /api/v1/admin/users/{id}/toggle-status`

- Edit user information
- Reset password
- Activate/Deactivate account
- Assign to customer groups
- Add internal notes

---

## 5. Category Management

### 5.1 Category Operations
**Endpoints**:
- `POST /api/v1/admin/categories`
- `PUT /api/v1/admin/categories/{id}`
- `DELETE /api/v1/admin/categories/{id}`

- Create categories
- Edit category details
- Delete categories
- Set parent categories
- Manage category hierarchy
- Upload category images
- SEO settings for categories

---

## 6. Marketing & Promotions

### 6.1 Coupon Management
**Endpoint**: `GET /api/v1/admin/coupons`
**Controller**: `Admin\CouponController`

- **Create Coupons**
  - Code generation (manual/auto)
  - Discount type (percentage/fixed/free shipping)
  - Usage limits (total, per customer)
  - Date validity
  - Minimum order amount
  - Product/Category restrictions
  - Customer group restrictions
- **Coupon Analytics**
  - Usage statistics
  - Revenue impact
  - Customer demographics
  - Popular products with coupons
- **Bulk Operations**
  - Generate multiple codes
  - Activate/Deactivate
  - Extend validity
  - Delete unused coupons

### 6.2 Bundle Discounts
**Endpoint**: `GET /api/v1/admin/bundle-discounts`
**Controller**: `BundleDiscountController`

- Create bundle rules
- Set discount percentages
- Define product combinations
- Set validity periods
- Priority management
- Preview calculations
- Performance analytics

### 6.3 Promotional Campaigns
**Endpoint**: `GET /api/v1/admin/promotional-campaigns`
**Controller**: `PromotionalCampaignController`

- **Campaign Creation**
  - Campaign name and description
  - Target audience selection
  - Campaign duration
  - Budget allocation
  - Channel selection
- **Campaign Management**
  - Activate/Pause campaigns
  - Generate campaign coupons
  - Track performance
  - ROI analysis
- **Campaign Analytics**
  - Conversion rates
  - Revenue generated
  - Customer acquisition
  - Channel performance

---

## 7. Shipping Configuration

### 7.1 Shipping Zones
**Endpoint**: `GET /api/v1/admin/shipping/zones`
**Controller**: `ShippingConfigController`

- Create shipping zones
- Assign pincodes to zones
- Set zone-based pricing
- Configure delivery times
- COD availability settings

### 7.2 Weight Slabs
**Endpoint**: `GET /api/v1/admin/shipping/weight-slabs`
**Controller**: `ShippingConfigController`

- Define weight ranges
- Set pricing per slab
- Zone-specific pricing
- Bulk import rates

### 7.3 Delivery Options
**Endpoint**: `GET /api/v1/admin/delivery-options`
**Controller**: `DeliveryOptionController`

- **Service Types**
  - Standard delivery
  - Express delivery
  - Same-day delivery
  - Scheduled delivery
- **Configuration**
  - Base price
  - Additional charges
  - Cutoff times
  - Available days
  - Zone restrictions

### 7.4 Shipping Insurance
**Endpoint**: `GET /api/v1/admin/shipping-insurance`
**Controller**: `ShippingInsuranceController`

- Create insurance plans
- Set coverage percentages
- Define premium rates
- Mandatory/Optional settings
- Value-based pricing

### 7.5 Pincode Management
**Endpoint**: `GET /api/v1/admin/shipping/pincodes`
**Controller**: `ShippingConfigController`

- Add/Edit pincodes
- Bulk import pincodes
- Zone assignment
- COD availability
- Delivery time settings
- Service availability

---

## 8. Content Management

### 8.1 Site Configuration
**Endpoint**: `PUT /api/v1/admin/content/site-config`
**Controller**: `ContentController::updateSiteConfig()`

- **General Settings**
  - Site name, tagline
  - Logo, favicon
  - Contact information
  - Business hours
- **Theme Settings**
  - Color scheme
  - Typography
  - Layout options
- **Feature Toggles**
  - Enable/disable features
  - Module activation
- **SEO Settings**
  - Default meta tags
  - Sitemap settings
  - Robots.txt

### 8.2 Homepage Configuration
**Endpoint**: `PUT /api/v1/admin/content/homepage-config`
**Controller**: `ContentController::updateHomepageConfig()`

- Hero section management
- Featured categories
- Featured products
- Promotional banners
- Content blocks
- Testimonials

### 8.3 Navigation Management
**Endpoint**: `PUT /api/v1/admin/content/navigation-config`
**Controller**: `ContentController::updateNavigationConfig()`

- Header menu items
- Footer menu items
- Mobile menu
- Category menu
- Quick links

### 8.4 Static Pages
**Endpoint**: `PUT /api/v1/admin/content/pages/{slug}`
**Controller**: `ContentController::updateContentPage()`

- About Us page
- Terms & Conditions
- Privacy Policy
- Shipping Policy
- Return Policy
- FAQ management

### 8.5 Media Library
**Endpoints**:
- `POST /api/v1/admin/content/media/upload`
- `GET /api/v1/admin/content/media/library`
- `DELETE /api/v1/admin/content/media/{id}`


- Upload images/documents
- Organize media
- Image optimization
- CDN management

### 8.6 Content Moderation
**Endpoint**: `GET /api/v1/admin/content-moderation`
**Controller**: `ContentModerationController`

- Review user-generated content
- Approve/Reject reviews
- Feature content
- Moderate social posts
- Content analytics

---

## 9. Payment Configuration

### 9.1 Payment Gateways
**Part of Site Configuration**

- Enable/Disable gateways
- Configure API credentials
- Set gateway priorities
- Test mode settings
- Transaction fees

### 9.2 Payment Methods
- Razorpay configuration
- Cashfree configuration
- PayU configuration
- PhonePe configuration
- COD settings
- Bank transfer details

---

## 10. System Management

### 10.1 System Health
**Endpoint**: `GET /api/v1/admin/system/health`

- System status
- Database connection
- Cache status
- Queue status
- Storage status
- Memory usage
- Server time

### 10.2 Cache Management
**Endpoint**: `POST /api/v1/admin/system/cache/clear`

- Clear application cache
- Clear configuration cache
- Clear route cache
- Clear view cache

### 10.3 Application Optimization
**Endpoint**: `POST /api/v1/admin/system/optimize`

- Optimize application
- Rebuild indexes
- Clean temporary files

---

## 11. Reports & Export

### 11.1 Sales Reports
- Daily/Weekly/Monthly/Yearly sales
- Product performance reports
- Category performance reports
- Customer purchase reports
- Payment method reports

### 11.2 Inventory Reports
- Stock status report
- Low stock report
- Stock movement history
- Stock valuation report

### 11.3 Customer Reports
- New customer report
- Customer lifetime value
- Customer segmentation
- Geographic distribution

### 11.4 Export Functions
- Export orders (CSV/Excel)
- Export products (CSV/Excel)
- Export customers (CSV/Excel)
- Export inventory (CSV/Excel)
- Export financial data

---

## 12. Notifications & Communications

### 12.1 Email Management
- Order confirmation emails
- Shipping notifications
- Password reset emails
- Promotional emails
- Abandoned cart emails

### 12.2 SMS Notifications
- Order status updates
- Delivery notifications
- OTP messages
- Promotional SMS

### 12.3 Push Notifications
- App notifications
- Browser notifications
- Promotional alerts

---

## 13. Security & Access Control

### 13.1 Role Management
- Admin roles
- Staff roles
- Permission assignment
- Role-based access control

### 13.2 Activity Logs
- User activity tracking
- Admin action logs
- Login history
- API access logs

### 13.3 Security Settings
- Two-factor authentication
- IP whitelisting
- Session management
- Password policies

---

## 14. Integration Management

### 14.1 Third-party Integrations
- Payment gateway APIs
- Shipping provider APIs
- SMS gateway
- Email service provider
- Analytics tools

### 14.2 API Management
- API key generation
- Rate limiting
- Webhook configuration
- API documentation

---

## 15. Mobile App Management

### 15.1 App Configuration
- App banners
- Push notification settings
- Deep linking configuration
- App-specific promotions

### 15.2 App Analytics
- App usage statistics
- User engagement metrics
- App-specific conversions

---

## Implementation Priority

### Phase 1 - Core Features (Must Have)
1. Dashboard Overview
2. Product Management (CRUD)
3. Order Management
4. User Management
5. Category Management
6. Basic Shipping Configuration

### Phase 2 - Advanced Features (Should Have)
1. Detailed Analytics
2. Coupon Management
3. Bundle Discounts
4. Content Management
5. Payment Configuration
6. Advanced Shipping Options

### Phase 3 - Enhancement Features (Nice to Have)
1. Promotional Campaigns
2. Content Moderation
3. Advanced Reports
4. Email Management
5. System Management
6. Integration Management

---

## Technical Requirements

### Authentication
- Laravel Sanctum for API authentication
- Role-based access control using Spatie Permissions
- Session management
- CSRF protection

### Performance
- Response time < 2 seconds for lists
- Pagination for large datasets
- Caching for frequently accessed data
- Lazy loading for images

### Security
- Input validation on all forms
- XSS protection
- SQL injection prevention
- File upload validation
- Rate limiting

### UI/UX Requirements
- Responsive design (mobile, tablet, desktop)
- Real-time search
- Bulk operations
- Keyboard shortcuts
- Dark mode support
- Export functionality
- Print-friendly views

### Browser Support
- Chrome (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Edge (latest 2 versions)

---

## API Response Format

All API responses should follow this format:

```json
{
  "success": true|false,
  "message": "Operation message",
  "data": {
    // Response data
  },
  "errors": {
    // Validation errors if any
  },
  "meta": {
    "current_page": 1,
    "total_pages": 10,
    "total_items": 100,
    "per_page": 10
  }
}
```

---

## Notes

1. All admin routes are prefixed with `/api/v1/admin/`
2. All admin routes require authentication via Laravel Sanctum
3. All admin routes require `admin` role (checked via middleware)
4. File uploads should support multiple formats (images: jpg, png, webp; documents: pdf, csv, xlsx)
5. All delete operations should have confirmation dialogs
6. All forms should have client-side and server-side validation
7. All lists should have search, filter, and sort capabilities
8. All data exports should be queued for large datasets
9. All monetary values should support multiple currencies (future enhancement)
10. All dates should be displayed in user's timezone

---

## End of Document

This specification covers all administrative features for the BookBharat e-commerce platform. Each feature maps to existing backend functionality and can be implemented in the admin panel frontend.
