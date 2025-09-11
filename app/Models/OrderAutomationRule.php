<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderAutomationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'trigger_event',
        'conditions',
        'actions',
        'priority',
        'is_active',
        'execution_count',
        'last_executed_at',
        'metadata',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'last_executed_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTrigger($query, string $trigger)
    {
        return $query->where('trigger_event', $trigger);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    public function evaluateConditions(Order $order): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $order)) {
                return false;
            }
        }

        return true;
    }

    protected function evaluateCondition(array $condition, Order $order): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        $orderValue = data_get($order, $field);

        return match($operator) {
            '=' => $orderValue == $value,
            '!=' => $orderValue != $value,
            '>' => $orderValue > $value,
            '<' => $orderValue < $value,
            '>=' => $orderValue >= $value,
            '<=' => $orderValue <= $value,
            'in' => in_array($orderValue, (array)$value),
            'not_in' => !in_array($orderValue, (array)$value),
            'contains' => str_contains($orderValue, $value),
            'starts_with' => str_starts_with($orderValue, $value),
            'ends_with' => str_ends_with($orderValue, $value),
            default => false,
        };
    }

    public function executeActions(Order $order): void
    {
        foreach ($this->actions as $action) {
            $this->executeAction($action, $order);
        }

        $this->increment('execution_count');
        $this->update(['last_executed_at' => now()]);
    }

    protected function executeAction(array $action, Order $order): void
    {
        $type = $action['type'] ?? '';
        $params = $action['params'] ?? [];

        switch ($type) {
            case 'change_status':
                $order->update(['status' => $params['status']]);
                break;
            case 'send_notification':
                dispatch(new \App\Jobs\SendOrderNotification($order, $params['template']));
                break;
            case 'add_tag':
                $order->tags()->attach($params['tag_id']);
                break;
            case 'assign_to_user':
                $order->update(['assigned_to' => $params['user_id']]);
                break;
            case 'trigger_webhook':
                dispatch(new \App\Jobs\TriggerWebhook($params['url'], $order->toArray()));
                break;
            case 'add_note':
                $order->notes()->create(['content' => $params['note']]);
                break;
        }
    }
}