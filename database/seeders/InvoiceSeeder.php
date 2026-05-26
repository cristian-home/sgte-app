<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use Database\Seeders\Support\SeedClock;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Issue dates are anchored relative to today (between ~2 weeks and
     * ~3 months ago) so the curated invoice set always falls in the
     * "past" window where ServiceSeeder assigns invoiced services.
     */
    public function run(): void
    {
        $year = SeedClock::today()->format('Y');

        $invoices = [
            [
                'invoice_number' => "FAC-0001-{$year}",
                'total_value' => 2500000.00,
                'issue_offset' => -90,
                'payment_status' => PaymentStatus::Paid->value,
                'notes' => 'Factura de servicios - Clinica San Rafael',
            ],
            [
                'invoice_number' => "FAC-0002-{$year}",
                'total_value' => 1800000.00,
                'issue_offset' => -60,
                'payment_status' => PaymentStatus::Paid->value,
                'notes' => 'Factura transporte escolar - Colegio del Rosario',
            ],
            [
                'invoice_number' => "FAC-0003-{$year}",
                'total_value' => 3200000.00,
                'issue_offset' => -45,
                'payment_status' => PaymentStatus::Pending->value,
                'notes' => 'Factura servicios turisticos - Dann Carlton',
            ],
            [
                'invoice_number' => "FAC-0004-{$year}",
                'total_value' => 950000.00,
                'issue_offset' => -30,
                'payment_status' => PaymentStatus::Overdue->value,
                'notes' => 'Factura servicios ocasionales',
            ],
            [
                'invoice_number' => "FAC-0005-{$year}",
                'total_value' => 4100000.00,
                'issue_offset' => -15,
                'payment_status' => PaymentStatus::Pending->value,
                'notes' => null,
            ],
        ];

        foreach ($invoices as $invoice) {
            $issueDate = SeedClock::dateString($invoice['issue_offset']);
            unset($invoice['issue_offset']);

            Invoice::firstOrCreate(
                ['invoice_number' => $invoice['invoice_number']],
                array_merge($invoice, ['issue_date' => $issueDate]),
            );
        }
    }
}
