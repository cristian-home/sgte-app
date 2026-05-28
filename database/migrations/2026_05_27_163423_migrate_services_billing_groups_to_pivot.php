<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrate `services.billing_groups` (JSON array of enum codes) to a
     * normalized `billing_group_service` pivot. Seeds the five default
     * groups (salud / escolar / turismo / empresarial / ocasional) so
     * existing service JSON rows have something to point at, then drops
     * the JSON column.
     */
    public function up(): void
    {
        $now = now();

        // Seed the five defaults (idempotent — if the seeder already ran
        // standalone the unique `code` constraint becomes a no-op insert).
        $defaults = [
            ['code' => 'salud', 'name' => 'Salud'],
            ['code' => 'escolar', 'name' => 'Escolar'],
            ['code' => 'turismo', 'name' => 'Turismo'],
            ['code' => 'empresarial', 'name' => 'Empresarial'],
            ['code' => 'ocasional', 'name' => 'Ocasional'],
        ];

        foreach ($defaults as $row) {
            DB::table('billing_groups')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'active' => true,
                    'description' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        Schema::create('billing_group_service', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('billing_group_id');
            $table->timestamps();

            $table->primary(['service_id', 'billing_group_id']);
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('billing_group_id')->references('id')->on('billing_groups')->cascadeOnDelete();
            $table->index('billing_group_id');
        });

        // Map: code → id (read once).
        $codeToId = DB::table('billing_groups')->pluck('id', 'code')->all();

        // Copy JSON arrays into pivot rows.
        if (Schema::hasColumn('services', 'billing_groups')) {
            $services = DB::table('services')
                ->whereNotNull('billing_groups')
                ->get(['id', 'billing_groups']);

            $inserts = [];
            foreach ($services as $service) {
                $codes = json_decode($service->billing_groups, true);
                if (! is_array($codes)) {
                    continue;
                }
                foreach ($codes as $code) {
                    if (! is_string($code)) {
                        continue;
                    }
                    if (! isset($codeToId[$code])) {
                        continue;
                    }
                    $inserts[] = [
                        'service_id' => $service->id,
                        'billing_group_id' => $codeToId[$code],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (! empty($inserts)) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    DB::table('billing_group_service')->insert($chunk);
                }
            }

            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('billing_groups');
            });
        }
    }

    public function down(): void
    {
        // Restore the JSON column.
        Schema::table('services', function (Blueprint $table) {
            $table->json('billing_groups')->nullable()->after('quantity');
        });

        // Reconstruct JSON arrays from the pivot.
        $rows = DB::table('billing_group_service as p')
            ->join('billing_groups as bg', 'bg.id', '=', 'p.billing_group_id')
            ->select('p.service_id', 'bg.code')
            ->get();

        $grouped = $rows->groupBy('service_id');
        foreach ($grouped as $serviceId => $records) {
            DB::table('services')
                ->where('id', $serviceId)
                ->update([
                    'billing_groups' => json_encode($records->pluck('code')->all()),
                ]);
        }

        Schema::dropIfExists('billing_group_service');
        // billing_groups table is dropped by the previous migration's down().
    }
};
