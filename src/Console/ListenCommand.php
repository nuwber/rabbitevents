<?php

namespace Nuwber\Events\Console;

use Illuminate\Console\Command;
use Nuwber\Events\Console\Log;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
use Nuwber\Events\Queue\ConsumerFactory;
use Nuwber\Events\Queue\JobsFactory;
use Nuwber\Events\Queue\MessageProcessor;
use Nuwber\Events\Queue\NameResolver;
use Nuwber\Events\Queue\ProcessingOptions;
use Nuwber\Events\Queue\Worker;

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
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=0 : Number of times to attempt a job before logging it failed}
                            {--sleep=5 : Sleep time in seconds before running failed job next time}
                            {--quiet: No console output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for event thrown from other services';

    /**
     * @var ExceptionHandler
     */
    private $exceptions;

    /**
     * @var array
     */
    protected $logWriters = [];
    /**
     * @var Dispatcher
     */
    private $events;

    /**
     * @var AmqpContext
     */
    private $context;

    public function __construct(ExceptionHandler $exceptions, Dispatcher $events, AmqpContext $context)
    {
        parent::__construct();

        $this->exceptions = $exceptions;
        $this->events = $events;
        $this->context = $context;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $options = $this->gatherProcessingOptions();

        $this->registerLogWriters($options->connectionName);
        $this->listenForEvents();

        $nameResolver = new NameResolver(
            $this->getEvent($options->connectionName),
            $options->service
        );

        $consumer = (new ConsumerFactory($this->context, $this->laravel[AmqpTopic::class]))
            ->make($nameResolver);

        $processor = new MessageProcessor(
            $this->events,
            $this->exceptions,
            new JobsFactory($this->laravel, $this->context, $consumer),
            $options
        );

        (new Worker($consumer, $processor, $this->exceptions))->work($options);
    }

    /**
     * Listen for the queue events in order to update the console output.
     *
     * @return void
     */
    protected function listenForEvents()
    {
        $callback = function ($event) {
            foreach ($this->logWriters as $writer) {
                $writer->log($event);
            }
        };

        $this->events->listen(JobProcessing::class, $callback);
        $this->events->listen(JobProcessed::class, $callback);
        $this->events->listen(JobFailed::class, $callback);
    }

    /**
     * Gather all of the queue worker options as a single object.
     *
     * @return ProcessingOptions
     */
    protected function gatherProcessingOptions()
    {
        return new ProcessingOptions(
            $this->option('memory'),
            $this->option('timeout'),
            $this->option('tries'),
            $this->option('sleep'),
            $this->option('service') ?: $this->laravel['config']->get("app.name"),
            $this->getConnection()
        );
    }

    /**
     * @return string
     */
    protected function getConnection()
    {
        return $this->option('connection')
            ?: $this->laravel['config']['queue.default'];
    }

    /**
     * Get the queue name for the worker.
     *
     * @param  string $connection
     *
     * @return string
     */
    protected function getEvent($connection = 'rabbitmq')
    {
        return $this->argument('event')
            ?: $this->laravel['config']
                ->get("queue.connections.$connection.queue", 'default');
    }

    /**
     * Register classes to write log output
     */
    private function registerLogWriters($connection = 'rabbitmq')
    {
        if (!$this->option('quiet')) {
            $this->logWriters[] = new Log\Output($this->laravel, $this->output);
        }

        if ($this->laravel['config']->get("queue.connections.$connection.logging.enabled")) {
            $this->logWriters[] = new Log\General($this->laravel);
        }
    }
}
