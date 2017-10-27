<?php

namespace Nuwber\Events\Tests;

use Nuwber\Events\Dispatcher;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class DispatcherTest extends TestCase
{
    private $listen = [
        'item.created' => [
            'Listeners/Class1',
            'Listeners/Class2',
        ],
        'item.updated' => [
            'Listeners/Class3'
        ],
        'item.*' => [
            'Listeners/Class4'
        ]
    ];

    public function testGetEvents()
    {
        $events = array_keys($this->listen);

        self::assertEquals($events, $this->setupDispatcher()->getEvents());
    }

    public function testListen()
    {
        $dispatcher = new Dispatcher();
        $dispatcher->listen('item.event', function() {});

        self::assertTrue($dispatcher->hasListeners('item.event'));
    }

    public function testAddedClosureListeners()
    {
        $dispatcher = new Dispatcher();

        $dispatcher->listen('item.event', function() {});
        $dispatcher->listen('item.event', function() {});

        $listeners = $dispatcher->getListeners('item.event');

        self::assertCount(1, $listeners);

        self::assertEquals(['Closure'], array_keys($listeners));

        self::assertCount(2, $listeners['Closure']);
    }

    public function testCorrectWildcardHandling()
    {
        $listeners = $this->setupDispatcher()
            ->getListeners('item.event');

        self::assertCount(1, $listeners);


        self::assertEquals(['Listeners/Class4'], array_keys($listeners));
    }

    public function testListenersAddedWithNameAsKey()
    {
        $listeners = $this->setupDispatcher()
            ->getListeners('item.created');

        // Expected 3 because 'item.created' + 'item.*'
        self::assertCount(3, $listeners);

        self::assertEquals(['Listeners/Class1', 'Listeners/Class2', 'Listeners/Class4'], array_keys($listeners));
    }

    private function setupDispatcher()
    {
        $dispatcher = new Dispatcher();

        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }

        return $dispatcher;
    }
}
