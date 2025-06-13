<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Automatic Standards Application
    |--------------------------------------------------------------------------
    |
    | These settings control how the package automatically applies database
    | standards and optimizations.
    |
    */
    'auto_standards' => [
        // Use selective operations by default instead of fixing entire database
        'selective_fixing' => env('POSTGRES_HELPER_SELECTIVE_FIXING', false),
        
        // Automatically apply standards when tables are created (requires event triggers)
        'enable_event_triggers' => env('POSTGRES_HELPER_EVENT_TRIGGERS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Control performance monitoring and optimization features.
    |
    */
    'performance' => [
        // Log operations that take longer than this threshold (milliseconds)
        'log_slow_operations' => env('POSTGRES_HELPER_LOG_SLOW_OPS', true),
        'slow_operation_threshold' => env('POSTGRES_HELPER_SLOW_THRESHOLD', 1000),
        
        // Enable detailed operation statistics collection
        'enable_statistics' => env('POSTGRES_HELPER_STATISTICS', true),
        
        // Cache which tables have been fixed recently (seconds)
        'cache_duration' => env('POSTGRES_HELPER_CACHE_DURATION', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define validation rules for different table patterns. Supports wildcards.
    |
    */
    'table_validations' => [
        // Example: All tables ending with '_types' should have these columns
        '*_types' => [
            'required_columns' => ['id', 'name', 'created_at', 'updated_at'],
            'required_indexes' => ['name_unique'],
        ],
        
        // Example: Permission tables
        'permissions*' => [
            'required_columns' => ['id', 'name', 'guard_name', 'created_at', 'updated_at'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure how operations are logged.
    |
    */
    'logging' => [
        // Log channel to use for operation logs
        'channel' => env('POSTGRES_HELPER_LOG_CHANNEL', 'daily'),
        
        // Log successful operations (not just errors)
        'log_success' => env('POSTGRES_HELPER_LOG_SUCCESS', false),
    ],
];