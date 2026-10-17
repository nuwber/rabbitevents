<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\Console;

use RabbitEvents\Foundation\Context;
use RabbitEvents\Listener\Console\FailedCommand;

class FailedCommandTest extends FailedQueueTestCase
{
    public function test_displays_message_when_no_failed_events_found(): void
    {
        $app = $this->createMockApplication();
        [$context] = $this->createContext([]);
        $app->instance(Context::class, $context);

        [$result, $output] = $this->runCommand(new FailedCommand(), $app);

        self::assertEquals(0, $result);
        self::assertStringContainsString('No failed messages found in [test-service:.failed].', $output);
    }

    public function test_displays_failed_messages_in_table_and_requeues_them(): void
    {
        $app = $this->createMockApplication();
        $transportMsg = $this->createFailedTransportMessage('uuid-1234', 'order.created', null, '1700000000', 'Database timeout');
        [$context, , $consumer] = $this->createContext([$transportMsg]);
        $app->instance(Context::class, $context);

        [$result, $outputContent] = $this->runCommand(new FailedCommand(), $app, ['--queue' => 'custom-queue']);

        self::assertEquals(0, $result);
        self::assertStringContainsString('uuid-1234', $outputContent);
        self::assertStringContainsString('order.created', $outputContent);
        self::assertStringContainsString('Database timeout', $outputContent);

        self::assertCount(1, $consumer->rejected);
        self::assertTrue($consumer->rejected[0]['requeue']);
    }
}
