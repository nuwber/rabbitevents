<?php

namespace Nuwber\Events\Tests;

use Enqueue\AmqpLib\AmqpContext;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Amqp\Impl\AmqpTopic;
use Nuwber\Events\MessageFactory;
use PHPUnit\Framework\TestCase;

class MessageFactoryTest extends TestCase
{
    public function testMake()
    {
        $data = ['id' => 1];
        $event = 'item.created';

        $expectedMessage = new AmqpMessage(json_encode($data));
        $expectedMessage->setRoutingKey($event);

        $context = \Mockery::mock(AmqpContext::class)->makePartial();

        $factory = new MessageFactory($context, new AmqpTopic('events'));

        $message = $factory->make($event, $data);

        self::assertEquals($expectedMessage, $message);
    }
}
