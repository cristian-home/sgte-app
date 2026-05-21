<?php

namespace Database\Factories\Support;

/**
 * Curated list of real Colombian landmarks for seed/factory use.
 *
 * Coordinates are approximate (within ~100m of the actual building) and
 * their `source` / `accuracy` mirror what the production flow would
 * persist if an operator had geocoded the address through Google Places
 * or dropped a pin manually. The list intentionally mixes:
 *
 * - `source='google'` rows with various Google Geocoder `location_type`
 *   `accuracy` levels (ROOFTOP / RANGE_INTERPOLATED / GEOMETRIC_CENTER /
 *   APPROXIMATE), exercising the full coordinate-quality spectrum the UI
 *   must render — and a syntactically plausible `place_id` (the durable
 *   Google reference persisted on `*_place_id`).
 * - `source='manual'` rows with `accuracy=null` and no `place_id`,
 *   exercising the pin-picker path (the badge under the input shows
 *   "pin manual" in gray for these).
 *
 * Each record's `municipality_code` is the DANE code; the factory and
 * seeder resolve `municipality_id` via that code. Names matching the
 * DB are official DANE names (e.g. SANTIAGO DE CALI, CARTAGENA DE
 * INDIAS), but the address strings use everyday Colombian nomenclature
 * (Calle X #Y-Z) — that's the gap the address autocomplete bridges.
 */
