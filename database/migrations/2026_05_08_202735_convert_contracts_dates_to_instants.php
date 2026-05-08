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

        Schema::table('contracts', function (Blueprint $table): void {
            $table->timestampTz('start_at')->nullable()->after('contract_object');
            $table->timestampTz('end_at')->nullable()->after('start_at');
            $table->string('timezone', 64)->nullable()->after('end_at');
        });

        DB::table('contracts')->orderBy('id')->lazyById()->each(function (object $row) use ($operationTz): void {
            if ($row->start_date === null || $row->end_date === null) {
                return;
            }
            $startAt = Tz::startOfDayInTzAsUtc((string) $row->start_date, $operationTz);
            $endAt = Tz::endOfDayInTzAsUtc((string) $row->end_date, $operationTz);

            DB::table('contracts')->where('id', $row->id)->update([
                'start_at' => $startAt,
                'end_at' => $endAt,
                'timezone' => $operationTz,
            ]);
        });

        Schema::table('contracts', function (Blueprint $table) use ($operationTz): void {
            $table->timestampTz('start_at')->nullable(false)->change();
            $table->timestampTz('end_at')->nullable(false)->change();
            $table->string('timezone', 64)->default($operationTz)->nullable(false)->change();
            $table->dropColumn(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->date('start_date')->nullable()->after('contract_object');
            $table->date('end_date')->nullable()->after('start_date');
        });

        DB::table('contracts')->orderBy('id')->lazyById()->each(function (object $row): void {
            if ($row->start_at === null || $row->end_at === null) {
                return;
            }
            $tz = is_string($row->timezone) && $row->timezone !== '' ? $row->timezone : Tz::operation();

            $startDate = \Carbon\CarbonImmutable::parse($row->start_at)->setTimezone($tz)->format('Y-m-d');
            $endDate = \Carbon\CarbonImmutable::parse($row->end_at)->setTimezone($tz)->subSecond()->format('Y-m-d');

            DB::table('contracts')->where('id', $row->id)->update([
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
            $table->dropColumn(['start_at', 'end_at', 'timezone']);
        });
    }
};
