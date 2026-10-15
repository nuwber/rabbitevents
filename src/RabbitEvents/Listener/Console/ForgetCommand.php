<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Console;

use Illuminate\Console\Command;

class ForgetCommand extends Command
{
    use InteractsWithFailedQueue;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitevents:forget
                            {id : The ID of the failed message to delete}
                            {--queue= : The failed queue name}
                            {--connection= : The connection name}
                            {--service= : The service name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a failed message from the Dead Letter Queue';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $id = (string) $this->argument('id');

        if (!$this->forgetMessage($id)) {
            $this->error("Unable to find failed message with ID [{$id}].");
            return 1;
        }

        $this->info("Failed message [{$id}] deleted successfully.");

        return 0;
    }

    protected function forgetMessage(string $id): bool
    {
        $found = false;

        $this->eachFailedMessage(function ($message, $consumer) use ($id, &$found) {
            if ($message->id() === $id) {
                $consumer->acknowledge($message);
                $found = true;
                return false;
            }

            $consumer->reject($message, true);
            
            return true;
        });

        return $found;
    }
}
