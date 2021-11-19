<?php

namespace Nuwber\Events\Tests\Event;

use Illuminate\Container\Container;
use Mockery\Exception\InvalidCountException;
use Nuwber\Events\Event\Publishable;
use Nuwber\Events\Event\Testing\PublishableEventTesting;
use Nuwber\Events\Event\Publisher;
use Nuwber\Events\Event\ShouldPublish;
use Nuwber\Events\Tests\TestCase;

class PublishableTest extends TestCase
{
    /**
     * @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|Publisher
     */
    private $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publisher = \Mockery::spy(Publisher::class);
        Container::getInstance()->instance(Publisher::class, $this->publisher);
    }

    public function testPublishingMethod()
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

    public function testFake()
    {
        Listener::fake();

        $payload = [
            "key1" => 'value1',
            "key2" => 'value2',
        ];

        self::assertNull(Listener::publish($payload));

        Listener::assertPublished('something.happened', $payload);
    }

    public function testFakeAssertWithoutPayload()
    {
        Listener::fake();

        self::assertNull(Listener::publish(['whatever' => 1]));

        Listener::assertPublished('something.happened');
    }

    public function testFakeAssertionFailed()
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

    public function testAssertNotPublishedIfNotPublished()
    {
        Listener::fake();

        self::assertNull(AnotherListener::publish());

        Listener::assertNotPublished();
    }

    public function testAssertNotPublishedIfPublished()
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
    use \Nuwber\Events\Event\Testing\PublishableEventTesting;

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
