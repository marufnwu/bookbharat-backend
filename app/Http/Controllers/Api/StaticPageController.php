<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StaticPageController extends Controller
{
    /**
     * Get static page content by slug
     */
    public function getPage($slug)
    {
        try {
            // Get page data from cache (in production, this would be from database)
            $pageData = Cache::get("content_page_{$slug}");
            
            if (!$pageData) {
                // Return default content if no custom content is set
                $pageData = $this->getDefaultContent($slug);
                
                if (!$pageData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Page not found.',
                    ], 404);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $pageData
            ], 200);

        } catch (\Exception $e) {
            Log::error('Static page retrieval error: ' . $e->getMessage(), [
                'slug' => $slug,
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve page content.',
            ], 500);
        }
    }

    /**
     * Get all available static pages
     */
    public function getPages()
    {
        try {
            $pages = [
                'privacy' => [
                    'slug' => 'privacy',
                    'title' => 'Privacy Policy',
                    'description' => 'Learn about our privacy policy and data protection practices.'
                ],
                'terms' => [
                    'slug' => 'terms',
                    'title' => 'Terms of Service',
                    'description' => 'Terms and conditions for using BookBharat services.'
                ],
                'cookies' => [
                    'slug' => 'cookies',
                    'title' => 'Cookie Policy',
                    'description' => 'Information about cookies and how we use them.'
                ],
                'refund' => [
                    'slug' => 'refund',
                    'title' => 'Refund Policy',
                    'description' => 'Our refund and return policy details.'
                ],
                'shipping' => [
                    'slug' => 'shipping',
                    'title' => 'Shipping Policy',
                    'description' => 'Information about shipping and delivery.'
                ],
                'about' => [
                    'slug' => 'about',
                    'title' => 'About Us',
                    'description' => 'Learn more about BookBharat and our mission.'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => array_values($pages)
            ], 200);

        } catch (\Exception $e) {
            Log::error('Static pages list error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve pages list.',
            ], 500);
        }
    }

    /**
     * Get default content for static pages
     */
    private function getDefaultContent($slug)
    {
        $defaultContent = [
            'privacy' => [
                'title' => 'Privacy Policy',
                'content' => $this->getDefaultPrivacyContent(),
                'meta_title' => 'Privacy Policy | BookBharat',
                'meta_description' => 'Learn about our privacy policy and data protection practices.',
                'updated_at' => now()->toISOString()
            ],
            'terms' => [
                'title' => 'Terms of Service',
                'content' => $this->getDefaultTermsContent(),
                'meta_title' => 'Terms of Service | BookBharat',
                'meta_description' => 'Terms and conditions for using BookBharat services.',
                'updated_at' => now()->toISOString()
            ],
            'cookies' => [
                'title' => 'Cookie Policy',
                'content' => $this->getDefaultCookieContent(),
                'meta_title' => 'Cookie Policy | BookBharat',
                'meta_description' => 'Information about cookies and how we use them.',
                'updated_at' => now()->toISOString()
            ],
            'refund' => [
                'title' => 'Refund Policy',
                'content' => $this->getDefaultRefundContent(),
                'meta_title' => 'Refund Policy | BookBharat',
                'meta_description' => 'Our refund and return policy details.',
                'updated_at' => now()->toISOString()
            ],
            'shipping' => [
                'title' => 'Shipping Policy',
                'content' => $this->getDefaultShippingContent(),
                'meta_title' => 'Shipping Policy | BookBharat',
                'meta_description' => 'Information about shipping and delivery.',
                'updated_at' => now()->toISOString()
            ],
            'about' => [
                'title' => 'About Us',
                'content' => $this->getDefaultAboutContent(),
                'meta_title' => 'About Us | BookBharat',
                'meta_description' => 'Learn more about BookBharat and our mission.',
                'updated_at' => now()->toISOString()
            ]
        ];

        return $defaultContent[$slug] ?? null;
    }

    private function getDefaultPrivacyContent()
    {
        return "
<h1>Privacy Policy</h1>
<p><strong>Last updated: " . date('F j, Y') . "</strong></p>

<h2>1. Information We Collect</h2>
<p>BookBharat collects information you provide directly to us, such as when you create an account, make a purchase, or contact us.</p>

<h2>2. How We Use Your Information</h2>
<p>We use the information we collect to provide, maintain, and improve our services, process transactions, and communicate with you.</p>

<h2>3. Information Sharing</h2>
<p>We do not sell, trade, or otherwise transfer your personal information to third parties without your consent, except as described in this privacy policy.</p>

<h2>4. Data Security</h2>
<p>We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>

<h2>5. Your Rights</h2>
<p>You have the right to access, update, or delete your personal information. You can also opt out of certain communications from us.</p>

<h2>6. Contact Us</h2>
<p>If you have any questions about this privacy policy, please contact us at support@bookbharat.com.</p>
        ";
    }

    private function getDefaultTermsContent()
    {
        return "
<h1>Terms of Service</h1>
<p><strong>Last updated: " . date('F j, Y') . "</strong></p>

<h2>1. Acceptance of Terms</h2>
<p>By using BookBharat's services, you agree to be bound by these terms of service.</p>

<h2>2. Use of Services</h2>
<p>You may use our services only for lawful purposes and in accordance with these terms.</p>

<h2>3. Account Responsibilities</h2>
<p>You are responsible for maintaining the confidentiality of your account information and for all activities that occur under your account.</p>

<h2>4. Orders and Payments</h2>
<p>All orders are subject to acceptance and availability. Prices are subject to change without notice.</p>

<h2>5. Intellectual Property</h2>
<p>The content on this website is protected by copyright and other intellectual property laws.</p>

<h2>6. Limitation of Liability</h2>
<p>BookBharat shall not be liable for any indirect, incidental, or consequential damages.</p>

<h2>7. Contact Information</h2>
<p>For questions about these terms, contact us at support@bookbharat.com.</p>
        ";
    }

    private function getDefaultCookieContent()
    {
        return "
<h1>Cookie Policy</h1>
<p><strong>Last updated: " . date('F j, Y') . "</strong></p>

<h2>1. What Are Cookies</h2>
<p>Cookies are small text files that are stored on your device when you visit our website.</p>

<h2>2. How We Use Cookies</h2>
<p>We use cookies to improve your browsing experience, analyze website traffic, and personalize content.</p>

<h2>3. Types of Cookies We Use</h2>
<ul>
<li><strong>Essential Cookies:</strong> Required for the website to function properly</li>
<li><strong>Analytics Cookies:</strong> Help us understand how visitors interact with our website</li>
<li><strong>Marketing Cookies:</strong> Used to deliver relevant advertisements</li>
</ul>

<h2>4. Managing Cookies</h2>
<p>You can control and delete cookies through your browser settings. However, this may affect the functionality of our website.</p>

<h2>5. Contact Us</h2>
<p>If you have questions about our cookie policy, contact us at support@bookbharat.com.</p>
        ";
    }

    private function getDefaultRefundContent()
    {
        return "
<h1>Refund Policy</h1>
<p><strong>Last updated: " . date('F j, Y') . "</strong></p>

<h2>1. Return Window</h2>
<p>You may return most items within 30 days of delivery for a full refund.</p>

<h2>2. Condition of Items</h2>
<p>Items must be in original condition with all packaging and accessories included.</p>

<h2>3. Refund Process</h2>
<p>Refunds will be processed to your original payment method within 7-10 business days of receiving your return.</p>

<h2>4. Non-Returnable Items</h2>
<p>Digital products, personalized items, and certain other products cannot be returned.</p>

<h2>5. Return Shipping</h2>
<p>We provide free return shipping for defective items. For other returns, shipping costs are the customer's responsibility.</p>

<h2>6. Contact Us</h2>
<p>For return requests or questions, contact our customer service at support@bookbharat.com.</p>
        ";
    }

    private function getDefaultShippingContent()
    {
        return "
<h1>Shipping Policy</h1>
<p><strong>Last updated: " . date('F j, Y') . "</strong></p>

<h2>1. Shipping Areas</h2>
<p>We currently ship within India to most locations. Some remote areas may have limited shipping options.</p>

<h2>2. Shipping Costs</h2>
<p>Free shipping is available on orders above ₹499. For smaller orders, shipping charges apply based on location.</p>

<h2>3. Delivery Times</h2>
<ul>
<li><strong>Metro Cities:</strong> 2-3 business days</li>
<li><strong>Other Cities:</strong> 3-5 business days</li>
<li><strong>Remote Areas:</strong> 5-7 business days</li>
</ul>

<h2>4. Order Processing</h2>
<p>Orders are processed within 24 hours of placement. You'll receive tracking information once your order ships.</p>

<h2>5. Contact Us</h2>
<p>For shipping inquiries, contact us at support@bookbharat.com.</p>
        ";
    }

    private function getDefaultAboutContent()
    {
        return "
<h1>About BookBharat</h1>

<h2>Our Story</h2>
<p>BookBharat was founded with a simple mission: to make quality books accessible to readers across India. We believe that books have the power to educate, inspire, and transform lives.</p>

<h2>Our Mission</h2>
<p>To be India's most trusted online bookstore, providing readers with easy access to a vast collection of books across all genres and languages.</p>

<h2>What We Offer</h2>
<ul>
<li>Millions of books across all categories</li>
<li>Competitive prices and regular discounts</li>
<li>Fast and reliable delivery</li>
<li>Excellent customer service</li>
<li>Easy returns and exchanges</li>
</ul>

<h2>Our Values</h2>
<ul>
<li><strong>Quality:</strong> We ensure all books meet our quality standards</li>
<li><strong>Service:</strong> Customer satisfaction is our top priority</li>
<li><strong>Trust:</strong> We build lasting relationships with our customers</li>
<li><strong>Innovation:</strong> We continuously improve our services</li>
</ul>

<h2>Contact Us</h2>
<p>Have questions or feedback? We'd love to hear from you!</p>
<ul>
<li><strong>Email:</strong> support@bookbharat.com</li>
<li><strong>Phone:</strong> +91 12345 67890</li>
<li><strong>Address:</strong> Mumbai, Maharashtra, India</li>
</ul>
        ";
    }
}