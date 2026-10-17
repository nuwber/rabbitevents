<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

interface Connection
{
    public function createProducer(): Producer;

    public function makeConsumer(Destination $queue): QueueConsumer;

    public function makeTopic(?string $name = null): Destination;

    public function makeQueue(string $queueName, array $events, Destination $topic): Destination;

    public function getDeduplicationStrategy(): ?Deduplicator;
}
