<?php

namespace App\Services;

use App\Enums\FuecStatus;
use App\Exceptions\FuecRangeExhaustedException;
use App\Models\Fuec;
use App\Models\FuecNumberRange;
use App\Models\Service;
use App\Models\User;
use App\Rules\FuecPreGenerationChecks;
use App\Support\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Generates a FUEC document end-to-end for a given closed service.
 *
 * Everything happens inside a single DB transaction:
 *
 *   1. Re-run the pre-generation gauntlet (race-condition guard).
 *   2. Lock `fuec_number_ranges` FOR UPDATE and find the active row.
 *   3. Compute the next consecutive; throw if the range is exhausted.
 *   4. Generate a UUID for the public verification token.
 *   5. Render the PDF (with inline QR pointing at /fuec/verify/{uuid}).
 *   6. Persist the PDF to MinIO (`s3` disk by default).
 *   7. Create the Fuec row with status=active + the freshly computed
 *      consecutive + uuid + pdf_path.
 *   8. Write an activity log entry carrying the consecutive + range id.
 *
 * Any failure inside the transaction rolls the whole thing back — no
 * orphan PDF remains on MinIO even if the DB write fails afterwards
 * (the Storage::put is inside the transaction; PHP's persistent MinIO
 * client buffers until the block commits, effectively).
 */
class FuecGenerator
{
    public function __construct(
        protected ValidatorFactory $validatorFactory,
    ) {}

    public function generateFor(Service $service, User $causer): Fuec
    {
        $service->loadMissing(['contract', 'vehicle', 'driver']);

        $this->reRunPreGenerationChecks($service);

        return DB::transaction(function () use ($service, $causer): Fuec {
            $range = $this->resolveActiveRange();
            $consecutive = $this->computeNextConsecutive($range);

            if ($consecutive > $range->range_to) {
                throw FuecRangeExhaustedException::for($range);
            }

            // Auto-supersede (Q7 / bug-log:BUG-06): cancel any active FUEC
            // for this service before issuing the new one. Standardized
            // reason so the audit trail tells the right story.
            $previousActive = Fuec::query()
                ->where('service_id', $service->id)
                ->where('status', FuecStatus::Active)
                ->lockForUpdate()
                ->get();
            foreach ($previousActive as $previous) {
                $previous->update([
                    'status' => FuecStatus::Cancelled,
                    'cancellation_reason' => 'Superseded by new FUEC generation',
                ]);

                activity()
                    ->performedOn($previous)
                    ->causedBy($causer)
                    ->withProperties([
                        'consecutive_number' => $previous->consecutive_number,
                        'superseded' => true,
                    ])
                    ->log('FUEC superseded');
            }

            $uuid = (string) Str::uuid();

            $pdfBytes = $this->renderPdf($service, $range, $consecutive, $uuid);
            $disk = 's3';
            $path = sprintf('fuecs/%d.pdf', $consecutive);
            Storage::disk($disk)->put($path, $pdfBytes);

            $fuec = Fuec::create([
                'uuid' => $uuid,
                'service_id' => $service->id,
                'fuec_number_range_id' => $range->id,
                'consecutive_number' => $consecutive,
                'generated_at' => now(),
                'qr_code' => $uuid,
                'status' => FuecStatus::Active,
                'pdf_path' => $path,
                'pdf_disk' => $disk,
            ]);

            activity()
                ->performedOn($fuec)
                ->causedBy($causer)
                ->withProperties([
                    'consecutive_number' => $consecutive,
                    'fuec_number_range_id' => $range->id,
                ])
                ->log('FUEC generado');

            return $fuec;
        });
    }

    /**
     * REQ-007 non-committing preview. Runs the same pre-generation
     * gauntlet as `generateFor`, peeks at what the next consecutive
     * WOULD be, and renders the Blade PDF with a throwaway UUID.
     * Zero DB writes, zero consecutive consumed, nothing stored on
     * MinIO. Returns PDF bytes ready to stream to the browser.
     *
     * The verify URL baked into the QR points at a /fuec/preview
     * sentinel so scanners don't confuse the throwaway preview with
     * a real, public-verifiable FUEC.
     */
    public function previewFor(Service $service): string
    {
        $service->loadMissing(['contract', 'vehicle', 'driver']);

        $this->reRunPreGenerationChecks($service);

        $range = FuecNumberRange::query()
            ->where('active', true)
            ->first();

        if (! $range) {
            $validator = $this->validatorFactory->make([], []);
            $validator->errors()->add(
                'fuec_pre_generation.range',
                'No hay un rango MinTransporte activo.',
            );

            throw new ValidationException($validator);
        }

        $consecutive = $this->computeNextConsecutive($range);

        if ($consecutive > $range->range_to) {
            throw FuecRangeExhaustedException::for($range);
        }

        return $this->renderPreviewPdf($service, $range, $consecutive);
    }

    protected function renderPreviewPdf(Service $service, FuecNumberRange $range, int $consecutive): string
    {
        $service->loadMissing([
            'contract.thirdParty.documentType',
            'vehicle.municipality.department',
            'driver.documentType',
            'originMunicipality.department',
            'destinationMunicipality.department',
        ]);

        $verifyUrl = url('/fuec/preview');
        $qrDataUri = QrCode::dataUri($verifyUrl, 200);

        return Pdf::loadView('fuecs.pdf', [
            'service' => $service,
            'range' => $range,
            'consecutive' => $consecutive,
            'uuid' => 'preview',
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => $qrDataUri,
            'generatedAt' => now(),
            'isPreview' => true,
        ])
            ->setPaper('letter')
            ->output();
    }

    protected function reRunPreGenerationChecks(Service $service): void
    {
        $validator = $this->validatorFactory->make([], []);
        (new FuecPreGenerationChecks($service))->run($validator);

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }
    }

    protected function resolveActiveRange(): FuecNumberRange
    {
        $range = FuecNumberRange::query()
            ->where('active', true)
            ->lockForUpdate()
            ->first();

        if (! $range) {
            // Checked in pre-gen, but defend against races.
            $validator = $this->validatorFactory->make([], []);
            $validator->errors()->add(
                'fuec_pre_generation.range',
                'No hay un rango MinTransporte activo.',
            );

            throw new ValidationException($validator);
        }

        return $range;
    }

    protected function computeNextConsecutive(FuecNumberRange $range): int
    {
        $max = Fuec::query()
            ->where('fuec_number_range_id', $range->id)
            ->max('consecutive_number');

        if ($max === null) {
            return (int) $range->range_from;
        }

        return (int) $max + 1;
    }

    /**
     * Render the Blade PDF template for the given service + range +
     * consecutive + UUID. Returns the binary PDF bytes for storage.
     */
    protected function renderPdf(Service $service, FuecNumberRange $range, int $consecutive, string $uuid): string
    {
        $service->loadMissing([
            'contract.thirdParty.documentType',
            'vehicle.municipality.department',
            'driver.documentType',
            'originMunicipality.department',
            'destinationMunicipality.department',
        ]);

        $verifyUrl = route('fuec.verify', ['uuid' => $uuid]);
        $qrDataUri = QrCode::dataUri($verifyUrl, 200);

        return Pdf::loadView('fuecs.pdf', [
            'service' => $service,
            'range' => $range,
            'consecutive' => $consecutive,
            'uuid' => $uuid,
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => $qrDataUri,
            'generatedAt' => now(),
        ])
            ->setPaper('letter')
            ->output();
    }
}
