<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Console;

use Illuminate\Console\Command;

class FlushFailedCommand extends Command
{
    use InteractsWithFailedQueue;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitevents:flush
                            {--queue= : The failed queue name}
                            {--connection= : The connection name}
                            {--service= : The service name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush all failed messages from the Dead Letter Queue';

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

        $count = 0;

        while ($message = $consumer->nextMessage(500)) {
            $consumer->acknowledge($message);
            $count++;
        }

        $this->info("All [{$count}] failed messages deleted from [{$queueName}].");

        return 0;
    }
}
