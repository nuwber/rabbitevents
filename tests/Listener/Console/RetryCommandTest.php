<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\Console;

use ArrayAccess;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Serialization\JsonSerializer;
use RabbitEvents\Foundation\Serialization\SerializerRegistry;
use RabbitEvents\Listener\Console\RetryCommand;
use RabbitEvents\Tests\Foundation\Stubs\ConnectionStub;
use RabbitEvents\Tests\Foundation\Stubs\ProducerStub;
use RabbitEvents\Tests\Foundation\Stubs\QueueConsumerStub;
use RabbitEvents\Tests\Foundation\Stubs\TransportMessageStub;
use RabbitEvents\Tests\Listener\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class RetryCommandTest extends TestCase
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
        ?string $originalQueue = null
    ): TransportMessageStub {
        $headers = [
            'x-original-routing-key' => $event,
            'x-failed-at' => (string) time(),
            'x-exception-message' => 'Something failed',
        ];

        if ($originalQueue) {
            $headers['x-death'] = [['queue' => $originalQueue, 'reason' => 'rejected']];
        }

        return new TransportMessageStub(
            body: json_encode(['data' => 'test']),
            properties: [
                'message_id' => $id,
                'content_type' => 'application/json',
                'event' => $event,
                'routing_key' => $event,
                'x-attempts' => 3,
                'headers' => $headers,
            ]
        );
    }

    public function test_requires_id_or_all_flag(): void
    {
        $app = $this->createMockApplication();
        $command = new RetryCommand();
        $command->setLaravel($app);

        $input = new ArrayInput([]);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $result = $command->handle();

        self::assertEquals(1, $result);
        self::assertStringContainsString('Please specify the message ID to retry or pass the --all option.', $bufferedOutput->fetch());
    }

    public function test_retries_single_message_by_id(): void
    {
        $app = $this->createMockApplication();

        $transportMsg = $this->createFailedTransportMessage('msg-123', 'order.created');
        [$context, $producer, $consumer] = $this->createContext([$transportMsg]);
        $app->instance(Context::class, $context);

        $command = new RetryCommand();
        $command->setLaravel($app);

        $input = new ArrayInput(['id' => 'msg-123']);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $result = $command->handle();

        self::assertEquals(0, $result);
        self::assertStringContainsString('The failed message [msg-123] has been pushed back onto the queue!', $bufferedOutput->fetch());

        self::assertCount(1, $producer->sent);
        self::assertEquals('test-service:', $producer->sent[0]['destination']->getName());
        self::assertEquals(0, $producer->sent[0]['message']->getProperty('x-attempts'));
        self::assertEquals('order.created', $producer->sent[0]['message']->getRoutingKey());
        self::assertCount(1, $consumer->acknowledged);
    }

    public function test_retries_all_messages(): void
    {
        $app = $this->createMockApplication();

        $msg1 = $this->createFailedTransportMessage('msg-1', 'order.created');
        $msg2 = $this->createFailedTransportMessage('msg-2', 'user.registered');
        [$context, $producer, $consumer] = $this->createContext([$msg1, $msg2]);
        $app->instance(Context::class, $context);

        $command = new RetryCommand();
        $command->setLaravel($app);

        $input = new ArrayInput(['--all' => true]);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $result = $command->handle();

        self::assertEquals(0, $result);
        $outputContent = $bufferedOutput->fetch();
        self::assertStringContainsString('The failed message [msg-1] has been pushed back onto the queue!', $outputContent);
        self::assertStringContainsString('The failed message [msg-2] has been pushed back onto the queue!', $outputContent);
        self::assertStringContainsString('Retried 2 failed message(s).', $outputContent);

        self::assertCount(2, $producer->sent);
        self::assertCount(2, $consumer->acknowledged);
    }

    public function test_retries_message_to_queue_extracted_from_x_death(): void
    {
        $app = $this->createMockApplication();

        $transportMsg = $this->createFailedTransportMessage('msg-999', 'order.created', 'custom-worker-queue');
        [$context, $producer, $consumer] = $this->createContext([$transportMsg]);
        $app->instance(Context::class, $context);

        $command = new RetryCommand();
        $command->setLaravel($app);

        $input = new ArrayInput(['id' => 'msg-999']);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $result = $command->handle();

        self::assertEquals(0, $result);
        self::assertStringContainsString('The failed message [msg-999] has been pushed back onto the queue!', $bufferedOutput->fetch());

        self::assertCount(1, $producer->sent);
        self::assertEquals('custom-worker-queue', $producer->sent[0]['destination']->getName());
        self::assertEquals(0, $producer->sent[0]['message']->getProperty('x-attempts'));
        self::assertCount(1, $consumer->acknowledged);
    }

    public function test_reports_error_when_message_id_not_found(): void
    {
        $app = $this->createMockApplication();

        $msg1 = $this->createFailedTransportMessage('msg-1', 'order.created');
        [$context, $producer, $consumer] = $this->createContext([$msg1]);
        $app->instance(Context::class, $context);

        $command = new RetryCommand();
        $command->setLaravel($app);

        $input = new ArrayInput(['id' => 'nonexistent-id']);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $result = $command->handle();

        self::assertEquals(1, $result);
        self::assertStringContainsString('Failed message [nonexistent-id] not found.', $bufferedOutput->fetch());
        self::assertCount(0, $producer->sent);
        self::assertCount(1, $consumer->rejected);
    }
}
