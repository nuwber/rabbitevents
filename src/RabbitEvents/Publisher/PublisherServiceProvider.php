<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

use Illuminate\Support\ServiceProvider;
use RabbitEvents\Foundation\Support\Sender;
use RabbitEvents\Publisher\Commands\ObserverMakeCommand;
use RabbitEvents\Foundation\Context;

class PublisherServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->singleton(
            Publisher::class,
            static fn($app) => new Publisher(
                new MessageFactory(),
                new Sender($app[Context::class]->makeTopic(), $app[Context::class]->createProducer())
            )
        );
    }

    public function register(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([ObserverMakeCommand::class]);
    }
}
