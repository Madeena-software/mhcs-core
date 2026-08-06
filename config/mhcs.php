<?php

$localLoginDefaults = in_array(env('APP_ENV', 'production'), ['local', 'testing'], true);

return [
    'modules' => [
        'Member',
        'Operator',
        'Doctor',
        'Image Gateway',
    ],

    'web_interfaces' => [
        'member',
        'operator',
        'doctor',
        'administrator',
    ],

    'admin_panel' => [
        'access_permissions' => [
            'member.admin.access',
        ],
    ],

    'queue_purposes' => [
        'notifications',
        'image orchestration',
        'AI routing',
        'payouts',
    ],

    'scheduler_purposes' => [
        'retries',
        'reconciliation',
        'reminders',
        'daily doctor payout batches',
    ],

    'shared_foundations' => [
        'authentication and authorization context',
        'application database',
        'cache and queue',
    ],

    'object_storage' => [
        'authority' => 'Image Gateway',
    ],

    'external_adapters' => [
        'payment gateways',
        'AI providers',
        'email or notification providers',
        'object storage',
        'MPIPS',
    ],

    'module_transport' => 'in-process application contracts and domain events',

    'network_boundary_rule' => 'requires measured operational need and an approved architecture decision',

    'mpips' => [
        'boundary' => 'private black-box external service',
        'caller' => 'image-worker',
        'direct_application_clients' => [],
    ],

    'security' => [
        'identifier_key' => env('MHCS_IDENTIFIER_KEY'),
        'object_key' => env('MHCS_OBJECT_ENCRYPTION_KEY'),
        'grant_key' => env('MHCS_ACCESS_GRANT_KEY'),
        'manifest_key' => env('MHCS_MANIFEST_KEY'),
        'manifest_key_id' => env('MHCS_MANIFEST_KEY_ID'),
        'asset_grants' => [
            'max_ttl_seconds' => env('MHCS_ASSET_GRANT_MAX_TTL_SECONDS', $localLoginDefaults ? 300 : null),
            'audiences' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('MHCS_ASSET_GRANT_AUDIENCES', $localLoginDefaults ? 'member-view,operator-identity' : '')),
            ), static fn (string $audience): bool => $audience !== '')),
        ],
        'login' => [
            // Safe local/test defaults; production values must be injected and approved.
            'pair_max_attempts' => env('MHCS_LOGIN_PAIR_MAX_ATTEMPTS', $localLoginDefaults ? 5 : null),
            'origin_max_attempts' => env('MHCS_LOGIN_ORIGIN_MAX_ATTEMPTS', $localLoginDefaults ? 10 : null),
            'identifier_max_attempts' => env('MHCS_LOGIN_IDENTIFIER_MAX_ATTEMPTS', $localLoginDefaults ? 20 : null),
            'decay_seconds' => env('MHCS_LOGIN_DECAY_SECONDS', $localLoginDefaults ? 60 : null),
        ],
    ],

    'image_policy' => [
        'file_count' => env('MHCS_IMAGE_FILE_COUNT'),
        'per_file_bytes' => env('MHCS_IMAGE_PER_FILE_BYTES'),
        'total_bytes' => env('MHCS_IMAGE_TOTAL_BYTES'),
        'decompressed_bytes' => env('MHCS_IMAGE_DECOMPRESSED_BYTES'),
        'max_width' => env('MHCS_IMAGE_MAX_WIDTH'),
        'max_height' => env('MHCS_IMAGE_MAX_HEIGHT'),
        'field_count' => env('MHCS_IMAGE_FIELD_COUNT'),
        'cpu_seconds' => env('MHCS_IMAGE_CPU_SECONDS'),
        'memory_bytes' => env('MHCS_IMAGE_MEMORY_BYTES'),
        'execution_seconds' => env('MHCS_IMAGE_EXECUTION_SECONDS'),
        'process_count' => env('MHCS_IMAGE_PROCESS_COUNT'),
        'temporary_storage_bytes' => env('MHCS_IMAGE_TEMPORARY_STORAGE_BYTES'),
        'accepted_forms' => env('MHCS_IMAGE_ACCEPTED_FORMS'),
        'recovery_window_seconds' => env('MHCS_IMAGE_RECOVERY_WINDOW_SECONDS'),
        'max_attempts' => env('MHCS_IMAGE_MAX_ATTEMPTS'),
    ],
];
