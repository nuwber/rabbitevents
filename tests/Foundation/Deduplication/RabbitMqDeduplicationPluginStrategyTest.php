<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Foundation\Deduplication;

use Interop\Amqp\Impl\AmqpQueue;
use RabbitEvents\Foundation\Contracts\TransportMessage;
use RabbitEvents\Foundation\Deduplication\RabbitMqDeduplicationPluginStrategy;
use RabbitEvents\Tests\Foundation\TestCase;

class RabbitMqDeduplicationPluginStrategyTest extends TestCase
{
    public function testAlwaysReturnsFalseForIsDuplicate(): void
    {
        $strategy = new RabbitMqDeduplicationPluginStrategy();
        $message = \Mockery::mock(TransportMessage::class);

        self::assertFalse($strategy->isDuplicate($message));
    }

    public function testPrepareQueueSetsArgument(): void
    {
        $strategy = new RabbitMqDeduplicationPluginStrategy();
        $queue = new AmqpQueue('test-queue');

        $strategy->prepareQueue($queue);

        self::assertTrue($queue->getArgument('x-message-deduplication'));
    }

    public function testMarkHandledDoesNothing(): void
    {
        $strategy = new RabbitMqDeduplicationPluginStrategy();
        $message = \Mockery::mock(TransportMessage::class);

        $strategy->markHandled($message);
        self::assertTrue(true);
    }
}
