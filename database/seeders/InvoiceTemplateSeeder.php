<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvoiceTemplate;

class InvoiceTemplateSeeder extends Seeder
{
    public function run(): void
    {
        InvoiceTemplate::updateOrCreate(
            ['name' => 'Default Invoice'],
            [
                'description' => 'Standard invoice template with company details',
                'header_html' => $this->getDefaultHeader(),
                'footer_html' => $this->getDefaultFooter(),
                'styles_css' => $this->getDefaultStyles(),
                'thank_you_message' => 'Thank you for your business!',
                'legal_disclaimer' => 'Payment is due within 30 days of invoice date.',
                'logo_url' => null,
                'show_company_address' => true,
                'show_gst_number' => true,
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }

    private function getDefaultHeader(): string
    {
        return '<div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #333; margin-bottom: 10px;">INVOICE</h1>
            <p style="color: #666; font-size: 14px;">Invoice Date: {{invoice_date}}</p>
        </div>';
    }

    private function getDefaultFooter(): string
    {
        return '<div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd;">
            <p style="color: #666; font-size: 12px; text-align: center;">
                {{thank_you_message}}<br>
                {{legal_disclaimer}}
            </p>
        </div>';
    }

    private function getDefaultStyles(): string
    {
        return '
            body { font-family: Arial, sans-serif; font-size: 14px; color: #333; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f5f5f5; font-weight: bold; }
            .text-right { text-align: right; }
            .text-bold { font-weight: bold; }
            .total-row { background-color: #f9f9f9; font-size: 16px; }
        ';
    }
}
