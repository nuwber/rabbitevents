<?php

namespace Nuwber\Events\Console\Log;

use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Nuwber\Events\Queue\Job;

class Output extends Writer
{

    /**
     * @var Application
     */
    protected $laravel;

    /**
     * @var OutputStyle
     */
    protected $output;

    public function __construct($laravel, $output)
    {
        $this->laravel = $laravel;
        $this->output = $output;
    }

    /**
     * @inheritdoc
     */
    public function log($event)
    {
        $status = $this->getStatus($event);

        $this->writeStatus($event->job, $status, $this->getType($status));
        if (isset($event->exception)) {
            $this->output->writeln('Exception message: ' . $event->exception->getMessage());
        }
    }

    /**
     * Format the status output for the queue worker.
     *
     * @param  \Nuwber\Events\Queue\Job $listener
     * @param  string $status
     * @param  string $type
     * @return void
     */
    protected function writeStatus(Job $listener, $status, $type)
    {
        $this->output->writeln(sprintf(
            "<{$type}>[%s] %s</{$type}> %s",
            Carbon::now()->format('Y-m-d H:i:s'),
            str_pad("{$status}:", 11),
            $listener->resolveName()
        ));
    }

    protected function getType($status)
    {
        switch ($status) {
            case self::STATUS_PROCESSED:
                return 'info';
            case self::STATUS_FAILED:
                return 'error';
            default:
                return 'comment';
        }
    }
}
