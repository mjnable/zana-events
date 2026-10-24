<?php

declare(strict_types=1);

namespace Zana\Events;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Holds listeners and returns those matching an event, highest priority first.
 *
 * Listeners given as class names, or [class name, method], are resolved from
 * the container on the first dispatch that needs them, then reused.
 */
final class ListenerProvider implements ListenerProviderInterface
{
    /** @var list<array{event: string, listener: callable|string|array{string, string}, priority: int}> */
    private array $listeners = [];

    /** @var array<string, object> */
    private array $resolved = [];

    public function __construct(private readonly ?ContainerInterface $container = null)
    {
    }

    /**
     * @param class-string $event A class or interface; instances of it match
     * @param callable|class-string|array{class-string, string} $listener
     */
    public function listen(string $event, callable|string|array $listener, int $priority = 0): self
    {
        if (is_string($listener) && !class_exists($listener) && !is_callable($listener)) {
            throw new EventsException("Listener {$listener} is neither a class nor a callable.");
        }

        $this->listeners[] = ['event' => $event, 'listener' => $listener, 'priority' => $priority];

        return $this;
    }

    /**
     * Register classes carrying #[AsListener].
     *
     * @param class-string ...$classes
     */
    public function addListenerClass(string ...$classes): self
    {
        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(AsListener::class);
            if ($attributes === []) {
                throw new EventsException("{$class} has no #[AsListener] attribute.");
            }

            foreach ($attributes as $attribute) {
                $listener = $attribute->newInstance();
                $event = $listener->event ?? $this->inferEvent($reflection, $listener->method);
                $this->listen($event, $listener->method === '__invoke' ? $class : [$class, $listener->method], $listener->priority);
            }
        }

        return $this;
    }

    /**
     * @param class-string<EventSubscriberInterface> ...$subscribers
     */
    public function addSubscriber(string ...$subscribers): self
    {
        foreach ($subscribers as $subscriber) {
            foreach ($subscriber::subscribedEvents() as $event => $definition) {
                $entries = is_string($definition) ? [[$definition]] : (is_string($definition[0]) ? [$definition] : $definition);

                foreach ($entries as $entry) {
                    /** @var array{0: string, 1?: int} $entry */
                    $this->listen($event, [$subscriber, $entry[0]], $entry[1] ?? 0);
                }
            }
        }

        return $this;
    }

    /**
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $matching = [];
        foreach ($this->listeners as $position => $definition) {
            if ($event instanceof $definition['event']) {
                $matching[] = [$definition, $position];
            }
        }

        usort($matching, static fn (array $a, array $b): int => [$b[0]['priority'], $a[1]] <=> [$a[0]['priority'], $b[1]]);

        foreach ($matching as [$definition]) {
            yield $this->callable($definition['listener']);
        }
    }

    /**
     * @param callable|string|array{string, string} $listener
     */
    private function callable(callable|string|array $listener): callable
    {
        if (is_string($listener) && class_exists($listener)) {
            $instance = $this->instance($listener);
            if (!is_callable($instance)) {
                throw new EventsException("Listener {$listener} must be invokable.");
            }

            return $instance;
        }

        if (is_array($listener) && is_string($listener[0])) {
            $callable = [$this->instance($listener[0]), $listener[1]];
            if (!is_callable($callable)) {
                throw new EventsException("Listener {$listener[0]}::{$listener[1]}() does not exist.");
            }

            return $callable;
        }

        if (!is_callable($listener)) {
            throw new EventsException('Invalid listener.');
        }

        return $listener;
    }

    private function instance(string $class): object
    {
        if (isset($this->resolved[$class])) {
            return $this->resolved[$class];
        }

        if ($this->container === null) {
            throw new EventsException("Listener {$class} needs a container to be resolved.");
        }

        $instance = $this->container->get($class);
        if (!is_object($instance)) {
            throw new EventsException("The container returned no object for listener {$class}.");
        }

        return $this->resolved[$class] = $instance;
    }

    /**
     * @param \ReflectionClass<object> $class
     * @return class-string
     */
    private function inferEvent(\ReflectionClass $class, string $method): string
    {
        if (!$class->hasMethod($method)) {
            throw new EventsException("{$class->getName()}::{$method}() does not exist.");
        }

        $type = ($class->getMethod($method)->getParameters()[0] ?? null)?->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            throw new EventsException("Cannot infer the event of {$class->getName()}::{$method}(): its first parameter needs a class type, or give #[AsListener(event: ...)].");
        }

        /** @var class-string */
        return $type->getName();
    }
}
