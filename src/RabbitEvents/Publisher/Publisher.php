<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\DatabaseTransactionsManager;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Publisher\Support\AfterCommitMessageInterface;

class Publisher
{
    public function __construct(
        private MessageFactory $messageFactory,
        private Transport      $transport
    )
    {
    }


    /**
     * @throws BindingResolutionException
     */
    public function publish(ShouldPublish $event): void
    {
        $manager = $this->resolveTransactionManager();
        if ($event instanceof AfterCommitMessageInterface && !is_null($manager)) {
            /** @var ShouldPublish $event */
            $manager->addCallback(
                fn() => $this->send($event)
            );
        } else {
            $this->send($event);
        }
    }

    /**
     * Get the database transaction manager implementation from the resolver.
     *
     * @return DatabaseTransactionsManager|null
     * @throws BindingResolutionException
     */
    protected function resolveTransactionManager(): ?DatabaseTransactionsManager
    {
        return Container::getInstance()->bound('db.transactions')
            ? Container::getInstance()->make('db.transactions')
            : null;
    }

    protected function send(ShouldPublish $event): void
    {
        $this->transport->send(
            $this->messageFactory->make($event)
        );
    }
}
