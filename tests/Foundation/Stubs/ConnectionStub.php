<?php

namespace RabbitEvents\Tests\Foundation\Stubs;

use RabbitEvents\Foundation\Contracts\Connection;
use RabbitEvents\Foundation\Contracts\Destination;
use RabbitEvents\Foundation\Contracts\Producer;
use RabbitEvents\Foundation\Contracts\QueueConsumer;

class ConnectionStub implements Connection
{
    public array $createdConsumers = [];
    public int $createdProducers = 0;
    public array $createdTopics = [];
    public array $createdQueues = [];
    
    public function createProducer(): Producer
    {
        $this->createdProducers++;
        return new ProducerStub();
    }

    public function makeConsumer(Destination $queue): QueueConsumer
    {
        $this->createdConsumers[] = $queue;
        return new QueueConsumerStub();
    }

    public function makeTopic(): Destination
    {
        $this->createdTopics[] = 'topic';
        return new DestinationStub();
    }

    public function makeQueue(string $queueName, array $events, Destination $topic): Destination
    {
        $this->createdQueues[] = [
            'name' => $queueName,
            'events' => $events,
            'topic' => $topic
        ];
        
        return new DestinationStub();
    }
}
