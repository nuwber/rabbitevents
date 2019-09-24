<?php

namespace Nuwber\Events\Console;

use Illuminate\Console\Command;
use Nuwber\Events\Facades\BroadcastEvent;

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
    
    public function handle()
    {
        $events = BroadcastEvent::getEvents();

        if (count($events) === 0) {
            $this->error("Your application doesn't have any registered broadcast events.");
            return;
        }

        foreach ($events as $event) {
            $this->info($event);
        }
    }
}
