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
        $sslConfig = collect($this->config->get('ssl', []));
        $factory = new AmqpConnectionFactory([
            'dsn' => $this->config->get('dsn'),
            'host' => $this->config->get('host', '127.0.0.1'),
            'port' => $this->config->get('port', 5672),
            'user' => $this->config->get('user', 'guest'),
            'pass' => $this->config->get('pass', 'guest'),
            'vhost' => $this->config->get('vhost', '/'),
            'ssl_on' => $sslConfig->get('is_enabled', false),
            'ssl_verify' => $sslConfig->get('verify_peer', true),
            'ssl_cacert' => $sslConfig->get('cafile'),
            'ssl_cert' => $sslConfig->get('local_cert'),
            'ssl_key' => $sslConfig->get('local_key'),
            'ssl_passphrase' => $sslConfig->get('passphrase'),
            'read_timeout' => $this->config->get('read_timeout', 3.),
            'write_timeout' => $this->config->get('write_timeout', 3.),
            'connection_timeout' => $this->config->get('connection_timeout', 3.),
            'heartbeat' => $this->config->get('heartbeat', 0),
            'persisted' => $this->config->get('persisted', false),
            'lazy' => $this->config->get('lazy', true),
            'keepalive' => $this->config->get('keepalive', false),
            'qos_prefetch_count' => $this->config->get('qos_prefetch_count', 1),
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
