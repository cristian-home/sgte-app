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

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('internal_code', 20);
            $table->string('plate', 6);
            $table->string('mobile_number', 20);
            $table->string('brand', 50);
            $table->string('line', 50);
            $table->integer('model_year');
            $table->enum('type', ['bus', 'buseta', 'van', 'automobile']);
            $table->string('engine_number', 50);
            $table->string('chassis_number', 50);
            $table->integer('capacity');
            $table->string('city', 100);
            $table->boolean('is_third_party')->default(false);
            $table->foreignId('third_party_id')->nullable()->constrained();
            $table->date('soat_due_date');
            $table->date('rtm_due_date');
            $table->date('operation_card_due_date');
            $table->enum('status', ['active', 'maintenance', 'retired'])->default('active');
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
        Schema::dropIfExists('vehicles');
    }
};
