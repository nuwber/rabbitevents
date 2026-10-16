<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\Support;

use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Support\Failer;
use RabbitEvents\Tests\Foundation\Stubs\DestinationStub;
use RabbitEvents\Tests\Foundation\Stubs\ProducerStub;
use RabbitEvents\Tests\Foundation\Stubs\TransportMessageStub;
use RabbitEvents\Tests\Foundation\TestCase;
use RabbitEvents\Tests\Listener\Payload;

class FailerTest extends TestCase
{
    public function testSendAttachesDiagnosticHeadersAndPublishesToDlx(): void
    {
        $destination = new DestinationStub('rabbitevents.dlx');
        $producer = new ProducerStub();
        $failer = new Failer($destination, $producer, 'billing:order.created');

        $message = new Message('order.created', new Payload(['order_id' => 10]));
        $transportMessage = new TransportMessageStub();
        $transportMessage->setRoutingKey('order.created');
        $message->setTransportMessage($transportMessage);

        $exception = new \RuntimeException('Payment gateway error');
        $message->markAsFailed($exception, 'App\\Listeners\\ChargeOrder');

        $failer->send($message);

        self::assertCount(1, $producer->sent);
        self::assertSame($destination, $producer->sent[0]['destination']);
        self::assertEquals('billing:order.created', $transportMessage->getRoutingKey());

        self::assertIsInt($message->header('x-failed-at'));
        self::assertEquals('order.created', $message->header('x-original-routing-key'));
        self::assertEquals(\RuntimeException::class, $message->header('x-exception-class'));
        self::assertEquals('Payment gateway error', $message->header('x-exception-message'));
        self::assertNotEmpty($message->header('x-exception-trace'));
        self::assertEquals('App\\Listeners\\ChargeOrder', $message->header('x-listener'));
    }
}
