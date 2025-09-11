<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function index()
    {
        // Check for authentication using Sanctum
        $user = request()->user();
        $userId = $user ? $user->id : null;
        $sessionId = request()->header('X-Session-ID');

        \Log::info('CartController::index called', [
            'userId' => $userId, 
            'sessionId' => $sessionId,
            'auth_check' => $user !== null,
            'auth_user' => $user?->id
        ]);

        $cart = $this->cartService->getCart($userId, $sessionId);
        $cartSummary = $this->cartService->getCartSummary($userId, $sessionId);

        return response()->json([
            'success' => true,
            'cart' => [
                'items' => $cart ? $cart->items : [],
                'summary' => $cartSummary,
                'items_count' => $cart ? $cart->items->count() : 0,
                'is_empty' => !$cart || $cart->items->isEmpty()
            ]
        ]);
    }

    public function add(Request $request)
    {
        return $this->store($request);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1|max:99',
            'attributes' => 'nullable|array',
        ]);

        try {
            $user = $request->user();
            $userId = $user ? $user->id : null;
            $sessionId = $request->header('X-Session-ID');

            $cartItem = $this->cartService->addToCart(
                $request->product_id,
                $request->variant_id,
                $request->quantity,
                $request->attributes ?? [],
                $userId,
                $sessionId
            );

            $cartSummary = $this->cartService->getCartSummary($userId, $sessionId);

            return response()->json([
                'success' => true,
                'message' => 'Item added to cart successfully',
                'cart_item' => $cartItem,
                'cart_summary' => $cartSummary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:99',
        ]);

        $user = $request->user();
        $userId = $user ? $user->id : null;
        if ($cartItem->cart->user_id !== $userId && $cartItem->cart->session_id !== $request->header('X-Session-ID')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $updatedItem = $this->cartService->updateCartItem($cartItem, $request->quantity);
            $sessionId = $request->header('X-Session-ID');
            $cartSummary = $this->cartService->getCartSummary($userId, $sessionId);

            return response()->json([
                'success' => true,
                'message' => 'Cart item updated successfully',
                'cart_item' => $updatedItem,
                'cart_summary' => $cartSummary
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(CartItem $cartItem)
    {
        $user = request()->user();
        $userId = $user ? $user->id : null;
        if ($cartItem->cart->user_id !== $userId && $cartItem->cart->session_id !== request()->header('X-Session-ID')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $this->cartService->removeFromCart($cartItem);

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart'
        ]);
    }

    public function clear()
    {
        $user = request()->user();
        $userId = $user ? $user->id : null;
        $sessionId = request()->header('X-Session-ID');
        $this->cartService->clearCart($userId, $sessionId);

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully'
        ]);
    }

    public function applyCoupon(Request $request)
    {
        $request->validate(['coupon_code' => 'required|string']);

        try {
            $user = $request->user();
            $userId = $user ? $user->id : null;
            $sessionId = $request->header('X-Session-ID');
            $result = $this->cartService->applyCoupon($request->coupon_code, $userId, $sessionId);

            return response()->json([
                'success' => true,
                'message' => 'Coupon applied successfully',
                'discount' => $result['discount'],
                'cart_summary' => $result['cart_summary']
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function removeCoupon()
    {
        try {
            $user = request()->user();
            $userId = $user ? $user->id : null;
            $sessionId = request()->header('X-Session-ID');
            $cartSummary = $this->cartService->removeCoupon($userId, $sessionId);

            return response()->json([
                'success' => true,
                'message' => 'Coupon removed successfully',
                'cart_summary' => $cartSummary
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function validateCart()
    {
        try {
            $user = request()->user();
            $userId = $user ? $user->id : null;
            $sessionId = request()->header('X-Session-ID');
            $validation = $this->cartService->validateCart($userId, $sessionId);

            return response()->json(['success' => true, 'validation' => $validation]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function getAvailableCoupons()
    {
        try {
            $coupons = Coupon::valid()
                ->select('id', 'code', 'name', 'description', 'type', 'value', 'minimum_order_amount', 'maximum_discount_amount', 'expires_at')
                ->get()
                ->map(function ($coupon) {
                    return [
                        'id' => $coupon->id,
                        'code' => $coupon->code,
                        'name' => $coupon->name,
                        'description' => $coupon->description,
                        'type' => $coupon->type,
                        'formatted_value' => $coupon->formatted_value,
                        'minimum_order_amount' => $coupon->minimum_order_amount,
                        'maximum_discount_amount' => $coupon->maximum_discount_amount,
                        'expires_at' => $coupon->expires_at?->format('Y-m-d H:i:s'),
                        'is_active' => true,
                    ];
                });

            return response()->json([
                'success' => true,
                'coupons' => $coupons
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
