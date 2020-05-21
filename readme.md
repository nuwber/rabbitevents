# Events publishing for Laravel by using RabbitMQ

[![Build Status](https://travis-ci.org/nuwber/rabbitevents.svg?branch=master)](https://travis-ci.org/nuwber/rabbitevents)

Nuwber's events provides a simple observer implementation, allowing you to listen for various events that occur in your current and another applications. For example if you need to react to some event published from another API. 

Do not confuse this package with Laravel's broadcast. This package was made to communicate a backend to backend.
 
Generally, this is compilation of Laravel's [events](https://laravel.com/docs/events) and [queues](https://laravel.com/docs/queues) with some improvements like [middleware](#middleware).

Listener classes are typically stored in the `app/Listeners` folder. You may use Laravel's artisan command to generate them as it described in the [official documentation](https://laravel.com/docs/events).

All RabbitMQ calls are done by using [Laravel queue package](https://github.com/php-enqueue/laravel-queue) as an example. So for better understanding read their documentation first.

# Table of contents
1. [Installation](#installation)
    - [Configuration](#configuration)
1. [Events](#events)    
    - [Defining Events](#defining-events")
    - [Retrying Failed Events](#retry-failed-events)
1. [Listeners](#listeners)
    - [Register a Listener](#register-regular-listener)
        - [Wildcard Listeners](#register-wildcard-listeners)
    - [Defining Listeners](#defining-listeners)
        - [Listeners for wildcard events](#defining-wildcard-listeners)
    - [Middleware](#listener-middleware)
    - [Stopping The Propagation](#stopping-propagination)
1. [Console commands](#commands)
    - [rabbitevents:install](#command-install) - install package assets 
    - [rabbitevents:listen](#command-listen) - listen to an event
    - [rabbitevents:list](#command-list) - display list of registered events
    - [rabbitevents:make:observer](#command-make-observer) - make an Eloquent model events observer
1. [Logging](#logging)
1. [Testing](#testing)
1. [Examples](/examples)

# Installation <a name="installation"></a>
You may use Composer to install RabbitEvents into your Laravel project:

```bash
$ composer require nuwber/rabbitevents
```

After installing RabbitEvents, publish its config and a service provider using the `rabbitevents:install` Artisan command:

```bash
$ php artisan rabbitevents:install
```

## Configuration <a name="configuration"></a>
After publishing assets, its primary configuration file will be located at `config/rabbitevents.php`. 
This configuration file allows you to configure your connection and logging options.

It's very similar to queue connection but now you'll never be confused if you have another connection to RabbitMQ. 

```php
<?php
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
            'logging' => [
                'enabled' => env('RABBITEVENTS_LOG_ENABLED', false),
                'level' => env('RABBITEVENTS_LOG_LEVEL', 'info'),
            ],
        ],
    ],
];
```

# Events <a name="events"></a>

This is the example how to publish your event and payload:

```php
<?php
$model = new SomeModel(['name' => 'Jonh Doe', 'email' => 'email@example.com']);
$someArray = ['key' => 'item'];
$someString = 'Hello!';

// Example 1. Old way to publish your data. Will be deprecated it next versions.
// Remember: You MUST pass array of arguments
fire('something.happened', [$model->toArray(), $someArray, $someString]);

// Example 2. Method `publish` from `Publishable` trait
SomeEvent::publish($model, $someArray, $someString);

$someEvent = new SomeEvent($model, $someArray, $someString);

// Example 3. Use helper `publish`
publish($someEvent);

// Example 4. You could use helper `publish` as you used to use helper `fire`
publish('something.happened', [$model->toArray(), $someArray, $someString]);
publish($someEvent->publishEventName(), $someEvent->toPublish());
```

## Defining Events <a name="defining-events">

If you want to make your event class publishable you should implement interface `ShouldPublish`. 
Example of such class you could see [here](https://github.com/nuwber/rabbitevents/issues/29#issuecomment-531859944).
If you'll try to publish an event without implementation, 
the exception `InvalidArgumentException('Event must be a string or implement "ShouldPublish" interface')` will be thrown.

If you want to add method `publish` to an event class (Example 2) you could use the trait `Publishable`. 

There are helper functions `publish` and `fire` (will be deprecated in next versions).
Examples 1, 3 and 4 illustrates how to use them.

## Retrying Failed Events <a name="retry-failed-events"></a>

The [rabbitevents:listen](#command-listen) command sets number of tries to handle a Job to `1` by default. 
This means that there will be 2 attempts (first attempt and 1 retry) to handle  your event with delay of `sleep` option (default is 5 seconds). 
`--tries=0` means that Rabbitevents will attempt to handle an event forever.
If for some reason event handling shouldn't be retried, throw `\Nuwber\Events\Exception\FailedException` from a Listener. It will mark an event Job as `failed` without new attempts to handle.

More examples [here](#examples)

# Listeners <a name="listeners"></a>
## Register a Listener <a name="register-regular-listener"></a>

The `listen` property of `RabbitEventsServiceProvider` contains an array of all events (keys) and their listeners (values). Of course, you may add as many events to this array as your application requires.

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
        'App\Listeners\ChangeUserRole',
    ],
];
```

### Wildcard Event Listeners <a name="register-wildcard-listener"></a>

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

## Defining Listeners <a name="defining-listeners"></a>

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

### Listeners for wildcard events <a name="defining-wildcard-listeners"></a>

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

### Middleware <a name="listener-middleware"></a>

Listener middleware allow you to wrap custom logic around the execution of a listener job, reducing boilerplate in the jobs themselves. For example, we have an event 'charge.succeeded' which can be handled in several APIs but only if this payment is for its entity.

```php
<?php

/**
 * Hadle payment but only if a type is 'mytype'
 *
 * @param array $payload
 * @return void
 */ 
public function handle($payload) 
{
    if (\Arr::get($payload, 'entity.type') !== 'mytype') {
        return;
    }   
    
    Entity::find(\Arr::get($payload, 'entity.id'))->activate();
}
```

It is ok if you have only one listener. What if there're many listeners, that must implement the same check on the start of each `handle` method? Code will become a bit noisy.

Instead of writing same check at the start of each listener, we could define listener middleware that handles an entity type.

```php
<?php

namespace App\Listeners\RabbitEvents\Middleware;

class FilterEntities
{
    /** 
     * @param string $event Event Name. Passing only fpr wildcard events
     * @param array $payload
     */
    public function handle([$event,] $payload)
    {
        return !\Arr::get($payload, 'entity.type') == 'mytype';
    }
}
```

It doesn't work as route middleware. I still didn't find a beautiful way how to pass only `$payload` for a simple event and `$event` plus `$payload` for wildcard ones.

If we need to stop propagation, just return `false`. 

After creating listener middleware, they may be attached to a listener by returning them from the listener's `middleware` method or as an array item from `$middleware` attribute. 

```php
<?php

use App\Listeners\RabbitEvents\Middleware\FilterEntities;

class PaymentListener
{
    public array $middleware = [
        FilterEntities::class,
        `App\Listeners\RabbitEvents\Middleware\AnotherMiddleware@someAction`,
    ];      

    /** 
     * @param string $event Event Name. Passing only for wildcard events
     * @param array $payload
     */
    public function middleware([$event, ]$payload)
    {
        return !\Arr::get($payload, 'entity.type') == 'mytype';  
    }
}
```

This is just illustration how you could pass middleware to listener. You could choose the way you prefer.

## Stopping The Propagation Of An Event <a name="stopping-propagination"></a>

Sometimes, you may wish to stop the propagation of an event to other listeners. You may do so by returning `false` from your listener's handle method as it is in Laravel's listeners.

# Console commands <a name='commands'></a>
## Command `rabbitevents:install` <a name='command-install'></a>
If you don't want manually create config file and register a service provider, you may run the command `rabbitevents:install` 
which will automatically do all this stuff.

```bash
$ php artisan rabbitevents:install 
``` 

## Command `rabbitevents:listen` <a name='command-listen'></a>

There is the command which is registers events in RabbitMQ:

```bash
$ php artisan rabbitevents:listen event.name --memory=512 --timeout=60 --tries=3 --sleep=5
```

After this command start event will be registered in RabbitMQ as a separate queue which has bind to an event.

To detach command from console you can run this way: 

```bash
$ php artisan rabbitevents:listen event.name > /dev/null &
```

In this case you need to remember that you have organize some system such as [Supervisor](http://supervisord.org/) or [pm2](http://pm2.keymetrics.io/) which will controll your processes.

If your listener crashes then the managers will rerun your listener and all messages that were sent to queue will be handled in same order as they were sent. There're known problem: as queues are separated and you have messages that affects the same entity there's no guaranty that all actions will be done in expected order. To avoid such problems you can send message time as a part of payload and handle it internally in your listeners.


## Command `rabbitevents:list` <a name='command-list'></a>

To get list of all registered events there's the command:

```bash
$ php artisan rabbitevents:list
```

## Command `rabbitevents:make:observer` <a name='command-make-observer'></a>

Sometimes you may with to publish an event to each change of a model. Observers classes have method names which reflect the Eloquent events you wish to listen for. Each of these methods receives the model as their only argument. The difference from Laravel's command is that `rabbitevents:make:observer` creates an observer class with stubbed `fire` function call in each method.

```bash
$ php artisan rabbitevents:make:observer UserObserver --model=User
```

This command will place the new observer in your `App/Observers` directory. If this directory does not exist, Artisan will create it for you. Your fresh observer will look like the following:

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
        publish('User.created', [$user->toArray()]);
    }

    /**
     * Handle the user "updated" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function updated(User $user)
    {
        publish('User.updated', [$user->toArray()]);
    }

    /**
     * Handle the user "deleted" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function deleted(User $user)
    {
        publish('User.deleted', [$user->toArray()]);
    }

    /**
     * Handle the user "restored" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function restored(User $user)
    {
        publish('User.restored', [$user->toArray()]);
    }

    /**
     * Handle the user "force deleted" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function forceDeleted(User $user)
    {
        publish('User.forceDeleted', [$user->toArray()]);
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

# Logging <a name='logging'></a>

The package provides 2 ways to see what happens on your listener. By default it writes `processing`, `processed` and `failed` messages to php output. Message includes service, event and listener name. If you want to turn this feature off, just run listener with `--quiet` option.

The package also supports your application logger. To use it set config value `rabbitevents.connection.rabbitmq.logging.enabled` to `true` and choose log level.

# Testing <a name="testing"></a>

We always write tests. Tests in our applications contains many mocks and fakes to test how events published. We've made this process a bit easier for Event classes that implements `ShouldPublish` and uses `Publishable` trait.   

```php
<?php
use \App\Listeners\Listener;

Listener::fake();

$payload = [
    "key1" => 'value1',
    "key2" => 'value2',
];

Listener::publish($payload);

Listener::assertPublished('something.happened', $payload);

AnotherListener::assertNotPublished();
```

If assertion not passes `Mockery\Exception\InvalidCountException` will bw thrown. 
Don't forget to call `\Mockery::close()` in `tearDown` or similar methods of your tests.
