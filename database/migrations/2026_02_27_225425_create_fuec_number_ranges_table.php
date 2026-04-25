<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MinTransporte FUEC consecutive ranges (REQ-007 AC#3).
 *
 * One active range at a time; when the range is exhausted the admin
 * registers a new resolution via the Fuec Number Range CRUD. The
 * partial unique index on `(active) WHERE active = true` enforces
 * the "at most one active" invariant at the database level (Postgres
 * only; SQLite test env skips the predicate — the controller + form
 * request enforce the same invariant application-side).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuec_number_ranges', function (Blueprint $table): void {
            $table->id();
            $table->string('resolution_number', 50);
            $table->smallInteger('resolution_year');
            $table->unsignedInteger('range_from');
            $table->unsignedInteger('range_to');
            $table->boolean('active')->default(false);
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX fuec_number_ranges_one_active_uidx ON fuec_number_ranges (active) WHERE active = true');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuec_number_ranges');
    }
};
