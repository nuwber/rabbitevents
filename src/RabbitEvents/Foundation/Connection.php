<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation;

use Interop\Amqp\AmqpContext;
use Enqueue\AmqpLib\AmqpConnectionFactory;
use Enqueue\AmqpTools\DelayStrategy;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Illuminate\Support\Arr;

class Connection
{
    private array $config;

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
        $this->config = $config;
    }

    /**
     * @return AmqpConnectionFactory
     */
    public function connect(): AmqpConnectionFactory
    {
        if (!$this->connection) {
            $this->connection = $this->factory();
        }

        return $this->connection;
    }

    /**
     * @return AmqpContext
     */
    public function createContext(): AmqpContext
    {
        return $this->connect()->createContext();
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
            $class = $this->getConfig('delay_strategy', RabbitMqDlxDelayStrategy::class);

            $this->delayStrategy = new $class();
        }

        return $this->delayStrategy;
    }

    public function getConfig($key = null, $default = null): mixed
    {
        if (!is_null($key)) {
            return Arr::get($this->config, $key, $default);
        }

        return $this->config;
    }

    /**
     * @return AmqpConnectionFactory
     */
    protected function factory(): AmqpConnectionFactory
    {
        $factory = new AmqpConnectionFactory([
            'dsn' => $this->getConfig('dsn'),
            'host' => $this->getConfig('host', '127.0.0.1'),
            'port' => $this->getConfig('port', 5672),
            'user' => $this->getConfig('user', 'guest'),
            'pass' => $this->getConfig('pass', 'guest'),
            'vhost' => $this->getConfig('vhost', '/'),
            'ssl_on' => $this->getConfig('ssl.is_enabled', false),
            'ssl_verify' => $this->getConfig('ssl.verify_peer', true),
            'ssl_cacert' => $this->getConfig('ssl.cafile'),
            'ssl_cert' => $this->getConfig('ssl.local_cert'),
            'ssl_key' => $this->getConfig('ssl.local_key'),
            'ssl_passphrase' => $this->getConfig('ssl.passphrase'),
            'read_timeout' => $this->getConfig('read_timeout', 3.),
            'write_timeout' => $this->getConfig('write_timeout', 3.),
            'connection_timeout' => $this->getConfig('connection_timeout', 3.),
            'heartbeat' => $this->getConfig('heartbeat', 0),
            'persisted' => $this->getConfig('persisted', false),
            'lazy' => $this->getConfig('lazy', true),
            'qos_global' => $this->getConfig('qos.global', false),
            'qos_prefetch_size' => $this->getConfig('qos.prefetch_size', 0),
            'qos_prefetch_count' => $this->getConfig('qos.prefetch_count', 1),
        ]);

        $factory->setDelayStrategy($this->getDelayStrategy());

        return $factory;
    }
}
