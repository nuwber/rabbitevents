<?php
/**
 *
 */

namespace Butik\Events\Traits;

/**
 * @author Sergey Kvartnikov <s.kvartnikov@butik.ru>
 *
 * Created at 15.11.2018
 */
trait ClassAsEventName
{
    /**
     * @return string
     * @throws \ReflectionException
     */
    public function getExternalEventName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }
}