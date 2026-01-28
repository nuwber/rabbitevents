<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Serialization;

use RabbitEvents\Foundation\Contracts\Serializer;
use RabbitEvents\Foundation\Exceptions\UnsupportedContentTypeException;

class SerializerRegistry
{
    /**
     * @var array<string, Serializer>
     */
    private array $serializers = [];

    public function register(Serializer $serializer): void
    {
        $this->serializers[$serializer->contentType()] = $serializer;
    }

    /**
     * @param string $contentType
     * @return Serializer
     * @throws UnsupportedContentTypeException
     */
    public function get(string $contentType): Serializer
    {
        if (isset($this->serializers[$contentType])) {
            return $this->serializers[$contentType];
        }

        throw new UnsupportedContentTypeException("Unsupported content type: {$contentType}");
    }
}
