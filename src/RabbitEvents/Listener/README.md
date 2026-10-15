# RabbitEvents Listener

The RabbitEvents Listener component provides an API for handling events published across the application structure. More information is available in the [Nuwber's RabbitEvents documentation](https://github.com/nuwber/rabbitevents).

If you only need to handle events, you can use the RabbitEvents `Listener` separately from the main library.

## Table of Contents

1. [Installation via Composer](#installation)
2. [Configuration](#configuration)
3. [Register a Listener](#register)
   - [Registering Listeners via Attributes](#registering-listeners-via-attributes)
   - [Listener Discovery](#listener-discovery)
4. [Defining Listeners](#defining-listeners)
   - [Handling Protobuf Messages](#handling-protobuf-messages)
5. [Middleware](#listener-middleware)
6. [Stopping The Propagation Of An Event](#stopping-propagation)
7. [Console Commands](#commands)
   - [Command `rabbitevents:listen`](#command-listen)
     - [Options](#listen-options)
   - [Command `rabbitevents:list`](#command-list)
   - [Event Discovery Caching](#discovery-caching)
8. [Supervisor Configuration](#supervisor)
9. [Logging](#logging)

## Installation via Composer<a name="installation"></a>

RabbitEvents Listener can be installed via the Composer package manager:

```bash
composer require rabbitevents/listener
```

After installing Listener, you can execute the `rabbitevents:install` Artisan command, which will install the RabbitEvents configuration file and `RabbitEventsServiceProvider` into your application:

```bash
php artisan rabbitevents:install
```

## Configuration <a name="configuration"></a>

Details about the configuration are described in the library [documentation](https://github.com/nuwber/rabbitevents#configuration).

## Register a Listener <a name="register"></a>

The `listen` property of `RabbitEventsServiceProvider` contains an array of all events (keys) and their listeners (values). You can add as many events to this array as your application requires.

You can even register listeners using the `*` as a wildcard parameter, allowing you to catch multiple events with the same listener. Wildcard listeners receive the event name as their first argument and the entire event data array as their second argument.

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

### Registering Listeners via Attributes<a name="registering-listeners-via-attributes"></a>

Instead of manually registering listeners in the `$listen` array, you can use the `#[Listener]` attribute on your listener class or methods.

**Class Listener**

```php
use RabbitEvents\Listener\Attributes\Listener;

#[Listener(event: 'payment.succeeded')]
class SendNotification
{
    public function handle($payload)
    {
        // ...
    }
}
```

**Method Listeners**

```php
class UserEventSubscriber
{
    #[Listener(event: 'user.created')]
    public function onUserCreated($payload) {}

    #[Listener(event: 'user.deleted')]
    public function onUserDeleted($payload) {}
}
```

To enable auto-discovery, ensure you are using the `RabbitEventsServiceProvider`.

### Listener Discovery <a name="listener-discovery"></a>

Listener discovery is **enabled by default**. The service provider will automatically scan your `app/Listeners` directory for classes with the `#[Listener]` attribute.

If you wish to **disable** auto-discovery, you can override the `shouldDiscoverEvents` method in your `RabbitEventsServiceProvider`:

```php
/**
 * Determine if events and listeners should be automatically discovered.
 */
public function shouldDiscoverEvents(): bool
{
    return false;
}
```

By default, the `listenerDirectory` is set to `app_path('Listeners')`. You can override this method if your listeners are located elsewhere.

```php
/**
 * Get the listener directory path.
 */
protected function listenerDirectory(): string
{
    return app_path('Bus/Listeners');
}
```

When discovery is enabled, the service provider will scan the directory for classes with the `#[Listener]` attribute and register them automatically.

## Defining Listeners <a name="defining-listeners"></a>

Event listeners receive the event data in the method provided in the `$listen` definition. If no method is provided, the `handle` method will be called. Within the handle method, you can perform actions necessary to respond to the event:

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

### Handling Protobuf Messages<a name="handling-protobuf-messages"></a>

If you publish a Protobuf message, the Listener will automatically receive the hydrated object instance.
> **Note**: You must install the `rabbitevents/protobuf` package to use this feature.

```php
<?php

use App\Messages\AccountCreated;

class AccountListener
{
    public function handle(AccountCreated $message)
    {
        // $message is an instance of App\Messages\AccountCreated
        $id = $message->getId();
    }
}
```

## Middleware <a name="listener-middleware"></a>

A Listener middleware allows you to wrap custom logic around the execution of a listener, reducing boilerplate in the handlers themselves. For example, you have an event `charge.succeeded` which can be handled in several APIs but only if this payment is for a specific entity.

Without middleware, you had to check the entity type in a listener's handle method.

```php
<?php

/**
 * Handle payment but only if the type is 'mytype'
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

It is okay if you have only one listener. What if many listeners must implement the same check at the first line of the `handle` method? The code will become a bit noisy.

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

After creating listener middleware, they can be attached to a listener by returning them from the listener's `middleware` method or as an array item from the `$middleware` attribute.

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

You can see that you are also able to specify which method of the attached middleware class should be called.

### Pipeline Middleware (Onion-style)

In addition to filter middleware, RabbitEvents supports Laravel's onion-style pipeline middleware accepting a `$next` closure:

```php
<?php

namespace App\Listeners\RabbitEvents\Middleware;

use Closure;

class MeasureExecutionTime
{
    public function handle($payload, Closure $next)
    {
        $start = hrtime(true);

        $response = $next($payload);

        $elapsed = (hrtime(true) - $start) / 1e6;
        \Log::info("Execution took {$elapsed}ms");

        return $response;
    }
}
```

## Stopping The Propagation Of An Event <a name="stopping-propagation"></a>

Sometimes, you may wish to stop the propagation of an event to other listeners. You can do so by returning `false` from your listener's handle method.

# Reliability & Idempotency <a name="reliability"></a>

RabbitEvents is designed to work **100% out of the box** relying solely on RabbitMQ—no MySQL database tables, Redis instances, or external migrations are required.

### Dead Letter Queue (DLQ)

When messages exceed maximum retry attempts (`--tries`) or encounter fatal unhandled exceptions, RabbitEvents does not delete them silently. Instead:
- It declares a topic Dead Letter Exchange (`rabbitevents.dlx`).
- It binds a dedicated DLQ queue (`{queue}.failed`).
- When a message fails, it is moved to `{queue}.failed` alongside diagnostic headers: `x-failed-at`, `x-original-routing-key`, `x-exception-message`, and `x-exception-class`.

### Deduplication (Idempotency)

To guard against duplicate message delivery, RabbitEvents includes a pluggable deduplication strategy system:
- **`InMemoryDeduplicator` (Default)**: An in-memory LRU ring buffer per worker process tracking processed message IDs. Zero database or Redis setup required.
- **`RabbitMqDeduplicationPluginStrategy`**: Delegates deduplication to the RabbitMQ broker via the `rabbitmq-message-deduplication` plugin using the `x-deduplication-header`.
- **`NullDeduplicator`**: Disables deduplication if not needed.

Configured in `config/rabbitevents.php`:
```php
'deduplication' => [
    'enabled' => env('RABBITEVENTS_DEDUPLICATION_ENABLED', true),
    'strategy' => env('RABBITEVENTS_DEDUPLICATION_STRATEGY', \RabbitEvents\Foundation\Deduplication\InMemoryDeduplicator::class),
    'cache_size' => env('RABBITEVENTS_DEDUPLICATION_CACHE_SIZE', 10000),
],
```

# Console Commands <a name='commands'></a>

## Command `rabbitevents:listen` <a name='command-listen'></a>

There is a command that registers events in RabbitMQ:

```bash
php artisan rabbitevents:listen event.name --memory=512 --tries=3 --sleep=5 -v
```

This command registers a separate queue in RabbitMQ bound to an event. As `rabbitevents:listen` registers a queue, you should run this command before you start publishing your events. Nothing will happen if you publish an event first, but it will not be handled by a Listener without the first run.

To start listening to all events registered in the application, you can run without any event names:

```bash
php artisan rabbitevents:listen
```

or mention events separated by commas:

```bash
php artisan rabbitevents:listen event.one,event.two,event.n
```

If the list of events is too long, the queue name will be created as an MD5 checksum of the lengthy queue name. 
This was implemented because the AMQP driver does not accept longer names.

You can start listening to an event only by using the `rabbitevents:listen` command, so you could use some system such as [Supervisor](http://supervisord.org/) or [pm2](http://pm2.keymetrics.io/) to control your listeners.

### Options<a name="listen-options"></a>

`--service=` - By default, it's `config('app.name')`. When a queue starts, the name of the service becomes a part of a queue name: `service:event.name`. You could override the first part of a queue name by this option.\
`--memory=128` - The memory limit in megabytes. The RabbitEvents worker restarts if the limit is exceeded.\
`--timeout=60` - The length of time (in seconds) each Message should be allowed to be handled.\
`--tries=1` - The Number of times to attempt to handle a Message before routing it to the DLQ (`.failed`).\
`--sleep=5` - How long in seconds a worker would wait before the next try to handle a failed message.\
`--quiet` - Hide console output.\
`-v` - Verbosity. Enables a stack trace for Exceptions.

## Dead Letter Queue (DLQ) Commands

Manage failed messages sitting in the Dead Letter Queue without needing MySQL or Redis:

### View Failed Messages (`rabbitevents:failed`)
```bash
php artisan rabbitevents:failed
php artisan rabbitevents:failed --queue=my-service:order.created
```
Displays a table of failed messages containing message ID, event name, failed timestamp, and exception details.

### Retry Failed Messages (`rabbitevents:retry`)
```bash
# Retry a specific message by its UUID
php artisan rabbitevents:retry 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d

# Retry all failed messages
php artisan rabbitevents:retry --all
```
Republishes failed message(s) back onto the original queue where it failed (with attempts reset) and removes them from the failed queue.

### Delete a Failed Message (`rabbitevents:forget`)
```bash
php artisan rabbitevents:forget 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
```

### Flush All Failed Messages (`rabbitevents:flush`)
```bash
php artisan rabbitevents:flush
```

## Command `rabbitevents:list` <a name='command-list'></a>

To get the list of all registered events, please use the command `rabbitevents:list`.

```bash
php artisan rabbitevents:list
```

## Event Discovery Caching <a name="discovery-caching"></a>

If you are using event discovery, scanning the listener directory on every request (or worker boot) can be slow in large applications. You can cache the discovered events using the `rabbitevents:cache` command:

```bash
php artisan rabbitevents:cache
```

This command will generate a `bootstrap/cache/rabbitevents.php` file. The `RabbitEventsServiceProvider` will automatically use this file instead of scanning the directory.

To clear the cache, use the `rabbitevents:clear` command:

```bash
php artisan rabbitevents:clear
```

> [!IMPORTANT]
> You should run `rabbitevents:cache` during your deployment process.

# Supervisor Configuration<a name="supervisor"></a>

The Supervisor configuration is similar to [Laravel Queue](https://laravel.com/docs/queues#supervisor-configuration).

# Logging & Observability <a name='logging'></a>

The package provides 2 ways to see what happens to your listener:
1. **Console Output**: Outputs color-coded logs including the unique Message ID and execution duration (in milliseconds):
   ```
   [9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d] Processing: app:payment.succeeded - App\Listeners\SendNotification
   [9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d] Processed:  app:payment.succeeded - App\Listeners\SendNotification (12.45ms)
   ```
   To get exception stack traces, run with verbosity >= 1 (`-v`). To suppress output, use `--quiet`.

2. **Application Logger**: Set `rabbitevents.logging.enabled` to `true` to send events directly to your application log channel (`rabbitevents.logging.channel`).

