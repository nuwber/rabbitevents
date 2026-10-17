<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\Console;

use RabbitEvents\Foundation\Context;
use RabbitEvents\Listener\Console\ForgetCommand;

class ForgetCommandTest extends FailedQueueTestCase
{
    public function test_forgets_message_by_id(): void
    {
        $app = $this->createMockApplication();
        $transportMsg = $this->createFailedTransportMessage('msg-to-delete');
        [$context, , $consumer] = $this->createContext([$transportMsg]);
        $app->instance(Context::class, $context);

        [$result, $output] = $this->runCommand(new ForgetCommand(), $app, ['id' => 'msg-to-delete']);

        self::assertEquals(0, $result);
        self::assertStringContainsString('Failed message [msg-to-delete] deleted successfully.', $output);
        self::assertCount(1, $consumer->acknowledged);
    }

    public function test_reports_error_when_message_not_found(): void
    {
        $app = $this->createMockApplication();
        [$context] = $this->createContext([]);
        $app->instance(Context::class, $context);

        [$result, $output] = $this->runCommand(new ForgetCommand(), $app, ['id' => 'nonexistent']);

        self::assertEquals(1, $result);
        self::assertStringContainsString('Unable to find failed message with ID [nonexistent]', $output);
    }
}
