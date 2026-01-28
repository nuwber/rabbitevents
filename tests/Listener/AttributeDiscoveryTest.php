<?php

namespace RabbitEvents\Tests\Listener;

use RabbitEvents\Listener\Attributes\Listener;
use RabbitEvents\Listener\HasListeners\RegisterListeners;
use RabbitEvents\Tests\Foundation\TestCase;

class AttributeDiscoveryTest extends TestCase
{
    public function testDiscovery()
    {
        $app = \Mockery::mock('Illuminate\Foundation\Application');
        $app->shouldReceive('bound')->with('path.bootstrap')->andReturn(false);
        $app->shouldReceive('path')->with('Listeners')->andReturn(__DIR__ . '/Fixtures/Listeners');
        $app->shouldReceive('basePath')->andReturn(__DIR__ . '/Fixtures');
        $app->shouldReceive('getNamespace')->andReturn('RabbitEvents\Tests\Listener\Fixtures\\');

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
}

use RabbitEvents\Listener\ListenerServiceProvider;

class TestServiceProvider extends ListenerServiceProvider
{
    // protected $listen = []; // Inherited

    public function registerListeners() {
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
    public function handle() {}

    #[Listener(event: 'my.event')]
    public function onOtherEvent() {}
}
