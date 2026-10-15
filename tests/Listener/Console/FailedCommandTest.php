<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\Console;

use ArrayAccess;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Serialization\JsonSerializer;
use RabbitEvents\Foundation\Serialization\SerializerRegistry;
use RabbitEvents\Listener\Console\FailedCommand;
use RabbitEvents\Tests\Foundation\Stubs\ConnectionStub;
use RabbitEvents\Tests\Foundation\Stubs\ProducerStub;
use RabbitEvents\Tests\Foundation\Stubs\QueueConsumerStub;
use RabbitEvents\Tests\Foundation\Stubs\TransportMessageStub;
use RabbitEvents\Tests\Listener\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class FailedCommandTest extends TestCase
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

    private function createFailedTransportMessage(
        string $id,
        string $event = 'order.created',
        string $failedAt = '1700000000',
        string $exception = 'Database timeout'
    ): TransportMessageStub {
        return new TransportMessageStub(
            body: json_encode(['data' => 'test']),
            properties: [
                'message_id' => $id,
                'content_type' => 'application/json',
                'event' => $event,
                'routing_key' => $event,
                'headers' => [
                    'x-original-routing-key' => $event,
                    'x-failed-at' => $failedAt,
                    'x-exception-message' => $exception,
                ],
            ]
        );
    }

    public function test_displays_message_when_no_failed_events_found(): void
    {
        $app = $this->createMockApplication();

        [$context] = $this->createContext([]);
        $app->instance(Context::class, $context);

        $command = new FailedCommand();
        $command->setLaravel($app);

        $input = new ArrayInput([]);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $result = $command->handle();

        self::assertEquals(0, $result);
        self::assertStringContainsString('No failed messages found in [test-service:.failed].', $bufferedOutput->fetch());
    }

    public function test_displays_failed_messages_in_table_and_requeues_them(): void
    {
        $app = $this->createMockApplication();

        $transportMsg = $this->createFailedTransportMessage('uuid-1234', 'order.created', '1700000000', 'Database timeout');
        [$context, , $consumer] = $this->createContext([$transportMsg]);
        $app->instance(Context::class, $context);

        $command = new FailedCommand();
        $command->setLaravel($app);

        $input = new ArrayInput(['--queue' => 'custom-queue']);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $result = $command->handle();

        self::assertEquals(0, $result);
        $outputContent = $bufferedOutput->fetch();
        self::assertStringContainsString('uuid-1234', $outputContent);
        self::assertStringContainsString('order.created', $outputContent);
        self::assertStringContainsString('Database timeout', $outputContent);

        // Verify real message was requeued (rejected with requeue=true) so it stays in failed queue
        self::assertCount(1, $consumer->rejected);
        self::assertTrue($consumer->rejected[0]['requeue']);
    }
}