final class RealColombianAddresses
{
    /**
     * @return list<array{municipality_code:string,address:string,coordinates:string,source:string,accuracy:string|null,place_id?:string}>
     */
    public static function all(): array
    {
        return [
            // Bogotá D.C. — code 11001
            [
                'municipality_code' => '11001',
                'address' => 'Carrera 11 #82-71',
                'coordinates' => '4.6679000,-74.0541000',
                'source' => 'google',
                'accuracy' => 'ROOFTOP',
                'place_id' => 'ChIJaY1z8KcZP44Rk5lEZJrKn2Q',
            ],
            [
                'municipality_code' => '11001',
                'address' => 'Calle 26 #103-09',
                'coordinates' => '4.7016000,-74.1469000',
                'source' => 'google',
                'accuracy' => 'ROOFTOP',
                'place_id' => 'ChIJX2v7tQUZP44RbW0pLm9xVtE',
            ],
            [
                'municipality_code' => '11001',
                'address' => 'Carrera 7 #40-62',
                'coordinates' => '4.6291000,-74.0648000',
                'source' => 'google',
                'accuracy' => 'ROOFTOP',
                'place_id' => 'ChIJk9Lm3RYZP44RtZ4eWnQ8sRc',
            ],
            [
                'municipality_code' => '11001',
                'address' => 'Carrera 30 #45-03',
                'coordinates' => '4.6378000,-74.0828000',
                'source' => 'google',
                'accuracy' => 'ROOFTOP',
                'place_id' => 'ChIJp7Qd5sIZP44RvL2mHnB3kYx',
            ],
            [
                'municipality_code' => '11001',
                'address' => 'Calle 100 #11A-35',
                'coordinates' => '4.6862000,-74.0451000',
                'source' => 'google',
                'accuracy' => 'ROOFTOP',
                'place_id' => 'ChIJd3Nf6tEZP44RcK8wTmP1zUv',
            ],
            [
                'municipality_code' => '11001',
                'address' => 'Avenida Calle 26 #57-83',
                'coordinates' => '4.6543000,-74.0962000',
                'source' => 'google',
                'accuracy' => 'GEOMETRIC_CENTER',
                'place_id' => 'ChIJr5Hg7uJZP44RmX9oVnL4qWb',
            ],
            [
                'municipality_code' => '11001',
                'address' => 'Carrera 13 #93-40',
                'coordinates' => '4.6764000,-74.0530000',
                'source' => 'google',
                'accuracy' => 'RANGE_INTERPOLATED',
                'place_id' => 'ChIJt8Jk9vMZP44RpY2sBnK6rZc',
            ],
            [
                'municipality_code' => '11001',
                'address' => 'Calle 41A Sur #83-17',
                'coordinates' => '4.6302670,-74.1663090',
                'source' => 'google',
                'accuracy' => 'GEOMETRIC_CENTER',
                'place_id' => 'ChIJw1Lm2xNZP44RnZ5tCnM7sXd',
            ],
            [
                'municipality_code' => '11001',
                'address' => 'Carrera 8 #5-30',
                'coordinates' => '4.5984000,-74.0763000',
                'source' => 'manual',
                'accuracy' => null,
            ],
            [
                'municipality_code' => '11001',
                'address' => 'Calle 170 #54-90',
                'coordinates' => '4.7430000,-74.0640000',
                'source' => 'manual',
                'accuracy' => null,
            ],

            // Medellín — code 5001 (Antioquia codes in the seed CSV are
            // stripped of the leading zero, so we mirror that here).
            [
                'municipality_code' => '5001',
                'address' => 'Carrera 70 #1-15',
                'coordinates' => '6.2562000,-75.5905000',
                'source' => 'google',
                'accuracy' => 'ROOFTOP',
                'place_id' => 'ChIJa4Np5yQZP44RoW6uDnN8tYe',
            ],
            [
                'municipality_code' => '5001',
                'address' => 'Carrera 36 #5-00',
                'coordinates' => '6.2086000,-75.5680000',
                'source' => 'google',
                'accuracy' => 'GEOMETRIC_CENTER',
                'place_id' => 'ChIJb7Qr8zSZP44RpX7vEnO9uZf',
            ],
            [
                'municipality_code' => '5001',
                'address' => 'Avenida El Poblado #16A-09',
                'coordinates' => '6.2103000,-75.5703000',
                'source' => 'manual',
                'accuracy' => null,
            ],

            // Santiago de Cali — code 76001
            [
                'municipality_code' => '76001',
                'address' => 'Avenida Roosevelt #34-72',
                'coordinates' => '3.4214000,-76.5436000',
                'source' => 'google',
                'accuracy' => 'ROOFTOP',
                'place_id' => 'ChIJc1St2aUZP44RqY8wFnP1vAg',
            ],
            [
                'municipality_code' => '76001',
                'address' => 'Calle 9 #50-25',
                'coordinates' => '3.4399000,-76.5469000',
                'source' => 'google',
                'accuracy' => 'ROOFTOP',
                'place_id' => 'ChIJd4Uv5bVZP44RrZ9xGnQ2wBh',
            ],

            // Cartagena de Indias — code 13001
            [
                'municipality_code' => '13001',
                'address' => 'Calle de la Soledad #5-29',
                'coordinates' => '10.4244000,-75.5510000',
                'source' => 'google',
                'accuracy' => 'RANGE_INTERPOLATED',
                'place_id' => 'ChIJe7Wx8cWZP44RsA1yHnR3xCi',
            ],

            // Soacha — code 25754
            [
                'municipality_code' => '25754',
                'address' => 'Carrera 8 #26-60',
                'coordinates' => '4.5793000,-74.2155000',
                'source' => 'google',
                'accuracy' => 'RANGE_INTERPOLATED',
                'place_id' => 'ChIJf1Yz2dXZP44RtB2zInS4yDj',
            ],

            // Zipaquirá — code 25899
            [
                'municipality_code' => '25899',
                'address' => 'Calle 1 #6-14',
                'coordinates' => '5.0269000,-74.0044000',
                'source' => 'google',
                'accuracy' => 'APPROXIMATE',
                'place_id' => 'ChIJg4Ab5eYZP44RuC3aJnT5zEk',
            ],

            // Chía — code 25175
            [
                'municipality_code' => '25175',
                'address' => 'Calle 17 #11-90',
                'coordinates' => '4.8615000,-74.0556000',
                'source' => 'manual',
                'accuracy' => null,
            ],

            // Bucaramanga — code 68001
            [
                'municipality_code' => '68001',
                'address' => 'Carrera 27 #36-37',
                'coordinates' => '7.1254000,-73.1198000',
                'source' => 'google',
                'accuracy' => 'ROOFTOP',
                'place_id' => 'ChIJh7Cd8fZZP44RvD4bKnU6aFl',
            ],
        ];
    }

    /**
     * Pick a random address record. Useful for factory states that
     * want any landmark, regardless of municipality.
     *
     * @return array{municipality_code:string,address:string,coordinates:string,source:string,accuracy:string|null,place_id?:string}
     */
    public static function random(): array
    {
        $items = self::all();

        return $items[array_rand($items)];
    }
}
