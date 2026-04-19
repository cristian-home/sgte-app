<?php

namespace Database\Seeders;

use App\Enums\ServiceStatus;
use App\Models\Fuec;
use App\Models\FuecNumberRange;
use App\Models\Service;
use App\Models\User;
use App\Services\FuecGenerator;
use App\Support\EnsureS3Bucket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Throwable;

/**
 * Seeds FUEC data that lets the /fuecs UI + public verify page work
 * out of the box:
 *
 * 1. Ensures the active MinTransporte `RES-0001` range exists.
 * 2. Ensures the MinIO bucket exists (EnsureS3Bucket — otherwise
 *    Storage::put silently fails and Descargar PDF 404s forever).
 * 3. Generates up to 3 REAL FUECs via FuecGenerator — these get
 *    proper PDFs rendered + stored on MinIO, so clicking
 *    "Descargar PDF" actually streams a real document.
 * 4. The remaining seeded FUECs are fast DB-only rows with
 *    `pdf_path = null` — index view still shows them, but the
 *    PDF download for those rows 404s (expected: they were never
 *    rendered).
 */
class FuecSeeder extends Seeder
{
    public function run(): void
    {
        $closedServices = Service::where('service_status', ServiceStatus::Closed)->get();

        if ($closedServices->isEmpty()) {
            return;
        }

        $range = FuecNumberRange::firstOrCreate(
            ['resolution_number' => 'RES-0001', 'resolution_year' => (int) now()->format('Y')],
            [
                'range_from' => 1000,
                'range_to' => 9999,
                'active' => true,
                'notes' => 'Rango inicial de demostración para el entorno de staging.',
            ],
        );

        // How many seeded rows should go through the real generator +
        // store an actual PDF on MinIO? Capped at 3 so `db:seed` stays
        // fast; the rest are DB-only placeholders.
        $realPdfCount = 3;

        $admin = User::query()->where('email', 'admin@sgte.app')->first();
        $canGenerate = $admin !== null && EnsureS3Bucket::ensure('s3');
        $generator = $canGenerate ? app(FuecGenerator::class) : null;

        foreach ($closedServices as $index => $service) {
            // Reserve the first N closed services for real PDF generation.
            if ($generator !== null && $index < $realPdfCount) {
                try {
                    $generator->generateFor($service, $admin);

                    continue;
                } catch (Throwable $e) {
                    // Fall through to DB-only row — pre-gen validation
                    // (contract vigente, docs non-expired, etc.) may
                    // legitimately reject a seeded service; that's fine.
                }
            }

            Fuec::firstOrCreate(
                ['service_id' => $service->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'service_id' => $service->id,
                    'fuec_number_range_id' => $range->id,
                    'consecutive_number' => $range->range_from + $index,
                    'generated_at' => $service->service_date->format('Y-m-d').' 18:00:00',
                    'qr_code' => (string) Str::uuid(),
                    'status' => 'active',
                    'pdf_path' => null,
                    'pdf_disk' => 's3',
                ],
            );
        }
    }
}
