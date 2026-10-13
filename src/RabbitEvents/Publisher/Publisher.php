<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

use Illuminate\Container\Container;
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
     * @return string|null
     */
    public function publish(ShouldPublish $event): ?string
    {
        $container = Container::getInstance();
        if (isset($event->afterCommit) && $event->afterCommit && $container && $container->bound('db')) {
            $container->make('db')->afterCommit(function () use ($event) {
                $this->executePublish($event);
            });

            return null;
        }

        return $this->executePublish($event);
    }

    protected function executePublish(ShouldPublish $event): ?string
    {
        $message = $this->messageFactory->make($event);

        $this->transport->send($message);

        return $message->id();
    }
}
