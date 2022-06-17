<?php

namespace RabbitEvents\Tests\Listener\Stubs;

class ListenerStubForMiddleware
{
    protected ?array $middlewarePayload = null;

    public function handle(): ?array
    {
        return $this->middlewarePayload;
    }

    public function middleware(): void
    {
        $this->middlewarePayload = func_get_args();
    }

    public function __invoke(): mixed
    {
        return func_get_args();
    }
}
