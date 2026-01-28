<?php

namespace RabbitEvents\Tests\Listener;

use RabbitEvents\Listener\Attributes\Listener;
use RabbitEvents\Listener\HasListeners\RegisterListeners;
use RabbitEvents\Tests\Foundation\TestCase;

class AttributeDiscoveryTest extends TestCase
{
    public function testDiscovery()
    {
        $provider = new TestServiceProvider(\Mockery::mock('Illuminate\Contracts\Foundation\Application'));
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
