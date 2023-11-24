<?php

namespace RabbitEvents\Tests;

use Illuminate\Support\Str;
use RabbitEvents\Listener\QueueName;
use PHPUnit\Framework\TestCase;

class QueueNameTest extends TestCase
{
    public function test_resolve_queue_name_with_single_event(): void
    {
        self::assertEquals('test-app:item.created', QueueName::resolve('test-app', ['item.created']));
    }

    public function test_resolve_queue_name_with_multiple_events()
    {
        self::assertEquals(
            'test-app:count.one,count.two,count.three',
            QueueName::resolve('test-app', ['count.one', 'count.two', 'count.three'])
        );
    }

    public function test_resolve_queue_name_with_many_events_in_name()
    {
        $queueName = QueueName::resolve('test-app', [Str::random(300)]);

        self::assertLessThan(200, strlen($queueName) );
    }
}
