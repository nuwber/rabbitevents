# Events broadcasting for Laravel by using RabbitMQ

[![Build Status](https://travis-ci.org/nuwber/rabbitevents.svg?branch=master)](https://travis-ci.org/nuwber/rabbitevents)

Nuwber's broadcasting events provides a simple observer implementation, allowing you to listen for various events that occur in your current and another applications. For example if you need to react to some event fired from another API. 

Do not confuse this package with Laravel's broadcast. This package was made to communicate in backend to backend way.
 
Generally, this is compilation of Laravel's [events](https://laravel.com/docs/events) and [queues](https://laravel.com/docs/queues).

Listener classes are typically stored in the `app/Listeners` folder. You may use Laravel's artisan command to generate them as it described in the [official documentation](https://laravel.com/docs/events).


All RabbitMQ calls are done by using [Laravel queue package](https://github.com/php-enqueue/laravel-queue). So for better understanding read their documentation first.

## Installation
Add this library to your composer.json

```
composer require nuwber/rabbitevents
```

### Register service providers

First of all you need to create a service provider which is extends `Nuwber\Events\BroadcastEventServiceProvider` 
and register it in your `config/app.php` in `providers` section.

To provide `amqp_inerop` connection you need to register `Enqueue\LaravelQueue\EnqueueServiceProvider` in same way.

## RabbitMQ configuring

The library uses internal Laravel's queue system. To configure connection you need to make changes in `config/queue.php`:

-  in the `connections` section add:

```
'connections' => [
    'interop' => [
        'driver' => 'amqp_interop',
        'connection_factory_class' => \Enqueue\AmqpLib\AmqpConnectionFactory::class,
        'host' => 'localhost',
        'port' => 5672,
        'user' => env('RABBITMQ_USER', 'guest'),
        'pass' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => 'events',
        'logging' => [
        	'enabled' => false,
        	'level' => 'info',
        ]
    ],
],
```
- specify your credentials in `.env` file
- set `interop` connection as default

## Registering Events & Listeners

The `listen` property of `BroadcastEventServiceProvider` contains an array of all events (keys) and their listeners (values). Of course, you may add as many events to this array as your application requires.

```php
<?php
/**
 * The event listener mappings for the application.
 *
 * @var array
 */
protected $listen = [
    'item.created' => [
        'App\Listeners\SendItemCreatedNotification',
    ],
];
```

#### Wildcard Event Listeners


You may even register listeners using the * as a wildcard parameter, allowing you to catch multiple events on the same listener. Wildcard listeners receive the event name as their first argument, and the entire event data array as their second argument:

```php
<?php
/**
 * The event listener mappings for the application.
 *
 * @var array
 */
protected $listen = [
    'item.*' => [
        'App\Listeners\ItemLogger',
    ],
];
```

## Defining Listeners

Event listeners receive the event data (usually this is an array) in their `handle` method. Within the handle method, you may perform any actions necessary to respond to the event:

```php
<?php

namespace App\Listeners;

class ItemLogger
{

    /**
     * Handle the event.
     *
     * @param  array $payload
     * @return void
     */
    public function handle(array $payload)
    {
        log(...);
    }
}
```

#### Listeners for wildcard events

There's difference for `handle` method of listeners for wildcard events. It receives fired event name as a first argument and payload as the second:

```php
<?php

namespace App\Listeners;

class ItemLogger
{

    /**
     * Handle the event.
     *
     * @param  string $event
     * @param  array $payload
     * @return void
     */
    public function handle(string $event, array $payload)
    {
    	if ($event === 'item.created') {
    		// do something special
    	}
    	
       log(...);
    }
}
```

#### Stopping The Propagation Of An Event

Sometimes, you may wish to stop the propagation of an event to other listeners. You may do so by returning `false` from your listener's handle method as it is in Laravel's listeners.

## Running listeners

There is the command which is registers events in RabbitMQ:

```
php artisan rabbitevents:listen event.name
```

After this command start event will be registered in RabbitMQ as a separate queue which has bind to an event.

To detach command from console you can run this way: 

```
php artisan rabbitevents:listen event.name > /dev/null &
```

In this case you need to remember that you have organize some system such as [Supervisor](http://supervisord.org/) or [pm2](http://pm2.keymetrics.io/) which will controll your processes.

If your listener will be crached in some reason these managers will rerun your listener and all messages that were sent to queue will be handled in same order as they were sent. There're known problem: as queues are separated and you have messages that affects the same entity there's no guaranty that all actions will be done in expected order. To avoid such problems you can send message time as a part of payload and hanle it internally in your listeners.

### Get list of registered events

To get list of all registered listeners there's the command:

```
php artisan rabbitevents:list
```

## Event firing

To fire event to RabbitMQ you can use the helper function `fire`. You can pass array as second argument. Elements of this array will be used as arguments in event listener handler.

```php
<?php
// your activity
$payload = [
    // First argument
    [
        'user_id' => 1,
        'first_name' => 'John',
        'last_name' = 'Doe'
    ],

    // Second argument
    [
        'product_id' => 72,
        'description' => 'Product Description',
        'amount' => 9.99
    ],
    //...
];

fire('item.created', $payload);
```

## Logging

The package provides 2 ways to see what happenes on your listener. By default it writes `processing`, `processed` and `failed` messages to php output. Message includes service, event and listener name. If you want to turn this feature off, just run listener with `--quiet` option.

The package also supports your application logger. To use it set config value `connection.interop.logging.enabled` to true and choose log level.
