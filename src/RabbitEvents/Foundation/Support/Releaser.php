<?php

namespace RabbitEvents\Foundation\Support;

use Illuminate\Support\InteractsWithTime;
use Interop\Queue\Exception\DeliveryDelayNotSupportedException;
use RabbitEvents\Foundation\Contracts\DelaysDelivery;

class Releaser extends Sender implements DelaysDelivery
{
    use InteractsWithTime;

    public function setDelay(int $delay = 0): void
    {
        try {
            $this->producer->setDeliveryDelay($this->secondsUntil($delay) * 1000);
        } catch (DeliveryDelayNotSupportedException $e) {
        }
    }
}
