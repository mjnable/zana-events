<?php

declare(strict_types=1);

namespace Zana\Events;

/**
 * A class that declares several listeners at once.
 */
interface EventSubscriberInterface
{
    /**
     * Event class => a method name, [method, priority], or a list of [method, priority].
     *
     * @return array<class-string, string|array{0: string, 1?: int}|list<array{0: string, 1?: int}>>
     */
    public static function subscribedEvents(): array;
}
