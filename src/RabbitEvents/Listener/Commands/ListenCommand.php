<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Support\Releaser;
use RabbitEvents\Listener\Events\ListenerHandled;
use RabbitEvents\Listener\Events\ListenerHandleFailed;
use RabbitEvents\Listener\Events\ListenerHandlerExceptionOccurred;
use RabbitEvents\Listener\Events\ListenerHandling;
use RabbitEvents\Listener\Events\MessageProcessingFailed;
use RabbitEvents\Listener\Events\WorkerStopping;
use RabbitEvents\Listener\Facades\RabbitEvents;
use RabbitEvents\Listener\ListenerOptions;
use RabbitEvents\Listener\Message\HandlerFactory;
use RabbitEvents\Listener\Message\Processor;
use RabbitEvents\Listener\QueueName;
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
                            {events? : The name of the events to listen to}
                            {--service= : The name of current service. Necessary to identify listeners}
                            {--queue= : The queue to listen on}
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
    protected $description = 'Listen for events thrown from other services';

    protected array $logWriters = [];

    /**
     * Execute the console command.
     * @param Context $context
     * @param Worker $worker
     * @return int
     */
    public function handle(Context $context, Worker $worker)
    {
        $this->checkExtLoaded();

        $this->registerLogWriters();
        $this->listenForApplicationEvents();

        $options = $this->gatherOptions();

        $queue = $context->makeQueue(
            $this->option('queue') ?: QueueName::resolve($options->service, $options->events),
            $options->events,
            $context->makeTopic()
        );

        $handlerFactory = new HandlerFactory(
            $this->laravel,
            new Releaser($queue, $context->createProducer())
        );

        return $worker->work(
            new Processor($handlerFactory, $this->laravel['events']),
            $context->makeConsumer($queue),
            $options
        );
    }

    /**
     * Gather all the queue worker options as a single object.
     *
     * @return ListenerOptions
     */
    protected function gatherOptions(): ListenerOptions
    {
        return new ListenerOptions(
            $this->option('service') ?: $this->laravel['config']->get("app.name"),
            $this->laravel['config']['rabbitevents.default'],
            $this->gatherEvents(),
            (int)$this->option('memory'),
            (int)$this->option('tries'),
            (int)$this->option('timeout'),
            (int)$this->option('sleep'),
        );
    }

    private function gatherEvents(): array
    {
        $events = $this->argument('events');

        if (is_null($events)) {
            return RabbitEvents::getEvents();
        }

        if (Str::contains($events, ',')) {
            return array_map('trim', explode(',', $events));
        }

        return [$events];
    }

    /**
     * Listen for the queue events in order to update the console output.
     *
     * @return void
     */
    protected function listenForApplicationEvents(): void
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
        $this->laravel['events']->listen(MessageProcessingFailed::class, function ($event) {
            $this->output->error('Message processing failed with the exception: ' . $event->exception->getMessage());
        });
        $this->laravel['events']->listen(WorkerStopping::class, function ($event) {
            $this->output->info('Worker has been stopped with the status code ' . $event->status);
        });
    }

    /**
     * Register classes to write log output
     */
    protected function registerLogWriters(): void
    {
        if (!$this->option('quiet')) {
            $this->logWriters[] = new Log\Output($this->laravel, $this->output);
        }

        [$enabled, $defaultLoglevel, $channel] = $this->parseLoggingConfiguration();

        if ($enabled) {
            $this->logWriters[] = new Log\General($this->laravel, $defaultLoglevel, $channel);
        }
    }

    private function parseLoggingConfiguration(): array
    {
        $config = $this->laravel['config']->get('rabbitevents');

        return [
            Arr::get($config, 'logging.enabled', false),
            Arr::get($config, 'logging.level', 'info'),
            Arr::get($config, 'logging.channel'),
        ];
    }

    private function checkExtLoaded(): void
    {
        if (extension_loaded('amqp') && !class_exists('Enqueue\AmqpExt\AmqpConnectionFactory')) {
            $this->info("You have ext-amqp extension installed. Require enqueue/amqp-ext package to use it.");
            $this->info("The package can be installed via 'composer require enqueue/amqp-ext' command.");
        }
    }
}
