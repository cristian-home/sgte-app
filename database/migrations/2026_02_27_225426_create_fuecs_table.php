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

        Schema::create('fuecs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('fuec_number_range_id')
                ->constrained('fuec_number_ranges')
                ->restrictOnDelete();
            $table->integer('consecutive_number');
            $table->timestampTz('generated_at');
            $table->string('qr_code', 255);
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->string('pdf_path', 500)->nullable();
            $table->string('pdf_disk', 50)->default('s3');
            // REQ-007 audit trail: operators must justify why a
            // consecutive number was burned (typo vs. legitimate
            // service cancellation). Populated on POST /fuecs/{fuec}/cancel.
            $table->string('cancellation_reason', 1000)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('consecutive_number');
            $table->index('fuec_number_range_id', 'fuecs_range_idx');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuecs');
    }
};
