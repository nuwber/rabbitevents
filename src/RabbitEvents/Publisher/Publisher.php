<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

use RabbitEvents\Foundation\Contracts\Transport;

class Publisher
{
    public function __construct(
        private MessageFactory $messageFactory,
        private Transport $transport
    ) {
    }

    /**
     * Publish event to
     *
     * @param ShouldPublish $event
     * @return void
     */
    public function publish(ShouldPublish $event): void
    {
        $this->transport->send(
            $this->messageFactory->make($event)
        );
    }
}
