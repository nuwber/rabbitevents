<?php

use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;

return [

    /*
    |--------------------------------------------------------------------------
    | RabbitEvents Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the RabbitMQ connection settings that should be used
    | by RabbitEvents. Note that `vhost` should be created by you manually.
    | @see https://www.rabbitmq.com/vhosts.html for more info
    |
    */

    'default' => env('RABBITEVENTS_CONNECTION', 'rabbitmq'),
    'connections' => [
        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'exchange' => env('RABBITEVENTS_EXCHANGE', 'events'),
            'host' => env('RABBITEVENTS_HOST', 'localhost'),
            'port' => env('RABBITEVENTS_PORT', 5672),
            'user' => env('RABBITEVENTS_USER', 'guest'),
            'pass' => env('RABBITEVENTS_PASSWORD', 'guest'),
            'vhost' => env('RABBITEVENTS_VHOST', 'events'),
            'delay_strategy' => env('RABBITEVENTS_DELAY_STRATEGY', RabbitMqDlxDelayStrategy::class),
            'ssl' => [
                'is_enabled' => env('RABBITEVENTS_SSL_ENABLED', false),
                'verify_peer' => env('RABBITEVENTS_SSL_VERIFY_PEER', true),
                'cafile' => env('RABBITEVENTS_SSL_CAFILE'),
                'local_cert' => env('RABBITEVENTS_SSL_LOCAL_CERT'),
                'local_key' => env('RABBITEVENTS_SSL_LOCAL_KEY'),
                'passphrase' => env('RABBITEVENTS_SSL_PASSPHRASE', ''),
            ],
            'read_timeout' => env('RABBITEVENTS_READ_TIMEOUT', 3.),
            'write_timeout' => env('RABBITEVENTS_WRITE_TIMEOUT', 3.),
            'connection_timeout' => env('RABBITEVENTS_CONNECTION_TIMEOUT', 3.),
            'heartbeat' => env('RABBITEVENTS_HEARTBEAT', 0),
            'persisted' => env('RABBITEVENTS_PERSISTED', false),
            'lazy' => env('RABBITEVENTS_LAZY', true),
            'qos' => [
                'global' => env('RABBITEVENTS_QOS_GLOBAL', false),
                'prefetch_size' => env('RABBITEVENTS_QOS_PREFETCH_SIZE', 0),
                'prefetch_count' => env('RABBITEVENTS_QOS_PREFETCH_COUNT', 1),
            ]
        ],
    ],
    'logging' => [
        'enabled' => env('RABBITEVENTS_LOG_ENABLED', false),
        'level' => env('RABBITEVENTS_LOG_LEVEL', 'info'),
    ],
];
