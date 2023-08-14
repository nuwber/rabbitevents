# RabbitEvents Listener
The RabbitEvents Listener component provides an API to handle events that were published across the application structure. More information is available in the [Nuwber's RabbitEvents documentation](https://github.com/nuwber/rabbitevents).

If you need just to handle events, you could use the RabbitEvents `Listener` separately from the main library. 

## Table of contents
1. [Installation via Composer](#installation)
1. [Configuration](#configuration)
1. [Register a Listener](#register)
1. [Defining Listeners](#defining-listeners)
1. [Middleware](#listener-middleware)
1. [Stopping The Propagation Of An Event](#stopping-propagination)
1. [Console commands](#commands)
1. [Logging](#logging)

## Installation via Composer<a name="installation"></a>
RabbitEvents Listener may be installed via the Composer package manager:

```bash
composer require rabbitevents/listener
```

After installing Listener, you may execute the `rabbitevents:install` Artisan command, which will install the RabbitEvents configuration file and `RabbitEventsServiceProvider` into your application:

```bash
php artisan rabbitevents:install
```

## Configuration <a name="configuration"></a>
Details about the configuration are described in the library [documentation](https://github.com/nuwber/rabbitevents#configuration).

## Register a Listener <a name="register"></a>

The `listen` property of `RabbitEventsServiceProvider` contains an array of all events (keys) and their listeners (values). Of course, you may add as many events to this array as your application requires.

You may even register listeners using the `*` as a wildcard parameter, allowing you to catch multiple events on the same listener. Wildcard listeners receive the event name as their first argument and the entire event data array as their second argument.

```php
<?php

use RabbitEvents\Listener\ListenerServiceProvider;

class RabbitEventsServiceProvider extends ListenerServiceProvider
{
	protected $listen = [
       'payment.succeeded' => [
           'App\Listeners\SendNotification',
           'App\Listeners\ChangeUserRole@process',
           function($payload) { Log::info('Payment Succeeded', $payload); },
           ['App\Listeners\ChangeUserRole', 'process']
        ],
        'item.*' => [
            AllItemEventsListener::class
        ]
    ];
}
```

## Defining Listeners <a name="defining-listeners"></a>

Event listeners receive the event data at the method provided in the `$listen` definition. If no method is provided, the `handle` method will be called. Within the handle method, you may perform actions necessary to respond to the event:

**Example of The Event Listener class**

```php
<?php

class SendNotification
{
   public function handle($eventPayload) 
   {
   	    Mailer::to(Arr::get($eventPayload, 'user.email'))
   	        ->subject('Payment Succeeded')
   	        ->message('...');
   }
}
```

**Example of The Wildcard Event Listener class**

```php
<?php

class AllItemEventsListener
{
    public function handle($event, $payload)
    {
        match($event) => [
            'item.created' => ...,
            'item.deleted' => ...,
            'item.updated' => ...,
        ];
    }
}
```

## Middleware <a name="listener-middleware"></a>

A Listener middleware allows you to wrap custom logic around the execution of a listener, reducing boilerplate in the handlers themselves. For example, you have an event `charge.succeeded` which can be handled in several APIs but only if this payment is for its entity.

Without middleware, you had to check an entity type in a listener handle method.

```php
<?php

/**
 * Handle payment but only if a type is 'mytype'
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

It is ok if you have only one listener. What if many listeners must implement the same check at the first line of the `handle` method? The code will become a bit noisy.

Instead of writing the same check at the start of each listener, you could define listener middleware that handles an entity type.

```php
<?php

namespace App\Listeners\RabbitEvents\Middleware;

class FilterEntities
{
    /** 
     * @param string $event the event name. Passing only for wildcard events
     * @param array $payload
     */
    public function handle([$event,] $payload)
    {
        return !\Arr::get($payload, 'entity.type') == 'mytype';
    }
}
```

In this example, you see that if you need to stop the propagation, just return `false`.

After creating listener middleware, they may be attached to a listener by returning them from the listener's `middleware` method or as an array item from the `$middleware` attribute.

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
     * @param string $event the Event Name. Passing only for wildcard events
     * @param array $payload
     */
    public function middleware([$event, ]$payload)
    {
        return !\Arr::get($payload, 'entity.type') == 'mytype';  
    }
}
```
You may see, that you are also able to specify which method of the attached middleware class should be called.

This is just an illustration of how you could pass middleware to the listener. You could choose the way you prefer.

## Stopping The Propagation Of An Event <a name="stopping-propagination"></a>

Sometimes, you may wish to stop the propagation of an event to other listeners. You may do so by returning `false` from your listener's handle method.

# Console commands <a name='commands'></a>
## Command `rabbitevents:listen` <a name='command-listen'></a>

There is the command which is registers events in RabbitMQ:

```bash
php artisan rabbitevents:listen event.name --memory=512 --tries=3 --sleep=5
```

This command registers a separate queue in RabbitMQ bound to an event. As the only `rabbitevents:listen` registers a queue, you should run this command before you start to publish your events. Nothing will happen if you publish an event first, but it will not be handled by a Listener without the first run.

To start listening to all events registered in the application, you could run without any event names

```bash
php artisan rabbitevents:listen
```

or to mention events separated by comma

```bash
php artisan rabbitevents:listen event.one,event.two,event.n
```

You could start listening to an event only by using `rabbitevents:listen` command, so you could use some system such as [Supervisor](http://supervisord.org/) or [pm2](http://pm2.keymetrics.io/) to control your listeners.

If your listener crashes, then managers will rerun your listener and all messages sent to a queue will be handled in the same order as they were sent. There is the known problem: as queues are separated and you have messages that affect the same entity there's no guarantee that all actions will be done in an expected order. To avoid such problems you can send message time as a part of the payload and handle it internally in your listeners.

### Options<a name="listen-options"></a>

| Option       | Default value                  | Description                                                                                                                                                         |
|--------------|--------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `--service=` | result of `config('app.name')` | When a queue starts the name of the service becomes a part of a queue name: `service:event.name`. You could override the first part of a queue name by this option. |
| `--memory=`  | `128`                          | The memory limit in megabytes. The RabbitEvents have restarting a worker if limit exceeded.                                                                         |
| `--timeout=` | `60`                           | The length of time (in seconds) each Message should be allowed to be handled.                                                                                       |
| `--tries=`   | `1`                            | Number of times to attempt to handle a Message before logging it failed.                                                                                            |
| `--sleep=`   | `5`                            | Sleep time in seconds before handling failed message next time.                                                                                                     |
| `--quiet`    | disabled                       | No console output.                                                                                                                                                  |
| `-v`         | disabled                       | Verbosity. Enables stack trace for exceptions.                                                                                                                      |

## Command `rabbitevents:list` <a name='command-list'></a>

To get the list of all registered events please use the command `rabbitevents:list`.

```bash
php artisan rabbitevents:list
```

# Supervisor configuration<a name="supervisor"></a>
The suprvisor configuration is similar to [Laravel Queue](https://laravel.com/docs/5.1/queues#supervisor-configuration). We decided to not copy this doc here.

# Logging <a name='logging'></a>

The package provides 2 ways to see what happens to your listener. By default, it writes `processing`, `processed`, `failed` messages and occurred exceptions to console output. 
The message includes service, event, and listener name. To get exception's trace run listener with verbosity >= 1, for example `-v`.   
If you want to turn this feature off, just run listener with the `--quiet` option.

The package also supports your application logger. To use it set config value `rabbitevents.logging.enabled` to `true` and choose log level.

When choosing to use the application logger you may configure the package's logging channel using the config value `rabbitevents.logging.channel` by default it will use the value from `logging.default`.
