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

        Schema::create('third_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained();
            $table->string('identification_number', 50);
            $table->boolean('is_natural_person')->default(true);
            $table->string('first_name', 100)->nullable();
            $table->string('second_name', 100)->nullable();
            $table->string('first_lastname', 100)->nullable();
            $table->string('second_lastname', 100)->nullable();
            $table->string('company_name', 200)->nullable();
            $table->string('trade_name', 200)->nullable();
            $table->foreignId('municipality_id')->nullable()->constrained();
            $table->string('address', 255);
            $table->string('phone', 50);
            $table->string('email', 255);
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_provider')->default(false);
            $table->boolean('active')->default(true);
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
        Schema::dropIfExists('third_parties');
    }
};
