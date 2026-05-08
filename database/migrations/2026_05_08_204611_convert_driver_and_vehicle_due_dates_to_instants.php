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

        // Drivers
        Schema::table('drivers', function (Blueprint $table): void {
            $table->timestampTz('license_due_at')->nullable()->after('license_category');
            $table->string('timezone', 64)->nullable()->after('license_due_at');
        });

        DB::table('drivers')->orderBy('id')->lazyById()->each(function (object $row) use ($operationTz): void {
            if ($row->license_due_date === null) {
                return;
            }
            DB::table('drivers')->where('id', $row->id)->update([
                'license_due_at' => Tz::endOfDayInTzAsUtc((string) $row->license_due_date, $operationTz),
                'timezone' => $operationTz,
            ]);
        });

        Schema::table('drivers', function (Blueprint $table) use ($operationTz): void {
            $table->timestampTz('license_due_at')->nullable(false)->change();
            $table->string('timezone', 64)->default($operationTz)->nullable(false)->change();
            $table->dropColumn('license_due_date');
        });

        // Vehicles
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->timestampTz('soat_due_at')->nullable()->after('third_party_id');
            $table->timestampTz('rtm_due_at')->nullable()->after('soat_due_at');
            $table->timestampTz('operation_card_due_at')->nullable()->after('rtm_due_at');
            $table->string('timezone', 64)->nullable()->after('operation_card_due_at');
        });

        DB::table('vehicles')->orderBy('id')->lazyById()->each(function (object $row) use ($operationTz): void {
            $update = ['timezone' => $operationTz];
            foreach ([
                'soat_due_date' => 'soat_due_at',
                'rtm_due_date' => 'rtm_due_at',
                'operation_card_due_date' => 'operation_card_due_at',
            ] as $oldCol => $newCol) {
                if ($row->{$oldCol} === null) {
                    continue;
                }
                $update[$newCol] = Tz::endOfDayInTzAsUtc((string) $row->{$oldCol}, $operationTz);
            }
            DB::table('vehicles')->where('id', $row->id)->update($update);
        });

        Schema::table('vehicles', function (Blueprint $table) use ($operationTz): void {
            $table->timestampTz('soat_due_at')->nullable(false)->change();
            $table->timestampTz('rtm_due_at')->nullable(false)->change();
            $table->timestampTz('operation_card_due_at')->nullable(false)->change();
            $table->string('timezone', 64)->default($operationTz)->nullable(false)->change();
            $table->dropColumn(['soat_due_date', 'rtm_due_date', 'operation_card_due_date']);
        });
    }

    public function down(): void
    {
        // Drivers
        Schema::table('drivers', function (Blueprint $table): void {
            $table->date('license_due_date')->nullable()->after('license_category');
        });

        DB::table('drivers')->orderBy('id')->lazyById()->each(function (object $row): void {
            if ($row->license_due_at === null) {
                return;
            }
            $tz = is_string($row->timezone) && $row->timezone !== '' ? $row->timezone : Tz::operation();
            $licenseDate = \Carbon\CarbonImmutable::parse($row->license_due_at)->setTimezone($tz)->subSecond()->format('Y-m-d');
            DB::table('drivers')->where('id', $row->id)->update(['license_due_date' => $licenseDate]);
        });

        Schema::table('drivers', function (Blueprint $table): void {
            $table->date('license_due_date')->nullable(false)->change();
            $table->dropColumn(['license_due_at', 'timezone']);
        });

        // Vehicles
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->date('soat_due_date')->nullable()->after('third_party_id');
            $table->date('rtm_due_date')->nullable()->after('soat_due_date');
            $table->date('operation_card_due_date')->nullable()->after('rtm_due_date');
        });

        DB::table('vehicles')->orderBy('id')->lazyById()->each(function (object $row): void {
            $tz = is_string($row->timezone) && $row->timezone !== '' ? $row->timezone : Tz::operation();
            $update = [];
            foreach ([
                'soat_due_at' => 'soat_due_date',
                'rtm_due_at' => 'rtm_due_date',
                'operation_card_due_at' => 'operation_card_due_date',
            ] as $newCol => $oldCol) {
                if ($row->{$newCol} === null) {
                    continue;
                }
                $update[$oldCol] = \Carbon\CarbonImmutable::parse($row->{$newCol})->setTimezone($tz)->subSecond()->format('Y-m-d');
            }
            if ($update !== []) {
                DB::table('vehicles')->where('id', $row->id)->update($update);
            }
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->date('soat_due_date')->nullable(false)->change();
            $table->date('rtm_due_date')->nullable(false)->change();
            $table->date('operation_card_due_date')->nullable(false)->change();
            $table->dropColumn(['soat_due_at', 'rtm_due_at', 'operation_card_due_at', 'timezone']);
        });
    }
};
