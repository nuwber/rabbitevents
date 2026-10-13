<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Support;

class Uuid
{
    public static function generate(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // UUID version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // RFC 4122 variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
