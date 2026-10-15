<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\Console\Log;

use Illuminate\Container\Container;
use Mockery as m;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Console\Log\General;
use RabbitEvents\Listener\Events\ListenerHandlerExceptionOccurred;
use RabbitEvents\Listener\Events\ListenerHandling;
use RabbitEvents\Listener\Message\Handler;
use RabbitEvents\Tests\Listener\Payload;
use RabbitEvents\Tests\Listener\TestCase;

class GeneralTest extends TestCase
{
    public function testLogsHandlerPayload(): void
    {
        $handler = $this->getHandler();
        $logger = m::spy();

        $this->getWriter($logger)->log(new ListenerHandling($handler));

        $logger->shouldHaveReceived('log')->with('info', 'Handler "item.created:ListenerClass" Processing', [
            'handler' => [
                'name' => 'item.created:ListenerClass',
                'attempts' => 0,
                'payload' => ['id' => 1],
            ],
            'status' => General::STATUS_PROCESSING,
        ]);
    }

    public function testLogsExceptionWithHandlerPayload(): void
    {
        $handler = $this->getHandler();
        $exception = new \RuntimeException('Listener failed');
        $logger = m::spy();

        $this->getWriter($logger)->log(new ListenerHandlerExceptionOccurred($handler, $exception));

        $logger->shouldHaveReceived('log')->with('error', 'Handler "item.created:ListenerClass" Exception Occurred', [
            'handler' => [
                'name' => 'item.created:ListenerClass',
                'attempts' => 0,
                'payload' => ['id' => 1],
            ],
            'status' => General::STATUS_EXCEPTION,
            'exception' => $exception,
        ]);
    }

    private function getWriter($logger): General
    {
        $logManager = m::mock();
        $logManager->shouldReceive('channel')->with(null)->andReturn($logger);

        $app = new Container();
        $app->instance('log', $logManager);

        return new General($app);
    }

    private function getHandler(): Handler
    {
        return new Handler(
            new Message('item.created', new Payload(['id' => 1])),
            static fn($event, $payload) => $event,
            'ListenerClass',
            m::mock(Transport::class)
        );
    }
}
