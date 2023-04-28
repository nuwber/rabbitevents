# RabbitEvents

[![Tests Status](https://github.com/nuwber/rabbitevents/workflows/Unit%20tests/badge.svg?branch=master)](https://github.com/nuwber/rabbitevents/actions?query=branch%3Amaster+workflow%3A%22Unit+tests%22)
[![codecov](https://codecov.io/gh/nuwber/rabbitevents/branch/master/graph/badge.svg?token=8E9CY6866R)](https://codecov.io/gh/nuwber/rabbitevents)
[![Total Downloads](https://img.shields.io/packagist/dt/nuwber/rabbitevents)](https://packagist.org/packages/nuwber/rabbitevents)
[![Latest Version](https://img.shields.io/packagist/v/nuwber/rabbitevents)](https://packagist.org/packages/nuwber/rabbitevents)
[![License](https://img.shields.io/packagist/l/nuwber/rabbitevents)](https://packagist.org/packages/nuwber/rabbitevents)

Let's imagine a use case: the User made a payment. You need to handle this payment, register the user, send him emails, send analytics data to your analysis system, and so on. The modern infrastructure requires you to create microservices that are doing their specific job and only it: one handles payments, one is for the user management, one is the mailing system, one is for the analysis. How to let all of them know that a payment succeeded and handle this message? The answer is "To use RabbitEvents".

Once again, the RabbitEvents library helps you to publish an event and handle it in another app. No sense to use it in the same app, because  Laravel's Events works for this better.

## Table of contents
1. [Installation via Composer](#installation)
   * [Configuration](#configuration)
1. [Upgrade from 7.x to 8.x](#upgrade_7.x-8.x)
1. [Publisher component](#publisher)
1. [Listener component](#listener)
1. [Non-standard use](#non-standard-use)

## Installation via Composer<a name="installation"></a>
You may use Composer to install RabbitEvents into your Laravel project:

```bash
composer require nuwber/rabbitevents
```

### Configuration<a name="configuration"></a>
After installing RabbitEvents, publish its config and a service provider using the `rabbitevents:install` Artisan command:

```bash
php artisan rabbitevents:install
```

This command installs the config file at `config/rabbitevents.php` and the Service Provider file at `app/providers/RabbitEventsServiceProvider.php`.

The config file is very similar to the queue connection, but with the separate config, you'll never be confused if you have another connection to RabbitMQ.

```php
<?php
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;

return [
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
        'channel' => env('RABBITEVENTS_LOG_CHANNEL'),
    ],
];
```
## Upgrade from 7.x to 8.x<a name="upgrade_7.x-8.x"></a>

### PHP 8.1 required
RabbitEvents now requires PHP 8.1 or greater.

### Supported Laravel versions
RabbitEvents now supports Laravel 9.0 or greater.

### Removed `--connection` option from the `rabbitevents:listen` command
There's an issue [#98](https://github.com/nuwber/rabbitevents/issues/98) that still need to be resolved.
The default connection is always used instead. 

## RabbitEvents Publisher<a name="publisher"></a>

The RabbitEvents Publisher component provides an API to publish events across the application structure. More information about how it works you could find on the RabbitEvents [Publisher page](https://github.com/rabbitevents/publisher).

## RabbitEvents Listener<a name="listener"></a>

The RabbitEvents Listener component provides an API to handle events that were published across the application structure. More information about how it works you could find on the RabbitEvents [Listener page](https://github.com/rabbitevents/listener).

## Non-standard use <a name="#non-standard-use"></a>

If you're using only one of parts of RabbitEvents, you should know a few things:

1. You remember, we're using RabbitMQ as the transport layer. In the [RabbitMQ Documentation](https://www.rabbitmq.com/tutorials/tutorial-five-python.html) you could find examples how to publish your messages using a routing key.
   This is an event name like `something.happened` from examples above.

1. RabbitEvents expects that a message body is a JSON encoded array. Every element of an array will be passed to a Listener as a separate variable. Example:
```json
[
  {
    "key": "value"  
  },
  "string",
  123 
]
```

There'e 3 elements of an array, so 3 variables will be passed to a Listener (array, string and integer).
If an associative array is being passed, the Dispatcher wraps this array by itself.
