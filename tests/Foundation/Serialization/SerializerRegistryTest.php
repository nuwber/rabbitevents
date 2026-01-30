<?php

namespace RabbitEvents\Tests\Foundation\Serialization;

use Mockery;
use PHPUnit\Framework\TestCase;
use RabbitEvents\Foundation\Contracts\ContentType;
use RabbitEvents\Foundation\Contracts\Serializer;
use RabbitEvents\Foundation\Serialization\SerializerRegistry;

class SerializerRegistryTest extends TestCase
{
    private function mockSerializer(string $contentType)
    {
        $type = Mockery::mock(ContentType::class);
        $type->shouldReceive('__toString')->andReturn($contentType);
        $type->shouldReceive('getValue')->andReturn($contentType);

        $serializer = Mockery::mock(Serializer::class);
        $serializer->shouldReceive('contentType')->andReturn($type);

        return $serializer;
    }

    public function testRegisterAndGet()
    {
        $serializer = $this->mockSerializer('application/json');

        $registry = new SerializerRegistry();
        $registry->register($serializer);

        $this->assertSame($serializer, $registry->get('application/json'));
    }

    public function testGetDefaultExplicit()
    {
        $s1 = $this->mockSerializer('application/json');
        $s2 = $this->mockSerializer('application/xml');

        $registry = new SerializerRegistry();
        $registry->register($s1, true);
        $registry->register($s2);

        $this->assertSame($s1, $registry->getDefault());
    }

    public function testGetDefaultImplicitSingle()
    {
        $s1 = $this->mockSerializer('application/json');

        $registry = new SerializerRegistry();
        $registry->register($s1);

        $this->assertSame($s1, $registry->getDefault());
    }

    public function testGetDefaultThrowsExceptionIfAmbiguous()
    {
        $s1 = $this->mockSerializer('application/json');
        $s2 = $this->mockSerializer('application/xml');

        $registry = new SerializerRegistry();
        $registry->register($s1);
        $registry->register($s2);

        $this->expectException(\RuntimeException::class);
        $registry->getDefault();
    }
}
