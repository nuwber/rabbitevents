<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Console;

use Illuminate\Console\Command;
use RabbitEvents\Listener\Dispatcher;

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

    public function handle(Dispatcher $dispatcher): void
    {
        $events = $dispatcher->getEvents();

        if (count($events) === 0) {
            $this->error("There’re no events registered in the RabbitEvents Service Provider.");
            return;
        }

        foreach ($events as $event) {
            $this->info($event);
        }
    }
}
