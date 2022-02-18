<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Commands;

use Illuminate\Console\Command;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Support\QueueName;
use RabbitEvents\Listener\Events\HandlerExceptionOccurred;
use RabbitEvents\Listener\Events\MessageProcessingFailed;
use RabbitEvents\Listener\Events\MessageProcessed;
use RabbitEvents\Listener\Events\MessageProcessing;
use RabbitEvents\Listener\Message\HandlerFactory;
use RabbitEvents\Listener\Message\Processor;
use RabbitEvents\Listener\Message\ProcessingOptions;
use RabbitEvents\Listener\Worker;

/**
 * @codeCoverageIgnore
 */
class ListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitevents:listen
                            {event : The name of the event to listen to}
                            {--service= : The name of current service. Necessary to identify listeners}
                            {--connection= : The name of the queue connection to work}
                            {--memory=128 : The memory limit in megabytes}
                            {--timeout=60 : The number of seconds a massage could be handled}
                            {--tries=1 : Number of times to attempt to handle a Message before logging it failed}
                            {--sleep=5 : Sleep time in seconds before handling failed message next time}
                            {--quiet: No console output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for event thrown from other services';

    protected array $logWriters = [];

    /**
     * Execute the console command.
     * @param Context $context
     * @param Worker $worker
     * @throws \Throwable
     */
    public function handle(Context $context, Worker $worker): void
    {
        $options = $this->gatherProcessingOptions();

        $this->registerLogWriters($options->connectionName);

        $this->listenForEvents();

        $worker->work(
            new Processor(new HandlerFactory($this->laravel), $this->laravel['events']),
            $context->createConsumer(
                new QueueName($options->service, $this->argument('event')),
                $this->argument('event')
            ),
            $options
        );
    }

    /**
     * Gather all the queue worker options as a single object.
     *
     * @return \RabbitEvents\Listener\Message\ProcessingOptions
     */
    protected function gatherProcessingOptions(): ProcessingOptions
    {
        return new ProcessingOptions(
            $this->option('service') ?: $this->laravel['config']->get("app.name"),
            $this->option('connection') ?: $this->laravel['config']['rabbitevents.default'],
            (int) $this->option('memory'),
            (int) $this->option('tries'),
            (int) $this->option('sleep')
        );
    }

    /**
     * Listen for the queue events in order to update the console output.
     *
     * @return void
     */
    protected function listenForEvents(): void
    {
        $callback = function ($event) {
            foreach ($this->logWriters as $writer) {
                $writer->log($event);
            }
        };

        $this->laravel['events']->listen(MessageProcessing::class, $callback);
        $this->laravel['events']->listen(MessageProcessed::class, $callback);
        $this->laravel['events']->listen(MessageProcessingFailed::class, $callback);
        $this->laravel['events']->listen(HandlerExceptionOccurred::class, $callback);
    }

    /**
     * Register classes to write log output
     *
     * @param string $connection
     */
    protected function registerLogWriters(string $connection = 'rabbitmq'): void
    {
        if (!$this->option('quiet')) {
            $this->logWriters[] = new Log\Output($this->laravel, $this->output);
        }

        if ($this->laravel['config']->get("rabbitevents.connections.$connection.logging.enabled")) {
            $this->logWriters[] = new Log\General($this->laravel);
        }
    }
}
