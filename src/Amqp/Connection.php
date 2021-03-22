<?php

namespace Nuwber\Events\Amqp;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Enqueue\AmqpLib\AmqpContext;
use Enqueue\AmqpTools\DelayStrategy;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Illuminate\Support\Collection;
use Nuwber\Events\Queue\Context;
use Interop\Queue\Topic;

class Connection
{
    /**
     * @var Collection
     */
    private $config;

    /**
     * @var DelayStrategy
     */
    private $delayStrategy;

    /**
     * @var AmqpConnectionFactory
     */
    private $connection;

    public function __construct(array $config)
    {
        $this->config = collect($config);
    }

    /**
     * @return AmqpConnectionFactory
     */
    public function connect(): AmqpConnectionFactory
    {
        if (!$this->connection) {
            $this->connection = $this->makeFactory();
        }

        return $this->connection;
    }

    /**
     * @return Context
     */
    public function createContext(): Context
    {
        $context = $this->connect()->createContext();

        return new Context($context, $this->makeTopic($context));
    }

    protected function makeTopic(AmqpContext $context): Topic
    {
        return (new TopicFactory($context))->make($this->config->get('exchange'));
    }

    /**
     * @return AmqpConnectionFactory
     */
    protected function makeFactory(): AmqpConnectionFactory
    {
        $factory = new AmqpConnectionFactory([
            'dsn' => $this->config->get('dsn'),
            'host' => $this->config->get('host', '127.0.0.1'),
            'port' => $this->config->get('port', 5672),
            'user' => $this->config->get('user', 'guest'),
            'pass' => $this->config->get('pass', 'guest'),
            'vhost' => $this->config->get('vhost', '/'),
            'ssl_on' => $this->config->get('ssl.is_enabled', false),
            'ssl_verify' => $this->config->get('ssl.verify_peer', true),
            'ssl_cacert' => $this->config->get('ssl.cafile'),
            'ssl_cert' => $this->config->get('ssl.local_cert'),
            'ssl_key' => $this->config->get('ssl.local_key'),
            'ssl_passphrase' => $this->config->get('ssl.passphrase'),
        ]);

        $factory->setDelayStrategy($this->getDelayStrategy());

        return $factory;
    }

    /**
     * @param DelayStrategy $strategy
     * @return $this
     */
    public function setDelayStrategy(DelayStrategy $strategy): self
    {
        $this->delayStrategy = $strategy;

        return $this;
    }

    /**
     * @return DelayStrategy
     */
    public function getDelayStrategy(): DelayStrategy
    {
        if (!$this->delayStrategy) {
            if ($this->config->has('delay_strategy')) {
                $class = $this->config->get('delay_strategy');
            } else {
                $class = RabbitMqDlxDelayStrategy::class;
            }

            $this->delayStrategy = new $class();
        }

        return $this->delayStrategy;
    }
}
