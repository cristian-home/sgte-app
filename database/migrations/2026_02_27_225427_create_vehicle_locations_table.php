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

        Schema::create('vehicle_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestampTz('recorded_at');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->boolean('is_manual')->default(false);
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['vehicle_id', 'recorded_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_locations');
    }
};
