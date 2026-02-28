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
            $table->foreignId('service_id')->constrained();
            $table->integer('consecutive_number');
            $table->timestamp('generated_at');
            $table->string('qr_code', 255);
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->string('pdf_url', 500)->nullable();
            $table->timestamps();
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
