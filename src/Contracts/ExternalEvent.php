<?php

namespace Butik\Events\Contracts;

/**
 * @author Sergey Kvartnikov <s.kvartnikov@butik.ru>
 *
 * Created at 15.11.2018
 */
interface ExternalEvent
{
    /**
     * @return array
     */
    public function getExternalPayload(): array;

    /**
     * @return string
     */
    public function getExternalEventName(): string;
}