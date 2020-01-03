<?php

namespace Nuwber\Events\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

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
        $this->comment('Publishing RabbitEvents Service Provider...');
        $this->callSilent('vendor:publish', ['--tag' => 'rabbitevents-provider']);


        $this->comment('Publishing RabbitEvents Configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'rabbitevents-config']);

        $this->registerServiceProvider();

        $this->info('RabbitEvents scaffolding installed successfully.');
    }

    /**
     * Register the Horizon service provider in the application configuration file.
     *
     * @return void
     */
    protected function registerServiceProvider()
    {
        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());
        $prefix = "{namespace}\\Providers";
        $appConfig = file_get_contents($this->laravel->configPath('app.php'));
        if (Str::contains($appConfig, $prefix . 'RabbitEventsServiceProvider::class')) {
            return;
        }
        file_put_contents(
            $this->laravel->configPath('app.php'),
            str_replace(
                "{$prefix}\\EventServiceProvider::class," . PHP_EOL,
                "{$prefix}\\EventServiceProvider::class," . PHP_EOL
                . "        {$prefix}\\RabbitEventsServiceProvider::class," . PHP_EOL,
                $appConfig
            )
        );
        
        file_put_contents($this->laravel->path('Providers/RabbitEventsServiceProvider.php'), str_replace(
            "namespace App\Providers;",
            "namespace {$namespace}\Providers;",
            file_get_contents($this->laravel->path('Providers/RabbitEventsServiceProvider.php'))
        ));
    }
}
