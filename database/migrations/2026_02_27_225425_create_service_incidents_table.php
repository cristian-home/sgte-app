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
            $table->enum('incident_type', ['delay', 'accident', 'breakdown', 'traffic', 'weather', 'customer_no_show', 'other']);
            $table->text('description');
            $table->foreignId('registrar_id')->constrained('users');
            $table->boolean('is_driver_report')->default(false);
            $table->timestamp('reported_at');
            $table->boolean('affects_billing')->default(false);
            $table->decimal('additional_value', 12, 2)->nullable();
            $table->timestamps();
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
