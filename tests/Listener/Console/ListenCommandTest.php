<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\Console;

use ArrayAccess;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher as LaravelDispatcher;
use Mockery as m;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Contracts\Destination;
use RabbitEvents\Foundation\Contracts\Producer;
use RabbitEvents\Foundation\Contracts\QueueConsumer;
use RabbitEvents\Listener\Console\ListenCommand;
use RabbitEvents\Listener\Dispatcher;
use RabbitEvents\Listener\Worker;
use RabbitEvents\Listener\WorkerExitStatus;
use RabbitEvents\Tests\Listener\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ListenCommandTest extends TestCase
{
    public function test_aborts_when_no_listeners_registered_and_no_arguments_passed(): void
    {
        $app = $this->createMockApplication();
        $dispatcher = new Dispatcher($app);
        $app->instance(Dispatcher::class, $dispatcher);

        $command = new ListenCommand();
        $command->setLaravel($app);

        $input = new ArrayInput([]);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $context = m::mock(Context::class);
        $worker = m::mock(Worker::class);

        $result = $command->handle($context, $worker);

        self::assertEquals(WorkerExitStatus::ERROR, $result);
        self::assertStringContainsString('No RabbitEvents listeners registered in the application', $bufferedOutput->fetch());
    }

    public function test_aborts_when_non_existent_event_argument_provided(): void
    {
        $app = $this->createMockApplication();
        $dispatcher = new Dispatcher($app);
        $dispatcher->listen('user.registered', static function () {});
        $app->instance(Dispatcher::class, $dispatcher);

        $command = new ListenCommand();
        $command->setLaravel($app);

        $input = new ArrayInput(['events' => 'non.existent.event']);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $context = m::mock(Context::class);
        $worker = m::mock(Worker::class);

        $result = $command->handle($context, $worker);

        self::assertEquals(WorkerExitStatus::ERROR, $result);
        $outputContent = $bufferedOutput->fetch();
        self::assertStringContainsString('No listeners registered for event(s): non.existent.event', $outputContent);
        self::assertStringContainsString('Available registered events: user.registered', $outputContent);
    }

    public function test_warns_on_partial_missing_events_but_proceeds(): void
    {
        $app = $this->createMockApplication();
        $dispatcher = new Dispatcher($app);
        $dispatcher->listen('user.registered', static function () {});
        $app->instance(Dispatcher::class, $dispatcher);

        $command = new ListenCommand();
        $command->setLaravel($app);

        $input = new ArrayInput(['events' => 'user.registered,missing.event']);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $context = m::mock(Context::class);
        $worker = m::mock(Worker::class);
        $destination = m::mock(Destination::class);
        $consumer = m::mock(\RabbitEvents\Foundation\Consumer::class);
        $producer = m::mock(Producer::class);

        $context->shouldReceive('makeTopic')->once()->andReturn($destination);
        $context->shouldReceive('makeQueue')->once()->andReturn($destination);
        $context->shouldReceive('createProducer')->once()->andReturn($producer);
        $context->shouldReceive('makeConsumer')->once()->andReturn($consumer);

        $worker->shouldReceive('work')->once()->andReturn(WorkerExitStatus::SUCCESS);

        $result = $command->handle($context, $worker);

        self::assertEquals(WorkerExitStatus::SUCCESS, $result);
        $outputContent = $bufferedOutput->fetch();
        self::assertStringContainsString('No listeners registered for event(s): missing.event', $outputContent);
    }

    public function test_matches_wildcard_registered_listeners(): void
    {
        $app = $this->createMockApplication();
        $dispatcher = new Dispatcher($app);
        $dispatcher->listen('user.*', static function () {});
        $app->instance(Dispatcher::class, $dispatcher);

        $command = new ListenCommand();
        $command->setLaravel($app);

        $input = new ArrayInput(['events' => 'user.created']);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $context = m::mock(Context::class);
        $worker = m::mock(Worker::class);
        $destination = m::mock(Destination::class);
        $consumer = m::mock(\RabbitEvents\Foundation\Consumer::class);
        $producer = m::mock(Producer::class);

        $context->shouldReceive('makeTopic')->once()->andReturn($destination);
        $context->shouldReceive('makeQueue')->once()->andReturn($destination);
        $context->shouldReceive('createProducer')->once()->andReturn($producer);
        $context->shouldReceive('makeConsumer')->once()->andReturn($consumer);

        $worker->shouldReceive('work')->once()->andReturn(WorkerExitStatus::SUCCESS);

        $result = $command->handle($context, $worker);

        self::assertEquals(WorkerExitStatus::SUCCESS, $result);
        self::assertStringNotContainsString('No listeners registered', $bufferedOutput->fetch());
    }

    private function createMockApplication(): Container
    {
        $app = new Container();
        $configObj = new class([
            'app.name' => 'test-service',
            'rabbitevents.default' => 'rabbitmq',
            'rabbitevents.logging.enabled' => false,
        ]) implements ArrayAccess {
            /** @param array<string, mixed> $items */
            public function __construct(private array $items) {}
            public function offsetExists($offset): bool { return isset($this->items[$offset]); }
            public function offsetGet($offset): mixed { return $this->items[$offset] ?? null; }
            public function offsetSet($offset, $value): void { $this->items[$offset] = $value; }
            public function offsetUnset($offset): void { unset($this->items[$offset]); }
            public function get(string $key, mixed $default = null): mixed { return $this->items[$key] ?? $default; }
        };

        $app->instance('config', $configObj);
        $app->instance('events', new LaravelDispatcher($app));

        return $app;
    }
}
