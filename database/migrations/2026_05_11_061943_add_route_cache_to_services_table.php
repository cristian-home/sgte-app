<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache the driving route between a service's origin and destination
     * so /gps/map can render a polyline without hitting Mapbox on every
     * page load. Populated by the FetchServiceRoute job whenever both
     * coordinate pairs are set; cleared by the Service model's saving
     * hook when either coord changes.
     *
     * `route_fetched_at` doubles as a "fetch attempted" sentinel —
     * non-null with a null `route_geometry` means Mapbox returned no
     * route (or failed) and the map should fall back to a straight line.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('route_geometry')->nullable();
            $table->integer('route_distance_m')->nullable();
            $table->integer('route_duration_s')->nullable();
            $table->timestampTz('route_fetched_at')->nullable();
            $table->string('route_source', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'route_geometry',
                'route_distance_m',
                'route_duration_s',
                'route_fetched_at',
                'route_source',
            ]);
        });
    }
};
