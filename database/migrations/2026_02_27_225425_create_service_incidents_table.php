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

        Schema::create('service_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('incident_type_id')->nullable(false)->constrained('incident_types');
            $table->text('description');
            $table->foreignId('registrar_id')->constrained('users');
            $table->boolean('is_driver_report')->default(false);
            $table->timestampTz('reported_at');
            $table->boolean('affects_billing')->default(false);
            $table->decimal('additional_value', 12, 2)->nullable();
            $table->timestampsTz();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_incidents');
    }
};
