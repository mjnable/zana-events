<?php

declare(strict_types=1);

namespace Zana\Events;

use Attribute;

/**
 * Declare a listener class, read by ListenerProvider::addListenerClass().
 * Without an event, the type of the method's first parameter is used.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class AsListener
{
    /**
     * @param class-string|null $event
     */
    public function __construct(
        public readonly ?string $event = null,
        public readonly int $priority = 0,
        public readonly string $method = '__invoke',
    ) {
    }
}
