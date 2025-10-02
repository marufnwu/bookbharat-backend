<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Address;
use App\Services\CartService;
use App\Services\OrderAutomationService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderController extends Controller
{
    protected $cartService;
    protected $orderService;
    protected $paymentService;

    public function __construct(CartService $cartService, OrderAutomationService $orderService, PaymentService $paymentService)
    {
        $this->cartService = $cartService;
        $this->orderService = $orderService;
        $this->paymentService = $paymentService;
    }

    public function index(Request $request)
    {
        $query = Auth::user()->orders()->with(['orderItems.product', 'referralCode']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where('order_number', 'like', '%' . $request->search . '%');
        }

        $orders = $query->orderBy('created_at', 'desc')
                        ->paginate($request->input('per_page', 10));

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    public function store(Request $request)
    {
        // Determine if user is authenticated
        $user = Auth::user();
        $isAuthenticated = $user !== null;
        
        // Dynamic validation based on authentication status
        if ($isAuthenticated) {
            $request->validate([
                'shipping_address_id' => 'required|exists:addresses,id',
                'billing_address_id' => 'required|exists:addresses,id',
                'payment_method' => 'required|string',
                'coupon_code' => 'nullable|string',
                'notes' => 'nullable|string|max:500',
                // REMOVED: shipping_cost - SECURITY: Never trust client for money calculations
            ]);
        } else {
            $request->validate([
                'shipping_address' => 'required|array',
                'shipping_address.name' => 'required|string|max:255',
                'shipping_address.phone' => 'required|string|max:20',
                'shipping_address.email' => 'nullable|email',
                'shipping_address.address_line_1' => 'required|string|max:255',
                'shipping_address.city' => 'required|string|max:100',
                'shipping_address.state' => 'required|string|max:100',
                'shipping_address.postal_code' => 'required|string|max:10',
                'shipping_address.country' => 'required|string|max:100',
                'billing_address' => 'required|array',
                'billing_address.name' => 'required|string|max:255',
                'billing_address.phone' => 'required|string|max:20',
                'billing_address.email' => 'nullable|email',
                'billing_address.address_line_1' => 'required|string|max:255',
                'billing_address.city' => 'required|string|max:100',
                'billing_address.state' => 'required|string|max:100',
                'billing_address.postal_code' => 'required|string|max:10',
                'billing_address.country' => 'required|string|max:100',
                'payment_method' => 'required|string',
                'coupon_code' => 'nullable|string',
                'notes' => 'nullable|string|max:500',
                // REMOVED: shipping_cost - SECURITY: Never trust client for money calculations
            ]);
        }

        try {
            DB::beginTransaction();

            $sessionId = $request->header('X-Session-ID');

            // Validate cart
            $userId = $isAuthenticated ? $user->id : null;
            $cart = $this->cartService->getCart($userId, $sessionId);
            if (!$cart || !$cart->items || $cart->items->isEmpty()) {
                throw new \Exception('Cart is empty or not found');
            }
            
            // Validate each cart item for stock availability
            foreach ($cart->items as $item) {
                $product = $item->product;
                $variant = $item->variant;
                if ($variant) {
                    if ($variant->available_stock < $item->quantity) {
                        throw new \Exception("Insufficient stock for {$product->name} - {$variant->name}. Available: {$variant->available_stock}, Requested: {$item->quantity}");
                    }
                } else {
                    if ($product->stock_quantity < $item->quantity) {
                        throw new \Exception("Insufficient stock for {$product->name}. Available: {$product->stock_quantity}, Requested: {$item->quantity}");
                    }
                }
            }

            // Get addresses
            if ($isAuthenticated) {
                // For authenticated users, get addresses by ID and verify ownership
                $shippingAddress = $user->addresses()->findOrFail($request->shipping_address_id);
                $billingAddress = $user->addresses()->findOrFail($request->billing_address_id);

                $shippingAddressData = $shippingAddress->toArray();
                $billingAddressData = $billingAddress->toArray();

                // Get delivery pincode for shipping calculation
                $deliveryPincode = $shippingAddress->postal_code;
            } else {
                // For guest users, use provided address data
                $shippingAddressData = $request->shipping_address;
                $billingAddressData = $request->billing_address;

                // Get delivery pincode for shipping calculation
                $deliveryPincode = $request->shipping_address['postal_code'];
            }

            // SECURITY: Always calculate shipping server-side, NEVER trust client
            // Recalculate cart summary with proper shipping based on delivery address
            $cartSummary = $this->cartService->getCartSummary($userId, $sessionId, $deliveryPincode);

            // Use server-calculated shipping amount (NEVER from client)
            $shippingAmount = $cartSummary['shipping_cost'];
            $discountAmount = $cartSummary['coupon_discount'] ?? 0;
            $totalAmount = $cartSummary['discounted_subtotal'] + $cartSummary['tax_amount'] + $shippingAmount;

            // Create order
            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'user_id' => $userId,
                'status' => 'pending',
                'subtotal' => $cartSummary['subtotal'],
                'tax_amount' => $cartSummary['tax_amount'],
                'shipping_amount' => $shippingAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'currency' => 'INR',
                'payment_method' => $request->payment_method,
                'billing_address' => $billingAddressData,
                'shipping_address' => $shippingAddressData,
                'notes' => $request->notes,
            ]);

            // Create order items from cart (we already have $cart from validation)
            if ($cart && $cart->items) {
                foreach ($cart->items as $cartItem) {
                    $product = $cartItem->product;
                    $variant = $cartItem->variant;
                    
                    // Get product name (with variant name if applicable)
                    $productName = $product->name;
                    if ($variant && $variant->name) {
                        $productName .= ' - ' . $variant->name;
                    }
                    
                    // Get product SKU (use variant SKU if available, otherwise product SKU)
                    $productSku = $variant && $variant->sku ? $variant->sku : ($product->sku ?? $product->slug ?? 'N/A');
                    
                    $order->orderItems()->create([
                        'product_id' => $cartItem->product_id,
                        'product_name' => $productName,
                        'product_sku' => $productSku,
                        'quantity' => $cartItem->quantity,
                        'unit_price' => $cartItem->unit_price,
                        'total_price' => $cartItem->total_price ?? ($cartItem->unit_price * $cartItem->quantity),
                        'product_attributes' => $cartItem->attributes ? json_encode($cartItem->attributes) : null,
                    ]);
                }
            }

            // Apply coupon if provided
            if ($request->coupon_code) {
                $this->cartService->applyCoupon($request->coupon_code, $userId, $sessionId);
            }

            // Process payment - this will initiate payment with the gateway
            $paymentResult = $this->paymentService->processPayment($order, $request->payment_method);

            $order->update([
                'payment_status' => 'pending', // Always pending until webhook confirms
                'payment_transaction_id' => $paymentResult['transaction_id'] ?? null,
            ]);

            // Don't clear cart yet - wait for successful payment
            // Cart will be cleared when payment is confirmed via webhook

            DB::commit();

            // Check if this is COD or requires redirect
            $requiresRedirect = !in_array($request->payment_method, ['cod']);

            // Extract payment data from nested structure
            // PaymentService returns: payment_data => { success, data: {...gateway fields}, message }
            $gatewayData = $paymentResult['payment_data']['data'] ?? $paymentResult['payment_data'] ?? [];
            $paymentUrl = $gatewayData['payment_url'] ?? null;
            $paymentSessionId = $gatewayData['payment_session_id'] ?? $gatewayData['session_id'] ?? null;

            return response()->json([
                'success' => true,
                'message' => $requiresRedirect ? 'Redirecting to payment gateway' : 'Order placed successfully',
                'order' => $order->load(['orderItems.product']),
                'payment_details' => [
                    'payment_method' => $paymentResult['payment_method'],
                    'transaction_id' => $paymentResult['transaction_id'],
                    'payment_url' => $paymentUrl,
                    'payment_data' => $gatewayData,
                    'message' => $paymentResult['message'] ?? 'Payment initiated'
                ],
                'requires_redirect' => $requiresRedirect,
                'redirect_url' => $paymentUrl,
                'payment_session_id' => $paymentSessionId
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function show($order_id)
    {
        // Try to find by ID first, then by order number
        $order = Order::where('id', $order_id)
            ->orWhere('order_number', $order_id)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Check if the order belongs to the authenticated user
        if ($order->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $order->load(['orderItems.product']);

        // Transform the order data to match frontend expectations
        $orderData = $order->toArray();
        
        // Add computed fields
        $orderData['customer_name'] = $order->user->name ?? $order->customer_name;
        $orderData['customer_email'] = $order->user->email ?? $order->customer_email;
        $orderData['customer_phone'] = $order->user->phone ?? $order->customer_phone;
        
        // Parse shipping and billing addresses if they're JSON
        if (is_string($orderData['shipping_address'])) {
            $orderData['shipping_address'] = json_decode($orderData['shipping_address'], true);
        }
        if (is_string($orderData['billing_address'])) {
            $orderData['billing_address'] = json_decode($orderData['billing_address'], true);
        }

        return response()->json([
            'success' => true,
            'data' => $orderData
        ]);
    }

    public function cancel(Request $request, $order_id)
    {
        // Try to find by ID first, then by order number
        $order = Order::where('id', $order_id)
            ->orWhere('order_number', $order_id)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($order->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled at this stage'
            ], 400);
        }

        try {
            $this->orderService->processOrderStateTransition(
                $order,
                'cancelled',
                Auth::id(),
                $request->input('reason', 'Cancelled by customer')
            );

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'order' => $order->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function downloadInvoice($order_id)
    {
        // Find order by ID or order number
        $order = Order::where('id', $order_id)
            ->orWhere('order_number', $order_id)
            ->with(['orderItems.product', 'user'])
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Check if the order belongs to the authenticated user
        if ($order->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            // Generate PDF
            $pdf = Pdf::loadView('pdf.invoice', compact('order'));
            $pdf->setPaper('A4', 'portrait');
            
            $filename = 'invoice-' . $order->order_number . '.pdf';
            
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadReceipt($order_id)
    {
        // Find order by ID or order number
        $order = Order::where('id', $order_id)
            ->orWhere('order_number', $order_id)
            ->with(['orderItems.product', 'user'])
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Check if the order belongs to the authenticated user
        if ($order->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            // Generate PDF
            $pdf = Pdf::loadView('pdf.receipt', compact('order'));
            $pdf->setPaper('A4', 'portrait');
            
            $filename = 'receipt-' . $order->order_number . '.pdf';
            
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    public function adminIndex(Request $request)
    {
        $query = Order::with(['user', 'orderItems.product']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('order_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', function($uq) use ($request) {
                      $uq->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('email', 'like', '%' . $request->search . '%');
                  });
            });
        }

        $orders = $query->orderBy('created_at', 'desc')
                        ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|string',
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            $this->orderService->processOrderStateTransition(
                $order,
                $request->status,
                Auth::id(),
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'order' => $order->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function updatePaymentStatus(Request $request, Order $order)
    {
        $request->validate([
            'payment_status' => 'required|string|in:pending,paid,failed,refunded'
        ]);

        $order->update(['payment_status' => $request->payment_status]);

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated successfully',
            'order' => $order->fresh()
        ]);
    }

}
