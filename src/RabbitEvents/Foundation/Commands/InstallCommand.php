<?php

namespace RabbitEvents\Foundation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $registeredTags = ServiceProvider::publishableGroups();

        $this->comment('Publishing RabbitEvents Configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'rabbitevents-config']);

        if (in_array('rabbitevents-listener-provider', $registeredTags)) {
            $this->comment('Publishing RabbitEvents Service Provider...');
            $this->callSilent('vendor:publish', ['--tag' => 'rabbitevents-listener-provider']);
            $this->registerServiceProvider();
        }

        $this->info('RabbitEvents scaffolding installed successfully.');
    }

    private function registerServiceProvider(): void
    {
        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());
        $prefix = "{$namespace}\\Providers";
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
