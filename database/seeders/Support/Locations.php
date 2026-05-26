<?php

namespace Database\Seeders\Support;

/**
 * Fixed location anchors used by the curated seed dataset.
 *
 * The constants below match the shape that the address-autocomplete and
 * pin-picker flows persist in production — same keys as the rows from
 * `Database\Factories\Support\RealColombianAddresses` — so seeded data
 * is structurally indistinguishable from operator-entered data.
 */
final class Locations
{
    /**
     * Bogotá warehouse — Zona Industrial Montevideo. Used as the
     * deterministic anchor for every `is_manual=true` VehicleLocation
     * row: a dispatcher pinning a vehicle that hasn't reported GPS yet
     * realistically points at the depot.
     *
     * @var array{municipality_code:string,address:string,coordinates:string,source:string,accuracy:null,place_id:null}
     */
    public const BODEGA_BOGOTA = [
        'municipality_code' => '11001',
        'address' => 'Bodega Principal — Calle 22C #68B-50, Zona Industrial Montevideo',
        'coordinates' => '4.6486000,-74.1110000',
        'source' => 'manual',
        'accuracy' => null,
        'place_id' => null,
    ];

    /**
     * Lat/lng pair for the warehouse — convenience for callers that need
     * the raw numeric pair rather than the address record.
     *
     * @return array{lat: float, lng: float}
     */
    public static function bodegaCoordinates(): array
    {
        return ['lat' => 4.6486, 'lng' => -74.1110];
    }
}
