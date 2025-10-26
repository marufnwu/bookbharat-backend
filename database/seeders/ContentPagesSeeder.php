<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ContentPage;

class ContentPagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'content' => $this->getPrivacyContent(),
                'excerpt' => 'Learn about our privacy policy and data protection practices.',
                'meta_title' => 'Privacy Policy | BookBharat',
                'meta_description' => 'Read our privacy policy to understand how we collect, use, and protect your personal information.',
                'order' => 1,
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of Service',
                'content' => $this->getTermsContent(),
                'excerpt' => 'Terms and conditions for using BookBharat services.',
                'meta_title' => 'Terms of Service | BookBharat',
                'meta_description' => 'Review our terms of service for using BookBharat platform.',
                'order' => 2,
            ],
            [
                'slug' => 'cookies',
                'title' => 'Cookie Policy',
                'content' => $this->getCookiesContent(),
                'excerpt' => 'Information about cookies and how we use them.',
                'meta_title' => 'Cookie Policy | BookBharat',
                'meta_description' => 'Learn about how we use cookies to enhance your browsing experience.',
                'order' => 3,
            ],
            [
                'slug' => 'refund',
                'title' => 'Refund Policy',
                'content' => $this->getRefundContent(),
                'excerpt' => 'Our refund and return policy details.',
                'meta_title' => 'Refund Policy | BookBharat',
                'meta_description' => 'Understand our refund and return policy.',
                'order' => 4,
            ],
            [
                'slug' => 'shipping',
                'title' => 'Shipping Policy',
                'content' => $this->getShippingContent(),
                'excerpt' => 'Information about shipping and delivery.',
                'meta_title' => 'Shipping Policy | BookBharat',
                'meta_description' => 'Learn about our shipping and delivery policies.',
                'order' => 5,
            ],
            [
                'slug' => 'about',
                'title' => 'About Us',
                'content' => $this->getAboutContent(),
                'excerpt' => 'Learn more about BookBharat and our mission.',
                'meta_title' => 'About BookBharat',
                'meta_description' => 'Discover BookBharat, India\'s trusted online bookstore.',
                'order' => 6,
            ],
        ];

        foreach ($pages as $page) {
            ContentPage::updateOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }
    }

    private function getPrivacyContent()
    {
        return '<h1>Privacy Policy</h1>
<p><strong>Last updated: ' . date('F j, Y') . '</strong></p>

<h2>1. Introduction</h2>
<p>BookBharat respects your privacy and is committed to protecting your personal data.</p>

<h2>2. Information We Collect</h2>
<p>We collect information you provide directly to us, such as when you create an account, make a purchase, or contact us for support.</p>

<h2>3. How We Use Your Information</h2>
<p>We use the information we collect to process orders, communicate with you, and improve our services.</p>

<h2>4. Data Security</h2>
<p>We implement appropriate security measures to protect your personal information.</p>

<h2>5. Your Rights</h2>
<p>You have the right to access, update, or delete your personal information.</p>

<h2>6. Contact Us</h2>
<p>If you have any questions about this privacy policy, please contact us at {{support_email}}.</p>';
    }

    private function getTermsContent()
    {
        return '<h1>Terms of Service</h1>
<p><strong>Last updated: ' . date('F j, Y') . '</strong></p>

<h2>1. Acceptance of Terms</h2>
<p>By using BookBharat\'s services, you agree to be bound by these terms of service.</p>

<h2>2. Use of Services</h2>
<p>You may use our services only for lawful purposes and in accordance with these terms.</p>

<h2>3. Contact Information</h2>
<p>For questions about these terms, contact us at {{support_email}}.</p>';
    }

    private function getCookiesContent()
    {
        return '<h1>Cookie Policy</h1>
<p><strong>Last updated: ' . date('F j, Y') . '</strong></p>

<h2>1. What Are Cookies</h2>
<p>Cookies are small text files stored on your device when you visit our website.</p>

<h2>2. Contact Us</h2>
<p>If you have questions about our cookie policy, contact us at {{support_email}}.</p>';
    }

    private function getRefundContent()
    {
        return '<h1>Refund Policy</h1>
<p><strong>Last updated: ' . date('F j, Y') . '</strong></p>

<h2>1. Return Window</h2>
<p>You may return most items within 30 days of delivery for a full refund.</p>

<h2>2. Contact Us</h2>
<p>For return requests, contact our customer service at {{support_email}}.</p>';
    }

    private function getShippingContent()
    {
        return '<h1>Shipping Policy</h1>
<p><strong>Last updated: ' . date('F j, Y') . '</strong></p>

<h2>1. Shipping Areas</h2>
<p>We currently ship within India to most locations.</p>

<h2>2. Shipping Costs</h2>
<p>Free shipping is available on orders above ₹{{free_shipping_threshold}}.</p>

<h2>3. Contact Us</h2>
<p>For shipping inquiries, contact us at {{support_email}}.</p>';
    }

    private function getAboutContent()
    {
        return '<h1>About BookBharat</h1>

<h2>Our Story</h2>
<p>BookBharat was founded with a simple mission: to make quality books accessible to readers across India.</p>

<h2>Contact Us</h2>
<ul>
<li><strong>Email:</strong> {{support_email}}</li>
<li><strong>Phone:</strong> {{contact_phone}}</li>
<li><strong>Address:</strong> {{company_city}}, {{company_state}}, {{company_country}}</li>
</ul>';
    }
}
