<?php

namespace RabbitEvents\Tests\Listener\Message;

use Illuminate\Container\Container;
use Mockery as m;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Message\Handler;
use RabbitEvents\Listener\Message\HandlerFactory;
use RabbitEvents\Tests\Listener\Payload;
use RabbitEvents\Tests\Listener\TestCase;

class HandlerFactoryTest extends TestCase
{
    public function testMakeJob(): void
    {
        $transport = m::mock(Transport::class);

        $message = new Message('item.created', new Payload([]), $transport);

        $factory = new HandlerFactory(m::mock(Container::class));
        $handler = $factory->make($message, static function() {}, 'ClassName');

        self::assertInstanceOf(Handler::class, $handler);

        self::assertSame($message, $handler->getMessage());
    }
}
