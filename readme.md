# Events broadcasting for Laravel by using RabbitMQ

[![Build Status](https://travis-ci.org/nuwber/rabbitevents.svg?branch=master)](https://travis-ci.org/nuwber/rabbitevents)

# Table of contents
1. [Introduction](#introduction)
2. [Installation](#installation)
    - [Register seervice providers](#register-sp)
    - [Configure RabbitMQ connection](#rabbitmq-connection-config)
3. [Events & Listeners](#events-listeners)
    - [Register a Listener](#register-regular-listener)
        - [Wildcard Listeners](#register-wildcard-listeners)
    - [Defining Listeners](#defining-listeners)
        - [Listeners for wildcard events](#defining-wildcard-listeners)
    - [Stopping The Propagation Of An Event](#stopping-propagination)
    - [How to dispatch an event](#event-firing)
4. [Console commands](#commands)
    - [rabbitevents:listen](#command-listen) - listen to an event
    - [rabbitevents:list](#command-list) - display list of registered events
    - [rabbitevents:make:observer](#command-make-observer) - make an Eloquent model events observer
5. [Logging](#logging)

    
## Introduction <a name="introduction"></a>

Nuwber's broadcasting events provides a simple observer implementation, allowing you to listen for various events that occur in your current and another applications. For example if you need to react to some event fired from another API. 

Do not confuse this package with Laravel's broadcast. This package was made to communicate in backend to backend way.
 
Generally, this is compilation of Laravel's [events](https://laravel.com/docs/events) and [queues](https://laravel.com/docs/queues).

Listener classes are typically stored in the `app/Listeners` folder. You may use Laravel's artisan command to generate them as it described in the [official documentation](https://laravel.com/docs/events).


All RabbitMQ calls are done by using [Laravel queue package](https://github.com/php-enqueue/laravel-queue). So for better understanding read their documentation first.

## Installation <a name="installation"></a>
Add this library to your composer.json

```bash
$ composer require nuwber/rabbitevents
```

### Register service providers <a name="register-sp"></a>

First of all you need to create a service provider which is extends `Nuwber\Events\BroadcastEventServiceProvider` 
and register it in your `config/app.php` in `providers` section.

To provide `amqp_inerop` connection you need to register `Enqueue\LaravelQueue\EnqueueServiceProvider` in same way.

### Configure RabbitMQ connection <a name="rabbitmq-connection-config"></a>

The library uses internal Laravel's queue system. To configure connection you should make changes in `config/queue.php`:

```php
<?php
[
    'default' => env('QUEUE_CONNECTION', 'interop'),
    'connections' => [
        'interop' => [
            'driver' => 'amqp_interop',
            'connection_factory_class' => \Enqueue\AmqpLib\AmqpConnectionFactory::class,
            'host' => env('RABBITMQ_HOST', 'localhost'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'pass' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => 'events',
            'logging' => [
                'enabled' => false,
                'level' => 'info',
            ]
        ],
    ],
]
```

We've got few requests that library doesn't work because of `Call to undefined method Illuminate\Queue\SyncQueue::getPsrContext()` exception.
The reason is because setting `QUEUE_CONNECTION` in `.env.example` is set to `sync`. So in your .env file you need to change this value to `interop` or remove this setting if you set it as in the exampe above.

## Events & Listeners <a name="events-listeners"></a>

### Register a Listener <a name="register-regular-listener"></a>

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

#### Wildcard Event Listeners <a name="register-wildcard-listener"></a>


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

### Defining Listeners <a name="defining-listeners"></a>

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

#### Listeners for wildcard events <a name="defining-wildcard-listeners"></a>

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

### Stopping The Propagation Of An Event <a name="stopping-propagination"></a>

Sometimes, you may wish to stop the propagation of an event to other listeners. You may do so by returning `false` from your listener's handle method as it is in Laravel's listeners.

### How to dispatch an event <a name="event-firing"></a>


To broadcast an event you can use the helper function `fire` (you may create your own solution). The first argument is event name, that second is payload. Note that payload should me an array of items array.

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

## Console commands <a name='commands'></a>
### Command `rabbitevents:listen` <a name='command-listen'></a>

There is the command which is registers events in RabbitMQ:

```bash
$ php artisan rabbitevents:listen event.name
```

After this command start event will be registered in RabbitMQ as a separate queue which has bind to an event.

To detach command from console you can run this way: 

```bash
$ php artisan rabbitevents:listen event.name > /dev/null &
```

In this case you need to remember that you have organize some system such as [Supervisor](http://supervisord.org/) or [pm2](http://pm2.keymetrics.io/) which will controll your processes.

If your listener crashes then the managers will rerun your listener and all messages that were sent to queue will be handled in same order as they were sent. There're known problem: as queues are separated and you have messages that affects the same entity there's no guaranty that all actions will be done in expected order. To avoid such problems you can send message time as a part of payload and handle it internally in your listeners.


### Command `rabbitevents:list` <a name='command-list'></a>

To get list of all registered events there's the command:

```bash
$ php artisan rabbitevents:list
```

### Command `rabbitevents:make:observer` <a name='command-make-observer'></a>

Sometimes you may with to send a broadcast event to each change of a model. Observers classes have method names which reflect the Eloquent events you wish to listen for. Each of these methods receives the model as their only argument. The difference from Laravel's command is that `rabbitevents:make:observer` creates an observer class with stubbed `fire` function call in each method.

```bash
$ php artisan rabbitevents:make:observer UserObserver --model=User
```

This command will place the new observer in your App/Observers directory. If this directory does not exist, Artisan will create it for you. Your fresh observer will look like the following:

```php
<?php

namespace App\Observers;

use App\User;

class UserObserver
{
    /**
     * Handle the user "created" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function created(User $user)
    {
        fire('User.created', [$user->toArray()]);
    }

    /**
     * Handle the user "updated" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function updated(User $user)
    {
        fire('User.updated', [$user->toArray()]);
    }

    /**
     * Handle the user "deleted" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function deleted(User $user)
    {
        fire('User.deleted', [$user->toArray()]);
    }

    /**
     * Handle the user "restored" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function restored(User $user)
    {
        fire('User.restored', [$user->toArray()]);
    }

    /**
     * Handle the user "force deleted" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function forceDeleted(User $user)
    {
        fire('User.forceDeleted', [$user->toArray()]);
    }
}
```

To register an observer, use the observe method on the model you wish to observe. You may register observers in the boot method of one of your service providers. In this example, we'll register the observer in the AppServiceProvider:

```php
<?php

namespace App\Providers;

use App\User;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        User::observe(UserObserver::class);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
```

## Logging <a name='logging'></a>

The package provides 2 ways to see what happens on your listener. By default it writes `processing`, `processed` and `failed` messages to php output. Message includes service, event and listener name. If you want to turn this feature off, just run listener with `--quiet` option.

The package also supports your application logger. To use it set config value `connection.interop.logging.enabled` to true and choose log level.
