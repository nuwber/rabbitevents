<?php

namespace RabbitEvents\Tests\Foundation;

use Interop\Amqp\Impl\AmqpQueue;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Context;
use Mockery as m;

class ContextTest extends TestCase
{
    public function test_create_consumer()
    {
        $amqpQueue = new AmqpQueue('name');
        $destination = new \RabbitEvents\Foundation\Amqp\AmqpDestinationAdapter($amqpQueue);
        
        $connectionStub = new \RabbitEvents\Tests\Foundation\Stubs\ConnectionStub();

        $context = new Context($connectionStub, m::mock(\RabbitEvents\Foundation\Serialization\SerializerRegistry::class));
        $consumer = $context->makeConsumer($destination);

        self::assertInstanceOf(Consumer::class, $consumer);
        self::assertCount(1, $connectionStub->createdConsumers);
        self::assertSame($destination, $connectionStub->createdConsumers[0]);
    }

    public function test_make_queue()
    {
        $events = ['event.one', 'event.two'];
        $queueName = 'test-app:rabbitevents';
        
        $topic = new \RabbitEvents\Tests\Foundation\Stubs\DestinationStub();

        $connectionStub = new \RabbitEvents\Tests\Foundation\Stubs\ConnectionStub();

        $queue = (new Context($connectionStub, m::mock(\RabbitEvents\Foundation\Serialization\SerializerRegistry::class)))
            ->makeQueue($queueName, $events, $topic);

        self::assertInstanceOf(\RabbitEvents\Foundation\Contracts\Destination::class, $queue);
        self::assertCount(1, $connectionStub->createdQueues);
        self::assertEquals($queueName, $connectionStub->createdQueues[0]['name']);
        self::assertSame($topic, $connectionStub->createdQueues[0]['topic']);
    }

    public function test_create_producer()
    {
        $connectionStub = new \RabbitEvents\Tests\Foundation\Stubs\ConnectionStub();
        
        $context = new Context($connectionStub, m::mock(\RabbitEvents\Foundation\Serialization\SerializerRegistry::class));
        $producer = $context->createProducer();
        
        self::assertInstanceOf(\RabbitEvents\Foundation\Contracts\Producer::class, $producer);
        self::assertEquals(1, $connectionStub->createdProducers);
    }
    
    public function test_make_topic()
    {
        $connectionStub = new \RabbitEvents\Tests\Foundation\Stubs\ConnectionStub();
        
        $context = new Context($connectionStub, m::mock(\RabbitEvents\Foundation\Serialization\SerializerRegistry::class));
        $topic = $context->makeTopic();
        
        self::assertInstanceOf(\RabbitEvents\Foundation\Contracts\Destination::class, $topic);
        self::assertCount(1, $connectionStub->createdTopics);
    }


}
