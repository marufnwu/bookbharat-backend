<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FaqController extends Controller
{
    /**
     * Get all FAQs
     */
    public function index(Request $request)
    {
        try {
            $category = $request->get('category');
            $search = $request->get('search');
            
            // Get FAQs from cache (in production, this would be from database)
            $faqs = Cache::get('faqs', $this->getDefaultFaqs());
            
            // Filter by category if provided
            if ($category) {
                $faqs = array_filter($faqs, function($faq) use ($category) {
                    return $faq['category'] === $category;
                });
            }
            
            // Filter by search term if provided
            if ($search) {
                $faqs = array_filter($faqs, function($faq) use ($search) {
                    return stripos($faq['question'], $search) !== false || 
                           stripos($faq['answer'], $search) !== false;
                });
            }
            
            // Sort by order, then by question
            usort($faqs, function($a, $b) {
                if ($a['order'] === $b['order']) {
                    return strcmp($a['question'], $b['question']);
                }
                return $a['order'] - $b['order'];
            });

            return response()->json([
                'success' => true,
                'data' => array_values($faqs)
            ], 200);

        } catch (\Exception $e) {
            Log::error('FAQ retrieval error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve FAQs.',
            ], 500);
        }
    }

    /**
     * Get FAQ categories
     */
    public function getCategories()
    {
        try {
            $categories = [
                [
                    'id' => 'orders',
                    'name' => 'Orders & Shipping',
                    'description' => 'Questions about placing orders, shipping, and delivery',
                    'icon' => 'truck'
                ],
                [
                    'id' => 'payments',
                    'name' => 'Payments & Billing',
                    'description' => 'Payment methods, billing, and refunds',
                    'icon' => 'credit-card'
                ],
                [
                    'id' => 'books',
                    'name' => 'Books & Products',
                    'description' => 'Book availability, condition, and recommendations',
                    'icon' => 'book-open'
                ],
                [
                    'id' => 'account',
                    'name' => 'Account & Profile',
                    'description' => 'Account management, login issues, and profile settings',
                    'icon' => 'user'
                ],
                [
                    'id' => 'returns',
                    'name' => 'Returns & Exchanges',
                    'description' => 'Return policy, exchanges, and refund process',
                    'icon' => 'rotate-ccw'
                ],
                [
                    'id' => 'technical',
                    'name' => 'Technical Support',
                    'description' => 'Website issues, app problems, and technical help',
                    'icon' => 'settings'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $categories
            ], 200);

        } catch (\Exception $e) {
            Log::error('FAQ categories error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve FAQ categories.',
            ], 500);
        }
    }

    /**
     * Get a specific FAQ by ID
     */
    public function show($id)
    {
        try {
            $faqs = Cache::get('faqs', $this->getDefaultFaqs());
            $faq = collect($faqs)->firstWhere('id', (int)$id);

            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $faq
            ], 200);

        } catch (\Exception $e) {
            Log::error('FAQ retrieval error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve FAQ.',
            ], 500);
        }
    }

    /**
     * Search FAQs
     */
    public function search(Request $request)
    {
        try {
            $query = $request->get('q', '');
            
            if (strlen($query) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search query must be at least 2 characters.',
                ], 422);
            }

            $faqs = Cache::get('faqs', $this->getDefaultFaqs());
            
            // Search in questions and answers
            $results = array_filter($faqs, function($faq) use ($query) {
                return stripos($faq['question'], $query) !== false || 
                       stripos($faq['answer'], $query) !== false ||
                       stripos($faq['category'], $query) !== false;
            });
            
            // Sort by relevance (question matches first, then answer matches)
            usort($results, function($a, $b) use ($query) {
                $aQuestionMatch = stripos($a['question'], $query) !== false;
                $bQuestionMatch = stripos($b['question'], $query) !== false;
                
                if ($aQuestionMatch && !$bQuestionMatch) return -1;
                if (!$aQuestionMatch && $bQuestionMatch) return 1;
                
                return strcmp($a['question'], $b['question']);
            });

            return response()->json([
                'success' => true,
                'data' => array_values($results),
                'query' => $query,
                'count' => count($results)
            ], 200);

        } catch (\Exception $e) {
            Log::error('FAQ search error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to search FAQs.',
            ], 500);
        }
    }

    /**
     * Get default FAQs data
     */
    private function getDefaultFaqs()
    {
        return [
            // Orders & Shipping
            [
                'id' => 1,
                'question' => 'How do I track my order?',
                'answer' => 'You can track your order by logging into your account and visiting the "My Orders" section. Once your order is shipped, you\'ll receive a tracking number via email and SMS.',
                'category' => 'orders',
                'order' => 1,
                'helpful_count' => 45,
                'not_helpful_count' => 2
            ],
            [
                'id' => 2,
                'question' => 'What are the shipping charges?',
                'answer' => 'We offer free shipping on orders above ₹499. For orders below ₹499, shipping charges vary based on your location and typically range from ₹40-₹80.',
                'category' => 'orders',
                'order' => 2,
                'helpful_count' => 38,
                'not_helpful_count' => 1
            ],
            [
                'id' => 3,
                'question' => 'How long does delivery take?',
                'answer' => 'Delivery times vary by location:\n• Metro cities: 2-3 business days\n• Other cities: 3-5 business days\n• Remote areas: 5-7 business days\n\nOrders placed on weekends or holidays may take an additional day.',
                'category' => 'orders',
                'order' => 3,
                'helpful_count' => 52,
                'not_helpful_count' => 3
            ],
            [
                'id' => 4,
                'question' => 'Can I modify or cancel my order?',
                'answer' => 'You can modify or cancel your order within 1 hour of placing it, provided it hasn\'t been shipped. To cancel or modify, visit "My Orders" or contact our customer support.',
                'category' => 'orders',
                'order' => 4,
                'helpful_count' => 29,
                'not_helpful_count' => 1
            ],

            // Payments & Billing
            [
                'id' => 5,
                'question' => 'What payment methods do you accept?',
                'answer' => 'We accept various payment methods:\n• Credit/Debit Cards (Visa, MasterCard, RuPay)\n• UPI (Google Pay, PhonePe, Paytm)\n• Net Banking\n• Digital Wallets\n• Cash on Delivery (COD)\n\nAll payments are secure and encrypted.',
                'category' => 'payments',
                'order' => 1,
                'helpful_count' => 67,
                'not_helpful_count' => 0
            ],
            [
                'id' => 6,
                'question' => 'Is Cash on Delivery (COD) available?',
                'answer' => 'Yes, COD is available for most locations across India. COD charges may apply for orders below ₹499. Please check during checkout if COD is available for your pincode.',
                'category' => 'payments',
                'order' => 2,
                'helpful_count' => 41,
                'not_helpful_count' => 2
            ],
            [
                'id' => 7,
                'question' => 'How do refunds work?',
                'answer' => 'Refunds are processed to your original payment method within 7-10 business days of return approval. For COD orders, refunds are processed via bank transfer or UPI.',
                'category' => 'payments',
                'order' => 3,
                'helpful_count' => 33,
                'not_helpful_count' => 1
            ],

            // Books & Products
            [
                'id' => 8,
                'question' => 'Are the books original and new?',
                'answer' => 'Yes, all our books are 100% original and new. We source directly from publishers and authorized distributors to ensure authenticity and quality.',
                'category' => 'books',
                'order' => 1,
                'helpful_count' => 78,
                'not_helpful_count' => 0
            ],
            [
                'id' => 9,
                'question' => 'Do you have books in regional languages?',
                'answer' => 'Yes, we have a wide collection of books in various regional languages including Hindi, Bengali, Tamil, Telugu, Marathi, Gujarati, and more. Use our language filter to find books in your preferred language.',
                'category' => 'books',
                'order' => 2,
                'helpful_count' => 56,
                'not_helpful_count' => 1
            ],
            [
                'id' => 10,
                'question' => 'Can I request a book that\'s not available?',
                'answer' => 'Absolutely! If you can\'t find a book you\'re looking for, contact our customer support with the book details. We\'ll do our best to source it for you.',
                'category' => 'books',
                'order' => 3,
                'helpful_count' => 42,
                'not_helpful_count' => 2
            ],

            // Account & Profile
            [
                'id' => 11,
                'question' => 'How do I create an account?',
                'answer' => 'Click on "Sign Up" at the top of any page and provide your email and create a password. You can also sign up during checkout or use social login options.',
                'category' => 'account',
                'order' => 1,
                'helpful_count' => 34,
                'not_helpful_count' => 0
            ],
            [
                'id' => 12,
                'question' => 'I forgot my password. How do I reset it?',
                'answer' => 'Click on "Forgot Password" on the login page, enter your email address, and you\'ll receive a password reset link. Follow the instructions in the email to create a new password.',
                'category' => 'account',
                'order' => 2,
                'helpful_count' => 28,
                'not_helpful_count' => 1
            ],
            [
                'id' => 13,
                'question' => 'How do I update my profile information?',
                'answer' => 'Log into your account and go to "My Profile" to update your personal information, contact details, and addresses. Make sure to save changes after updating.',
                'category' => 'account',
                'order' => 3,
                'helpful_count' => 31,
                'not_helpful_count' => 0
            ],

            // Returns & Exchanges
            [
                'id' => 14,
                'question' => 'What is your return policy?',
                'answer' => 'We offer a 30-day return policy from the date of delivery. Books must be in original condition with all packaging intact. Digital products cannot be returned.',
                'category' => 'returns',
                'order' => 1,
                'helpful_count' => 49,
                'not_helpful_count' => 2
            ],
            [
                'id' => 15,
                'question' => 'How do I return a book?',
                'answer' => 'To return a book:\n1. Go to "My Orders" and select the order\n2. Click on "Return Item"\n3. Select the reason for return\n4. We\'ll arrange pickup or provide return instructions\n5. Once received and verified, refund will be processed',
                'category' => 'returns',
                'order' => 2,
                'helpful_count' => 37,
                'not_helpful_count' => 1
            ],
            [
                'id' => 16,
                'question' => 'Can I exchange a book for a different one?',
                'answer' => 'Currently, we don\'t offer direct exchanges. You can return the book for a refund and place a new order for the book you want.',
                'category' => 'returns',
                'order' => 3,
                'helpful_count' => 25,
                'not_helpful_count' => 4
            ],

            // Technical Support
            [
                'id' => 17,
                'question' => 'The website is not loading properly. What should I do?',
                'answer' => 'Try these steps:\n1. Clear your browser cache and cookies\n2. Try a different browser or incognito mode\n3. Check your internet connection\n4. Disable browser extensions temporarily\n\nIf the issue persists, contact our technical support.',
                'category' => 'technical',
                'order' => 1,
                'helpful_count' => 23,
                'not_helpful_count' => 2
            ],
            [
                'id' => 18,
                'question' => 'I\'m having trouble placing an order. What should I do?',
                'answer' => 'If you\'re experiencing issues during checkout:\n1. Ensure all required fields are filled correctly\n2. Check if your payment method is valid\n3. Try a different payment method\n4. Clear browser cache\n\nContact support if the problem continues.',
                'category' => 'technical',
                'order' => 2,
                'helpful_count' => 19,
                'not_helpful_count' => 1
            ],
            [
                'id' => 19,
                'question' => 'How do I contact customer support?',
                'answer' => 'You can reach our customer support team:\n• Email: support@bookbharat.com\n• Phone: +91 12345 67890 (Mon-Fri, 9 AM - 6 PM)\n• Contact form on our website\n• Live chat (when available)\n\nWe respond to all queries within 24 hours.',
                'category' => 'technical',
                'order' => 3,
                'helpful_count' => 61,
                'not_helpful_count' => 0
            ]
        ];
    }
}