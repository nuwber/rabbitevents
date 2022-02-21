# RabbitEvents Publisher
The RabbitEvents Publisher component provides an API to publish events across the application structure. More information is available in the [Nuwber's RabbitEvents documentation](https://github.com/nuwber/rabbitevents).

Once again, the RabbitEvents library helps you to publish an event and handle it in another app. No sense to use it in the same app, because  Laravel's Events works for this better.

Let's imagine a use case: The user made a payment. You need to register this user, send him emails, send analytics data to your analysis system, and so on. The modern infrastructure requires you to create microservices that are doing their specific job and only it: one is for the user management, one is the mailing system, one is for the analysis. How to let all of them know that a payment succeeded? The answer is "To use RabbitEvents". By the way the [answer](https://github.com/rabbitevents/listener) to the question "How to handle these events?" is the same.

The RabbitEvents Publisher is the part that lets all other microservices know that a payment succeeded. 

## Table of contents
1. [Installation](#installation)
2. [Configuration](#configuration)
3. [How to publish an Event](#howto_publish)
4. [Testing](#testing)

## Installation<a name="installation"></a>

You could use the RabbitEvents Publisher separately from the main library. 

To get it you should add it to your Laravel application by Composer:

```bash
$ composer require rabbitevents/publisher
```

## Configuration <a name="configuration"></a>
The command `php artisan rabbitevents:install` installs the config file at `config/rabbitevents.php`.

It's very similar to queue connection, but now you'll never be confused if you have another connection to RabbitMQ.

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
            'logging' => [
                'enabled' => env('RABBITEVENTS_LOG_ENABLED', false),
                'level' => env('RABBITEVENTS_LOG_LEVEL', 'info'),
            ],
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
];
```

## How to publish an Event?<a name="howto_publish"></a>

### 1. Event class
This is an example event class:

```php
use App\Payment;
use App\User;
use RabbitEvents\Publisher\ShouldPublish;
use RabbitEvents\Publisher\Support\Publishable;

class PaymentSucceededRabbitEvent implements ShouldPublish
{
    use Publishable;

    public function __construct(private User $user, private Payment $payment)
    {
    }

    public function publishEventKey(): string
    {
        return 'payment.succeeded';
    }

    public function toPublish(): mixed
    {
        return [
            'user' => $this->user->toArray(),
            'payment' => $this->payment->toArray(),
        ];
    }
}
```
The only requirement for event classes is to implement the `\RabbitEvents\Publisher\ShouldPublish` interface.

As an alternative, you could extend `\RabbitEvents\Publisher\Support\AbstractPublishableEvent`. This class was made just to simplify event classes creation.

**To publish** this event you just need to call the `publish` method of the event class and pass here all necessary data.

```php
$payment = new Payment(...);

...

PaymentSucceededRabbitEvent::publish($request->user(), $payment);
```

The method `publish` is provided by the trait `Publishable`.

### 2. Helper function

Sometimes easier is to use the helper function `publish` with an event key and payload.

```php
publish(
	'payment.succeeded', 
	[
	    'user' => $request->user()->toArray(),
	    'payment' => $payment->toArray(),
	]
);
```

### 3. Helper function and Event class

You also could use the combination of two previous methods.

```php
publish(new PaymentSucceededRabbitEvent($request->user(), $payment));
```

## Testing <a name="testing"></a>

We always write tests. Tests in our applications contain many mocks and fakes to test how events are published.

There is the `PublishableEventTesting` trait that provides assertion methods in an Event class that you want to test.

`Event.php`

```php
<?php

namespace App\BroadcastEvents;

use Nuwber\Events\Event\Publishable;
use Nuwber\Events\Event\ShouldPublish;
use Nuwber\Events\Event\Testing\PublishableEventTesting;

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

`Test.php`

```php
<?php

use \App\BroadcastEvents\Event;
use \App\BroadcastEvents\AnotherEvent;

Event::fake();

$payload = [
    'key1' => 'value1',
    'key2' => 'value2',
];

Event::publish($payload);

Event::assertPublished('something.happened', $payload);

AnotherEvent::assertNotPublished();
```

If assertion does not passes `Mockery\Exception\InvalidCountException` will be thrown.
Don't forget to call `\Mockery::close()` in `tearDown` or similar methods of your tests.