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

        Schema::create('day_statuses', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->enum('status', ['projected', 'executed'])->default('projected');
            $table->foreignId('executor_id')->nullable()->constrained('users');
            $table->timestampTz('executed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_statuses');
    }
};
