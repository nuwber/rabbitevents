<?php

namespace RabbitEvents\Foundation\Contracts;

interface DelaysDelivery
{
    public function setDelay(int $delay = 0): void;
}
