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

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained();
            $table->string('identification_number', 50);
            $table->string('first_name', 100);
            $table->string('second_name', 100)->nullable();
            $table->string('first_lastname', 100);
            $table->string('second_lastname', 100)->nullable();
            $table->foreignId('municipality_id')->nullable()->constrained();
            $table->string('address', 255);
            $table->string('phone', 50);
            $table->string('email', 255);
            $table->string('license_category', 10);
            $table->date('license_due_date');
            $table->foreignId('eps_id')->constrained('eps');
            $table->foreignId('pension_fund_id')->constrained('pension_funds');
            $table->foreignId('severance_fund_id')->constrained('severance_funds');
            $table->boolean('has_social_security')->default(true);
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
        Schema::dropIfExists('drivers');
    }
};
