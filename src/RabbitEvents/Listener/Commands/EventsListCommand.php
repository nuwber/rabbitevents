<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Commands;

use Illuminate\Console\Command;
use RabbitEvents\Listener\Facades\RabbitEvents;

/**
 * @codeCoverageIgnore
 */
class EventsListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitevents:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List of registered broadcast events';

    public function handle(): void
    {
        $events = RabbitEvents::getEvents();

        if (count($events) === 0) {
            $this->error("There’re no events registered in the RabbitEvents Service Provider.");
            return;
        }

        foreach ($events as $event) {
            $this->info($event);
        }
    }
}
