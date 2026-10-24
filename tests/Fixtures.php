<?php

declare(strict_types=1);

namespace Zana\Events\Tests;

use Zana\Events\AsListener;
use Zana\Events\EventSubscriberInterface;
use Zana\Events\StoppableEvent;

interface PatientEvent
{
}

class PatientRegistered implements PatientEvent
{
    /** @var list<string> */
    public array $log = [];
}

final class VipPatientRegistered extends PatientRegistered
{
}

final class Cancellable extends StoppableEvent
{
    /** @var list<string> */
    public array $log = [];
}

final class Counter
{
    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }
}

final class SendWelcome
{
    public function __construct(public readonly Counter $counter)
    {
    }

    public function __invoke(PatientRegistered $event): void
    {
        $event->log[] = 'welcome';
    }
}

#[AsListener]
#[AsListener(event: VipPatientRegistered::class, priority: 10, method: 'vip')]
final class AttributeListener
{
    public function __invoke(PatientRegistered $event): void
    {
        $event->log[] = 'attribute';
    }

    public function vip(VipPatientRegistered $event): void
    {
        $event->log[] = 'attribute-vip';
    }
}

#[AsListener(method: 'untyped')]
final class UntypedListener
{
    public function untyped(mixed $event): void
    {
    }
}

#[AsListener(method: 'missing')]
final class MissingMethodListener
{
}

final class NotAListener
{
}

final class AuditSubscriber implements EventSubscriberInterface
{
    public static function subscribedEvents(): array
    {
        return [
            PatientRegistered::class => 'onRegistered',
            VipPatientRegistered::class => ['onVip', 5],
            Cancellable::class => [['first', 20], ['second']],
        ];
    }

    public function onRegistered(PatientRegistered $event): void
    {
        $event->log[] = 'audit';
    }

    public function onVip(VipPatientRegistered $event): void
    {
        $event->log[] = 'audit-vip';
    }

    public function first(Cancellable $event): void
    {
        $event->log[] = 'first';
    }

    public function second(Cancellable $event): void
    {
        $event->log[] = 'second';
    }
}
