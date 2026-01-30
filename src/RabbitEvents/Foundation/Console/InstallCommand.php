<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Console;

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

    public function handle(): void
    {
        $this->comment('Publishing RabbitEvents Configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'rabbitevents-config']);

        $this->registerServiceProvider();

        $this->info('RabbitEvents scaffolding installed successfully.');
    }

    private function registerServiceProvider(): void
    {
        $this->comment('Publishing RabbitEvents Service Provider...');
        $this->callSilent('vendor:publish', ['--tag' => 'rabbitevents-listener-provider']);

        if (file_exists($this->laravel->bootstrapPath('providers.php'))) {
            ServiceProvider::addProviderToBootstrapFile("App\\Providers\\RabbitEventsServiceProvider");
        } else {
            $this->info("Please register the RabbitEventsServiceProvider in your configuration.");
        }
    }
}
