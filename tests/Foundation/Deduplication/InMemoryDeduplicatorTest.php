<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Foundation\Deduplication;

use PHPUnit\Framework\TestCase;
use RabbitEvents\Foundation\Contracts\TransportMessage;
use RabbitEvents\Foundation\Deduplication\InMemoryDeduplicator;

class InMemoryDeduplicatorTest extends TestCase
{
    public function testDetectsDuplicate(): void
    {
        $deduplicator = new InMemoryDeduplicator(100);

        $msg1 = \Mockery::mock(TransportMessage::class);
        $msg1->shouldReceive('getMessageId')->andReturn('uuid-1');

        self::assertFalse($deduplicator->isDuplicate($msg1));

        $deduplicator->markHandled($msg1);

        self::assertTrue($deduplicator->isDuplicate($msg1));
    }

    public function testIgnoresMessagesWithoutId(): void
    {
        $deduplicator = new InMemoryDeduplicator(100);

        $msgNull = \Mockery::mock(TransportMessage::class);
        $msgNull->shouldReceive('getMessageId')->andReturnNull();

        self::assertFalse($deduplicator->isDuplicate($msgNull));
        $deduplicator->markHandled($msgNull);
        self::assertFalse($deduplicator->isDuplicate($msgNull));
    }

    public function testEvictsOldestWhenCapacityExceeded(): void
    {
        $capacity = 3;
        $deduplicator = new InMemoryDeduplicator($capacity);

        $messages = [];
        for ($i = 1; $i <= 4; $i++) {
            $msg = \Mockery::mock(TransportMessage::class);
            $msg->shouldReceive('getMessageId')->andReturn("uuid-{$i}");
            $messages[$i] = $msg;
        }

        $deduplicator->markHandled($messages[1]);
        $deduplicator->markHandled($messages[2]);
        $deduplicator->markHandled($messages[3]);

        self::assertTrue($deduplicator->isDuplicate($messages[1]));
        self::assertTrue($deduplicator->isDuplicate($messages[2]));
        self::assertTrue($deduplicator->isDuplicate($messages[3]));

        // Add 4th message, capacity is 3 -> 1st message should be evicted
        $deduplicator->markHandled($messages[4]);

        self::assertFalse($deduplicator->isDuplicate($messages[1]));
        self::assertTrue($deduplicator->isDuplicate($messages[2]));
        self::assertTrue($deduplicator->isDuplicate($messages[3]));
        self::assertTrue($deduplicator->isDuplicate($messages[4]));
        self::assertEquals(3, $deduplicator->count());
    }
}
