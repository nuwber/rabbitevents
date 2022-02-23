# RabbitEvents
Let's imagine a use case: the User made a payment. You need to handle this payment, register the user, send him emails, send analytics data to your analysis system, and so on. The modern infrastructure requires you to create microservices that are doing their specific job and only it: one handles payments, one is for the user management, one is the mailing system, one is for the analysis. How to let all of them know that a payment succeeded and handle this message? The answer is "To use RabbitEvents".

Once again, the RabbitEvents library helps you to publish an event and handle it in another app. No sense to use it in the same app, because  Laravel's Events works for this better.

## Table of contents
1. [Installation via Composer](#installation)
   * [Configuration](#configuration)
1. [Upgrade from 6.x to 7.x](#upgrade_6.x-7.x)
1. [Publisher component](#publisher)
1. [Listener component](#listener)
1. [Console commands](#commands)
   * [rabbitevents:listen](#command-listen) - listen to an event
   * [rabbitevents:list](#command-list) - display list of registered events
1. [Examples](/examples)
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
        ],
    ],
    'logging' => [
        'enabled' => env('RABBITEVENTS_LOG_ENABLED', false),
        'level' => env('RABBITEVENTS_LOG_LEVEL', 'info'),
    ],
];
```

## Upgrade from 6.x to 7.x<a name="upgrade_6.x-7.x"></a>

For better support and simplifying of RabbitEvents, it is now split into 3 Sub-Packages:

- [Publisher](https://github.com/rabbitevents/publisher) - required to PUBLISH an Event;
- [Listener](https://github.com/rabbitevents/listener) - required to HANDLE events;
- [Foundation](https://github.com/rabbitevents/foundation) - common code for Publisher and Listener.

If you've been using RabbitEvents as is, without any changes, it shouldn't impact your application.

If you have extended the functionality of the library in your application, you must revise your code because there were many huge changes in terms of simplifying the code.


### PHP 8.0 required
Rabbitevents now requires PHP 8.0 or greater.

### Supported Laravel versions
Rabbitevents now supports Laravel 8.0 or greater.

### Namespaces change
The main namespace was changed from `Nuwber\Events` to `RabbitEvents`.

### RabbitEventsServiceProvider changes
The `RabbitEventsServiceProvider` now extends `\RabbitEvents\Listener\ListenerServiceProvider`.

The `$listen` attribute now looks like `protected array $listen => [];`. Typehint `array` is retuired.

### Logging configuration
The logging configuration part was moved from a connection to the first level of the configuration. The old configuration is still supported but will be removed in the next releases.

### `\Illuminate\Queue` is not the requirement anymore

If you've been using this package on your fork or extension you should add this package in your `composer.json` as a requirement.

## RabbitEvents Publisher<a name="publisher"></a>

The RabbitEvents Publisher component provides an API to publish events across the application structure. More information about how it works you could find on the RabbitEvents [Publisher page](https://github.com/rabbitevents/publisher).

## RabbitEvents Listener<a name="listener"></a>

The RabbitEvents Listener component provides an API to handle events that were published across the application structure. More information about how it works you could find on the RabbitEvents [Listener page](https://github.com/rabbitevents/listener).

## Console commands <a name='commands'></a>
### Command `rabbitevents:listen` <a name='command-listen'></a>

There is the command which is registers events in RabbitMQ:

```bash
php artisan rabbitevents:listen event.name --memory=512 --tries=3 --sleep=5
```

This command registers a separate queue in RabbitMQ bound to an event. As the only `rabbitevents:listen` registers a queue, you should run this command before you start to publish your events. Nothing will happen if you publish an event first, but it will not be handled by a Listener without the first run.

You could start listening to an event only by using `rabbitevents:listen` command, so you have to use some system such as [Supervisor](http://supervisord.org/) or [pm2](http://pm2.keymetrics.io/) to control your listeners.

If your listener crashes, then managers will rerun your listener and all messages sent to a queue will be handled in the same order as they were sent. There is the known problem: as queues are separated and you have messages that affect the same entity there's no guarantee that all actions will be done in an expected order. To avoid such problems you can send message time as a part of the payload and handle it internally in your listeners.

### Command `rabbitevents:list` <a name='command-list'></a>

To get the list of all registered events please use the command `rabbitevents:list`.

```bash
php artisan rabbitevents:list
```

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
