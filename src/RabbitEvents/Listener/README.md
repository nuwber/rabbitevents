# RabbitEvents Listener
The RabbitEvents Listener component provides an API to handle events that were published across the application structure. More information is available in the [Nuwber's RabbitEvents documentation](https://github.com/nuwber/rabbitevents).

## Table of contents
1. [Installation via Composer](#installation)
2. [Configuration](#configuration)
3. 

## Installation via Composer<a name="installation"></a>

If you need just to handle events, you could use the RabbitEvents `Listener` separately from the main library. 

To get it you should add it to your Laravel application by Composer:

```bash
$ composer require rabbitevents/listener
```

## Configuration <a name="configuration"></a>
The command `php artisan rabbitevents:install` installs the config file at `config/rabbitevents.php` and the Service Provider file at `app/providers/RabbitEventsServiceProvider.php`.

More information is available in the main library [documentation](https://github.com/nuwber/rabbitevents#configuration).

## Register a Listener <a name="register-regular-listener"></a>

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
   public cunction handle($eventPayload) 
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

# RabbitEvents Listener specific console commands <a name='commands'></a>
## Command `rabbitevents:listen` <a name='command-listen'></a>

There is the command which is registers events in RabbitMQ:

```bash
$ php artisan rabbitevents:listen event.name --memory=512 --tries=3 --sleep=5
```

This command registers a separate queue in RabbitMQ bound to an event. As the only `rabbitevents:listen` registers a queue, you should run this command before you start to publish your events. Nothing will happen if you publish an event first, but it will not be handled by a Listener without the first run.

You could start listening to an event only by using `rabbitevents:listen` command, so you have to use some system such as [Supervisor](http://supervisord.org/) or [pm2](http://pm2.keymetrics.io/) to control your listeners.

If your listener crashes, then managers will rerun your listener and all messages sent to a queue will be handled in the same order as they were sent. There is the known problem: as queues are separated and you have messages that affect the same entity there's no guarantee that all actions will be done in an expected order. To avoid such problems you can send message time as a part of the payload and handle it internally in your listeners.

## Command `rabbitevents:list` <a name='command-list'></a>

To get the list of all registered events please use the command `rabbitevents:list`.

```bash
$ php artisan rabbitevents:list
```