<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\Console;

use RabbitEvents\Foundation\Context;
use RabbitEvents\Listener\Console\FlushFailedCommand;

class FlushFailedCommandTest extends FailedQueueTestCase
{
    public function test_flushes_all_failed_messages(): void
    {
        $app = $this->createMockApplication();
        $msg1 = $this->createFailedTransportMessage('msg-1');
        $msg2 = $this->createFailedTransportMessage('msg-2');
        [$context, , $consumer] = $this->createContext([$msg1, $msg2]);
        $app->instance(Context::class, $context);

        [$result, $output] = $this->runCommand(new FlushFailedCommand(), $app);

        self::assertEquals(0, $result);
        self::assertStringContainsString('All [2] failed messages deleted from [test-service:.failed].', $output);
        self::assertCount(2, $consumer->acknowledged);
    }
}
