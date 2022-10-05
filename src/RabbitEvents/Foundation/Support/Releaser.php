<?php

namespace RabbitEvents\Foundation\Support;

use Interop\Queue\Exception\DeliveryDelayNotSupportedException;
use RabbitEvents\Foundation\Contracts\DelaysDelivery;

class Releaser extends Sender implements DelaysDelivery
{
    public function setDelay(int $delay = 0): void
    {
        try {
            $this->producer->setDeliveryDelay($this->secondsUntil($delay) * 1000);
        } catch (DeliveryDelayNotSupportedException $e) {
        }
    }
}
