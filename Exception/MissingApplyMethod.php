<?php

declare(strict_types=1);

namespace Storm\Aggregate\Exception;

use LogicException;

/**
 * Thrown when an aggregate replays an event for which it declares no `apply{EventName}` method, a
 * programming error: an event in the aggregate's own history it cannot apply. Surfaced with the
 * aggregate, event, and id instead of an opaque "call to undefined method" Error from `apply()`.
 * Control bytes in the event class and method names are escaped, since an anonymous class name
 * carries a NUL byte and must not reach logs raw.
 */
final class MissingApplyMethod extends LogicException
{
    public static function on(string $aggregateClass, string $eventClass, string $aggregateId, string $method): self
    {
        return new self(sprintf(
            'Aggregate %s cannot apply %s (id %s): no %s() method declared.',
            $aggregateClass,
            addcslashes($eventClass, "\0..\37\177"),
            $aggregateId,
            addcslashes($method, "\0..\37\177"),
        ));
    }
}
