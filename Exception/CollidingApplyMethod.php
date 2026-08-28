<?php

declare(strict_types=1);

namespace Storm\Aggregate\Exception;

use LogicException;

/**
 * Thrown when convention dispatch resolves an apply method declared for ANOTHER event class, the
 * loud form of a short-name collision: two event classes sharing one short name reach the same
 * `apply{ShortEventClassName}` method, and applying the wrong handler would mutate state silently
 * into a wrong durable fact. Event short names must be unique per aggregate. Control bytes in the
 * event class name are escaped, since an anonymous class name carries a NUL byte.
 */
final class CollidingApplyMethod extends LogicException
{
    public static function between(string $aggregateClass, string $eventClass, string $declaredClass, string $aggregateId, string $method): self
    {
        return new self(sprintf(
            'Aggregate %s cannot apply %s (id %s): %s() is declared for %s; event short names must be unique per aggregate.',
            $aggregateClass,
            addcslashes($eventClass, "\0..\37\177"),
            $aggregateId,
            addcslashes($method, "\0..\37\177"),
            $declaredClass,
        ));
    }
}
