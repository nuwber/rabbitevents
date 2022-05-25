<?php declare(strict_types=1);

namespace RabbitEvents\Tests\Listener;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use RabbitEvents\Listener\Dispatcher;
use RabbitEvents\Tests\Listener\Stubs\ListenerStub;
use RabbitEvents\Tests\Listener\Stubs\ListenerStubForMiddleware;

class DispatcherTest extends TestCase
{
    private array $listen = [
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

    public function testGetEvents(): void
    {
        $events = array_keys($this->listen);

        self::assertEquals($events, $this->setupDispatcher()->getEvents());
    }

    public function testListen(): void
    {
        $dispatcher = new Dispatcher();
        $dispatcher->listen('item.event', static function() {});

        self::assertTrue($dispatcher->hasListeners('item.event'));
    }

    public function testAddedClosureListeners(): void
    {
        $dispatcher = new Dispatcher();
        $closure1 = static function() {};
        $closure2 = static function() {};

        $dispatcher->listen('item.event', $closure1);
        $dispatcher->listen('item.event', $closure2);

        $listeners = $dispatcher->getListeners('item.event');

        self::assertCount(2, $listeners);

        foreach ($listeners as $key => $listener) {

            [$class, $callback] = $listener;
            ++$key;

            self::assertEquals('Closure', $class);
            $varName = "closure$key";
            self::assertSame($$varName, $callback);
        }
    }

    public function testSimpleListenerCallWithAssocArrayAsPayload(): void
    {
        $payload = ['item' => true];

        $dispatcher = new Dispatcher();
        $dispatcher->listen('simple', ListenerStub::class);
        $listeners = $dispatcher->getListeners('simple');
        [,$closure] = Arr::first($listeners);

        //array is because listener returns func_get_args
        $this->assertEquals([$payload], $closure('simple', $payload));
    }

    public function testWildcardListenerCallWithAssocArrayAsPayload(): void
    {
        $payload = ['item' => true];

        $dispatcher = new Dispatcher();
        $dispatcher->listen('wildcard.*', ListenerStub::class);
        $listeners = $dispatcher->getListeners('wildcard.*');
        [,$closure] = Arr::first($listeners);

        //array is because listener returns func_get_args
        $this->assertEquals(['wildcard.event', $payload], $closure('wildcard.event', $payload));
    }

    public function testGetListeners()
    {
        $dispatcher = $this->setupDispatcher();

        $preparedListeners = $dispatcher->getListeners('item.created');

        $listener1 = array_shift($preparedListeners);
        [$class, $callable] = $listener1;

        self::assertEquals($this->listen['item.created'][0], $class);
        self::assertIsCallable($callable);

        $listener2 = array_shift($preparedListeners);
        [$class, $callable] = $listener2;

        self::assertEquals($this->listen['item.created'][1], $class);
        self::assertIsCallable($callable);

        $listener3 = array_shift($preparedListeners);
        [$class, $callable] = $listener3;

        self::assertEquals($this->listen['item.*'][0], $class);
        self::assertIsCallable($callable);
    }

    public function testAddListenerWhichIsAnObject()
    {
        $dispatcher = new Dispatcher();
        $dispatcher->listen('some.event', new ListenerStubForMiddleware());

        $listeners = $dispatcher->getListeners('some.event');

        [$class, $callback] = array_shift($listeners);

        self::assertEquals(ListenerStubForMiddleware::class, $class);

        $payload = ['pay' => 'load'];
        $result = $callback('some.event', $payload);

        self::assertEquals($payload, array_shift($result));
    }

    public function testListenerInstanceNotInstanceable()
    {
        $this->expectException(BindingResolutionException::class);

        $dispatcher = new Dispatcher();
        $dispatcher->listen('some.event', ['Not Existing', 'Class']);
        $listeners = $dispatcher->getListeners('some.event');

        [$class, $callback] = array_shift($listeners);

        self::assertEquals('Unknown Class', $class);

        $callback('some.event', []);
    }
    
    private function setupDispatcher(): Dispatcher
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
