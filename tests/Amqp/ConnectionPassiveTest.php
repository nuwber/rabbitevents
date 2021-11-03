<?php

namespace Nuwber\Events\Tests\Amqp;

use Enqueue\AmqpTools\DelayStrategy;
use Interop\Amqp\AmqpConnectionFactory;
use Nuwber\Events\Amqp\Connection;
use Nuwber\Events\Queue\Context;
use Nuwber\Events\Tests\TestCase;

class ConnectionPassiveTest extends TestCase
{
    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new Connection([
            'exchange' => 'custom',
            'exchange_passive' => true
        ]);
    }

    public function testConnect()
    {
        self::assertInstanceOf(AmqpConnectionFactory::class, $this->connection->connect());
    }

}
