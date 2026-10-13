<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\Console;

use ArrayAccess;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Mockery as m;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Contracts\Destination;
use RabbitEvents\Foundation\Contracts\Producer;
use RabbitEvents\Foundation\Contracts\TransportMessage;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Console\RetryCommand;
use RabbitEvents\Tests\Listener\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class RetryCommandTest extends TestCase
{
    private function createMockApplication(): Container
    {
        $app = new Container();
        $configObj = new class([
            'app.name' => 'test-service',
            'rabbitevents.default' => 'rabbitmq',
        ]) implements ArrayAccess {
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

        $transportMessage = m::mock(TransportMessage::class);
        $transportMessage->shouldReceive('setRoutingKey')->with('order.created')->once();

        $message = m::mock(Message::class);
        $message->shouldReceive('id')->andReturn('msg-123');
        $message->shouldReceive('event')->andReturn('order.created');
        $message->shouldReceive('header')->with('x-original-routing-key')->andReturn('order.created');
        $message->shouldReceive('transportMessage')->andReturn($transportMessage);

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')->with(500)->andReturn($message);
        $consumer->shouldReceive('acknowledge')->with($message)->once();

        $topic = m::mock(Destination::class);
        $queueDestination = m::mock(Destination::class);

        $producer = m::mock(Producer::class);
        $producer->shouldReceive('send')->with($topic, $transportMessage)->once();

        $context = m::mock(Context::class);
        $context->shouldReceive('makeTopic')->andReturn($topic);
        $context->shouldReceive('makeQueue')->with('test-service:.failed', [], $topic)->andReturn($queueDestination);
        $context->shouldReceive('makeConsumer')->with($queueDestination)->andReturn($consumer);
        $context->shouldReceive('createProducer')->andReturn($producer);

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
        self::assertStringContainsString('The failed message [msg-123] has been pushed back onto the exchange!', $bufferedOutput->fetch());
    }

    public function test_retries_all_messages(): void
    {
        $app = $this->createMockApplication();

        $transportMsg1 = m::mock(TransportMessage::class);
        $transportMsg1->shouldReceive('setRoutingKey')->with('order.created')->once();

        $msg1 = m::mock(Message::class);
        $msg1->shouldReceive('id')->andReturn('msg-1');
        $msg1->shouldReceive('event')->andReturn('order.created');
        $msg1->shouldReceive('header')->with('x-original-routing-key')->andReturn('order.created');
        $msg1->shouldReceive('transportMessage')->andReturn($transportMsg1);

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')->with(500)->andReturn($msg1, null);
        $consumer->shouldReceive('acknowledge')->with($msg1)->once();

        $topic = m::mock(Destination::class);
        $queueDestination = m::mock(Destination::class);

        $producer = m::mock(Producer::class);
        $producer->shouldReceive('send')->with($topic, $transportMsg1)->once();

        $context = m::mock(Context::class);
        $context->shouldReceive('makeTopic')->andReturn($topic);
        $context->shouldReceive('makeQueue')->with('test-service:.failed', [], $topic)->andReturn($queueDestination);
        $context->shouldReceive('makeConsumer')->with($queueDestination)->andReturn($consumer);
        $context->shouldReceive('createProducer')->andReturn($producer);

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
        self::assertStringContainsString('The failed message [msg-1] has been pushed back onto the exchange!', $outputContent);
        self::assertStringContainsString('Retried 1 failed message(s).', $outputContent);
    }
}
