<?php

namespace RabbitEvents\Tests\Foundation\Support;

use RabbitEvents\Foundation\Support\QueueName;
use PHPUnit\Framework\TestCase;

class QueueNameTest extends TestCase
{
    public function testResolve(): void
    {
        $queueName = new QueueName('test-app', 'item.created');

        self::assertEquals('test-app:item.created', $queueName->resolve());
    }
}
