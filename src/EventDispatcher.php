<?php

declare(strict_types=1);

namespace Zana\Events;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * PSR-14 dispatcher. Listener exceptions propagate to the caller.
 */
final readonly class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(private ListenerProviderInterface $listeners)
    {
    }

    /**
     * @template T of object
     * @param T $event
     * @return T
     */
    public function dispatch(object $event): object
    {
        $stoppable = $event instanceof StoppableEventInterface;

        foreach ($this->listeners->getListenersForEvent($event) as $listener) {
            if ($stoppable && $event->isPropagationStopped()) {
                break;
            }
            $listener($event);
        }

        return $event;
    }

    /**
     * Dispatch each event in turn, e.g. the events released by an aggregate.
     *
     * @param iterable<object> $events
     */
    public function dispatchAll(iterable $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
}
