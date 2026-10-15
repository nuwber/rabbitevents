<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Console;

use Illuminate\Console\Command;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Contracts\Destination;
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
                            {id? : The ID of the failed message to retry or "all"}
                            {--all : Retry all failed messages}
                            {--queue= : The failed queue name}
                            {--connection= : The connection name}
                            {--service= : The service name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry a failed message or all failed messages back onto the queue';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $id = (string) $this->argument('id');
        $all = (bool) $this->option('all') || $id === 'all';

        if (!$all && empty($id)) {
            $this->error('Please specify the message ID to retry or pass the --all option.');
            return 1;
        }

        $targetId = $all ? null : $id;
        $retried = $this->retryMessages($targetId);

        if (!$all && $retried === 0) {
            $this->error("Failed message [{$id}] not found.");
            return 1;
        }

        if ($all) {
            $this->info("Retried {$retried} failed message(s).");
        }

        return 0;
    }

    protected function retryMessages(?string $targetId): int
    {
        $context = $this->getContext();
        $producer = $context->createProducer();
        $retried = 0;

        $this->eachFailedMessage(function ($message, $consumer) use ($targetId, $producer, $context, &$retried) {
            $msgId = $message->id();

            if (is_null($targetId) || $msgId === $targetId) {
                $targetQueue = $this->getTargetQueue($context, $message);
                $this->republishMessage($message, $producer, $targetQueue);
                $consumer->acknowledge($message);

                $retried++;
                $this->info("The failed message [{$msgId}] has been pushed back onto the queue!");

                if (!is_null($targetId)) {
                    return false;
                }
            } else {
                $consumer->reject($message, true);
            }

            return true;
        });

        return $retried;
    }

    protected function getTargetQueue(Context $context, Message $message): Destination
    {
        $queueName = $this->getOriginalQueueName($message);

        return $context->makeQueue($queueName, [], $context->makeTopic());
    }

    protected function republishMessage($message, $producer, $destination): void
    {
        $routingKey = $this->extractEvent($message);
        $transportMessage = $message->transportMessage();
        $transportMessage->setRoutingKey($routingKey);
        $transportMessage->setProperty('x-attempts', 0);

        $producer->send($destination, $transportMessage);
    }
}
