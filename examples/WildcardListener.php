<?php

class WildcardListener
{
    public function handler($event, $payload)
    {
        switch ($event) {
            case 'something.happened':
                return new Action($payload);
            case 'something.else':
                return new AnotherAction($payload);
            default:
                throw \Exception("Unknown event $event");
        }
    }

    public function middleware($event, $payload)
    {
        // Stops the propagation if return `false`
    }
}
