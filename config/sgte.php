<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FUEC module
    |--------------------------------------------------------------------------
    |
    | Controls whether the Formato Único de Extracto de Contrato (FUEC)
    | module is available. When false, every FUEC route (including the
    | public verification endpoint) returns 404, the sidebar entry is
    | hidden, and no FUEC-related logic runs. The rest of the system
    | works without it. See REQ-007.
    |
    */

    'fuec_enabled' => env('SGTE_FUEC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | GPS module
    |--------------------------------------------------------------------------
    |
    | Reserved for REQ-010. Not wired yet, but the shape exists so the
    | shared `auth.featureFlags` Inertia prop is forward-compatible.
    |
    */

    'gps_enabled' => env('SGTE_GPS_ENABLED', false),

];
