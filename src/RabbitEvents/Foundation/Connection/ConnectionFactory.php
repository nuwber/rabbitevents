<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Connection;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use RabbitEvents\Foundation\Amqp\Connection as AmqpConnection;
use RabbitEvents\Foundation\Contracts\Connection;

class ConnectionFactory
{
    /**
     * The array of custom driver creators.
     *
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    public function __construct(protected ?Container $container = null)
    {
    }

    /**
     * Create a new connection instance.
     *
     * @param array $config
     * @return Connection
     *
     * @throws InvalidArgumentException
     */
    public function make(array $config): Connection
    {
        $driver = $config['driver'] ?? 'rabbitmq';

        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($config, $this->container);
        }

        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        throw new InvalidArgumentException("Unsupported transport connection driver [{$driver}].");
    }

    /**
     * Create a new RabbitMQ (AMQP) connection instance.
     *
     * @param array $config
     * @return Connection
     */
    protected function createRabbitmqDriver(array $config): Connection
    {
        return new AmqpConnection($config);
    }

    /**
     * Create a new AMQP connection instance (alias for rabbitmq).
     *
     * @param array $config
     * @return Connection
     */
    protected function createAmqpDriver(array $config): Connection
    {
        return $this->createRabbitmqDriver($config);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string $driver
     * @param Closure $callback
     * @return $this
     */
    public function extend(string $driver, Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }
}
