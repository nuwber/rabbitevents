<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RabbitEvents\Foundation\Message;

class FailedCommand extends Command
{
    use InteractsWithFailedQueue;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitevents:failed
                            {--queue= : The failed queue name}
                            {--connection= : The connection name}
                            {--service= : The service name}
                            {--limit=50 : The maximum number of failed messages to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all failed messages in the Dead Letter Queue';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $queueName = $this->getFailedQueueName();
        $context = $this->getContext();
        $consumer = $this->getConsumer($context, $queueName);

        $limit = (int) $this->option('limit');
        $rows = [];
        $seen = [];

        while (count($rows) < $limit) {
            $message = $consumer->nextMessage(500);

            if (!$message) {
                break;
            }

            $id = $message->id() ?? 'N/A';

            if (isset($seen[$id])) {
                $consumer->reject($message, true);
                break;
            }

            $seen[$id] = true;

            $failedAt = $message->header('x-failed-at');
            $failedAtStr = $failedAt ? Carbon::createFromTimestamp((int) $failedAt)->toDateTimeString() : 'Unknown';

            $exception = $message->header('x-exception-message')
                ?? $message->header('x-exception-class')
                ?? 'Unknown exception';

            $rows[] = [
                $id,
                $message->event(),
                $failedAtStr,
                $exception,
            ];

            // Requeue so the message is not deleted
            $consumer->reject($message, true);
        }

        if (empty($rows)) {
            $this->info("No failed messages found in [{$queueName}].");
            return 0;
        }

        $this->table(['ID', 'Event', 'Failed At', 'Exception'], $rows);

        return 0;
    }
}
