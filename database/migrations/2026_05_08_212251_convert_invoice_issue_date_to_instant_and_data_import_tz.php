<?php

use App\Support\Tz;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $operationTz = Tz::operation();

        // Invoice — issue_date (DATE) → issued_at (TIMESTAMPTZ at 00:00 in tz) + timezone.
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestampTz('issued_at')->nullable()->after('total_value');
            $table->string('timezone', 64)->nullable()->after('issued_at');
        });

        DB::table('invoices')->orderBy('id')->lazyById()->each(function (object $row) use ($operationTz): void {
            if ($row->issue_date === null) {
                return;
            }
            DB::table('invoices')->where('id', $row->id)->update([
                'issued_at' => Tz::startOfDayInTzAsUtc((string) $row->issue_date, $operationTz),
                'timezone' => $operationTz,
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) use ($operationTz): void {
            $table->timestampTz('issued_at')->nullable(false)->change();
            $table->string('timezone', 64)->default($operationTz)->nullable(false)->change();
            $table->dropColumn('issue_date');
        });

        // DataImport — already TIMESTAMPTZ, just add per-row TZ to align
        // with the rest of the business models.
        Schema::table('data_imports', function (Blueprint $table) use ($operationTz): void {
            $table->string('timezone', 64)->default($operationTz)->nullable(false)->after('files_purged_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->date('issue_date')->nullable()->after('total_value');
        });

        DB::table('invoices')->orderBy('id')->lazyById()->each(function (object $row): void {
            if ($row->issued_at === null) {
                return;
            }
            $tz = is_string($row->timezone) && $row->timezone !== '' ? $row->timezone : Tz::operation();
            $issueDate = \Carbon\CarbonImmutable::parse($row->issued_at)->setTimezone($tz)->format('Y-m-d');
            DB::table('invoices')->where('id', $row->id)->update(['issue_date' => $issueDate]);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->date('issue_date')->nullable(false)->change();
            $table->dropColumn(['issued_at', 'timezone']);
        });

        Schema::table('data_imports', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
