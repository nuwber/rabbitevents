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
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Console\FailedCommand;
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

    public function test_displays_message_when_no_failed_events_found(): void
    {
        $app = $this->createMockApplication();

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')->with(500)->andReturn(null);

        $destination = m::mock(Destination::class);
        $topic = m::mock(Destination::class);
        $context = m::mock(Context::class);
        $context->shouldReceive('makeTopic')->andReturn($topic);
        $context->shouldReceive('makeQueue')->with('test-service:.failed', [], $topic)->andReturn($destination);
        $context->shouldReceive('makeConsumer')->with($destination)->andReturn($consumer);

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

        $message = m::mock(Message::class);
        $message->shouldReceive('id')->andReturn('uuid-1234');
        $message->shouldReceive('event')->andReturn('order.created');
        $message->shouldReceive('header')->with('x-failed-at')->andReturn('1700000000');
        $message->shouldReceive('header')->with('x-exception-message')->andReturn('Database timeout');

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')->with(500)->andReturn($message, null);
        $consumer->shouldReceive('reject')->with($message, true)->once();

        $destination = m::mock(Destination::class);
        $topic = m::mock(Destination::class);
        $context = m::mock(Context::class);
        $context->shouldReceive('makeTopic')->andReturn($topic);
        $context->shouldReceive('makeQueue')->with('custom-queue.failed', [], $topic)->andReturn($destination);
        $context->shouldReceive('makeConsumer')->with($destination)->andReturn($consumer);

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
    }
}
