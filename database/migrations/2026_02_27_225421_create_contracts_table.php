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
            $table->date('start_date');
            $table->date('end_date');
            $table->text('route_description');
            $table->boolean('is_generic')->default(false);
            $table->boolean('active')->default(true);
            // REQ-011 billing unit semantics. Drives the dynamic
            // "Cantidad (…)" label on the service form so operators and
            // accounting know whether quantity means trips, passengers,
            // days, or hours. Nullable because legacy seeded contracts
            // and historical data predate this column.
            $table->enum('billing_unit_type', ['viaje', 'pasajero', 'dia', 'hora'])->nullable();
            $table->timestamps();
            $table->softDeletes();
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
