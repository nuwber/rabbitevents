<?php

namespace Nuwber\Events\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Nuwber\Events\ConsumerFactory;
use Nuwber\Events\MessageProcessor;
use Nuwber\Events\NameResolver;
use Nuwber\Events\ProcessingOptions;
use Nuwber\Events\Worker;
use Nuwber\Events\Log;

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

    /** @var Dispatcher */
    private $events;

    /**
     * @var ExceptionHandler
     */
    private $exceptions;

    /**
     * @var array
     */
    protected $logWriters = [];

    public function __construct(ExceptionHandler $exceptions)
    {
        parent::__construct();

        $this->exceptions = $exceptions;
    }

    /**
     * Execute the console command.
     *
     * @throws \Exception
     * @return mixed
     */
    public function handle()
    {
        $this->events = $this->laravel->make('events');

        $this->registerLogWriters();

        $this->listenForEvents();

        $options = $this->gatherProcessingOptions();

        $processor = new MessageProcessor($this->laravel, $this->events, $this->exceptions, $options);

        (new Worker(
            $this->laravel->make(ConsumerFactory::class)->make($this->nameResolver($options)),
            $this->exceptions
        ))->work($processor, $options);
    }

    protected function nameResolver(ProcessingOptions $options)
    {
        return new NameResolver($this->getEvent($this->getConnection()), $options->service);
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
    protected function getEvent($connection = 'interop')
    {
        return $this->argument('event')
            ?: $this->laravel['config']->get("queue.connections.$connection.queue", 'default');
    }

    /**
     * Register classes to write log output
     */
    private function registerLogWriters()
    {
        if (!$this->option('quiet')) {
            $this->logWriters[] = new Log\Output($this->laravel, $this->output);
        }

        if ($this->laravel['config']->get('queue.connections.interop.logging.enabled')) {
            $this->logWriters[] = new Log\General($this->laravel);
        }
    }
}
