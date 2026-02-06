<?php

namespace RabbitEvents\Tests\Listener;

use RabbitEvents\Listener\Attributes\Listener;
use RabbitEvents\Tests\Foundation\TestCase;
use RabbitEvents\Tests\MocksCommonObjects;

class AttributeDiscoveryTest extends TestCase
{
    use MocksCommonObjects;

    public function testDiscovery()
    {
        $app = $this->mockApplication();

        if (!is_dir(__DIR__ . '/Fixtures/Listeners')) {
            mkdir(__DIR__ . '/Fixtures/Listeners', 0777, true);
        }

        $provider = new TestServiceProvider($app);
        $provider->registerListeners();

        $events = $provider->listens();

        $this->assertArrayHasKey('my.event', $events);
        $this->assertEquals([
            [TestListener::class, 'handle'],
            [TestListener::class, 'onOtherEvent']
        ], $events['my.event']);

        $this->assertArrayHasKey('manual.event', $events);
        $this->assertEquals(['ManualListener'], $events['manual.event']);
    }

    public function testDeduplicateListenersWithClassConstant()
    {
        $app = $this->mockApplication();

        if (!is_dir(__DIR__ . '/Fixtures/Listeners')) {
            mkdir(__DIR__ . '/Fixtures/Listeners', 0777, true);
        }

        // Register the same listener both manually (with ::class) and via attribute
        $provider = new TestDuplicateServiceProvider($app);
        $provider->registerListeners();

        $events = $provider->listens();

        // Verify that 'test.duplicate.event' only has ONE listener, not two
        $this->assertArrayHasKey('test.duplicate.event', $events);
        $this->assertCount(1, $events['test.duplicate.event'], 'Listener registered both manually and via attribute should appear only once');
        $this->assertEquals([TestDuplicateListener::class, 'handle'], $events['test.duplicate.event'][0]);
    }
}

use RabbitEvents\Listener\ListenerServiceProvider;

class TestServiceProvider extends ListenerServiceProvider
{
    // protected $listen = []; // Inherited

    public function registerListeners()
    {
        $this->listenerClasses = [
            TestListener::class,
        ];
    }

    protected array $listen = [
        'manual.event' => [
            'ManualListener'
        ]
    ];
}

#[Listener(event: 'my.event')]
class TestListener
{
    public function handle()
    {
    }

    #[Listener(event: 'my.event')]
    public function onOtherEvent()
    {
    }
}

class TestDuplicateServiceProvider extends ListenerServiceProvider
{
    public function registerListeners()
    {
        $this->listenerClasses = [
            TestDuplicateListener::class, // Auto-discovered via attribute
        ];
    }

    protected array $listen = [
        'test.duplicate.event' => [
            TestDuplicateListener::class, // Manually registered with ::class constant
        ]
    ];
}

#[Listener(event: 'test.duplicate.event')]
class TestDuplicateListener
{
    public function handle()
    {
    }
}
