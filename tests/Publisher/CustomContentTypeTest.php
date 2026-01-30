<?php

namespace RabbitEvents\Tests\Publisher;

use Mockery;
use RabbitEvents\Foundation\Contracts\Serializer;
use RabbitEvents\Publisher\MessageFactory;
use RabbitEvents\Publisher\ShouldPublish;
use RabbitEvents\Foundation\Serialization\SerializerRegistry;
use RabbitEvents\Tests\Publisher\TestCase;

class CustomContentTypeTest extends TestCase
{
    public function testMakeMessageWithCustomContentType()
    {
        $event = new class implements ShouldPublish {
            public function publishEventKey(): string
            {
                return 'test.event';
            }

            public function toPublish(): mixed
            {
                return 'custom_payload';
            }
        };

        // We returns a dummy payload that has the custom contentType
        $payload = Mockery::mock(\RabbitEvents\Foundation\Contracts\Payload::class);
        $payload->shouldReceive('contentType')->andReturn(new class implements \RabbitEvents\Foundation\Contracts\ContentType {
            public function __toString(): string
            {
                return 'application/x-custom';
            }
            public function getValue(): string
            {
                return 'application/x-custom';
            }
        });
        $payload->shouldReceive('value')->andReturn('custom_value');
        $payload->shouldReceive('serialize')->andReturn('serialized_custom');

        $serializer = Mockery::mock(Serializer::class);
        $serializer->shouldReceive('canSerialize')
             ->with('custom_payload')
             ->andReturn(true);
        $serializer->shouldReceive('serialize')
            ->with('custom_payload')
            ->andReturn($payload);
        $serializer->shouldReceive('contentType')->andReturn(new class implements \RabbitEvents\Foundation\Contracts\ContentType {
            public function __toString(): string
            {
                return 'application/x-custom';
            }
            public function getValue(): string
            {
                return 'application/x-custom';
            }
        });

        $registry = new SerializerRegistry();
        $registry->register($serializer);

        $factory = new MessageFactory($registry);
        $message = $factory->make($event);

        $this->assertEquals('test.event', $message->getRoutingKey());
        $this->assertEquals('serialized_custom', $message->getBody());
        $this->assertEquals('application/x-custom', $message->getProperty('content_type'));
    }
}
