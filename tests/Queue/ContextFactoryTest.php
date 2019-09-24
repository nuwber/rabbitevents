<?php

namespace Nuwber\Events\Tests\Queue;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Nuwber\Events\Queue\ContextFactory;
use PHPUnit\Framework\TestCase;

class ContextFactoryTest extends TestCase
{

    public function testConnect()
    {
        $factory = new ContextFactory();

        $this->assertInstanceOf(AmqpConnectionFactory::class, $factory->connect([]));
    }
}
