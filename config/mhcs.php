<?php

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
        'caller' => 'Image Gateway worker',
        'direct_application_clients' => [],
    ],
];
