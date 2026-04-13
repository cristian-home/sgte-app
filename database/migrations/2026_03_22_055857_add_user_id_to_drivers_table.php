<?php

use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
        });

        // Link the reference driver user (driver@sgte.app) to the first
        // Driver record so DriverDashboardController can resolve
        // $user->driver and show their assigned services.
        //
        // Skipped in production/staging/testing where the init data
        // migration doesn't seed the reference user or driver records.
        if (app()->environment('production', 'staging', 'testing')) {
            return;
        }

        $driverUser = User::where('email', 'driver@sgte.app')->first();
        if (! $driverUser) {
            return;
        }

        $unlinkedDriver = Driver::whereNull('user_id')->orderBy('id')->first();
        if ($unlinkedDriver) {
            $unlinkedDriver->update(['user_id' => $driverUser->id]);
        }
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
