<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher\Support;

use function publish;

trait Publishable
{
    /**
     * Indicates if the event should be published after the active database transaction commits.
     *
     * @var bool|null
     */
    public ?bool $afterCommit = null;

    /**
     * @return string|null
     * @throws \Throwable
     */
    public static function publish(): ?string
    {
        return publish(new static(...func_get_args()));
    }

    /**
     * Indicate that the event should be published after the database transaction commits.
     *
     * @param bool $afterCommit
     * @return $this
     */
    public function afterCommit(bool $afterCommit = true): static
    {
        $this->afterCommit = $afterCommit;

        return $this;
    }
}
