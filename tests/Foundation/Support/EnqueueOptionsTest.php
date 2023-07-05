<?php

namespace RabbitEvents\Tests\Foundation\Support;

use RabbitEvents\Foundation\Support\EnqueueOptions;
use PHPUnit\Framework\TestCase;

class EnqueueOptionsTest extends TestCase
{
    public function test_resolve_queue_name_with_single_event(): void
    {
        $enqueueOptions = new EnqueueOptions('test-app', ['item.created']);

        self::assertEquals('test-app:item.created', $enqueueOptions->resolveQueueName());
    }

    public function test_resolve_queue_name_with_multiple_events()
    {
        $enqueueOptions = new EnqueueOptions('test-app', ['count.one', 'count.two', 'count.three']);

        self::assertEquals('test-app:count.one,count.two,count.three', $enqueueOptions->resolveQueueName());
    }
}
