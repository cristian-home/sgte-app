<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    |
    | This option controls the default search connection that gets used while
    | using Laravel Scout. This connection is used when syncing all models
    | to the search service. You should adjust this based on your needs.
    |
    | Supported: "algolia", "meilisearch", "typesense",
    |            "database", "collection", "null"
    |
    */

    'driver' => env('SCOUT_DRIVER', 'collection'),

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    |
    | Here you may specify a prefix that will be applied to all search index
    | names used by Scout. This prefix may be useful if you have multiple
    | "tenants" or applications sharing the same search infrastructure.
    |
    */

    'prefix' => env('SCOUT_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue Data Syncing
    |--------------------------------------------------------------------------
    |
    | This option allows you to control if the operations that sync your data
    | with your search engines are queued. When this is set to "true" then
    | all automatic data syncing will get queued for better performance.
    |
    */

    'queue' => env('SCOUT_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Database Transactions
    |--------------------------------------------------------------------------
    |
    | This configuration option determines if your data will only be synced
    | with your search indexes after every open database transaction has
    | been committed, thus preventing any discarded data from syncing.
    |
    */

    'after_commit' => false,

    /*
    |--------------------------------------------------------------------------
    | Chunk Sizes
    |--------------------------------------------------------------------------
    |
    | These options allow you to control the maximum chunk size when you are
    | mass importing data into the search engine. This allows you to fine
    | tune each of these chunk sizes based on the power of the servers.
    |
    */

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | This option allows to control whether to keep soft deleted records in
    | the search indexes. Maintaining soft deleted records can be useful
    | if your application still needs to search for the records later.
    |
    */

    'soft_delete' => false,

    /*
    |--------------------------------------------------------------------------
    | Identify User
    |--------------------------------------------------------------------------
    |
    | This option allows you to control whether to notify the search engine
    | of the user performing the search. This is sometimes useful if the
    | engine supports any analytics based on this application's users.
    |
    | Supported engines: "algolia"
    |
    */

    'identify' => env('SCOUT_IDENTIFY', false),

    /*
    |--------------------------------------------------------------------------
    | Algolia Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Algolia settings. Algolia is a cloud hosted
    | search engine which works great with Scout out of the box. Just plug
    | in your application ID and admin API key to get started searching.
    |
    */

    'algolia' => [
        'id' => env('ALGOLIA_APP_ID', ''),
        'secret' => env('ALGOLIA_SECRET', ''),
        'index-settings' => [
            // 'users' => [
            //     'searchableAttributes' => ['id', 'name', 'email'],
            //     'attributesForFaceting'=> ['filterOnly(email)'],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Meilisearch Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Meilisearch settings. Meilisearch is an open
    | source search engine with minimal configuration. Below, you can state
    | the host and key information for your own Meilisearch installation.
    |
    | See: https://www.meilisearch.com/docs/learn/configuration/instance_options#all-instance-options
    |
    */

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [
            // 'users' => [
            //     'filterableAttributes'=> ['id', 'name', 'email'],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Typesense Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Typesense settings. Typesense is an open
    | source search engine using minimal configuration. Below, you will
    | state the host, key, and schema configuration for the instance.
    |
    */

    'typesense' => [
        'client-settings' => [
            'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
            'nodes' => [
                [
                    'host' => env('TYPESENSE_HOST', 'localhost'),
                    'port' => env('TYPESENSE_PORT', '8108'),
                    'path' => env('TYPESENSE_PATH', ''),
                    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
                ],
            ],
            'nearest_node' => [
                'host' => env('TYPESENSE_HOST', 'localhost'),
                'port' => env('TYPESENSE_PORT', '8108'),
                'path' => env('TYPESENSE_PATH', ''),
                'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
            ],
            'connection_timeout_seconds' => env('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
            'healthcheck_interval_seconds' => env('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 30),
            'num_retries' => env('TYPESENSE_NUM_RETRIES', 3),
            'retry_interval_seconds' => env('TYPESENSE_RETRY_INTERVAL_SECONDS', 1),
        ],
        // 'max_total_results' => env('TYPESENSE_MAX_TOTAL_RESULTS', 1000),
        'model-settings' => [
            \App\Models\User::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'name', 'type' => 'string'],
                        ['name' => 'email', 'type' => 'string'],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'name,email',
                ],
            ],
            \App\Models\DocumentType::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'code', 'type' => 'string'],
                        ['name' => 'name', 'type' => 'string'],
                        ['name' => 'is_natural_person', 'type' => 'bool'],
                        ['name' => 'is_legal_person', 'type' => 'bool'],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'code,name',
                ],
            ],
            \App\Models\Eps::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'code', 'type' => 'string'],
                        ['name' => 'name', 'type' => 'string'],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'code,name',
                ],
            ],
            \App\Models\PensionFund::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'code', 'type' => 'string'],
                        ['name' => 'name', 'type' => 'string'],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'code,name',
                ],
            ],
            \App\Models\SeveranceFund::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'code', 'type' => 'string'],
                        ['name' => 'name', 'type' => 'string'],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'code,name',
                ],
            ],
            \App\Models\IncidentType::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'code', 'type' => 'string'],
                        ['name' => 'name', 'type' => 'string'],
                        ['name' => 'severity', 'type' => 'string'],
                        ['name' => 'description', 'type' => 'string', 'optional' => true],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'code,name',
                ],
            ],
            \App\Models\ThirdParty::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'document_type_id', 'type' => 'int32'],
                        ['name' => 'identification_number', 'type' => 'string'],
                        ['name' => 'is_natural_person', 'type' => 'bool'],
                        ['name' => 'first_name', 'type' => 'string', 'optional' => true],
                        ['name' => 'second_name', 'type' => 'string', 'optional' => true],
                        ['name' => 'first_lastname', 'type' => 'string', 'optional' => true],
                        ['name' => 'second_lastname', 'type' => 'string', 'optional' => true],
                        ['name' => 'company_name', 'type' => 'string', 'optional' => true],
                        ['name' => 'trade_name', 'type' => 'string', 'optional' => true],
                        ['name' => 'municipality_id', 'type' => 'int32', 'optional' => true],
                        ['name' => 'address', 'type' => 'string', 'optional' => true],
                        ['name' => 'phone', 'type' => 'string', 'optional' => true],
                        ['name' => 'email', 'type' => 'string', 'optional' => true],
                        ['name' => 'is_customer', 'type' => 'bool'],
                        ['name' => 'is_provider', 'type' => 'bool'],
                        ['name' => 'active', 'type' => 'bool'],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'identification_number,first_name,first_lastname,company_name,trade_name,email',
                ],
            ],
            \App\Models\Driver::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'document_type_id', 'type' => 'int32'],
                        ['name' => 'identification_number', 'type' => 'string'],
                        ['name' => 'first_name', 'type' => 'string'],
                        ['name' => 'second_name', 'type' => 'string', 'optional' => true],
                        ['name' => 'first_lastname', 'type' => 'string'],
                        ['name' => 'second_lastname', 'type' => 'string', 'optional' => true],
                        ['name' => 'municipality_id', 'type' => 'int32', 'optional' => true],
                        ['name' => 'address', 'type' => 'string', 'optional' => true],
                        ['name' => 'phone', 'type' => 'string', 'optional' => true],
                        ['name' => 'email', 'type' => 'string', 'optional' => true],
                        ['name' => 'license_category', 'type' => 'string', 'optional' => true],
                        ['name' => 'license_due_date', 'type' => 'string', 'optional' => true],
                        ['name' => 'eps_id', 'type' => 'int32', 'optional' => true],
                        ['name' => 'pension_fund_id', 'type' => 'int32', 'optional' => true],
                        ['name' => 'severance_fund_id', 'type' => 'int32', 'optional' => true],
                        ['name' => 'has_social_security', 'type' => 'bool'],
                        ['name' => 'active', 'type' => 'bool'],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'identification_number,first_name,first_lastname,email',
                ],
            ],
            \App\Models\Vehicle::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'internal_code', 'type' => 'string'],
                        ['name' => 'plate', 'type' => 'string'],
                        ['name' => 'mobile_number', 'type' => 'string', 'optional' => true],
                        ['name' => 'brand', 'type' => 'string', 'optional' => true],
                        ['name' => 'line', 'type' => 'string', 'optional' => true],
                        ['name' => 'model_year', 'type' => 'int32', 'optional' => true],
                        ['name' => 'type', 'type' => 'string', 'optional' => true],
                        ['name' => 'engine_number', 'type' => 'string', 'optional' => true],
                        ['name' => 'chassis_number', 'type' => 'string', 'optional' => true],
                        ['name' => 'capacity', 'type' => 'int32', 'optional' => true],
                        ['name' => 'municipality_id', 'type' => 'int32', 'optional' => true],
                        ['name' => 'is_third_party', 'type' => 'bool'],
                        ['name' => 'third_party_id', 'type' => 'int32', 'optional' => true],
                        ['name' => 'soat_due_date', 'type' => 'string', 'optional' => true],
                        ['name' => 'rtm_due_date', 'type' => 'string', 'optional' => true],
                        ['name' => 'operation_card_due_date', 'type' => 'string', 'optional' => true],
                        ['name' => 'status', 'type' => 'string'],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'internal_code,plate,brand,line',
                ],
            ],
            \App\Models\Contract::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'contract_number', 'type' => 'string'],
                        ['name' => 'third_party_id', 'type' => 'int32'],
                        ['name' => 'contract_object', 'type' => 'string', 'optional' => true],
                        ['name' => 'start_date', 'type' => 'string', 'optional' => true],
                        ['name' => 'end_date', 'type' => 'string', 'optional' => true],
                        ['name' => 'route_description', 'type' => 'string', 'optional' => true],
                        ['name' => 'is_generic', 'type' => 'bool'],
                        ['name' => 'active', 'type' => 'bool'],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'contract_number,contract_object,route_description',
                ],
            ],
            \App\Models\Invoice::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'invoice_number', 'type' => 'string'],
                        ['name' => 'total_value', 'type' => 'float'],
                        ['name' => 'issue_date', 'type' => 'string', 'optional' => true],
                        ['name' => 'payment_status', 'type' => 'string'],
                        ['name' => 'notes', 'type' => 'string', 'optional' => true],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'invoice_number,notes',
                ],
            ],
            \App\Models\DayStatus::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'date', 'type' => 'string'],
                        ['name' => 'status', 'type' => 'string'],
                        ['name' => 'executor_id', 'type' => 'int32', 'optional' => true],
                        ['name' => 'executed_at', 'type' => 'string', 'optional' => true],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'date,status',
                ],
            ],
            \App\Models\Fuec::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'service_id', 'type' => 'int32'],
                        ['name' => 'consecutive_number', 'type' => 'string'],
                        ['name' => 'generated_at', 'type' => 'string', 'optional' => true],
                        ['name' => 'qr_code', 'type' => 'string', 'optional' => true],
                        ['name' => 'status', 'type' => 'string'],
                        ['name' => 'pdf_url', 'type' => 'string', 'optional' => true],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'consecutive_number,status',
                ],
            ],
            \App\Models\ServiceIncident::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'service_id', 'type' => 'int32'],
                        ['name' => 'incident_type_id', 'type' => 'int32'],
                        ['name' => 'description', 'type' => 'string', 'optional' => true],
                        ['name' => 'registrar_id', 'type' => 'int32', 'optional' => true],
                        ['name' => 'is_driver_report', 'type' => 'bool'],
                        ['name' => 'reported_at', 'type' => 'string', 'optional' => true],
                        ['name' => 'affects_billing', 'type' => 'bool'],
                        ['name' => 'additional_value', 'type' => 'float', 'optional' => true],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'description',
                ],
            ],
            \App\Models\VehicleLocation::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'vehicle_id', 'type' => 'int32'],
                        ['name' => 'recorded_at', 'type' => 'string', 'optional' => true],
                        ['name' => 'latitude', 'type' => 'float'],
                        ['name' => 'longitude', 'type' => 'float'],
                        ['name' => 'is_manual', 'type' => 'bool'],
                    ],
                ],
                'search-parameters' => [
                    'query_by' => 'recorded_at',
                ],
            ],
        ],
        'import_action' => env('TYPESENSE_IMPORT_ACTION', 'upsert'),
    ],

];
