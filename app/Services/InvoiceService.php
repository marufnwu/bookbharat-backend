<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * Generate invoice for an order
     */
    public function generateInvoiceForOrder(Order $order)
    {
        try {
            // Check if invoice already exists
            $existingInvoice = Invoice::where('order_id', $order->id)->first();
            if ($existingInvoice) {
                return $existingInvoice;
            }

            $invoiceNumber = $this->generateInvoiceNumber();
            
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30), // 30 days payment terms
                'subtotal' => $order->subtotal,
                'tax_amount' => $order->tax_amount,
                'discount_amount' => $order->discount_amount,
                'shipping_amount' => $order->shipping_amount,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency ?? 'INR',
                'status' => $order->payment_status === 'completed' ? 'paid' : 'pending',
                'notes' => 'Invoice for Order #' . $order->order_number,
                'invoice_data' => [
                    'billing_address' => $order->billing_address,
                    'shipping_address' => $order->shipping_address,
                    'customer_details' => [
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                        'phone' => $order->shipping_phone ?? $order->user->phone
                    ]
                ]
            ]);

            // Create invoice items
            foreach ($order->items as $orderItem) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $orderItem->product_id,
                    'product_variant_id' => $orderItem->product_variant_id,
                    'product_name' => $orderItem->product_name,
                    'product_sku' => $orderItem->product_sku,
                    'quantity' => $orderItem->quantity,
                    'unit_price' => $orderItem->unit_price,
                    'total_price' => $orderItem->total_price,
                    'tax_rate' => $orderItem->tax_rate ?? 0,
                    'tax_amount' => $orderItem->tax_amount ?? 0,
                    'discount_amount' => $orderItem->discount_amount ?? 0
                ]);
            }

            Log::info('Invoice generated successfully', [
                'invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber
            ]);

            return $invoice;

        } catch (\Exception $e) {
            Log::error('Invoice generation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate PDF for invoice
     */
    public function generatePDF(Invoice $invoice)
    {
        try {
            // Load invoice with relationships
            $invoice->load(['order.user', 'invoiceItems.product']);

            // Generate PDF content (using a simple HTML approach)
            $html = $this->generateInvoiceHTML($invoice);
            
            // For now, we'll create a simple HTML file
            // In production, use libraries like TCPDF, DOMPDF, or Puppeteer
            $filename = 'invoice_' . $invoice->formatted_invoice_number . '.html';
            $filepath = 'invoices/' . $filename;
            
            Storage::disk('public')->put($filepath, $html);
            
            // Update invoice with PDF path
            $invoice->update(['pdf_path' => $filepath]);
            
            return Storage::disk('public')->url($filepath);

        } catch (\Exception $e) {
            Log::error('Invoice PDF generation failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate invoice HTML content
     */
    private function generateInvoiceHTML(Invoice $invoice)
    {
        $customerDetails = $invoice->invoice_data['customer_details'] ?? [];
        $billingAddress = $invoice->invoice_data['billing_address'] ?? [];
        $shippingAddress = $invoice->invoice_data['shipping_address'] ?? [];

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Invoice ' . $invoice->formatted_invoice_number . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .company-name { font-size: 24px; font-weight: bold; color: #2563eb; }
                .invoice-title { font-size: 18px; margin-top: 10px; }
                .invoice-info { display: flex; justify-content: space-between; margin-bottom: 30px; }
                .invoice-details, .customer-details { width: 48%; }
                .section-title { font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; }
                .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .items-table th { background-color: #f8f9fa; }
                .text-right { text-align: right; }
                .totals { margin-top: 20px; }
                .totals table { margin-left: auto; width: 300px; }
                .total-row { font-weight: bold; background-color: #f8f9fa; }
                .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-name">' . config('app.name', 'BookBharat') . '</div>
                <div class="invoice-title">TAX INVOICE</div>
            </div>

            <div class="invoice-info">
                <div class="invoice-details">
                    <div class="section-title">Invoice Details</div>
                    <p><strong>Invoice Number:</strong> ' . $invoice->formatted_invoice_number . '</p>
                    <p><strong>Invoice Date:</strong> ' . $invoice->invoice_date->format('d/m/Y') . '</p>
                    <p><strong>Due Date:</strong> ' . $invoice->due_date->format('d/m/Y') . '</p>
                    <p><strong>Order Number:</strong> ' . $invoice->order->order_number . '</p>
                    <p><strong>Status:</strong> ' . ucfirst($invoice->status) . '</p>
                </div>

                <div class="customer-details">
                    <div class="section-title">Bill To</div>
                    <p><strong>' . ($customerDetails['name'] ?? 'N/A') . '</strong></p>
                    <p>' . ($customerDetails['email'] ?? '') . '</p>
                    <p>' . ($customerDetails['phone'] ?? '') . '</p>
                    ' . ($billingAddress ? '<p>' . $this->formatAddress($billingAddress) . '</p>' : '') . '
                </div>
            </div>

            ' . ($shippingAddress && $shippingAddress !== $billingAddress ? '
            <div style="margin-bottom: 20px;">
                <div class="section-title">Ship To</div>
                <p>' . $this->formatAddress($shippingAddress) . '</p>
            </div>
            ' : '') . '

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Discount</th>
                        <th class="text-right">Tax</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($invoice->invoiceItems as $item) {
            $html .= '
                    <tr>
                        <td>' . $item->product_name . '</td>
                        <td>' . $item->product_sku . '</td>
                        <td class="text-right">' . $item->quantity . '</td>
                        <td class="text-right">₹' . number_format($item->unit_price, 2) . '</td>
                        <td class="text-right">₹' . number_format($item->discount_amount, 2) . '</td>
                        <td class="text-right">₹' . number_format($item->tax_amount, 2) . '</td>
                        <td class="text-right">₹' . number_format($item->total_price, 2) . '</td>
                    </tr>';
        }

        $html .= '
                </tbody>
            </table>

            <div class="totals">
                <table class="items-table">
                    <tr>
                        <td><strong>Subtotal:</strong></td>
                        <td class="text-right">₹' . number_format($invoice->subtotal, 2) . '</td>
                    </tr>
                    ' . ($invoice->discount_amount > 0 ? '
                    <tr>
                        <td><strong>Discount:</strong></td>
                        <td class="text-right">-₹' . number_format($invoice->discount_amount, 2) . '</td>
                    </tr>
                    ' : '') . '
                    ' . ($invoice->shipping_amount > 0 ? '
                    <tr>
                        <td><strong>Shipping:</strong></td>
                        <td class="text-right">₹' . number_format($invoice->shipping_amount, 2) . '</td>
                    </tr>
                    ' : '') . '
                    <tr>
                        <td><strong>Tax:</strong></td>
                        <td class="text-right">₹' . number_format($invoice->tax_amount, 2) . '</td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>TOTAL:</strong></td>
                        <td class="text-right"><strong>₹' . number_format($invoice->total_amount, 2) . '</strong></td>
                    </tr>
                </table>
            </div>

            ' . ($invoice->notes ? '
            <div style="margin-top: 30px;">
                <div class="section-title">Notes</div>
                <p>' . nl2br(e($invoice->notes)) . '</p>
            </div>
            ' : '') . '

            <div class="footer">
                <p>Thank you for your business!</p>
                <p>This is a computer generated invoice and does not require signature.</p>
                <p>Generated on ' . now()->format('d/m/Y H:i:s') . '</p>
            </div>
        </body>
        </html>';
    }

    /**
     * Format address for display
     */
    private function formatAddress($address)
    {
        if (!$address) return '';

        $parts = [];
        if (!empty($address['address_line_1'])) $parts[] = $address['address_line_1'];
        if (!empty($address['address_line_2'])) $parts[] = $address['address_line_2'];
        if (!empty($address['city'])) $parts[] = $address['city'];
        if (!empty($address['state'])) $parts[] = $address['state'];
        if (!empty($address['postal_code'])) $parts[] = $address['postal_code'];

        return implode(', ', $parts);
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber()
    {
        $year = now()->year;
        $month = now()->format('m');
        
        // Get the last invoice number for this year
        $lastInvoice = Invoice::whereYear('created_at', $year)
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $year . $month . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send invoice via email
     */
    public function sendInvoiceEmail(Invoice $invoice)
    {
        try {
            $pdfUrl = $this->generatePDF($invoice);
            
            // In production, implement email sending with invoice attachment
            // For now, just log the action
            Log::info('Invoice email sent', [
                'invoice_id' => $invoice->id,
                'customer_email' => $invoice->invoice_data['customer_details']['email'] ?? null,
                'pdf_url' => $pdfUrl
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Invoice email sending failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update invoice status
     */
    public function updateInvoiceStatus(Invoice $invoice, string $status)
    {
        $validStatuses = ['pending', 'paid', 'cancelled', 'overdue'];
        
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid invoice status: ' . $status);
        }

        $invoice->update(['status' => $status]);

        Log::info('Invoice status updated', [
            'invoice_id' => $invoice->id,
            'new_status' => $status
        ]);

        return $invoice;
    }
}