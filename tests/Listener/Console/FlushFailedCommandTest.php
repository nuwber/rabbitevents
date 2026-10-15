<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\Console;

use ArrayAccess;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Serialization\JsonSerializer;
use RabbitEvents\Foundation\Serialization\SerializerRegistry;
use RabbitEvents\Listener\Console\FlushFailedCommand;
use RabbitEvents\Tests\Foundation\Stubs\ConnectionStub;
use RabbitEvents\Tests\Foundation\Stubs\ProducerStub;
use RabbitEvents\Tests\Foundation\Stubs\QueueConsumerStub;
use RabbitEvents\Tests\Foundation\Stubs\TransportMessageStub;
use RabbitEvents\Tests\Listener\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class FlushFailedCommandTest extends TestCase
{
    private function createMockApplication(array $config = []): Container
    {
        $app = new Container();
        $configObj = new class(array_merge([
            'app.name' => 'test-service',
            'rabbitevents.default' => 'rabbitmq',
        ], $config)) implements ArrayAccess {
            public function __construct(private array $items) {}
            public function offsetExists($offset): bool { return isset($this->items[$offset]); }
            public function offsetGet($offset): mixed { return $this->items[$offset] ?? null; }
            public function offsetSet($offset, $value): void { $this->items[$offset] = $value; }
            public function offsetUnset($offset): void { unset($this->items[$offset]); }
            public function get(string $key, mixed $default = null): mixed { return $this->items[$key] ?? $default; }
        };

        $app->instance('config', $configObj);

        return $app;
    }

    private function createContext(array $messages = []): array
    {
        $consumer = new QueueConsumerStub($messages);
        $producer = new ProducerStub();

        $connection = new ConnectionStub();
        $connection->consumer = $consumer;
        $connection->producer = $producer;

        $registry = new SerializerRegistry();
        $registry->register(new JsonSerializer());

        $context = new Context($connection, $registry);

        return [$context, $producer, $consumer];
    }

    private function createFailedTransportMessage(string $id, string $event = 'order.created'): TransportMessageStub
    {
        return new TransportMessageStub(
            body: json_encode(['data' => 'test']),
            properties: [
                'message_id' => $id,
                'content_type' => 'application/json',
                'event' => $event,
                'routing_key' => $event,
                'headers' => [
                    'x-original-routing-key' => $event,
                    'x-failed-at' => (string) time(),
                    'x-exception-message' => 'Something failed',
                ],
            ]
        );
    }

    public function test_flushes_all_failed_messages(): void
    {
        $app = $this->createMockApplication();

        $msg1 = $this->createFailedTransportMessage('msg-1');
        $msg2 = $this->createFailedTransportMessage('msg-2');
        [$context, , $consumer] = $this->createContext([$msg1, $msg2]);
        $app->instance(Context::class, $context);

        $command = new FlushFailedCommand();
        $command->setLaravel($app);

        $input = new ArrayInput([]);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $result = $command->handle();

        self::assertEquals(0, $result);
        self::assertStringContainsString('All [2] failed messages deleted from [test-service:.failed].', $bufferedOutput->fetch());
        self::assertCount(2, $consumer->acknowledged);
    }
}
