<?php

class ExampleMiddleware
{
    public function handle($payload)
    {
        return \Arr::get($payload, 'entity.type') === 'mytype';
    }
}
