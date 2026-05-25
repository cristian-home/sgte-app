<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill missing service coordinates from the linked municipality
     * centroid.
     *
     * Some legacy/seed services carry an `origin_municipality_id` or
     * `destination_municipality_id` but no `*_coordinates`, which makes
     * the route static map fall back to a "Ruta no disponible"
     * placeholder. Every municipality already has latitude/longitude
     * (DANE-sourced catalog), so we project the centroid in as the
     * coarse-but-non-null coordinate. Source is tagged `manual` to
     * match the existing enum and to signal this is not a Google
     * Places geocode.
     */
    public function up(): void
    {
        $this->backfillSide('origin');
        $this->backfillSide('destination');
    }

    public function down(): void
    {
        // The original NULLs are not recoverable; leaving the
        // backfilled centroids in place is the safer behaviour.
    }

    private function backfillSide(string $side): void
    {
        $coordsCol = "{$side}_coordinates";
        $sourceCol = "{$side}_coordinates_source";
        $municipalityCol = "{$side}_municipality_id";

        $candidates = DB::table('services')
            ->join('municipalities', "services.{$municipalityCol}", '=', 'municipalities.id')
            ->whereNull("services.{$coordsCol}")
            ->whereNotNull("services.{$municipalityCol}")
            ->whereNotNull('municipalities.latitude')
            ->whereNotNull('municipalities.longitude')
            ->select([
                'services.id',
                'municipalities.latitude',
                'municipalities.longitude',
            ])
            ->get();

        foreach ($candidates as $row) {
            $coords = sprintf('%s,%s', $row->latitude, $row->longitude);

            DB::table('services')
                ->where('id', $row->id)
                ->update([
                    $coordsCol => $coords,
                    $sourceCol => 'manual',
                    'updated_at' => now(),
                ]);
        }
    }
};
