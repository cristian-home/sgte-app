<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 50);
            $table->foreignId('third_party_id')->constrained();
            $table->enum('contract_object', ['business', 'tourism', 'health', 'occasional']);
            // Half-open interval `[start_at, end_at)`. Both are UTC
            // TIMESTAMPTZ projected from the wall-clock first/last day
            // in `timezone` (start = 00:00 of first day; end =
            // next-midnight after last day). See HasTimezone +
            // Contract::scopeActiveAt.
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->string('timezone', 64)->default('America/Bogota');
            $table->text('route_description');
            $table->boolean('is_generic')->default(false);
            $table->boolean('active')->default(true);
            // REQ-011 billing unit semantics. Drives the dynamic
            // "Cantidad (…)" label on the service form so operators and
            // accounting know whether quantity means trips, passengers,
            // days, or hours. Nullable because legacy seeded contracts
            // and historical data predate this column.
            $table->enum('billing_unit_type', ['viaje', 'pasajero', 'dia', 'hora'])->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
