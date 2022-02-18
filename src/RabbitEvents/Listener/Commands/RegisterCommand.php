<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * @codeCoverageIgnore
 */
class RegisterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitevents:register';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install all of the RabbitEvents resources';

    public function handle(): void
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
