<?php
namespace Nuwber\Events\Event;

interface ShouldPublish
{
    /**
     * Event name that the same as RammitMQ's routing key. Example: `item.created`.
     *
     * @return string
     */
    public function publishEventKey(): string;

    /**
     * Payload to be published.
     * You can not to send serialized models because you haven't this model on the listener side (I hope).
     * Remember: This payload will be passed to `call_user_func_array` so each variable you want to be passed
     * as a separate argument should be a separate array element.
     *
     * @return array
     */
    public function toPublish(): array;
}
