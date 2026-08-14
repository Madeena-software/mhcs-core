<?php

$maxUploadMb = env('MHCS_MAX_UPLOAD_MB', 100);
$maxUploadBytes = $maxUploadMb === null ? null : (int) $maxUploadMb * 1024 * 1024;
$maxUploadMb = $maxUploadBytes === null ? null : intdiv($maxUploadBytes, 1024 * 1024);
$imageFileCount = env('MHCS_IMAGE_FILE_COUNT', 2);
$imagePairBytes = $maxUploadBytes === null ? null : $maxUploadBytes * 2;

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

    'private_object_disk' => env('MHCS_PRIVATE_OBJECT_DISK', 's3'),

    'upload' => [
        'max_file_mb' => $maxUploadMb,
        'max_file_bytes' => $maxUploadBytes,
        'max_request_bytes' => $imagePairBytes === null ? null : $imagePairBytes + 1024 * 1024,
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
        'base_url' => rtrim((string) env('MPIPS_BASE_URL', 'http://127.0.0.1:8014'), '/'),
        'api_key' => env('MPIPS_API_KEY'),
        'timeout_seconds' => (int) env('MPIPS_TIMEOUT_SECONDS', 360),
        'worker_timeout_seconds' => (int) env('IMAGE_GATEWAY_WORKER_TIMEOUT', 390),
        'max_attempts' => 5,
        'backoff_base_seconds' => 2,
        'backoff_cap_seconds' => 30,
    ],

    'security' => [
        'identifier_key' => env('MHCS_IDENTIFIER_KEY'),
        'grant_key' => env('MHCS_ACCESS_GRANT_KEY'),
        'manifest_key' => env('MHCS_MANIFEST_KEY'),
        'manifest_key_id' => env('MHCS_MANIFEST_KEY_ID'),
        'asset_grants' => [
            'max_ttl_seconds' => env('MHCS_ASSET_GRANT_MAX_TTL_SECONDS', 300),
            'audiences' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('MHCS_ASSET_GRANT_AUDIENCES', 'member-view,operator-identity')),
            ), static fn (string $audience): bool => $audience !== '')),
        ],
        'login' => [
            // Safe fallback defaults; production values can be injected and configured.
            'pair_max_attempts' => env('MHCS_LOGIN_PAIR_MAX_ATTEMPTS', 5),
            'origin_max_attempts' => env('MHCS_LOGIN_ORIGIN_MAX_ATTEMPTS', 10),
            'identifier_max_attempts' => env('MHCS_LOGIN_IDENTIFIER_MAX_ATTEMPTS', 20),
            'decay_seconds' => env('MHCS_LOGIN_DECAY_SECONDS', 60),
        ],
    ],

    'image_policy' => [
        'file_count' => $imageFileCount,
        'per_file_bytes' => $maxUploadBytes,
        'total_bytes' => $imagePairBytes,
        'decompressed_bytes' => env('MHCS_IMAGE_DECOMPRESSED_BYTES', 4194304),
        'max_width' => env('MHCS_IMAGE_MAX_WIDTH', 4096),
        'max_height' => env('MHCS_IMAGE_MAX_HEIGHT', 4096),
        'field_count' => env('MHCS_IMAGE_FIELD_COUNT', 32),
        'cpu_seconds' => env('MHCS_IMAGE_CPU_SECONDS', 5),
        'memory_bytes' => env('MHCS_IMAGE_MEMORY_BYTES', 134217728),
        'execution_seconds' => env('MHCS_IMAGE_EXECUTION_SECONDS', 30),
        'process_count' => env('MHCS_IMAGE_PROCESS_COUNT', 1),
        'temporary_storage_bytes' => env('MHCS_IMAGE_TEMPORARY_STORAGE_BYTES', 8388608),
        'accepted_forms' => env('MHCS_IMAGE_ACCEPTED_FORMS', 'zip-npz'),
        'recovery_window_seconds' => env('MHCS_IMAGE_RECOVERY_WINDOW_SECONDS', 300),
        'max_attempts' => env('MHCS_IMAGE_MAX_ATTEMPTS', 1),
    ],
];
