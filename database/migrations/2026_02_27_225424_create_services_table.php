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

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('driver_id')->nullable()->constrained();
            $table->foreignId('invoice_id')->nullable()->constrained();
            $table->date('service_date');
            $table->string('origin', 255);
            $table->string('destination', 255);
            $table->time('planned_start_time');
            $table->integer('planned_duration');
            $table->time('actual_start_time')->nullable();
            $table->time('actual_end_time')->nullable();
            $table->decimal('unit_value', 12, 2);
            $table->integer('quantity')->default(1);
            $table->string('billing_group', 50)->nullable();
            $table->enum('payment_method', ['cash', 'credit', 'transfer'])->default('credit');
            $table->enum('service_status', ['open', 'closed'])->default('open');
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
        Schema::dropIfExists('services');
    }
};
