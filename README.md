# Zana Events

A PSR-14 event dispatcher whose listeners are resolved lazily from the
container. Requires PHP 8.5 or later.

```
composer require mj-zana/events
```

## Usage

Register `EventsServiceProvider`, then add listeners in a module's `boot()`:

```php
public function boot(ContainerInterface $container): void
{
    $container->get(ListenerProvider::class)
        ->listen(PatientRegistered::class, SendWelcomeMail::class)          // invokable class, resolved on first dispatch
        ->listen(PatientRegistered::class, [AuditTrail::class, 'record'], priority: 10)
        ->addListenerClass(UpdateStatistics::class)                         // #[AsListener]
        ->addSubscriber(PatientNotifications::class);                       // EventSubscriberInterface
}
```

```php
$dispatcher->dispatch(new PatientRegistered($id));
$dispatcher->dispatchAll($patient->releaseEvents());    // domain events from an aggregate
```

- **Matching:** a listener for a class or interface receives instances of it,
  including subclasses.
- **Order:** higher priority runs first; at equal priority, registration order.
- **Stopping:** events extending `StoppableEvent` can stop propagation.
- **Exceptions** thrown by listeners propagate to the caller.

## License

MIT — see [LICENSE](LICENSE).
