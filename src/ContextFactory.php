<?php

namespace Nuwber\Events;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Illuminate\Support\Arr;
use Interop\Amqp\AmqpContext;

class ContextFactory
{
    /**
     * Makes Context
     *
     * @param array $config
     * @return AmqpContext
     */
    public function make(array $config): AmqpContext
    {
        return $this->connect($config)->createContext();
    }

    /**
     * Make connector
     *
     * @param array $config
     * @return AmqpConnectionFactory
     */
    public function connect(array $config): AmqpConnectionFactory
    {
        return new AmqpConnectionFactory([
            'dsn' => Arr::get($config, 'dsn'),
            'host' => Arr::get($config, 'host', '127.0.0.1'),
            'port' => Arr::get($config, 'port', 5672),
            'user' => Arr::get($config, 'user', 'guest'),
            'pass' => Arr::get($config, 'pass', 'guest'),
            'vhost' => Arr::get($config, 'vhost', '/'),
            'ssl_on' => Arr::get($config, 'ssl_params.ssl_on', false),
            'ssl_verify' => Arr::get($config, 'ssl_params.verify_peer', true),
            'ssl_cacert' => Arr::get($config, 'ssl_params.cafile'),
            'ssl_cert' => Arr::get($config, 'ssl_params.local_cert'),
            'ssl_key' => Arr::get($config, 'ssl_params.local_key'),
            'ssl_passphrase' => Arr::get($config, 'ssl_params.passphrase'),
        ]);
    }
}
