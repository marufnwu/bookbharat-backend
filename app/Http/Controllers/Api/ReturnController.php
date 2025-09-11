<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Return as ReturnModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReturnController extends Controller
{
    /**
     * Get return eligibility for an order
     */
    public function checkEligibility(Request $request, $orderId)
    {
        $user = Auth::user();
        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->with(['items.product', 'items.variant'])
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        if ($order->status !== 'delivered') {
            return response()->json([
                'eligible' => false,
                'reason' => 'Order must be delivered before returns can be initiated',
            ]);
        }

        $deliveredAt = $order->delivered_at ?? $order->updated_at;
        $daysSinceDelivery = $deliveredAt->diffInDays(now());
        $returnWindow = 30; // days

        if ($daysSinceDelivery > $returnWindow) {
            return response()->json([
                'eligible' => false,
                'reason' => "Return window of {$returnWindow} days has expired",
                'delivered_at' => $deliveredAt->toDateString(),
                'days_since_delivery' => $daysSinceDelivery,
            ]);
        }

        // Check if there's already a return request
        $existingReturn = ReturnModel::where('order_id', $orderId)
            ->whereIn('status', ['requested', 'approved', 'shipped', 'received'])
            ->first();

        if ($existingReturn) {
            return response()->json([
                'eligible' => false,
                'reason' => 'Return request already exists for this order',
                'existing_return' => [
                    'return_number' => $existingReturn->return_number,
                    'status' => $existingReturn->status,
                    'requested_at' => $existingReturn->requested_at,
                ],
            ]);
        }

        $eligibleItems = [];
        $ineligibleItems = [];

        foreach ($order->items as $item) {
            $product = $item->product;
            
            // Check product-specific return policy
            if ($product->is_digital) {
                $ineligibleItems[] = [
                    'item_id' => $item->id,
                    'product_name' => $product->name,
                    'reason' => 'Digital products are not returnable',
                ];
                continue;
            }

            // Check for personalized/customized items
            if ($product->is_personalized ?? false) {
                $ineligibleItems[] = [
                    'item_id' => $item->id,
                    'product_name' => $product->name,
                    'reason' => 'Personalized items are not returnable',
                ];
                continue;
            }

            $eligibleItems[] = [
                'item_id' => $item->id,
                'product_id' => $product->id,
                'variant_id' => $item->variant_id,
                'product_name' => $product->name,
                'variant_info' => $item->variant ? $item->variant->formatted_attributes : null,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ];
        }

        return response()->json([
            'eligible' => count($eligibleItems) > 0,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'delivered_at' => $deliveredAt->toDateString(),
                'days_since_delivery' => $daysSinceDelivery,
                'return_window_expires' => $deliveredAt->addDays($returnWindow)->toDateString(),
            ],
            'eligible_items' => $eligibleItems,
            'ineligible_items' => $ineligibleItems,
            'return_reasons' => $this->getReturnReasons(),
        ]);
    }

    /**
     * Create a return request
     */
    public function createReturn(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'return_type' => 'required|in:refund,exchange,store_credit',
            'reason' => 'required|string|in:defective,wrong_item,not_as_described,changed_mind,damaged_packaging,size_issue,color_issue',
            'description' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'images.*' => 'nullable|image|max:2048',
        ]);

        $user = Auth::user();
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Validate eligibility again
        $eligibilityCheck = $this->checkEligibility($request, $request->order_id);
        $eligibilityData = $eligibilityCheck->getData(true);
        
        if (!$eligibilityData['eligible']) {
            return response()->json(['error' => $eligibilityData['reason']], 400);
        }

        // Validate requested items
        foreach ($request->items as $itemData) {
            $orderItem = $order->items()->find($itemData['item_id']);
            if (!$orderItem) {
                return response()->json(['error' => 'Invalid item ID'], 400);
            }
            
            if ($itemData['quantity'] > $orderItem->quantity) {
                return response()->json(['error' => 'Cannot return more than purchased quantity'], 400);
            }
        }

        // Calculate refund amount
        $refundAmount = 0;
        foreach ($request->items as $itemData) {
            $orderItem = $order->items()->find($itemData['item_id']);
            $refundAmount += ($orderItem->unit_price * $itemData['quantity']);
        }

        // Handle image uploads
        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('returns', 'public');
                $images[] = $path;
            }
        }

        // Create return request
        $return = ReturnModel::create([
            'return_number' => $this->generateReturnNumber(),
            'order_id' => $order->id,
            'user_id' => $user->id,
            'return_type' => $request->return_type,
            'status' => 'requested',
            'reason' => $request->reason,
            'description' => $request->description,
            'items' => $request->items,
            'refund_amount' => $refundAmount,
            'requested_at' => now(),
            'images' => $images,
        ]);

        // TODO: Send notification to admin
        // TODO: Send confirmation email to customer

        return response()->json([
            'success' => true,
            'data' => [
                'return_id' => $return->id,
                'return_number' => $return->return_number,
                'status' => $return->status,
                'refund_amount' => $return->refund_amount,
                'estimated_processing_time' => '3-5 business days',
                'next_steps' => [
                    'Our team will review your return request within 24 hours',
                    'If approved, you will receive return shipping instructions',
                    'Pack items in original condition with all tags and packaging',
                    'Ship items using the provided return label',
                ],
            ]
        ]);
    }

    /**
     * Get customer's return history
     */
    public function getReturns(Request $request)
    {
        $user = Auth::user();
        
        $query = ReturnModel::where('user_id', $user->id)
            ->with(['order'])
            ->orderBy('requested_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $returns = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $returns->items(),
            'pagination' => [
                'current_page' => $returns->currentPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
                'last_page' => $returns->lastPage(),
            ]
        ]);
    }

    /**
     * Get return details
     */
    public function getReturn(Request $request, $returnId)
    {
        $user = Auth::user();
        $return = ReturnModel::where('id', $returnId)
            ->where('user_id', $user->id)
            ->with(['order', 'approvedBy', 'processedBy'])
            ->first();

        if (!$return) {
            return response()->json(['error' => 'Return not found'], 404);
        }

        $timeline = $this->getReturnTimeline($return);

        return response()->json([
            'success' => true,
            'data' => [
                'return' => $return,
                'timeline' => $timeline,
                'tracking_info' => $this->getTrackingInfo($return),
                'refund_info' => $this->getRefundInfo($return),
            ]
        ]);
    }

    /**
     * Cancel a return request (only if not yet approved)
     */
    public function cancelReturn(Request $request, $returnId)
    {
        $user = Auth::user();
        $return = ReturnModel::where('id', $returnId)
            ->where('user_id', $user->id)
            ->first();

        if (!$return) {
            return response()->json(['error' => 'Return not found'], 404);
        }

        if (!in_array($return->status, ['requested'])) {
            return response()->json([
                'error' => 'Cannot cancel return in current status: ' . $return->status
            ], 400);
        }

        $return->update([
            'status' => 'cancelled',
            'admin_notes' => 'Cancelled by customer',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Return request cancelled successfully',
        ]);
    }

    /**
     * Admin: Get all return requests
     */
    public function adminGetReturns(Request $request)
    {
        // Check admin permissions
        if (!Auth::user() || !Auth::user()->can('manage_returns')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = ReturnModel::with(['user', 'order'])
            ->orderBy('requested_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->return_type) {
            $query->where('return_type', $request->return_type);
        }

        if ($request->date_from) {
            $query->where('requested_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('requested_at', '<=', $request->date_to);
        }

        $returns = $query->paginate($request->get('per_page', 20));

        $summary = [
            'total_requests' => ReturnModel::count(),
            'pending_requests' => ReturnModel::where('status', 'requested')->count(),
            'processing_requests' => ReturnModel::whereIn('status', ['approved', 'shipped', 'received'])->count(),
            'completed_requests' => ReturnModel::where('status', 'completed')->count(),
            'total_refund_amount' => ReturnModel::where('status', 'completed')->sum('refund_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $returns->items(),
            'summary' => $summary,
            'pagination' => [
                'current_page' => $returns->currentPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
                'last_page' => $returns->lastPage(),
            ]
        ]);
    }

    /**
     * Admin: Update return status
     */
    public function adminUpdateReturn(Request $request, $returnId)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,received,processed,completed',
            'admin_notes' => 'nullable|string',
            'refund_method' => 'nullable|in:original_payment,store_credit,bank_transfer',
            'quality_check_results' => 'nullable|array',
        ]);

        if (!Auth::user() || !Auth::user()->can('manage_returns')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $return = ReturnModel::findOrFail($returnId);
        $adminUser = Auth::user();

        $updateData = [
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
        ];

        switch ($request->status) {
            case 'approved':
                $updateData['approved_at'] = now();
                $updateData['approved_by'] = $adminUser->id;
                // TODO: Generate return shipping label
                break;

            case 'received':
                $updateData['received_at'] = now();
                if ($request->quality_check_results) {
                    $updateData['quality_check_results'] = $request->quality_check_results;
                }
                break;

            case 'processed':
                $updateData['processed_at'] = now();
                $updateData['processed_by'] = $adminUser->id;
                if ($request->refund_method) {
                    $updateData['refund_method'] = $request->refund_method;
                }
                break;

            case 'completed':
                // TODO: Process actual refund
                break;
        }

        $return->update($updateData);

        // TODO: Send status update notification to customer

        return response()->json([
            'success' => true,
            'data' => $return->fresh(),
            'message' => 'Return status updated successfully',
        ]);
    }

    // Helper methods
    protected function generateReturnNumber()
    {
        return 'RET' . date('Ymd') . strtoupper(Str::random(6));
    }

    protected function getReturnReasons()
    {
        return [
            'defective' => 'Product is defective or damaged',
            'wrong_item' => 'Wrong item was delivered',
            'not_as_described' => 'Item not as described',
            'changed_mind' => 'Changed my mind',
            'damaged_packaging' => 'Damaged packaging',
            'size_issue' => 'Size does not fit',
            'color_issue' => 'Color is different than expected',
        ];
    }

    protected function getReturnTimeline($return)
    {
        $timeline = [];

        $timeline[] = [
            'status' => 'requested',
            'title' => 'Return Requested',
            'description' => 'Return request submitted',
            'timestamp' => $return->requested_at,
            'completed' => true,
        ];

        if ($return->approved_at) {
            $timeline[] = [
                'status' => 'approved',
                'title' => 'Return Approved',
                'description' => 'Return request approved by admin',
                'timestamp' => $return->approved_at,
                'completed' => true,
            ];
        }

        if ($return->shipped_at) {
            $timeline[] = [
                'status' => 'shipped',
                'title' => 'Item Shipped',
                'description' => 'Item shipped back to us',
                'timestamp' => $return->shipped_at,
                'completed' => true,
            ];
        }

        if ($return->received_at) {
            $timeline[] = [
                'status' => 'received',
                'title' => 'Item Received',
                'description' => 'Item received and inspected',
                'timestamp' => $return->received_at,
                'completed' => true,
            ];
        }

        if ($return->processed_at) {
            $timeline[] = [
                'status' => 'processed',
                'title' => 'Refund Processed',
                'description' => 'Refund has been processed',
                'timestamp' => $return->processed_at,
                'completed' => true,
            ];
        }

        return $timeline;
    }

    protected function getTrackingInfo($return)
    {
        if (!$return->return_tracking_number) {
            return null;
        }

        return [
            'tracking_number' => $return->return_tracking_number,
            'shipping_method' => $return->return_shipping_method,
            'estimated_delivery' => 'Contact shipping provider for latest updates',
        ];
    }

    protected function getRefundInfo($return)
    {
        if ($return->status === 'completed') {
            return [
                'amount' => $return->refund_amount,
                'method' => $return->refund_method,
                'processed_at' => $return->processed_at,
                'estimated_arrival' => '3-5 business days',
            ];
        }

        return [
            'expected_amount' => $return->refund_amount,
            'status' => 'pending',
        ];
    }
}