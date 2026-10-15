<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

use RabbitEvents\Foundation\Contracts\Transport;

class Publisher
{
    /**
     * @var (callable(): mixed)|null
     */
    protected $transactionManagerResolver;

    public function __construct(
        private MessageFactory $messageFactory,
        private Transport $transport
    ) {
    }

    /**
     * Set the database transaction manager resolver implementation.
     *
     * @param (callable(): mixed)|null $resolver
     * @return $this
     */
    public function setTransactionManagerResolver(?callable $resolver): self
    {
        $this->transactionManagerResolver = $resolver;

        return $this;
    }

    /**
     * Get the database transaction manager implementation from the resolver.
     */
    protected function resolveTransactionManager(): mixed
    {
        return $this->transactionManagerResolver ? ($this->transactionManagerResolver)() : null;
    }

    /**
     * Determine if the event should be published after the database transaction commits.
     */
    protected function shouldPublishAfterCommit(ShouldPublish $event): bool
    {
        return ($event instanceof ShouldPublishAfterCommit) || ($event->afterCommit ?? false);
    }

    /**
     * Publish event.
     *
     * @param ShouldPublish $event
     * @return string|null
     */
    public function publish(ShouldPublish $event): ?string
    {
        if ($this->shouldPublishAfterCommit($event) && $transactions = $this->resolveTransactionManager()) {
            $transactions->addCallback(function () use ($event) {
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
