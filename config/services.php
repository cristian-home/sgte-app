<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_maps' => [
        // Browser key: HTTP-referrer restricted. Shared with the Vite
        // bundle via VITE_GOOGLE_MAPS_BROWSER_KEY. Used for Maps
        // JavaScript, Places Autocomplete, client-side Geocoding, and
        // Static Maps.
        'browser_key' => env('GOOGLE_MAPS_BROWSER_KEY'),
        // Server key: IP-restricted. Used server-side by RoutesClient.
        'server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
        // Map ID for the vector map + AdvancedMarker support.
        'map_id' => env('GOOGLE_MAPS_MAP_ID'),
    ],

];
