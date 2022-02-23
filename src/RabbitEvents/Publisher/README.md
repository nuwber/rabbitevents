# RabbitEvents Publisher
The RabbitEvents Publisher component provides an API to publish events across the application structure. More information is available in the [Nuwber's RabbitEvents documentation](https://github.com/nuwber/rabbitevents).

The RabbitEvents Publisher is the part that lets all other microservices know that a payment succeeded. 

## Table of contents
1. [Installation via Composer](#installation)
2. [Configuration](#configuration)
3. [Publishing](#howto_publish)
4. [Testing](#testing)

## Installation via Composer<a name="installation"></a>
RabbitEvents Publisher may be installed via the Composer package manager:

```bash
composer require rabbitevents/publisher
```

After installing Publisher, you may execute the `rabbitevents:install` Artisan command, which will install the RabbitEvents configuration file into your application:

```bash
php artisan rabbitevents:install
```

## Configuration <a name="configuration"></a>
Details about the configuration are described in the library [documentation](https://github.com/nuwber/rabbitevents#configuration).

## Publishing<a name="howto_publish"></a>

### By using an Event class
This is an example event class:

```php
<?php

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

To **publish** this event you just need to call the `publish` method of the event class and pass here all necessary data.

```php
<?php

$payment = new Payment(...);

...

PaymentSucceededRabbitEvent::publish($request->user(), $payment);
```

The method `publish` is provided by the trait `Publishable`.

### By using the `publish` function

Sometimes easier is to use the helper function `publish` with an event key and payload.

```php
<?php

publish(
	'payment.succeeded', 
	[
	    'user' => $request->user()->toArray(),
	    'payment' => $payment->toArray(),
	]
);
```

### Publish an Event object with the `publish` function

You also could use the combination of two previous methods.

```php
<?php
$event = new PaymentSucceededEvent($request->user(), $payment);

event($event)
publish($event);
```

## Testing <a name="testing"></a>

We always write tests. Tests in our applications contain many mocks and fakes to test how events are published.

There is the `PublishableEventTesting` trait that provides assertion methods in an Event class that you want to test.

**Event.php**

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

If assertion does not passes `Mockery\Exception\InvalidCountException` will be thrown.
Don't forget to call `\Mockery::close()` in `tearDown` or similar methods of your tests.