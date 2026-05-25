<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the single free-text `billing_group` column with a JSON
     * array `billing_groups` keyed to the App\Enums\BillingGroup enum.
     *
     * Backfill: known-good values become single-element arrays of the
     * lower-cased enum value. Unknown legacy values (factory's
     * "Grupo A/B/C") become NULL — acceptable because the only running
     * environment is staging.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('billing_groups')->nullable()->after('quantity');
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                UPDATE services
                SET billing_groups = jsonb_build_array(lower(billing_group))
                WHERE billing_group IS NOT NULL
                  AND lower(billing_group) IN ('salud', 'escolar', 'turismo', 'empresarial', 'ocasional')
            SQL);
            DB::statement('DROP INDEX IF EXISTS services_billing_group_trgm_idx');
        } else {
            // SQLite (tests) — JSON values stored as text. Build the
            // single-element array literal from the lower-cased value.
            $rows = DB::table('services')
                ->whereNotNull('billing_group')
                ->whereIn(DB::raw('lower(billing_group)'), ['salud', 'escolar', 'turismo', 'empresarial', 'ocasional'])
                ->get(['id', 'billing_group']);

            foreach ($rows as $row) {
                DB::table('services')
                    ->where('id', $row->id)
                    ->update([
                        'billing_groups' => json_encode([strtolower($row->billing_group)]),
                    ]);
            }
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('billing_group');
        });
    }

    /**
     * Reverse the migration. Restores the column and the trigram index
     * on PostgreSQL, but does not attempt to flatten the JSON array
     * back into a single value — staging-only, one-way rollback.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('billing_group', 50)->nullable()->after('quantity');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX services_billing_group_trgm_idx ON services USING gin (billing_group gin_trgm_ops)');
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('billing_groups');
        });
    }
};
