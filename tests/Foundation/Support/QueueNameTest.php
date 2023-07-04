<?php

namespace RabbitEvents\Tests\Foundation\Support;

use RabbitEvents\Foundation\Support\QueueNameInterface;
use PHPUnit\Framework\TestCase;

class QueueNameTest extends TestCase
{
    public function test_resolve_queue_name_with_single_event(): void
    {
        $queueName = new QueueNameInterface('test-app', ['item.created']);

        self::assertEquals('test-app:item.created', $queueName->resolve());
    }

    public function test_resolve_queue_name_with_multiple_events()
    {
        $queueName = new QueueNameInterface('test-app', ['count.one', 'count.two', 'count.three']);

        self::assertEquals('test-app:count.one,count.two,count.three', $queueName->resolve());
    }
}
