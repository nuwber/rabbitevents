<?php

namespace Nuwber\Events\Tests;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Nuwber\Events\ContextFactory;
use PHPUnit\Framework\TestCase;

class ContextFactoryTest extends TestCase
{

    public function testConnect()
    {
        $factory = new ContextFactory();

        $this->assertInstanceOf(AmqpConnectionFactory::class, $factory->connect([]));
    }
}
