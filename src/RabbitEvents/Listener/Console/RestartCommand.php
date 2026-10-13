<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RestartCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitevents:restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restart RabbitEvents worker processes after their current event';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if ($this->laravel->bound('cache')) {
            $this->laravel->make('cache')->forever('rabbitevents:restart', Carbon::now()->getTimestamp());
        }

        $this->info('Broadcasting RabbitEvents worker restart signal.');

        return 0;
    }
}
