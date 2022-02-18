<?php

namespace RabbitEvents\Foundation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;

/**
 * @codeCoverageIgnore
 */
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitevents:install';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install all of the RabbitEvents resources';

    public function handle()
    {
        $registeredTags = ServiceProvider::publishableGroups();

        $this->comment('Publishing RabbitEvents Configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'rabbitevents-config']);

        if (in_array('rabbitevents-listener-provider', $registeredTags)) {
            $this->comment('Publishing RabbitEvents Service Provider...');
            $this->callSilent('vendor:publish', ['--tag' => 'rabbitevents-listener-provider']);
            $this->callSilent('rabbitevents:register');
        }

        $this->info('RabbitEvents scaffolding installed successfully.');
    }
}
