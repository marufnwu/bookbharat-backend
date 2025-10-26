<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ContentBlock;

class ContentBlocksSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            // Error Messages (EN)
            ['key' => 'error.404.title', 'language' => 'en', 'category' => 'error', 'content' => 'Page Not Found', 'description' => '404 page title'],
            ['key' => 'error.404.message', 'language' => 'en', 'category' => 'error', 'content' => 'The page you are looking for could not be found.', 'description' => '404 page message'],
            ['key' => 'error.500.title', 'language' => 'en', 'category' => 'error', 'content' => 'Server Error', 'description' => '500 page title'],
            ['key' => 'error.500.message', 'language' => 'en', 'category' => 'error', 'content' => 'Something went wrong on our end. Please try again later.', 'description' => '500 page message'],

            // Error Messages (HI)
            ['key' => 'error.404.title', 'language' => 'hi', 'category' => 'error', 'content' => 'पृष्ठ नहीं मिला', 'description' => '404 page title'],
            ['key' => 'error.404.message', 'language' => 'hi', 'category' => 'error', 'content' => 'आप जिस पृष्ठ की तलाश कर रहे हैं वह नहीं मिला।', 'description' => '404 page message'],
            ['key' => 'error.500.title', 'language' => 'hi', 'category' => 'error', 'content' => 'सर्वर त्रुटि', 'description' => '500 page title'],
            ['key' => 'error.500.message', 'language' => 'hi', 'category' => 'error', 'content' => 'हमारी तरफ से कुछ गलत हुआ। कृपया बाद में पुनः प्रयास करें।', 'description' => '500 page message'],

            // Empty States (EN)
            ['key' => 'empty.cart.message', 'language' => 'en', 'category' => 'empty_state', 'content' => 'Your cart is empty. Start adding products!', 'description' => 'Empty cart message'],
            ['key' => 'empty.wishlist.message', 'language' => 'en', 'category' => 'empty_state', 'content' => 'Your wishlist is empty. Add items you love!', 'description' => 'Empty wishlist message'],
            ['key' => 'empty.orders.message', 'language' => 'en', 'category' => 'empty_state', 'content' => 'You have no orders yet. Start shopping!', 'description' => 'No orders message'],
            ['key' => 'empty.search.message', 'language' => 'en', 'category' => 'empty_state', 'content' => 'No products found. Try different keywords.', 'description' => 'No search results message'],

            // Empty States (HI)
            ['key' => 'empty.cart.message', 'language' => 'hi', 'category' => 'empty_state', 'content' => 'आपकी टोकरी खाली है। उत्पाद जोड़ना शुरू करें!', 'description' => 'Empty cart message'],
            ['key' => 'empty.wishlist.message', 'language' => 'hi', 'category' => 'empty_state', 'content' => 'आपकी इच्छा सूची खाली है। अपने पसंदीदा आइटम जोड़ें!', 'description' => 'Empty wishlist message'],
            ['key' => 'empty.orders.message', 'language' => 'hi', 'category' => 'empty_state', 'content' => 'आपके कोई ऑर्डर नहीं हैं। खरीदारी शुरू करें!', 'description' => 'No orders message'],
            ['key' => 'empty.search.message', 'language' => 'hi', 'category' => 'empty_state', 'content' => 'कोई उत्पाद नहीं मिला। अलग कीवर्ड आजमाएं।', 'description' => 'No search results message'],

            // Success Messages (EN)
            ['key' => 'success.order.placed', 'language' => 'en', 'category' => 'success', 'content' => 'Your order has been placed successfully!', 'description' => 'Order placed success'],
            ['key' => 'success.profile.updated', 'language' => 'en', 'category' => 'success', 'content' => 'Your profile has been updated successfully!', 'description' => 'Profile updated success'],
            ['key' => 'success.address.added', 'language' => 'en', 'category' => 'success', 'content' => 'Address added successfully!', 'description' => 'Address added success'],
            ['key' => 'success.password.changed', 'language' => 'en', 'category' => 'success', 'content' => 'Your password has been changed successfully!', 'description' => 'Password changed success'],

            // Success Messages (HI)
            ['key' => 'success.order.placed', 'language' => 'hi', 'category' => 'success', 'content' => 'आपका ऑर्डर सफलतापूर्वक रखा गया है!', 'description' => 'Order placed success'],
            ['key' => 'success.profile.updated', 'language' => 'hi', 'category' => 'success', 'content' => 'आपका प्रोफाइल सफलतापूर्वक अपडेट कर दिया गया है!', 'description' => 'Profile updated success'],
            ['key' => 'success.address.added', 'language' => 'hi', 'category' => 'success', 'content' => 'पता सफलतापूर्वक जोड़ दिया गया!', 'description' => 'Address added success'],
            ['key' => 'success.password.changed', 'language' => 'hi', 'category' => 'success', 'content' => 'आपका पासवर्ड सफलतापूर्वक बदल दिया गया है!', 'description' => 'Password changed success'],

            // Loading Messages (EN)
            ['key' => 'loading.products', 'language' => 'en', 'category' => 'loading', 'content' => 'Loading products...', 'description' => 'Loading products message'],
            ['key' => 'loading.checkout', 'language' => 'en', 'category' => 'loading', 'content' => 'Processing your order...', 'description' => 'Checkout processing message'],
            ['key' => 'loading.page', 'language' => 'en', 'category' => 'loading', 'content' => 'Loading...', 'description' => 'Generic loading message'],
            ['key' => 'loading.search', 'language' => 'en', 'category' => 'loading', 'content' => 'Searching...', 'description' => 'Search loading message'],

            // Loading Messages (HI)
            ['key' => 'loading.products', 'language' => 'hi', 'category' => 'loading', 'content' => 'उत्पाद लोड हो रहे हैं...', 'description' => 'Loading products message'],
            ['key' => 'loading.checkout', 'language' => 'hi', 'category' => 'loading', 'content' => 'आपका ऑर्डर संसाधित किया जा रहा है...', 'description' => 'Checkout processing message'],
            ['key' => 'loading.page', 'language' => 'hi', 'category' => 'loading', 'content' => 'लोड हो रहा है...', 'description' => 'Generic loading message'],
            ['key' => 'loading.search', 'language' => 'hi', 'category' => 'loading', 'content' => 'खोज रहे हैं...', 'description' => 'Search loading message'],
        ];

        foreach ($blocks as $block) {
            ContentBlock::updateOrCreate(
                [
                    'key' => $block['key'],
                    'language' => $block['language'],
                ],
                $block
            );
        }
    }
}
