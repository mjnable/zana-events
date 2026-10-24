<?php

declare(strict_types=1);

namespace Zana\Events;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base class for events whose listeners can stop propagation.
 */
abstract class StoppableEvent implements StoppableEventInterface
{
    private bool $stopped = false;

    public function stopPropagation(): void
    {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}
