# RabbitEvents

[![Tests Status](https://github.com/rabbitevents/rabbitevents/workflows/Unit%20tests/badge.svg)](https://github.com/rabbitevents/rabbitevents/actions?query=branch%3Amaster+workflow%3A%22Unit+tests%22)
[![codecov](https://codecov.io/gh/rabbitevents/rabbitevents/branch/master/graph/badge.svg?token=8E9CY6866R)](https://codecov.io/gh/rabbitevents/rabbitevents)
[![Total Downloads](https://img.shields.io/packagist/dt/rabbitevents/rabbitevents)](https://packagist.org/packages/rabbitevents/rabbitevents)
[![Latest Version](https://img.shields.io/packagist/v/rabbitevents/rabbitevents)](https://packagist.org/packages/rabbitevents/rabbitevents)
[![License](https://img.shields.io/packagist/l/rabbitevents/rabbitevents)](https://packagist.org/packages/rabbitevents/rabbitevents)

Let's imagine a use case: a User made a payment. You need to handle this payment, register the user, send him emails, send analytics data to your analysis system, and so on. The modern infrastructure requires you to create microservices that do their specific job and only it: one handles payments, one is for user management, one is the mailing system, one is for analysis. How to let all of them know that a payment succeeded and handle this message? The answer is "To use RabbitEvents".

RabbitEvents helps you publish an event and handle it seamlessly in other applications across your microservice ecosystem.

## Demo
![rabbit-events-demo](https://github.com/nuwber/rabbitevents/assets/142213350/89df6194-e49d-4d58-8286-8932f182da4b)

## Table of Contents
1. [Installation via Composer](#installation)
   * [Configuration](#configuration)
1. [Upgrade from 9.x to 10.x](#upgrade_9.x-10.x)
1. [Quick Start](#quick-start)
   * [Publishing Events](#quick-start-publisher)
   * [Listening for Events](#quick-start-listener)
1. [Reliability & Guarantees (Zero-DB Architecture)](#reliability)
   * [Message IDs & Observability](#message-ids)
   * [Idempotency & Deduplication](#deduplication)
   * [Dead Letter Queue (DLQ) & Failed Message Management](#dead-letter-queue)
   * [Transactional Publishing ($afterCommit)](#transactional-publishing)
   * [Distributed Tracing & Context Propagation](#distributed-tracing)
   * [Zero-Downtime Worker Restarts](#worker-restarts)
1. [Listeners & Payloads](#listeners-payloads)
1. [Custom Serializers](#custom-serializers)
1. [Extensions & Ecosystem](#extensions)
1. [Examples](./examples)
1. [Speeding up RabbitEvents](#speeding-up-rabbitevents)
1. [Testing](#testing)
1. [Non-standard use](#non-standard-use)
1. [License](#license)

## Installation via Composer<a name="installation"></a>
You can use Composer to install RabbitEvents into your Laravel project:

```bash
composer require rabbitevents/rabbitevents
```

### Configuration<a name="configuration"></a>
After installing RabbitEvents, publish its configuration file and service provider using the `rabbitevents:install` command:

```bash
php artisan rabbitevents:install
```

This installs `config/rabbitevents.php` and `app/Providers/RabbitEventsServiceProvider.php`.

```php
<?php

use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;

return [
    'default' => env('RABBITEVENTS_CONNECTION', 'rabbitmq'),

    'default_serializer' => \RabbitEvents\Foundation\Serialization\JsonSerializer::class,

    'connections' => [
        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'exchange' => env('RABBITEVENTS_EXCHANGE', 'events'),
            'durable' => env('RABBITEVENTS_QUEUE_DURABLE', true),
            'host' => env('RABBITEVENTS_HOST', 'localhost'),
            'port' => env('RABBITEVENTS_PORT', 5672),
            'user' => env('RABBITEVENTS_USER', 'guest'),
            'pass' => env('RABBITEVENTS_PASSWORD', 'guest'),
            'vhost' => env('RABBITEVENTS_VHOST', 'events'),
            'delay_strategy' => env('RABBITEVENTS_DELAY_STRATEGY', RabbitMqDlxDelayStrategy::class),
            
            // In-memory or plugin-based deduplication
            'deduplication' => [
                'enabled' => env('RABBITEVENTS_DEDUPLICATION_ENABLED', true),
                'strategy' => \RabbitEvents\Listener\Deduplication\InMemoryDeduplicator::class,
                'capacity' => 1000,
            ],
            
            'ssl' => [
                'is_enabled' => env('RABBITEVENTS_SSL_ENABLED', false),
                'verify_peer' => env('RABBITEVENTS_SSL_VERIFY_PEER', true),
                'cafile' => env('RABBITEVENTS_SSL_CAFILE'),
                'local_cert' => env('RABBITEVENTS_SSL_LOCAL_CERT'),
                'local_key' => env('RABBITEVENTS_SSL_LOCAL_KEY'),
                'passphrase' => env('RABBITEVENTS_SSL_PASSPHRASE', ''),
            ],
            'read_timeout' => env('RABBITEVENTS_READ_TIMEOUT', 3.0),
            'write_timeout' => env('RABBITEVENTS_WRITE_TIMEOUT', 3.0),
            'connection_timeout' => env('RABBITEVENTS_CONNECTION_TIMEOUT', 3.0),
            'heartbeat' => env('RABBITEVENTS_HEARTBEAT', 0),
            'persisted' => env('RABBITEVENTS_PERSISTED', false),
            'lazy' => env('RABBITEVENTS_LAZY', true),
            'qos' => [
                'global' => env('RABBITEVENTS_QOS_GLOBAL', false),
                'prefetch_size' => env('RABBITEVENTS_QOS_PREFETCH_SIZE', 0),
                'prefetch_count' => env('RABBITEVENTS_QOS_PREFETCH_COUNT', 1),
            ],
        ],
    ],
    'logging' => [
        'enabled' => env('RABBITEVENTS_LOG_ENABLED', false),
        'level' => env('RABBITEVENTS_LOG_LEVEL', 'info'),
        'channel' => env('RABBITEVENTS_LOG_CHANNEL'),
    ],
];
```

## Upgrade from 9.x to 10.x<a name="upgrade_9.x-10.x"></a>

### 1. New Composer Package Name
RabbitEvents is now an independent project maintained under `rabbitevents/rabbitevents`.
To upgrade, replace `nuwber/rabbitevents` in your `composer.json` or run:

```bash
composer remove nuwber/rabbitevents
composer require rabbitevents/rabbitevents:^10.0
```

> **Zero Breaking Changes in PHP Code:** The namespace remains `RabbitEvents\...`. You do **not** need to change any `use` statements or event classes in your codebase.

### 2. Return Value of `publish()`
The `publish()` helper now returns the generated `string` Message ID (UUIDv4) or `null`, allowing you to record and track message delivery:

```php
// In 9.x: returned void
// In 10.x: returns ?string
$messageId = publish('user.registered', $payload);
```

### 3. Out-of-the-Box Deduplication
Deduplication is now enabled by default using an in-memory ring-buffer (`InMemoryDeduplicator`). If you prefer to disable it or use the RabbitMQ deduplication broker plugin, configure the `deduplication` array in `config/rabbitevents.php`.

### 4. Dead Letter Queue & Management Commands
When event listeners fail after exhausting attempts, messages are automatically saved to `{queue}.failed` via the `rabbitevents.dlx` Dead Letter Exchange. You can now inspect, retry, or flush failed messages using Artisan commands:
`php artisan rabbitevents:failed`, `retry`, `forget`, and `flush`.

### 5. Graceful Worker Restarts
Like Laravel's `queue:restart`, background workers now listen for restart signals. Add `php artisan rabbitevents:restart` to your deployment script.

---

## Quick Start<a name="quick-start"></a>

### 1. Publishing Events<a name="quick-start-publisher"></a>

#### Method A: Using the `publish()` helper
The quickest way to dispatch an event from anywhere in your application:

```php
use function RabbitEvents\Publisher\publish;

$messageId = publish('user.registered', [
    'user_id' => 123,
    'email' => 'alex@example.com',
    'created_at' => now()->toIso8601String(),
]);

logger()->info("Published event user.registered with ID: {$messageId}");
```

#### Method B: Using an Event Class
Define a dedicated event class implementing `ShouldPublish`:

```php
namespace App\Events;

use App\Models\User;
use RabbitEvents\Publisher\ShouldPublish;
use RabbitEvents\Publisher\Support\Publishable;

class UserRegistered implements ShouldPublish
{
    use Publishable;

    // Optional: publish only after the DB transaction commits
    public bool $afterCommit = true;

    public function __construct(public User $user)
    {
    }

    public function publishEventKey(): string
    {
        return 'user.registered';
    }

    public function toPublish(): array
    {
        return [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
        ];
    }
}
```

Dispatch it just like standard Laravel events:

```php
UserRegistered::publish($user);
// or: event(new UserRegistered($user));
```

---

### 2. Listening for Events<a name="quick-start-listener"></a>

#### Method A: Using PHP Attributes (Recommended)
Define a listener and tag it with `#[Listener]`:

```php
namespace App\Listeners;

use RabbitEvents\Listener\Attributes\Listener;

#[Listener('user.registered')]
class SendWelcomeEmail
{
    public function handle(array $payload): void
    {
        $userId = $payload['user_id'];
        $email = $payload['email'];

        // Send email or trigger internal processing...
    }
}
```

#### Method B: Using `RabbitEventsServiceProvider`
Register listeners explicitly in `app/Providers/RabbitEventsServiceProvider.php`:

```php
protected $listen = [
    'user.registered' => [
        \App\Listeners\SendWelcomeEmail::class,
    ],
    'order.*' => [
        \App\Listeners\OrderAuditSubscriber::class,
    ],
];
```

#### Start the Worker
Run the listener worker from your terminal or process manager (Supervisor, Docker, Kubernetes):

```bash
php artisan rabbitevents:listen --events=user.registered
```

Or listen to multiple events or wildcard patterns:

```bash
php artisan rabbitevents:listen --events="user.*,order.completed"
```

---

## Reliability & Guarantees (Zero-DB Architecture)<a name="reliability"></a>

RabbitEvents is designed for microservice environments where services should remain isolated and autonomous. It provides enterprise-level reliability **without requiring MySQL tables, Redis, or database migrations**.

### Message IDs & Observability<a name="message-ids"></a>
Every published event automatically receives a unique RFC 4122 UUIDv4 `message_id`.

```php
$messageId = publish('payment.processed', ['amount' => 99.00]);
// $messageId => "3fa85f64-5717-4562-b3fc-2c963f66afa6"
```

Listener worker console logs display the message ID and execution time in real-time:
```text
[3fa85f64-5717-4562-b3fc-2c963f66afa6] Processing: payment.processed - App\Listeners\HandlePayment
[3fa85f64-5717-4562-b3fc-2c963f66afa6] Processed:  payment.processed - App\Listeners\HandlePayment (14.22ms)
```

### Idempotency & Deduplication<a name="deduplication"></a>
In distributed systems, network retries can cause duplicate message deliveries. RabbitEvents prevents duplicate execution:

* **In-Memory Ring Buffer (Default)**: Automatically remembers the last `N` processed message IDs in worker memory. Requires **zero** dependencies.
* **RabbitMQ Message Deduplication Plugin**: For broker-level deduplication across multiple horizontally scaled workers using the [rabbitmq-message-deduplication](https://github.com/noxdafox/rabbitmq-message-deduplication) plugin.

Configure in `config/rabbitevents.php`:
```php
'deduplication' => [
    'enabled' => true,
    'strategy' => \RabbitEvents\Listener\Deduplication\InMemoryDeduplicator::class,
    'capacity' => 1000,
],
```

### Dead Letter Queue (DLQ) & Failed Message Management<a name="dead-letter-queue"></a>
When an event listener fails and exhausts all retry attempts (`--tries=3`), RabbitEvents automatically diverts the message to a Dead Letter Exchange (`rabbitevents.dlx`) and stores it in `{queue}.failed`.

The failed message retains its original payload, routing key, timestamp, and diagnostic headers (`x-exception-message`, `x-exception-class`).

You can inspect and manage failed messages with built-in Artisan commands:

```bash
# List all failed messages
php artisan rabbitevents:failed

# Retry a specific failed event by its ID
php artisan rabbitevents:retry 3fa85f64-5717-4562-b3fc-2c963f66afa6

# Retry all failed events
php artisan rabbitevents:retry --all

# Delete a failed message from the failed queue
php artisan rabbitevents:forget 3fa85f64-5717-4562-b3fc-2c963f66afa6

# Flush (purge) all messages from the failed queue
php artisan rabbitevents:flush
```

### Transactional Publishing (`$afterCommit`)<a name="transactional-publishing"></a>
Prevent race conditions by ensuring events are published only after database transactions commit:

```php
use RabbitEvents\Publisher\ShouldPublish;
use RabbitEvents\Publisher\Support\Publishable;

class InvoicePaid implements ShouldPublish
{
    use Publishable;

    public bool $afterCommit = true;

    // ...
}
```

### Distributed Tracing & Context Propagation<a name="distributed-tracing"></a>
Laravel 11+ `Context` data is automatically serialized into AMQP headers on publish and hydrated on the listener side, allowing seamless distributed tracing:

```php
use Illuminate\Support\Facades\Context;

// In Service A:
Context::add('trace_id', (string) Str::uuid());
publish('order.created', $orderData);

// In Service B (Listener):
// Context::get('trace_id') is automatically restored!
```

### Zero-Downtime Worker Restarts<a name="worker-restarts"></a>
During continuous deployment, signal workers to finish their current job and restart gracefully:

```bash
php artisan rabbitevents:restart
```

## Listeners & Payloads<a name="listeners-payloads"></a>

How the payload is passed to your listener depends on the serialization format:

**JSON (Default):**
The payload is decoded as an **associative array**.
```php
public function handle(array $payload) {
    // $payload['key']
}
```

**Protobuf:**
The payload is passed as the **Message Object** itself.
```php
public function handle(\Google\Protobuf\Internal\Message $message) {
    // $message->getSomething()
}
```

## Custom Serializers<a name="custom-serializers"></a>

You can implement your own serializer by implementing `RabbitEvents\Foundation\Contracts\Serializer` and `RabbitEvents\Foundation\Contracts\ContentType`.

```php
use RabbitEvents\Foundation\Contracts\Serializer;
use RabbitEvents\Foundation\Contracts\ContentType;
use RabbitEvents\Foundation\Contracts\Payload;

class MySerializer implements Serializer
{
    public function serialize($data): Payload { ... }
    public function deserialize($message): Payload { ... }
    public function contentType(): ContentType { return new MyContentType(); }
    public function canSerialize($payload): bool { ... }
}
```

Register your serializer in a ServiceProvider:

```php
$registry = $this->app->make(\RabbitEvents\Foundation\Serialization\SerializerRegistry::class);
$registry->register(new MySerializer());
```

New serializers are prepended to the registry, so they can override default behavior if they claim the payload.

## Extensions & Ecosystem<a name="extensions"></a>

- [Redis Streams Transport](https://github.com/rabbitevents/redis-transport) - Redis Streams transport driver for RabbitEvents.
- [Symfony Bundle](https://github.com/rabbitevents/symfony-bundle) - Official Symfony integration bundle for RabbitEvents.
- [Protobuf Support](https://github.com/rabbitevents/protobuf) - Official Google Protobuf serialization support for RabbitEvents. Required for using Protobuf messages.

## Examples<a name="examples"></a>

Practical implementation examples and ready-to-use boilerplate classes are available in the [examples](./examples) directory:

- **Publishers:**
  - [`UserCreated.php`](./examples/UserCreated.php) - Standard publishable event with payload mapping and publishing helper.
  - [`TransactionalEvent.php`](./examples/TransactionalEvent.php) - Transaction-safe event published only after DB commits (`$afterCommit = true`).
  - [`ContextTracingEvent.php`](./examples/ContextTracingEvent.php) - Distributed tracing via Laravel `Context` and capturing the returned message ID.
- **Listeners & Subscribers:**
  - [`AttributeListener.php`](./examples/AttributeListener.php) - Clean attribute-based listener with `#[Listener]`.
  - [`OrderEventsSubscriber.php`](./examples/OrderEventsSubscriber.php) - Handling multiple domain events within a single subscriber class.
  - [`IdempotentPaymentListener.php`](./examples/IdempotentPaymentListener.php) - Safe, idempotent processing and trace ID extraction.
  - [`WildcardListener.php`](./examples/WildcardListener.php) - Wildcard event pattern matching and conditional propagation.
  - [`Listener.php`](./examples/Listener.php) - Standard listener with a custom middleware stack.
- **Configuration & Providers:**
  - [`RabbitEventsServiceProvider.php`](./examples/RabbitEventsServiceProvider.php) - Explicit listener mapping via the `$listen` array.
  - [`ExampleMiddleware.php`](./examples/ExampleMiddleware.php) - Custom event middleware implementation.

## Speeding up RabbitEvents<a name="speeding-up-rabbitevents"></a>
To enhance the performance of RabbitEvents, consider installing the `php-amqp` extension along with the `enqueue/amqp-ext` package. 
By doing so, RabbitEvents will utilize the `enqueue/amqp-ext` package instead of the default `enqueue/amqp-lib` package. 
This substitution is advantageous because the C-written `php-amqp` package significantly outperforms the PHP-written `enqueue/amqp-lib` package.

You can install the `php-amqp` extension using the following command:
```bash
pecl install amqp
```
or use the way you prefer. More about it can be found [here](https://pecl.php.net/package/amqp).

Next, install the `enqueue/amqp-ext` package with the following command:
```bash
composer require enqueue/amqp-ext
```
No additional configuration is required.

## Testing <a name="testing"></a>

We always write tests. Tests in our applications contain many mocks and fakes to test how events are published.

There is the `PublishableEventTesting` trait that provides assertion methods in an Event class that you want to test.

**Event.php**

```php
<?php

namespace App\BroadcastEvents;

use RabbitEvents\Publisher\ShouldPublish;
use RabbitEvents\Publisher\Support\Publishable;
use RabbitEvents\Publisher\Support\PublishableEventTesting;

class Event implements ShouldPublish
{
    use Publishable;
    use PublishableEventTesting;

    public function __construct(private array $payload) 
    {
    }

    public function publishEventKey(): string
    {
        return 'something.happened';
    }

    public function toPublish(): array
    {
        return $this->payload;
    }
}
```

**Test.php**

```php
<?php

use \App\RabbitEvents\Event;
use \App\RabbitEvents\AnotherEvent;

Event::fake();

$payload = [
    'key1' => 'value1',
    'key2' => 'value2',
];

Event::publish($payload);

Event::assertPublished('something.happened', $payload);

AnotherEvent::assertNotPublished();
```

If the assertion does not pass, `Mockery\Exception\InvalidCountException` will be thrown.\
Don't forget to call `\Mockery::close()` in `tearDown` or similar methods of your tests.

## Non-standard use <a name="non-standard-use"></a>

If you're using only one part of RabbitEvents, you should know a few things:

1. You remember, we're using RabbitMQ as the transport layer. In the [RabbitMQ Documentation](https://www.rabbitmq.com/tutorials/tutorial-five-python.html), you can find examples of how to publish your messages using a routing key. This routing key is the event name, like `something.happened` from the examples above.

1. RabbitEvents expects that a message body is a JSON-encoded array. Every element of an array will be passed to a Listener as a separate variable. For example:
```json
[
  {
    "key": "value"  
  },
  "string",
  123 
]
```

There are 3 elements in this array, so 3 variables will be passed to a Listener (an array, a string, and an integer).
If an associative array is being passed, the Dispatcher wraps this array by itself.

# License <a name="license"></a>

RabbitEvents is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
