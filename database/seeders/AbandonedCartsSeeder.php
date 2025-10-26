<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PersistentCart;
use App\Models\User;
use Carbon\Carbon;

class AbandonedCartsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some users
        $users = User::take(10)->get();

        // If no users exist, create a demo user
        if ($users->isEmpty()) {
            $user = User::firstOrCreate(
                ['email' => 'demo@example.com'],
                [
                    'name' => 'Demo User',
                    'password' => bcrypt('password'),
                ]
            );
            $users = collect([$user]);
        }

        // Sample cart data
        $cartScenarios = [
            // Recent abandonments (last 6 hours)
            [
                'user_id' => $users->random()->id,
                'session_id' => 'session_' . uniqid(),
                'is_abandoned' => true,
                'cart_data' => json_encode([
                    [
                        'id' => 1,
                        'name' => 'The Great Gatsby',
                        'price' => 299,
                        'quantity' => 2,
                        'total' => 598
                    ],
                    [
                        'id' => 5,
                        'name' => 'To Kill a Mockingbird',
                        'price' => 349,
                        'quantity' => 1,
                        'total' => 349
                    ]
                ]),
                'total_amount' => 947,
                'items_count' => 3,
                'abandoned_at' => Carbon::now()->subHours(2),
                'recovery_email_count' => 0,
            ],

            // Abandoned 12 hours ago (first email sent)
            [
                'user_id' => $users->random()->id,
                'session_id' => 'session_' . uniqid(),
                'is_abandoned' => true,
                'cart_data' => json_encode([
                    [
                        'id' => 3,
                        'name' => '1984 by George Orwell',
                        'price' => 279,
                        'quantity' => 1,
                        'total' => 279
                    ]
                ]),
                'total_amount' => 279,
                'items_count' => 1,
                'abandoned_at' => Carbon::now()->subHours(12),
                'recovery_email_count' => 1,
                'last_recovery_email_sent' => Carbon::now()->subHours(11),
                'recovery_token' => bin2hex(random_bytes(16)),
            ],

            // Abandoned 30 hours ago (second email sent)
            [
                'user_id' => $users->random()->id,
                'session_id' => 'session_' . uniqid(),
                'is_abandoned' => true,
                'cart_data' => json_encode([
                    [
                        'id' => 7,
                        'name' => 'Pride and Prejudice',
                        'price' => 299,
                        'quantity' => 1,
                        'total' => 299
                    ],
                    [
                        'id' => 8,
                        'name' => 'Wuthering Heights',
                        'price' => 319,
                        'quantity' => 1,
                        'total' => 319
                    ],
                    [
                        'id' => 9,
                        'name' => 'Jane Eyre',
                        'price' => 289,
                        'quantity' => 1,
                        'total' => 289
                    ]
                ]),
                'total_amount' => 907,
                'items_count' => 3,
                'abandoned_at' => Carbon::now()->subHours(30),
                'recovery_email_count' => 2,
                'last_recovery_email_sent' => Carbon::now()->subHours(6),
                'recovery_token' => bin2hex(random_bytes(16)),
            ],

            // High-value cart (above 1000)
            [
                'user_id' => $users->random()->id,
                'session_id' => 'session_' . uniqid(),
                'is_abandoned' => true,
                'cart_data' => json_encode([
                    [
                        'id' => 10,
                        'name' => 'Complete Works of Shakespeare',
                        'price' => 899,
                        'quantity' => 1,
                        'total' => 899
                    ],
                    [
                        'id' => 11,
                        'name' => 'War and Peace',
                        'price' => 749,
                        'quantity' => 1,
                        'total' => 749
                    ]
                ]),
                'total_amount' => 1648,
                'items_count' => 2,
                'abandoned_at' => Carbon::now()->subHours(8),
                'recovery_email_count' => 0,
            ],

            // Guest cart (no user_id)
            [
                'user_id' => null,
                'session_id' => 'guest_' . uniqid(),
                'is_abandoned' => true,
                'cart_data' => json_encode([
                    [
                        'id' => 2,
                        'name' => 'Animal Farm',
                        'price' => 259,
                        'quantity' => 1,
                        'total' => 259
                    ]
                ]),
                'total_amount' => 259,
                'items_count' => 1,
                'abandoned_at' => Carbon::now()->subHours(4),
                'recovery_email_count' => 0,
            ],

            // Old abandonment (past email sequence)
            [
                'user_id' => $users->random()->id,
                'session_id' => 'session_' . uniqid(),
                'is_abandoned' => true,
                'cart_data' => json_encode([
                    [
                        'id' => 12,
                        'name' => 'The Catcher in the Rye',
                        'price' => 299,
                        'quantity' => 2,
                        'total' => 598
                    ]
                ]),
                'total_amount' => 598,
                'items_count' => 2,
                'abandoned_at' => Carbon::now()->subDays(5),
                'recovery_email_count' => 3,
                'last_recovery_email_sent' => Carbon::now()->subDays(3),
                'recovery_token' => bin2hex(random_bytes(16)),
            ],

            // Just abandoned (30 minutes ago)
            [
                'user_id' => $users->random()->id,
                'session_id' => 'session_' . uniqid(),
                'is_abandoned' => true,
                'cart_data' => json_encode([
                    [
                        'id' => 13,
                        'name' => 'The Alchemist',
                        'price' => 349,
                        'quantity' => 3,
                        'total' => 1047
                    ],
                    [
                        'id' => 14,
                        'name' => 'The Kite Runner',
                        'price' => 399,
                        'quantity' => 1,
                        'total' => 399
                    ]
                ]),
                'total_amount' => 1446,
                'items_count' => 4,
                'abandoned_at' => Carbon::now()->subMinutes(30),
                'recovery_email_count' => 0,
            ],

            // Cart with single expensive book
            [
                'user_id' => $users->random()->id,
                'session_id' => 'session_' . uniqid(),
                'is_abandoned' => true,
                'cart_data' => json_encode([
                    [
                        'id' => 15,
                        'name' => 'Atlas of World History',
                        'price' => 1299,
                        'quantity' => 1,
                        'total' => 1299
                    ]
                ]),
                'total_amount' => 1299,
                'items_count' => 1,
                'abandoned_at' => Carbon::now()->subHours(15),
                'recovery_email_count' => 1,
                'last_recovery_email_sent' => Carbon::now()->subHours(14),
                'recovery_token' => bin2hex(random_bytes(16)),
            ],
        ];

        // Create the abandoned carts
        foreach ($cartScenarios as $cartData) {
            PersistentCart::updateOrCreate(
                [
                    'session_id' => $cartData['session_id'],
                ],
                $cartData
            );
        }

        $this->command->info('Created ' . count($cartScenarios) . ' demo abandoned carts.');
    }
}
