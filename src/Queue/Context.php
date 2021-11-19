<?php

namespace Nuwber\Events\Queue;

use Interop\Queue\Topic;
use Interop\Amqp\AmqpContext;
use Nuwber\Events\Queue\Message\Sender;
use Nuwber\Events\Queue\Message\Transport;

/**
 * @mixin \Enqueue\AmqpLib\AmqpContext
 */
class Context
{
    /**
     * @var AmqpContext
     */
    private $context;

    /**
     * @var Transport
     */
    private $transport;

    /**
     * @var Topic
     */
    private $topic;

    public function __construct(AmqpContext $context, Topic $topic)
    {
        $this->context = $context;
        $this->topic = $topic;
    }

    public function __call(string $name, ?array $args)
    {
        return $this->context->$name(...$args);
    }

    /**
     * @return Transport
     */
    public function transport(): Transport
    {
        if (!$this->transport) {
            $this->transport = new Sender(
                $this->context->createProducer(),
                $this->topic()
            );
        }

        return $this->transport;
    }

    /**
     * @return Topic
     */
    public function topic(): Topic
    {
        return $this->topic;
    }

    /**
     * @param Transport $transport
     * @return $this
     */
    public function setTransport(Transport $transport): Context
    {
        $this->transport = $transport;

        return $this;
    }
}
