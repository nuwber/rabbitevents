<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Console;

use Illuminate\Console\Command;
use RabbitEvents\Foundation\Message;

class RetryCommand extends Command
{
    use InteractsWithFailedQueue;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitevents:retry
                            {id? : The ID of the failed message to retry}
                            {--all : Retry all failed messages}
                            {--queue= : The failed queue name}
                            {--connection= : The connection name}
                            {--service= : The service name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry a failed RabbitEvents message from the Dead Letter Queue';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $id = $this->argument('id');
        $all = (bool) $this->option('all') || $id === 'all';

        if (!$all && empty($id)) {
            $this->error('Please specify the message ID to retry or pass the --all option.');
            return 1;
        }

        $queueName = $this->getFailedQueueName();
        $context = $this->getContext();
        $consumer = $this->getConsumer($context, $queueName);
        $producer = $context->createProducer();
        $topic = $context->makeTopic();

        $retriedCount = 0;
        $found = false;
        $seen = [];

        while (true) {
            $message = $consumer->nextMessage(500);

            if (!$message) {
                break;
            }

            $msgId = $message->id();

            if ($all || $msgId === $id) {
                $routingKey = (string) ($message->header('x-original-routing-key') ?: $message->event());
                $transportMessage = $message->transportMessage();
                $transportMessage->setRoutingKey($routingKey);

                $producer->send($topic, $transportMessage);
                $consumer->acknowledge($message);

                $retriedCount++;
                $this->info("The failed message [{$msgId}] has been pushed back onto the exchange!");

                if (!$all) {
                    $found = true;
                    break;
                }
            } else {
                if ($msgId && isset($seen[$msgId])) {
                    $consumer->reject($message, true);
                    break;
                }

                if ($msgId) {
                    $seen[$msgId] = true;
                }

                $consumer->reject($message, true);
            }
        }

        if (!$all && !$found) {
            $this->error("Unable to find failed message with ID [{$id}] in [{$queueName}].");
            return 1;
        }

        if ($all) {
            $this->info("Retried {$retriedCount} failed message(s).");
        }

        return 0;
    }
}
