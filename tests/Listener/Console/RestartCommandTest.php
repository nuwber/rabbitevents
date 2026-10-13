<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\Console;

use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Mockery as m;
use RabbitEvents\Listener\Console\RestartCommand;
use RabbitEvents\Tests\Listener\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class RestartCommandTest extends TestCase
{
    public function test_broadcasts_restart_signal_to_cache(): void
    {
        $app = new Container();
        $cache = m::mock(\Illuminate\Contracts\Cache\Repository::class);
        $cache->shouldReceive('forever')->with('rabbitevents:restart', m::type('int'))->once();
        $app->instance('cache', $cache);

        $command = new RestartCommand();
        $command->setLaravel($app);

        $input = new ArrayInput([]);
        $bufferedOutput = new BufferedOutput();
        $output = new OutputStyle($input, $bufferedOutput);

        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput($output);

        $result = $command->handle();

        self::assertEquals(0, $result);
        self::assertStringContainsString('Broadcasting RabbitEvents worker restart signal.', $bufferedOutput->fetch());
    }
}
