<?php

declare(strict_types=1);

namespace Zana\Events\Tests;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Zana\Container\ServiceCollection;
use Zana\Core\Application;
use Zana\Events\EventDispatcher;
use Zana\Events\EventsException;
use Zana\Events\EventsServiceProvider;
use Zana\Events\ListenerProvider;

final class EventDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        Counter::$instances = 0;
    }

    public function testListenersRunByPriorityThenRegistrationOrder(): void
    {
        $provider = new ListenerProvider();
        $provider
            ->listen(PatientRegistered::class, static function (PatientRegistered $e): void { $e->log[] = 'low'; }, -5)
            ->listen(PatientRegistered::class, static function (PatientRegistered $e): void { $e->log[] = 'a'; })
            ->listen(PatientRegistered::class, static function (PatientRegistered $e): void { $e->log[] = 'high'; }, 10)
            ->listen(PatientRegistered::class, static function (PatientRegistered $e): void { $e->log[] = 'b'; });

        $event = (new EventDispatcher($provider))->dispatch(new PatientRegistered());

        $this->assertSame(['high', 'a', 'b', 'low'], $event->log);
    }

    public function testListenersMatchParentClassesAndInterfaces(): void
    {
        $provider = new ListenerProvider();
        $provider
            ->listen(PatientEvent::class, static function (PatientRegistered $e): void { $e->log[] = 'interface'; })
            ->listen(PatientRegistered::class, static function (PatientRegistered $e): void { $e->log[] = 'parent'; })
            ->listen(VipPatientRegistered::class, static function (PatientRegistered $e): void { $e->log[] = 'exact'; });

        $dispatcher = new EventDispatcher($provider);

        $this->assertSame(['interface', 'parent', 'exact'], $dispatcher->dispatch(new VipPatientRegistered())->log);
        $this->assertSame(['interface', 'parent'], $dispatcher->dispatch(new PatientRegistered())->log);
    }

    public function testStoppableEventsStopPropagation(): void
    {
        $provider = new ListenerProvider();
        $provider
            ->listen(Cancellable::class, static function (Cancellable $e): void { $e->log[] = 'one'; $e->stopPropagation(); })
            ->listen(Cancellable::class, static function (Cancellable $e): void { $e->log[] = 'two'; });

        $event = (new EventDispatcher($provider))->dispatch(new Cancellable());

        $this->assertSame(['one'], $event->log);
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testClassListenersAreResolvedLazilyAndReused(): void
    {
        $container = (new ServiceCollection())->transient(Counter::class)->transient(SendWelcome::class)->build();
        $provider = new ListenerProvider($container);
        $provider->listen(PatientRegistered::class, SendWelcome::class);
        $dispatcher = new EventDispatcher($provider);

        $this->assertSame(0, Counter::$instances, 'nothing is resolved at registration');

        $dispatcher->dispatch(new PatientRegistered());
        $event = $dispatcher->dispatch(new PatientRegistered());

        $this->assertSame(['welcome'], $event->log);
        $this->assertSame(1, Counter::$instances, 'the resolved listener is reused');
    }

    public function testClassAndMethodListeners(): void
    {
        $container = (new ServiceCollection())->singleton(AuditSubscriber::class)->build();
        $provider = (new ListenerProvider($container))->listen(PatientRegistered::class, [AuditSubscriber::class, 'onRegistered']);

        $this->assertSame(['audit'], (new EventDispatcher($provider))->dispatch(new PatientRegistered())->log);
    }

    public function testAttributeListeners(): void
    {
        $container = (new ServiceCollection())->singleton(AttributeListener::class)->build();
        $provider = (new ListenerProvider($container))->addListenerClass(AttributeListener::class);

        $this->assertSame(['attribute-vip', 'attribute'], (new EventDispatcher($provider))->dispatch(new VipPatientRegistered())->log);
    }

    public function testInvalidAttributeListenersAreRejected(): void
    {
        foreach ([
            [NotAListener::class, 'has no #[AsListener] attribute'],
            [UntypedListener::class, 'Cannot infer the event'],
            [MissingMethodListener::class, 'missing() does not exist'],
        ] as [$class, $message]) {
            try {
                (new ListenerProvider())->addListenerClass($class);
                $this->fail("Expected: {$message}");
            } catch (EventsException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testSubscribers(): void
    {
        $container = (new ServiceCollection())->singleton(AuditSubscriber::class)->build();
        $provider = (new ListenerProvider($container))->addSubscriber(AuditSubscriber::class);
        $dispatcher = new EventDispatcher($provider);

        $this->assertSame(['audit-vip', 'audit'], $dispatcher->dispatch(new VipPatientRegistered())->log);
        $this->assertSame(['first', 'second'], $dispatcher->dispatch(new Cancellable())->log);
    }

    public function testDispatchAll(): void
    {
        $count = 0;
        $provider = (new ListenerProvider())->listen(PatientRegistered::class, static function () use (&$count): void { $count++; });

        (new EventDispatcher($provider))->dispatchAll([new PatientRegistered(), new VipPatientRegistered()]);

        $this->assertSame(2, $count);
    }

    public function testListenerExceptionsPropagate(): void
    {
        $provider = (new ListenerProvider())->listen(PatientRegistered::class, static fn () => throw new \DomainException('listener failed'));

        $this->expectException(\DomainException::class);
        (new EventDispatcher($provider))->dispatch(new PatientRegistered());
    }

    public function testInvalidListeners(): void
    {
        try {
            /** @phpstan-ignore argument.type (an invalid listener is under test) */
            (new ListenerProvider())->listen(PatientRegistered::class, 'not_a_function_or_class');
            $this->fail('An unknown listener must be rejected.');
        } catch (EventsException $e) {
            $this->assertStringContainsString('neither a class nor a callable', $e->getMessage());
        }

        $provider = (new ListenerProvider())->listen(PatientRegistered::class, SendWelcome::class);
        $this->expectException(EventsException::class);
        $this->expectExceptionMessage('needs a container');
        (new EventDispatcher($provider))->dispatch(new PatientRegistered());
    }

    public function testTheProviderRegistersTheDispatcher(): void
    {
        $app = Application::configure(sys_get_temp_dir() . '/zana-events-app')->withProviders(EventsServiceProvider::class)->build();
        $container = $app->container();

        $container->get(ListenerProvider::class)->listen(PatientRegistered::class, static function (PatientRegistered $e): void { $e->log[] = 'wired'; });

        $dispatcher = $container->get(EventDispatcherInterface::class);
        $this->assertSame($container->get(EventDispatcher::class), $dispatcher);
        $this->assertSame(['wired'], $dispatcher->dispatch(new PatientRegistered())->log);
    }
}
