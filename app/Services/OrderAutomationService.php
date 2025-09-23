<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderWorkflow;
use App\Models\OrderAutomationRule;
use App\Jobs\ProcessOrderWorkflow;
use App\Jobs\SendOrderNotification;
use Carbon\Carbon;

class OrderAutomationService
{
    protected array $validTransitions = [
        'pending' => ['confirmed', 'cancelled', 'failed'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['completed', 'returned'],
        'failed' => ['pending', 'cancelled'],
        'cancelled' => [],
        'returned' => ['refunded', 'exchanged'],
        'refunded' => [],
        'exchanged' => ['processing'],
        'completed' => [],
    ];

    public function processOrderStateTransition(Order $order, string $newStatus, ?int $userId = null, string $notes = null, array $metadata = [])
    {
        $currentStatus = $order->status;
        
        // Validate transition
        if (!$this->isValidTransition($currentStatus, $newStatus)) {
            throw new \Exception("Invalid status transition from {$currentStatus} to {$newStatus}");
        }
        
        // Update order status
        $order->update(['status' => $newStatus]);
        
        // Record workflow step
        $workflow = OrderWorkflow::create([
            'order_id' => $order->id,
            'from_status' => $currentStatus,
            'to_status' => $newStatus,
            'triggered_by' => $userId ? 'user' : 'system',
            'triggered_by_id' => $userId,
            'reason' => $notes ?? "Status changed from {$currentStatus} to {$newStatus}",
            'notes' => $notes,
            'metadata' => $metadata,
            'processed_at' => now(),
        ]);
        
        // Trigger automation rules
        $this->triggerAutomationRules($order, $newStatus, $currentStatus);
        
        // Schedule automatic transitions
        $this->scheduleAutomaticTransitions($order);
        
        return $workflow;
    }
    
    protected function isValidTransition(string $currentStatus, string $newStatus): bool
    {
        return in_array($newStatus, $this->validTransitions[$currentStatus] ?? []);
    }
    
    protected function getNextPossibleStatuses(string $currentStatus): array
    {
        return $this->validTransitions[$currentStatus] ?? [];
    }
    
    public function triggerAutomationRules(Order $order, string $newStatus, ?string $previousStatus = null)
    {
        $rules = OrderAutomationRule::active()
            ->where('trigger_event', 'status_changed')
            ->orderBy('priority', 'desc')
            ->get();
            
        foreach ($rules as $rule) {
            if ($this->evaluateRuleConditions($rule, $order, $newStatus, $previousStatus)) {
                $this->executeRuleActions($rule, $order);
                $rule->increment('execution_count');
                $rule->update(['last_executed_at' => now()]);
            }
        }
    }
    
    protected function evaluateRuleConditions(OrderAutomationRule $rule, Order $order, string $newStatus, ?string $previousStatus = null): bool
    {
        $conditions = $rule->conditions;
        
        // Check status conditions
        if (isset($conditions['from_status'])) {
            // If rule requires a from_status but order has no previous status (newly created), skip this rule
            if ($previousStatus === null || $conditions['from_status'] !== $previousStatus) {
                return false;
            }
        }
        
        if (isset($conditions['to_status']) && $conditions['to_status'] !== $newStatus) {
            return false;
        }
        
        // Check order amount conditions
        if (isset($conditions['min_amount']) && $order->total_amount < $conditions['min_amount']) {
            return false;
        }
        
        if (isset($conditions['max_amount']) && $order->total_amount > $conditions['max_amount']) {
            return false;
        }
        
        // Check payment method conditions
        if (isset($conditions['payment_methods']) && !in_array($order->payment_method, $conditions['payment_methods'])) {
            return false;
        }
        
        // Check customer conditions - disabled for now
        if (isset($conditions['customer_groups'])) {
            // TODO: Implement customer groups properly
            // $userGroupIds = $order->user->customerGroups()->pluck('customer_groups.id')->toArray();
            // if (empty(array_intersect($userGroupIds, $conditions['customer_groups']))) {
            //     return false;
            // }
        }
        
        // Check time-based conditions
        if (isset($conditions['time_delay'])) {
            $createdAt = Carbon::parse($order->created_at);
            $requiredDelay = $conditions['time_delay']; // in minutes
            if ($createdAt->diffInMinutes(now()) < $requiredDelay) {
                return false;
            }
        }
        
        return true;
    }
    
    protected function executeRuleActions(OrderAutomationRule $rule, Order $order)
    {
        $actions = $rule->actions;
        
        foreach ($actions as $action => $params) {
            switch ($action) {
                case 'send_email':
                    $this->sendEmailAction($order, $params);
                    break;
                    
                case 'send_sms':
                    $this->sendSmsAction($order, $params);
                    break;
                    
                case 'update_status':
                    $this->updateStatusAction($order, $params);
                    break;
                    
                case 'create_task':
                    $this->createTaskAction($order, $params);
                    break;
                    
                case 'allocate_inventory':
                    $this->allocateInventoryAction($order, $params);
                    break;
                    
                case 'generate_invoice':
                    $this->generateInvoiceAction($order, $params);
                    break;
                    
                case 'ship_order':
                    $this->shipOrderAction($order, $params);
                    break;
            }
        }
    }
    
    protected function sendEmailAction(Order $order, array $params)
    {
        SendOrderNotification::dispatch($order, 'email', [
            'template' => $params['template'],
            'recipient' => $params['recipient'] ?? 'customer',
            'subject' => $params['subject'] ?? null,
        ]);
    }
    
    protected function sendSmsAction(Order $order, array $params)
    {
        SendOrderNotification::dispatch($order, 'sms', [
            'template' => $params['template'],
            'recipient' => $params['recipient'] ?? 'customer',
        ]);
    }
    
    protected function updateStatusAction(Order $order, array $params)
    {
        if (isset($params['delay'])) {
            ProcessOrderWorkflow::dispatch($order->id, $params['status'])
                ->delay(now()->addMinutes($params['delay']));
        } else {
            $this->processOrderStateTransition($order, $params['status'], null, $params['notes'] ?? 'Automated status update');
        }
    }
    
    protected function createTaskAction(Order $order, array $params)
    {
        // Create admin task - integrate with task management system
        // For now, just log it
        \Log::info("Task created for order {$order->id}: {$params['description']}");
    }
    
    protected function allocateInventoryAction(Order $order, array $params)
    {
        // Allocate inventory for order items
        foreach ($order->items as $item) {
            if ($item->variant) {
                $item->variant->allocateStock($item->quantity);
            }
        }
    }
    
    protected function generateInvoiceAction(Order $order, array $params)
    {
        // Generate invoice - integrate with invoice service
        \Log::info("Invoice generated for order {$order->id}");
    }
    
    protected function shipOrderAction(Order $order, array $params)
    {
        // Create shipping label and book pickup
        \Log::info("Shipping arranged for order {$order->id}");
    }
    
    protected function scheduleAutomaticTransitions(Order $order)
    {
        // Auto-cancel unpaid orders after 30 minutes
        if ($order->status === 'pending' && $order->payment_status !== 'paid') {
            ProcessOrderWorkflow::dispatch($order->id, 'cancelled')
                ->delay(now()->addMinutes(30));
        }
        
        // Auto-confirm paid orders within 2 hours
        if ($order->status === 'pending' && $order->payment_status === 'paid') {
            ProcessOrderWorkflow::dispatch($order->id, 'confirmed')
                ->delay(now()->addHours(2));
        }
        
        // Auto-complete orders 7 days after delivery
        if ($order->status === 'delivered') {
            ProcessOrderWorkflow::dispatch($order->id, 'completed')
                ->delay(now()->addDays(7));
        }
    }
    
    public function getOrderTimeline(Order $order)
    {
        return $order->workflows()
            ->orderBy('status_changed_at')
            ->get()
            ->map(function ($workflow) {
                return [
                    'status' => $workflow->current_status,
                    'timestamp' => $workflow->status_changed_at,
                    'changed_by' => 'System',
                    'notes' => $workflow->notes,
                    'is_automated' => $workflow->is_automated,
                ];
            });
    }

    public function processOrderCreated(Order $order)
    {
        // Initial workflow entry for order creation
        OrderWorkflow::create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => $order->status,
            'triggered_by' => 'system',
            'triggered_by_id' => null,
            'reason' => 'Order created',
            'notes' => 'Order created',
            'metadata' => [
                'order_total' => $order->total_amount,
                'payment_method' => $order->payment_method
            ],
            'processed_at' => now(),
        ]);

