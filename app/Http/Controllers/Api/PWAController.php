<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PWAController extends Controller
{
    public function manifest()
    {
        $manifest = [
            'name' => config('app.name', 'BookBharat'),
            'short_name' => 'BookBharat',
            'description' => 'Your premier online bookstore - Discover, Read, Share',
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#2563eb',
            'orientation' => 'portrait-primary',
            'categories' => ['books', 'shopping', 'education'],
            'icons' => [
                [
                    'src' => '/pwa-icons/icon-72x72.png',
                    'sizes' => '72x72',
                    'type' => 'image/png',
                    'purpose' => 'maskable any'
                ],
                [
                    'src' => '/pwa-icons/icon-96x96.png',
                    'sizes' => '96x96',
                    'type' => 'image/png',
                    'purpose' => 'maskable any'
                ],
                [
                    'src' => '/pwa-icons/icon-128x128.png',
                    'sizes' => '128x128',
                    'type' => 'image/png',
                    'purpose' => 'maskable any'
                ],
                [
                    'src' => '/pwa-icons/icon-144x144.png',
                    'sizes' => '144x144',
                    'type' => 'image/png',
                    'purpose' => 'maskable any'
                ],
                [
                    'src' => '/pwa-icons/icon-152x152.png',
                    'sizes' => '152x152',
                    'type' => 'image/png',
                    'purpose' => 'maskable any'
                ],
                [
                    'src' => '/pwa-icons/icon-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable any'
                ],
                [
                    'src' => '/pwa-icons/icon-384x384.png',
                    'sizes' => '384x384',
                    'type' => 'image/png',
                    'purpose' => 'maskable any'
                ],
                [
                    'src' => '/pwa-icons/icon-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable any'
                ]
            ],
            'screenshots' => [
                [
                    'src' => '/pwa-screenshots/mobile-home.png',
                    'sizes' => '540x720',
                    'type' => 'image/png',
                    'form_factor' => 'narrow'
                ],
                [
                    'src' => '/pwa-screenshots/desktop-home.png',
                    'sizes' => '1280x720',
                    'type' => 'image/png',
                    'form_factor' => 'wide'
                ]
            ],
            'shortcuts' => [
                [
                    'name' => 'Search Books',
                    'short_name' => 'Search',
                    'description' => 'Search for books',
                    'url' => '/search',
                    'icons' => [
                        [
                            'src' => '/pwa-icons/search-96x96.png',
                            'sizes' => '96x96'
                        ]
                    ]
                ],
                [
                    'name' => 'My Orders',
                    'short_name' => 'Orders',
                    'description' => 'View your orders',
                    'url' => '/orders',
                    'icons' => [
                        [
                            'src' => '/pwa-icons/orders-96x96.png',
                            'sizes' => '96x96'
                        ]
                    ]
                ],
                [
                    'name' => 'Wishlist',
                    'short_name' => 'Wishlist',
                    'description' => 'View your wishlist',
                    'url' => '/wishlist',
                    'icons' => [
                        [
                            'src' => '/pwa-icons/wishlist-96x96.png',
                            'sizes' => '96x96'
                        ]
                    ]
                ]
            ],
            'related_applications' => [
                [
                    'platform' => 'play',
                    'url' => 'https://play.google.com/store/apps/details?id=com.bookbharat.app',
                    'id' => 'com.bookbharat.app'
                ]
            ]
        ];

        return response()->json($manifest)
                         ->header('Content-Type', 'application/manifest+json');
    }

    public function serviceWorker()
    {
        $serviceWorker = "
const CACHE_NAME = 'bookbharat-v" . config('app.version', '1.0.0') . "';
const urlsToCache = [
  '/',
  '/css/app.css',
  '/js/app.js',
  '/pwa-icons/icon-192x192.png',
  '/offline.html'
];

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('fetch', function(event) {
  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        if (response) {
          return response;
        }

        return fetch(event.request).then(
          function(response) {
            if(!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            var responseToCache = response.clone();

            caches.open(CACHE_NAME)
              .then(function(cache) {
                cache.put(event.request, responseToCache);
              });

            return response;
          }
        );
      }).catch(function() {
        if (event.request.destination === 'document') {
          return caches.match('/offline.html');
        }
      })
    );
});

self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

// Background sync for cart updates
self.addEventListener('sync', function(event) {
  if (event.tag === 'background-sync-cart') {
    event.waitUntil(syncCart());
  }
});

function syncCart() {
  return self.registration.sync.register('background-sync-cart');
}

