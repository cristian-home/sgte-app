<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $invoices = [
            [
                'invoice_number' => 'FAC-0001-2026',
                'total_value' => 2500000.00,
                'issue_date' => '2026-01-31',
                'payment_status' => PaymentStatus::Paid->value,
                'notes' => 'Factura de servicios de enero - Clinica San Rafael',
            ],
            [
                'invoice_number' => 'FAC-0002-2026',
                'total_value' => 1800000.00,
                'issue_date' => '2026-02-15',
                'payment_status' => PaymentStatus::Paid->value,
                'notes' => 'Factura transporte escolar febrero - Colegio del Rosario',
            ],
            [
                'invoice_number' => 'FAC-0003-2026',
                'total_value' => 3200000.00,
                'issue_date' => '2026-02-28',
                'payment_status' => PaymentStatus::Pending->value,
                'notes' => 'Factura servicios turisticos febrero - Dann Carlton',
            ],
            [
                'invoice_number' => 'FAC-0004-2026',
                'total_value' => 950000.00,
                'issue_date' => '2026-01-15',
                'payment_status' => PaymentStatus::Overdue->value,
                'notes' => 'Factura servicios ocasionales enero',
            ],
            [
                'invoice_number' => 'FAC-0005-2026',
                'total_value' => 4100000.00,
                'issue_date' => '2026-02-28',
                'payment_status' => PaymentStatus::Pending->value,
                'notes' => null,
            ],
        ];

        foreach ($invoices as $invoice) {
            Invoice::firstOrCreate(
                ['invoice_number' => $invoice['invoice_number']],
                $invoice,
            );
        }
    }
}
