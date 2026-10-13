<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Console;

use Illuminate\Console\Command;

class ForgetCommand extends Command
{
    use InteractsWithFailedQueue;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitevents:forget
                            {id : The ID of the failed message to delete}
                            {--queue= : The failed queue name}
                            {--connection= : The connection name}
                            {--service= : The service name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a failed message from the Dead Letter Queue';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $id = $this->argument('id');
        $queueName = $this->getFailedQueueName();
        $context = $this->getContext();
        $consumer = $this->getConsumer($context, $queueName);

        $found = false;
        $seen = [];

        while (true) {
            $message = $consumer->nextMessage(500);

            if (!$message) {
                break;
            }

            $msgId = $message->id();

            if ($msgId === $id) {
                $consumer->acknowledge($message);
                $this->info("Failed message [{$id}] deleted successfully.");
                $found = true;
                break;
            }

            if ($msgId && isset($seen[$msgId])) {
                $consumer->reject($message, true);
                break;
            }

            if ($msgId) {
                $seen[$msgId] = true;
            }

            $consumer->reject($message, true);
        }

        if (!$found) {
            $this->error("Unable to find failed message with ID [{$id}] in [{$queueName}].");
            return 1;
        }

        return 0;
    }
}
