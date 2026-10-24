<?php

declare(strict_types=1);

namespace Zana\Events;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Contracts\ServiceResolverInterface;
use Zana\Core\ServiceProvider;

/**
 * Registers the listener provider and the dispatcher. Modules add listeners
 * in boot(): $container->get(ListenerProvider::class)->listen(...).
 */
final class EventsServiceProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->singleton(ListenerProvider::class, static fn (ServiceResolverInterface $root): ListenerProvider => new ListenerProvider($root))
            ->alias(ListenerProviderInterface::class, ListenerProvider::class)
            ->singleton(EventDispatcher::class)
            ->alias(EventDispatcherInterface::class, EventDispatcher::class);
    }

    public function origin(): string
    {
        return 'package:mj-zana/events';
    }
}
