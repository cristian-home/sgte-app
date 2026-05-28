<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the `billing_groups` catalog (and pivot) with a free-text
     * JSON column on `services`. The product no longer maintains a
     * curated list of groups; operators type ad-hoc tags ("salud",
     * "AC01") that are persisted verbatim per service.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'billing_groups')) {
                $table->json('billing_groups')->nullable()->after('quantity');
            }
        });

        // Backfill: copy each service's associated group names into the
        // new JSON column. Done in PHP so the encoding is identical
        // across SQLite (json text) and PostgreSQL (jsonb).
        if (Schema::hasTable('billing_group_service') && Schema::hasTable('billing_groups')) {
            $rows = DB::table('billing_group_service as p')
                ->join('billing_groups as bg', 'bg.id', '=', 'p.billing_group_id')
                ->select('p.service_id', 'bg.name')
                ->get();

            foreach ($rows->groupBy('service_id') as $serviceId => $records) {
                $names = $records
                    ->pluck('name')
                    ->map(fn ($n) => trim((string) $n))
                    ->filter(fn ($n) => $n !== '')
                    ->unique()
                    ->values()
                    ->all();

                if ($names === []) {
                    continue;
                }

                DB::table('services')
                    ->where('id', $serviceId)
                    ->update(['billing_groups' => json_encode($names)]);
            }
        }

        Schema::dropIfExists('billing_group_service');
        Schema::dropIfExists('billing_groups');

        // Drop the Spatie permission rows so they don't surface in the
        // Roles UI as orphan permissions. role_has_permissions cascades
        // via FK on permission_id.
        DB::table('permissions')->whereIn('name', [
            'billing-groups.view',
            'billing-groups.create',
            'billing-groups.update',
            'billing-groups.delete',
        ])->delete();
    }

    public function down(): void
    {
        // Recreate the catalog and the pivot (mirrors
        // 2026_05_27_163422 and 2026_05_27_163423).
        if (! Schema::hasTable('billing_groups')) {
            Schema::create('billing_groups', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 100);
                $table->boolean('active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        $now = now();
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

        if (! Schema::hasTable('billing_group_service')) {
            Schema::create('billing_group_service', function (Blueprint $table) {
                $table->unsignedBigInteger('service_id');
                $table->unsignedBigInteger('billing_group_id');
                $table->timestamps();
                $table->primary(['service_id', 'billing_group_id']);
                $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
                $table->foreign('billing_group_id')->references('id')->on('billing_groups')->cascadeOnDelete();
                $table->index('billing_group_id');
            });
        }

        // Best-effort: re-create pivot rows from the JSON, matching by
        // group name. Unknown names (free-text tags that never existed
        // in the catalog) are silently dropped.
        if (Schema::hasColumn('services', 'billing_groups')) {
            $nameToId = DB::table('billing_groups')->pluck('id', 'name')->all();
            $services = DB::table('services')
                ->whereNotNull('billing_groups')
                ->get(['id', 'billing_groups']);

            $inserts = [];
            foreach ($services as $service) {
                $names = json_decode($service->billing_groups, true);
                if (! is_array($names)) {
                    continue;
                }
                foreach ($names as $name) {
                    if (! is_string($name) || ! isset($nameToId[$name])) {
                        continue;
                    }
                    $inserts[] = [
                        'service_id' => $service->id,
                        'billing_group_id' => $nameToId[$name],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            if ($inserts !== []) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    DB::table('billing_group_service')->insert($chunk);
                }
            }

            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('billing_groups');
            });
        }

        $now = now();
        $perms = [
            'billing-groups.view',
            'billing-groups.create',
            'billing-groups.update',
            'billing-groups.delete',
        ];
        foreach ($perms as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $perm, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }
};