        // Schedule automatic transitions
        $this->scheduleAutomaticTransitions($order);

        // Trigger initial automation rules
        $this->triggerAutomationRules($order, $order->status, null);
    }
    
    public function getOrderMetrics(Carbon $startDate = null, Carbon $endDate = null)
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();
        
        $orders = Order::whereBetween('created_at', [$startDate, $endDate]);
        
        return [
            'total_orders' => $orders->count(),
            'completed_orders' => $orders->where('status', 'completed')->count(),
            'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
            'average_processing_time' => $this->calculateAverageProcessingTime($orders),
            'automation_rate' => $this->calculateAutomationRate($orders),
            'status_distribution' => $orders->groupBy('status')
                ->map(fn($group) => $group->count())
                ->toArray(),
        ];
    }
    
    protected function calculateAverageProcessingTime($orders)
    {
        $completedOrders = $orders->where('status', 'completed')->get();
        
        if ($completedOrders->isEmpty()) {
            return 0;
        }
        
        $totalTime = 0;
        $count = 0;
        
        foreach ($completedOrders as $order) {
            $createdAt = Carbon::parse($order->created_at);
            $completedWorkflow = $order->workflows()
                ->where('current_status', 'completed')
                ->first();
                
            if ($completedWorkflow) {
                $completedAt = Carbon::parse($completedWorkflow->status_changed_at);
                $totalTime += $createdAt->diffInHours($completedAt);
                $count++;
            }
        }
        
        return $count > 0 ? round($totalTime / $count, 2) : 0;
    }
    
    protected function calculateAutomationRate($orders)
    {
        $totalTransitions = OrderWorkflow::whereIn('order_id', $orders->pluck('id'))->count();
        $automatedTransitions = OrderWorkflow::whereIn('order_id', $orders->pluck('id'))
            ->where('is_automated', true)
            ->count();

        return $totalTransitions > 0 ? round(($automatedTransitions / $totalTransitions) * 100, 2) : 0;
    }

    /**
     * Get available status transitions for a given order status
     */
    public function getAvailableTransitions(string $currentStatus): array
    {
        return $this->validTransitions[$currentStatus] ?? [];
    }

    /**
     * Update order status with validation and workflow recording
     */
    public function updateOrderStatus(Order $order, string $newStatus, ?string $notes = null): void
    {
        $this->processOrderStateTransition($order, $newStatus, auth()->id(), $notes);
    }

    /**
     * Process payment confirmation for an order
     */
    public function processPaymentConfirmation(Order $order): void
    {
        // Update order timestamps
        $order->update([
            'confirmed_at' => now(),
        ]);

        // If order is pending, move to confirmed
        if ($order->status === 'pending') {
            $this->updateOrderStatus($order, 'confirmed', 'Payment confirmed');
        }

        // Send confirmation notification
        dispatch(new SendOrderNotification($order, 'payment_confirmed'));
    }
}