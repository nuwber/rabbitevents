<?php

namespace Nuwber\Events\Event;

abstract class AbstractPublishableEvent implements ShouldPublish
{
    use Publishable;
}
