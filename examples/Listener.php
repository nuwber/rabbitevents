<?php

class Listener
{
    public $middleware = [
       ExampleMiddleware::class
    ];

    public function handle($payload)
    {
        SomeModel::create(Arr::only(['item1', 'item2', 'item3']));
    }
}
