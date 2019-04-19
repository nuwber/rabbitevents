<?php

namespace Nuwber\Events;

use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\MaxAttemptsExceededException;
use Interop\Amqp\AmqpMessage;
use Nuwber\Events\Exceptions\FailedException;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Throwable;

class MessageProcessor
{
    /**
     * @var Dispatcher
     */
    protected $events;
    /**
     * @var ProcessingOptions
     */
    protected $options;
    /**
     * @var JobsFactory
     */
    protected $jobsFactory;
    /**
     * @var ExceptionHandler
     */
    protected $exceptions;

    public function __construct(
        Dispatcher $events,
        ExceptionHandler $exceptions,
        JobsFactory $jobsFactory,
        ProcessingOptions $options
    ) {
        $this->events = $events;
        $this->exceptions = $exceptions;
        $this->jobsFactory = $jobsFactory;
        $this->options = $options;
    }

    /**
     * Fire an event and call the listeners.
     *
     * @param AmqpMessage $message
     */
    public function process(AmqpMessage $message)
    {
        try {
            foreach ($this->jobsFactory->make($message) as $job) {
                $response = $this->runJob($job);

                // If a boolean false is returned from a listener, we will stop propagating
                // the event to any further listeners down in the chain, else we keep on
                // looping through the listeners and firing every one in our sequence.
                if ($response === false) {
                    break;
                }
            }
        } catch (Exception $e) {
            $this->exceptions->report($e);
        } catch (Throwable $e) {
            $this->exceptions->report($e = new FatalThrowableError($e));
        }
    }

    /**
     * Process concrete listener
     *
     * @param Job $job
     * @return array|null
     * @throws \Exception
     */
    public function runJob(Job $job)
    {
        try {
            $this->raiseBeforeEvent($job);

            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts($job, $this->options->maxTries);

            $response = $job->fire();

            $this->raiseAfterEvent($job);

            return $response;
        } catch (Exception $e) {
            $this->handleJobException($job, $e);
        } catch (Throwable $e) {
            $this->handleJobException($job, new FatalThrowableError($e));
        }
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * This will likely be because the job previously exceeded a timeout.
     *
     * @param  Job $job
     * @param  int $maxTries
     * @return void
     *
     * @throws \Exception
     */
    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts(Job $job, int $maxTries)
    {
        if ($maxTries === 0 || $job->attempts() <= $maxTries) {
            return;
        }

        $this->failJob($job, $e = new MaxAttemptsExceededException(
            'A queued job has been attempted too many times or run too long. The job may have previously timed out.'
        ));

        throw $e;
    }

    /**
     * Mark the given job as failed and raise the relevant event.
     *
     * @param  Job $job
     * @param  \Exception $e
     * @return void
     */
    protected function failJob(Job $job, $e)
    {
        //Added this to support Laravel version < 5.8
        if (class_exists('Illuminate\Queue\FailingJob')) {
            \Illuminate\Queue\FailingJob::handle($this->options->connectionName, $job, $e);
        } else {
            $job->fail($e);
        }
    }

    /**
     * Handle an exception that occurred while the job was running.
     *
     * @param  Job $job
     * @param  \Exception $exception
     * @return void
     *
     * @throws \Exception
     */
    protected function handleJobException(Job $job, $exception)
    {
        try {
            if ($exception instanceof FailedException) {
                $this->failJob($job, $exception);
            }

            // First, we will go ahead and mark the job as failed if it will exceed the maximum
            // attempts it is allowed to run the next time we process it. If so we will just
            // go ahead and mark it as failed now so we do not have to release this again.
            if (!$job->hasFailed()) {
                $this->markJobAsFailedIfWillExceedMaxAttempts(
                    $job,
                    $this->options->maxTries,
                    $exception
                );
            }

            $this->raiseExceptionOccurredEvent($job, $exception);
        } finally {
            // If we catch an exception, we will attempt to release the job back onto the queue
            // so it is not lost entirely. This'll let the job be retried at a later time by
            // another listener (or this same one). We will re-throw this exception after.
            if (!$job->isDeleted() && !$job->isReleased() && !$job->hasFailed()) {
                $job->release($this->options->sleep);
            }
        }

        throw $exception;
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * @param  Job $job
     * @param  int $maxTries
     * @param  \Exception $exception
     * @return void
     */
    protected function markJobAsFailedIfWillExceedMaxAttempts(Job $job, $maxTries, $exception)
    {
        if ($maxTries > 0 && $job->attempts() >= $maxTries) {
            $this->failJob($job, $exception);
        }
    }

    /**
     * Raise the before queue job event.
     *
     * @param  Job $job
     * @return void
     */
    protected function raiseBeforeEvent(Job $job)
    {
        $this->events->dispatch(new JobProcessing($this->options->connectionName, $job));
    }

    /**
     * Raise the after queue job event.
     *
     * @param  Job $job
     * @return void
     */
    protected function raiseAfterEvent(Job $job)
    {
        $this->events->dispatch(new JobProcessed($this->options->connectionName, $job));
    }

    /**
     * Raise the exception occurred queue job event.
     *
     * @param  Job $job
     * @param  \Exception $exception
     * @return void
     */
    protected function raiseExceptionOccurredEvent(Job $job, $exception)
    {
        $this->events->dispatch(new JobExceptionOccurred($this->options->connectionName, $job, $exception));
    }

    /**
     * Raise the failed queue job event.
     *
     * @param  Job $job
     * @param  \Exception $exception
     * @return void
     */
    protected function raiseFailedJobEvent(Job $job, $exception)
    {
        $this->events->dispatch(new JobFailed($this->options->connectionName, $job, $exception));
    }
}
