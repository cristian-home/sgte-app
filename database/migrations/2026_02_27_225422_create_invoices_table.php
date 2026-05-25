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

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('third_party_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->decimal('total_value', 12, 2)->default(0);
            // UTC TIMESTAMPTZ at start-of-day in `timezone`. Y-m-d view
            // is exposed via the model's `issue_date` accessor.
            $table->timestampTz('issued_at');
            $table->string('timezone', 64)->default('America/Bogota');
            $table->enum('payment_status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('invoices');
    }
};
