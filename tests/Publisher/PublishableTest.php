<?php

namespace RabbitEvents\Tests\Publisher;

use Illuminate\Container\Container;
use Mockery\Exception\InvalidCountException;
use RabbitEvents\Publisher\Publisher;
use RabbitEvents\Publisher\ShouldPublish;
use RabbitEvents\Publisher\Support\Publishable;
use RabbitEvents\Publisher\Support\PublishableEventTesting;

class PublishableTest extends TestCase
{
    private $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publisher = \Mockery::spy(Publisher::class);
        Container::getInstance()->instance(Publisher::class, $this->publisher);
    }

    public function testPublishingMethod(): void
    {
        $payload = [
            "key1" => 'value1',
            "key2" => 'value2',
        ];

        self::assertNull(Listener::publish($payload));

        $this->publisher->shouldHaveReceived()
            ->publish(\Mockery::on(function(ShouldPublish $object) use ($payload) {
                return $object instanceof Listener
                    && $object->publishEventKey() == 'something.happened'
                    && $object->toPublish() == $payload;
            }))
            ->once();
    }

    public function testFake(): void
    {
        Listener::fake();

        $payload = [
            "key1" => 'value1',
            "key2" => 'value2',
        ];

        self::assertNull(Listener::publish($payload));

        Listener::assertPublished('something.happened', $payload);
    }

    public function testFakeAssertWithoutPayload(): void
    {
        Listener::fake();

        self::assertNull(Listener::publish(['whatever' => 1]));

        Listener::assertPublished('something.happened');
    }

    public function testFakeAssertionFailed(): void
    {
        $this->expectException(InvalidCountException::class);

        Listener::fake();

        $payload = [
            "key1" => 'value1',
            "key2" => 'value2',
        ];

        self::assertNull(Listener::publish($payload));

        Listener::assertPublished('something.other.happened', []);
    }

    public function testAssertNotPublishedIfNotPublished(): void
    {
        Listener::fake();

        self::assertNull(AnotherListener::publish());

        Listener::assertNotPublished();
    }

    public function testAssertNotPublishedIfPublished(): void
    {
        $this->expectException(InvalidCountException::class);

        Listener::fake();

        Listener::publish([]);

        Listener::assertNotPublished();
    }
}

class Listener implements ShouldPublish
{
    use Publishable;
    use PublishableEventTesting;

    private $payload = [];

    public function __construct($payload)
    {
        $this->payload = $payload;
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

class AnotherListener implements ShouldPublish
{
    use Publishable;
    use PublishableEventTesting;

    public function publishEventKey(): string
    {
        // TODO: Implement publishEventKey() method.
    }

    public function toPublish(): array
    {
        // TODO: Implement toPublish() method.
    }

}
