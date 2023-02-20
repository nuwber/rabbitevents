<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use RabbitEvents\Foundation\Amqp\TopicDestinationFactory;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Support\QueueName;
use RabbitEvents\Foundation\Support\Releaser;
use RabbitEvents\Listener\Events\ListenerHandlerExceptionOccurred;
use RabbitEvents\Listener\Events\ListenerHandleFailed;
use RabbitEvents\Listener\Events\ListenerHandled;
use RabbitEvents\Listener\Events\ListenerHandling;
use RabbitEvents\Listener\Events\MessageProcessingFailed;
use RabbitEvents\Listener\Events\WorkerStopping;
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
     * @retur ?int
     */
    public function handle(Context $context, Worker $worker)
    {
        $options = $this->gatherProcessingOptions();

        $this->registerLogWriters($options->connectionName);

        $this->listenForEvents();

        $queue = $context->makeQueue(new QueueName($options->service, $this->argument('event')));

        $handlerFactory = new HandlerFactory(
            $this->laravel,
            new Releaser($queue, $context->createProducer())
        );

        return $worker->work(
            new Processor($handlerFactory, $this->laravel['events']),
            $context->makeConsumer($queue, $this->argument('event')),
            $options
        );
    }

    /**
     * Gather all the queue worker options as a single object.
     *
     * @return ProcessingOptions
     */
    protected function gatherProcessingOptions(): ProcessingOptions
    {
        return new ProcessingOptions(
            $this->option('service') ?: $this->laravel['config']->get("app.name"),
            $this->laravel['config']['rabbitevents.default'],
            (int) $this->option('memory'),
            (int) $this->option('tries'),
            (int) $this->option('timeout'),
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

        $this->laravel['events']->listen(ListenerHandling::class, $callback);
        $this->laravel['events']->listen(ListenerHandled::class, $callback);
        $this->laravel['events']->listen(ListenerHandleFailed::class, $callback);
        $this->laravel['events']->listen(ListenerHandlerExceptionOccurred::class, $callback);
        $this->laravel['events']->listen(MessageProcessingFailed::class, $callback);
        $this->laravel['events']->listen(WorkerStopping::class, function ($event) {
            $this->output->info('Worker has been stopped with the status code ' . $event->status);
        });
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

        [$enabled, $defaultLoglevel, $channel] = $this->parseLoggingConfiguration($connection);

        if ($enabled) {
            $this->logWriters[] = new Log\General($this->laravel, $defaultLoglevel, $channel);
        }
    }

    private function parseLoggingConfiguration(string $connection = 'rabbitmq'): array
    {
        $config = $this->laravel['config']->get('rabbitevents');

        if (Arr::has($config, 'logging')) {
            return [
                Arr::get($config, 'logging.enabled', false),
                Arr::get($config, 'logging.level', 'info'),
                Arr::get($config, 'logging.channel', null),
            ];
        }

        // TODO: In the next releases this part of config will be deleted
        return [
            Arr::get($config, "connections.$connection.logging.enabled", false),
            Arr::get($config, "connections.$connection.logging.level", 'info'),
            Arr::get($config, "connections.$connection.logging.channel", null),
        ];
    }
}
