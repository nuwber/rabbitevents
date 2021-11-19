<?php

namespace Nuwber\Events\Queue\Message;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Carbon;
use Interop\Amqp\AmqpMessage;
use Nuwber\Events\Queue\Events\JobExceptionOccurred;
use Nuwber\Events\Queue\Events\JobFailed;
use Nuwber\Events\Queue\Events\JobProcessed;
use Nuwber\Events\Queue\Events\JobProcessing;
use Nuwber\Events\Queue\Exceptions\FailedException;
use Nuwber\Events\Queue\Jobs\Factory;
use Nuwber\Events\Queue\Jobs\Job;
use Nuwber\Events\Queue\ProcessingOptions;
use Throwable;

class Processor
{
    /**
     * @var EventsDispatcher
     */
    protected $events;

    /**
     * @var Factory
     */
    private $jobsFactory;

    public function __construct(EventsDispatcher $events, Factory $jobsFactory)
    {
        $this->events = $events;
        $this->jobsFactory = $jobsFactory;
    }

    /**
     * Fire an event and call the listeners.
     *
     * @param AmqpMessage $message
     * @param ProcessingOptions $options
     * @throws Throwable
     */
    public function process(AmqpMessage $message, ProcessingOptions $options): void
    {
        foreach ($this->jobsFactory->makeJobs($message) as $job) {
            $response = $this->runJob($job, $options);

            // If a boolean false is returned from a listener, we will stop propagating
            // the event to any further listeners down in the chain, else we keep on
            // looping through the listeners and run every one in our sequence.
            if ($response === false) {
                break;
            }
        }
    }

    /**
     * Process concrete listener
     *
     * @param Job $job
     * @param ProcessingOptions $options
     * @return mixed
     * @throws Throwable
     */
    public function runJob(Job $job, ProcessingOptions $options)
    {
        try {
            $this->raiseBeforeEvent($job);

            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts($job, $options);

            $response = $job->fire();

            $this->raiseAfterEvent($job);

            return $response;
        } catch (Throwable $e) {
            $this->handleJobException($job, $options, $e);
        }
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * This will likely be because the job previously exceeded a timeout.
     *
     * @param Job $job
     * @param ProcessingOptions $options
     * @return void
     *
     */
    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts(Job $job, ProcessingOptions $options): void
    {
        if ($options->maxTries === 0 || $job->attempts() <= $options->maxTries) {
            return;
        }

        $job->fail($e = new MaxAttemptsExceededException(
            'A queued job has been attempted too many times or run too long. The job may have previously timed out.'
        ));

        $this->raiseAfterEvent($job);

        throw $e;
    }

    /**
     * Handle an exception that occurred while the job was running.
     *
     * @param Job $job
     * @param ProcessingOptions $options
     * @param Throwable $exception
     * @return void
     *
     * @throws Throwable
     */
    protected function handleJobException(Job $job, ProcessingOptions $options, Throwable $exception): void
    {
        try {
            if ($exception instanceof FailedException) {
                $job->fail($exception);
            }

            // First, we will go ahead and mark the job as failed if it will exceed the maximum
            // attempts it is allowed to run the next time we process it. If so we will just
            // go ahead and mark it as failed now so we do not have to release this again.
            if (!$job->hasFailed()) {
                $this->markJobAsFailedIfWillExceedMaxAttempts($job, $options->maxTries, $exception);
            }

            $this->raiseExceptionOccurredEvent($job, $exception);
        } finally {
            // If we catch an exception, we will attempt to release the job back onto the queue
            // so it is not lost entirely. This'll let the job be retried at a later time by
            // another listener (or this same one). We will re-throw this exception after.
            if (!$job->isDeleted() && !$job->isReleased() && !$job->hasFailed()) {
                $job->release($options->sleep);
            }
        }

        throw $exception;
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * @param Job $job
     * @param int $maxTries
     * @param Throwable $exception
     * @return void
     */
    protected function markJobAsFailedIfWillExceedMaxAttempts(Job $job, int $maxTries, Throwable $exception): void
    {
        if ($job->timeoutAt() && $job->timeoutAt() <= Carbon::now()->getTimestamp()) {
            $job->fail($exception);
        }

        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;
        if ($maxTries > 0 && $job->attempts() >= $maxTries) {
            $job->fail($exception);
        }
    }

    /**
     * Raise the before queue job event.
     *
     * @param Job $job
     * @return void
     */
    protected function raiseBeforeEvent(Job $job): void
    {
        $this->events->dispatch(new JobProcessing($job));
    }

    /**
     * Raise the after queue job event.
     *
     * @param Job $job
     * @return void
     */
    protected function raiseAfterEvent(Job $job): void
    {
        $this->events->dispatch(new JobProcessed($job));
    }

    /**
     * Raise the exception occurred queue job event.
     *
     * @param Job $job
     * @param Throwable $exception
     * @return void
     */
    protected function raiseExceptionOccurredEvent(Job $job, Throwable $exception): void
    {
        $this->events->dispatch(new JobExceptionOccurred($job, $exception));
    }

    /**
     * Raise the failed queue job event.
     *
     * @param Job $job
     * @param Throwable $exception
     * @return void
     */
    protected function raiseFailedJobEvent(Job $job, Throwable $exception): void
    {
        $this->events->dispatch(new JobFailed($job, $exception));
    }
}
