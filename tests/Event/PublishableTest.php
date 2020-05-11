<?php

namespace Nuwber\Events\Tests\Event;

use Illuminate\Container\Container;
use Mockery\Exception\InvalidCountException;
use Nuwber\Events\Event\Publishable;
use Nuwber\Events\Event\Publisher;
use Nuwber\Events\Event\ShouldPublish;
use Nuwber\Events\Tests\TestCase;

class PublishableTest extends TestCase
{
    /**
     * @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|Publisher
     */
    private $spy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spy = \Mockery::spy(Publisher::class);
        Container::getInstance()->instance(Publisher::class, $this->spy);
    }

    public function testPublishingMethod()
    {
        $this->expectNotToPerformAssertions();

        $payload = [
            "key1" => 'value1',
            "key2" => 'value2',
        ];

        Listener::publish($payload);

        $this->spy->shouldHaveReceived()
            ->publish(\Mockery::on(function(ShouldPublish $object) use ($payload) {
                return $object instanceof Listener
                    && $object->publishEventKey() == 'something.happened'
                    && $object->toPublish() == $payload;
            }))
            ->once();
    }

    public function testFake()
    {
        $this->expectNotToPerformAssertions();

        Listener::fake();

        $payload = [
            "key1" => 'value1',
            "key2" => 'value2',
        ];

        Listener::publish($payload);

        Listener::assertPublished('something.happened', $payload);
    }

    public function testFakeAssertionFailed()
    {
        $this->expectException(InvalidCountException::class);

        Listener::fake();

        $payload = [
            "key1" => 'value1',
            "key2" => 'value2',
        ];

        Listener::publish($payload);

        Listener::assertPublished('something.other.happened', []);
    }

    public function testAssertNotPublishedIfNotPublished()
    {
        $this->expectNotToPerformAssertions();

        Listener::fake();

        AnotherListener::publish();

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

    public function publishEventKey(): string
    {
        // TODO: Implement publishEventKey() method.
    }

    public function toPublish(): array
    {
        // TODO: Implement toPublish() method.
    }

}
