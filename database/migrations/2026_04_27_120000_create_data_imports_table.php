<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('original_filename');
            $table->string('disk', 32)->default('s3');
            $table->string('path')->nullable();
            $table->string('errors_path')->nullable();
            $table->string('status', 16)->default('queued');
            $table->boolean('dry_run')->default(false);
            $table->boolean('update_existing')->default(false);
            $table->unsignedInteger('rows_total')->nullable();
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedInteger('rows_errored')->default(0);
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('files_purged_at')->nullable();
            $table->string('timezone', 64)->default('America/Bogota');
            $table->timestampsTz();

            $table->index(['user_id', 'type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_imports');
    }
};
