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
use RabbitEvents\Listener\Console\ForgetCommand;
use RabbitEvents\Tests\Listener\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ForgetCommandTest extends TestCase
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

    public function test_forgets_message_by_id(): void
    {
        $app = $this->createMockApplication();

        $message = m::mock(Message::class);
        $message->shouldReceive('id')->andReturn('msg-to-delete');

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')->with(500)->andReturn($message);
        $consumer->shouldReceive('acknowledge')->with($message)->once();

        $topic = m::mock(Destination::class);
        $queueDestination = m::mock(Destination::class);

        $context = m::mock(Context::class);
        $context->shouldReceive('makeTopic')->andReturn($topic);
        $context->shouldReceive('makeQueue')->with('test-service:.failed', [], $topic)->andReturn($queueDestination);
        $context->shouldReceive('makeConsumer')->with($queueDestination)->andReturn($consumer);

        $app->instance(Context::class, $context);

        $command = new ForgetCommand();
        $command->setLaravel($app);

        $input = new ArrayInput(['id' => 'msg-to-delete']);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $result = $command->handle();

        self::assertEquals(0, $result);
        self::assertStringContainsString('Failed message [msg-to-delete] deleted successfully.', $bufferedOutput->fetch());
    }

    public function test_reports_error_when_message_not_found(): void
    {
        $app = $this->createMockApplication();

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')->with(500)->andReturn(null);

        $topic = m::mock(Destination::class);
        $queueDestination = m::mock(Destination::class);

        $context = m::mock(Context::class);
        $context->shouldReceive('makeTopic')->andReturn($topic);
        $context->shouldReceive('makeQueue')->with('test-service:.failed', [], $topic)->andReturn($queueDestination);
        $context->shouldReceive('makeConsumer')->with($queueDestination)->andReturn($consumer);

        $app->instance(Context::class, $context);

        $command = new ForgetCommand();
        $command->setLaravel($app);

        $input = new ArrayInput(['id' => 'nonexistent']);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $result = $command->handle();

        self::assertEquals(1, $result);
        self::assertStringContainsString('Unable to find failed message with ID [nonexistent]', $bufferedOutput->fetch());
    }
}
