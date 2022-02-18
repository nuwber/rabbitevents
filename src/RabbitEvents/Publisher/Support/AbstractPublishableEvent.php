<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher\Support;

use RabbitEvents\Publisher\ShouldPublish;

abstract class AbstractPublishableEvent implements ShouldPublish
{
    use Publishable;
}
