<?php

declare(strict_types=1);

namespace Storm\Aggregate\Exception;

use LogicException;

/**
 * Thrown when `version()` is read at the transient version 0, a programming error: a factory
 * returned a constructed aggregate without recording its creation event. The whole package
 * promises a usable aggregate is always at version 1 or more, and a 0 leaking into a repository
 * would surface as an off-by-one in a stream instead of an exception.
 */
final class TransientAggregateExposed extends LogicException
{
    public static function by(string $aggregateClass, string $aggregateId): self
    {
        return new self(sprintf(
            'Aggregate %s (id %s) exposes version 0: a factory returned it without recording its creation event.',
            $aggregateClass,
            $aggregateId,
        ));
    }
}
