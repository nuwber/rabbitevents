<?php

namespace RabbitEvents\Listener;

class QueueName
{
    public static function resolve(string $prefix, array $events): string
    {
        $name = $prefix . ':' . implode(',', $events);

        if (mb_strlen($name, 'ASCII') > 200) {
            $name = $prefix . ':' . md5($name);
        }

        return $name;
    }
}
