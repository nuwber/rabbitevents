<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Foundation\Support;

use PHPUnit\Framework\TestCase;
use RabbitEvents\Foundation\Support\Uuid;

class UuidTest extends TestCase
{
    public function testGenerateValidUuidV4(): void
    {
        $uuid1 = Uuid::generate();
        $uuid2 = Uuid::generate();

        self::assertNotEquals($uuid1, $uuid2);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid1
        );
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid2
        );
    }
}