// Push notifications
self.addEventListener('push', function(event) {
  const options = {
    body: event.data ? event.data.text() : 'New notification from BookBharat',
    icon: '/pwa-icons/icon-192x192.png',
    badge: '/pwa-icons/badge-72x72.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: '2'
    },
    actions: [
      {
        action: 'explore',
        title: 'Explore',
        icon: '/pwa-icons/checkmark.png'
      },
      {
        action: 'close',
        title: 'Close',
        icon: '/pwa-icons/xmark.png'
      }
    ]
  };

  event.waitUntil(
    self.registration.showNotification('BookBharat', options)
  );
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();

  if (event.action === 'explore') {
    event.waitUntil(
      clients.openWindow('/')
    );
  } else if (event.action === 'close') {
    event.notification.close();
  }
});
        ";

        return response($serviceWorker)
                 ->header('Content-Type', 'application/javascript')
                 ->header('Service-Worker-Allowed', '/');
    }

    public function offline()
    {
        $offlineHtml = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - BookBharat</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            text-align: center;
            padding: 50px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .offline-icon {
            font-size: 80px;
            margin-bottom: 30px;
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        .retry-btn {
            background: rgba(255,255,255,0.2);
            border: 2px solid white;
            color: white;
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .retry-btn:hover {
            background: white;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="offline-icon">📚</div>
    <h1>You\'re Offline</h1>
    <p>Please check your internet connection and try again.</p>
    <button class="retry-btn" onclick="window.location.reload()">Retry</button>
</body>
</html>
        ';

        return response($offlineHtml)->header('Content-Type', 'text/html');
    }

    public function getMobileHomepage()
    {
        return Cache::remember('mobile_homepage_data', 300, function () {
            return [
                'banners' => $this->getMobileBanners(),
                'featured_products' => Product::featured()
                    ->inStock()
                    ->select(['id', 'name', 'slug', 'price', 'compare_price', 'primary_image', 'average_rating'])
                    ->limit(6)
                    ->get(),
                'categories' => Category::active()
                    ->select(['id', 'name', 'slug', 'image'])
                    ->limit(8)
                    ->get(),
                'trending_products' => Product::active()
                    ->inStock()
                    ->withCount(['orderItems' => function ($query) {
                        $query->whereHas('order', function ($q) {
                            $q->where('created_at', '>=', now()->subDays(7));
                        });
                    }])
                    ->orderBy('order_items_count', 'desc')
                    ->select(['id', 'name', 'slug', 'price', 'compare_price', 'primary_image', 'average_rating'])
                    ->limit(6)
                    ->get(),
                'new_arrivals' => Product::active()
                    ->inStock()
                    ->latest()
                    ->select(['id', 'name', 'slug', 'price', 'compare_price', 'primary_image', 'average_rating'])
                    ->limit(6)
                    ->get(),
            ];
        });
    }

    public function getMobileUserData()
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        
        $userData = [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url,
            ],
            'cart_count' => $user->cart()->sum('quantity'),
            'wishlist_count' => $user->wishlists()->count(),
            'recent_orders' => $user->orders()
                ->latest()
                ->limit(3)
                ->select(['id', 'order_number', 'status', 'total_amount', 'created_at'])
                ->get(),
            'loyalty_points' => $user->loyaltyAccount ? $user->loyaltyAccount->points_balance : 0,
            'notifications_count' => $user->unreadNotifications()->count(),
            'addresses_count' => $user->addresses()->count(),
        ];

        return response()->json([
            'success' => true,
            'user_data' => $userData
        ]);
    }

    public function installPromptData()
    {
        return response()->json([
            'success' => true,
            'install_prompt' => [
                'title' => 'Install BookBharat App',
                'description' => 'Get the full app experience with offline reading, push notifications, and faster loading.',
                'features' => [
                    'Offline browsing',
                    'Push notifications for orders',
                    'Faster loading times',
                    'Home screen access',
                    'Full-screen experience'
                ],
                'cta_text' => 'Install App'
            ]
        ]);
    }

    public function registerPushNotification(Request $request)
    {
        $request->validate([
            'subscription' => 'required|array',
            'subscription.endpoint' => 'required|url',
            'subscription.keys' => 'required|array',
            'subscription.keys.p256dh' => 'required|string',
            'subscription.keys.auth' => 'required|string',
        ]);

        if (!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        
        // Store push subscription in user preferences
        $preferences = $user->preferences ?? [];
        $preferences['push_subscription'] = $request->subscription;
        
        $user->update(['preferences' => $preferences]);

        return response()->json([
            'success' => true,
            'message' => 'Push notification subscription registered successfully'
        ]);
    }

    public function getOfflineData()
    {
        return Cache::remember('pwa_offline_data', 3600, function () {
            return [
                'categories' => Category::active()
                    ->select(['id', 'name', 'slug'])
                    ->get(),
                'popular_searches' => [
                    'fiction books',
                    'bestsellers',
                    'mystery novels',
                    'self help',
                    'biography',
                    'science fiction',
                    'romance',
                    'philosophy'
                ],
                'emergency_contacts' => [
                    'support_email' => config('mail.support_email', 'support@bookbharat.com'),
                    'support_phone' => config('app.support_phone', '+91-1234567890'),
                ],
                'offline_message' => [
                    'title' => 'You\'re currently offline',
                    'message' => 'Some features may not be available. We\'ll sync your data when you\'re back online.',
                ]
            ];
        });
    }

    protected function getMobileBanners()
    {
        return [
            [
                'id' => 1,
                'title' => 'New Arrivals',
                'subtitle' => 'Discover the latest books',
                'image' => '/images/banners/mobile-new-arrivals.jpg',
                'cta' => 'Shop Now',
                'link' => '/categories/new-arrivals'
            ],
            [
                'id' => 2,
                'title' => 'Special Offer',
                'subtitle' => 'Up to 50% off on bestsellers',
                'image' => '/images/banners/mobile-special-offer.jpg',
                'cta' => 'View Deals',
                'link' => '/offers'
            ],
            [
                'id' => 3,
                'title' => 'Free Delivery',
                'subtitle' => 'On orders above ₹499',
                'image' => '/images/banners/mobile-free-delivery.jpg',
                'cta' => 'Order Now',
                'link' => '/products'
            ]
        ];
    }
}