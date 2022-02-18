<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

class Publisher
{
    public function __construct(private MessageFactory $messageFactory)
    {
    }

    /**
     * Publish event to
     *
     * @param ShouldPublish $event
     * @return void
     */
    public function publish(ShouldPublish $event): void
    {
        $this->messageFactory->make($event)->send();
    }
}
