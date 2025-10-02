<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject',
        'content',
        'variables',
        'styles',
        'category',
        'is_active',
        'description',
        'from_name',
        'from_email',
        'reply_to',
        'cc',
        'bcc',
        'version',
    ];

    protected $casts = [
        'variables' => 'array',
        'styles' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Template categories
     */
    const CATEGORIES = [
        'order' => 'Order Related',
        'user' => 'User Account',
        'marketing' => 'Marketing',
        'system' => 'System Notifications',
        'payment' => 'Payment',
        'shipping' => 'Shipping',
    ];

    /**
     * Default templates that should exist
     */
    const DEFAULT_TEMPLATES = [
        'order_confirmation' => [
            'category' => 'order',
            'variables' => ['order_id', 'customer_name', 'order_total', 'order_items', 'shipping_address', 'payment_method'],
        ],
        'order_shipped' => [
            'category' => 'shipping',
            'variables' => ['order_id', 'customer_name', 'tracking_number', 'carrier', 'estimated_delivery'],
        ],
        'order_delivered' => [
            'category' => 'shipping',
            'variables' => ['order_id', 'customer_name', 'delivery_date'],
        ],
        'order_cancelled' => [
            'category' => 'order',
            'variables' => ['order_id', 'customer_name', 'cancellation_reason', 'refund_amount'],
        ],
        'welcome_email' => [
            'category' => 'user',
            'variables' => ['customer_name', 'email', 'activation_link'],
        ],
        'password_reset' => [
            'category' => 'user',
            'variables' => ['customer_name', 'reset_link', 'expiry_time'],
        ],
        'abandoned_cart' => [
            'category' => 'marketing',
            'variables' => ['customer_name', 'cart_items', 'cart_total', 'cart_link'],
        ],
        'payment_success' => [
            'category' => 'payment',
            'variables' => ['order_id', 'payment_amount', 'payment_method', 'transaction_id'],
        ],
        'payment_failed' => [
            'category' => 'payment',
            'variables' => ['order_id', 'payment_amount', 'failure_reason', 'retry_link'],
        ],
        'newsletter_welcome' => [
            'category' => 'marketing',
            'variables' => ['subscriber_name', 'unsubscribe_link'],
        ],
    ];

    /**
     * Render the template with given data
     */
    public function render(array $data = []): array
    {
        $subject = $this->subject;
        $content = $this->content;

        // Replace variables in subject and content
        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';

            if (is_array($value) || is_object($value)) {
                $value = $this->renderComplexVariable($key, $value);
            }

            $subject = str_replace($placeholder, $value, $subject);
            $content = str_replace($placeholder, $value, $content);
        }

        // Apply styles if available
        if ($this->styles) {
            $content = $this->applyStyles($content);
        }

        return [
            'subject' => $subject,
            'content' => $content,
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'reply_to' => $this->reply_to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
        ];
    }

    /**
     * Render complex variables like arrays or objects
     */
    private function renderComplexVariable($key, $value): string
    {
        if ($key === 'order_items') {
            return $this->renderOrderItems($value);
        }

        if ($key === 'cart_items') {
            return $this->renderCartItems($value);
        }

        // Default rendering for other arrays/objects
        return json_encode($value);
    }

    /**
     * Render order items as HTML table
     */
    private function renderOrderItems($items): string
    {
        $html = '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<thead><tr>';
        $html .= '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Product</th>';
        $html .= '<th style="text-align: center; padding: 8px; border-bottom: 1px solid #ddd;">Qty</th>';
        $html .= '<th style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;">Price</th>';
        $html .= '<th style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;">Total</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . ($item['name'] ?? '') . '</td>';
            $html .= '<td style="text-align: center; padding: 8px; border-bottom: 1px solid #eee;">' . ($item['quantity'] ?? 1) . '</td>';
            $html .= '<td style="text-align: right; padding: 8px; border-bottom: 1px solid #eee;">₹' . number_format($item['price'] ?? 0, 2) . '</td>';
            $html .= '<td style="text-align: right; padding: 8px; border-bottom: 1px solid #eee;">₹' . number_format(($item['quantity'] ?? 1) * ($item['price'] ?? 0), 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Render cart items similar to order items
     */
    private function renderCartItems($items): string
    {
        return $this->renderOrderItems($items);
    }

    /**
     * Apply custom styles to the content
     */
    private function applyStyles($content): string
    {
        if (!$this->styles || !is_array($this->styles)) {
            return $content;
        }

        $styleTag = '<style>';
        foreach ($this->styles as $selector => $rules) {
            $styleTag .= $selector . '{';
            if (is_array($rules)) {
                foreach ($rules as $property => $value) {
                    $styleTag .= $property . ':' . $value . ';';
                }
            } else {
                $styleTag .= $rules;
            }
            $styleTag .= '}';
        }
        $styleTag .= '</style>';

        // Add styles to the beginning of content
        return $styleTag . $content;
    }

    /**
     * Get template by name
     */
    public static function getByName(string $name): ?self
    {
        return static::where('name', $name)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Create a new version of this template
     */
    public function createNewVersion(): self
    {
        $newTemplate = $this->replicate();
        $newTemplate->version = $this->version + 1;
        $newTemplate->save();

        return $newTemplate;
    }

    /**
     * Get all available variables for a template
     */
    public function getAvailableVariables(): array
    {
        return $this->variables ?? self::DEFAULT_TEMPLATES[$this->name]['variables'] ?? [];
    }

    /**
     * Validate that all required variables are present in data
     */
    public function validateData(array $data): bool
    {
        $requiredVars = $this->getAvailableVariables();

        foreach ($requiredVars as $var) {
            if (!isset($data[$var])) {
                return false;
            }
        }

        return true;
    }
}