<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reduce the required-fields surface on vehicle creation. Only plate,
 * type, capacity, and the three legal documents (SOAT, RTM, TO) remain
 * mandatory at the application layer. The descriptive/identification
 * fields below become nullable so a fleet can register a vehicle
 * incrementally as paperwork arrives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('mobile_number', 20)->nullable()->change();
            $table->string('brand', 50)->nullable()->change();
            $table->string('line', 50)->nullable()->change();
            $table->integer('model_year')->nullable()->change();
            $table->string('engine_number', 50)->nullable()->change();
            $table->string('chassis_number', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('mobile_number', 20)->nullable(false)->change();
            $table->string('brand', 50)->nullable(false)->change();
            $table->string('line', 50)->nullable(false)->change();
            $table->integer('model_year')->nullable(false)->change();
            $table->string('engine_number', 50)->nullable(false)->change();
            $table->string('chassis_number', 50)->nullable(false)->change();
        });
    }
};
