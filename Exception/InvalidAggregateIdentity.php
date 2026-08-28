<?php

declare(strict_types=1);

namespace Storm\Aggregate\Exception;

use InvalidArgumentException;
use Storm\Contracts\Aggregate\InvalidAggregateIdentity as InvalidAggregateIdentityContract;
use Throwable;

/**
 * The string form handed to `fromString()` is not a valid identity. The parser's own exception,
 * Symfony Uid here, is preserved as the `getPrevious()` cause and stays out of the domain.
 * Implements the contracted `InvalidAggregateIdentityContract` on an SPL `InvalidArgumentException`.
 */
final class InvalidAggregateIdentity extends InvalidArgumentException implements InvalidAggregateIdentityContract
{
    public static function notAValidUuid(string $id, Throwable $cause): self
    {
        return new self(sprintf('"%s" is not a valid aggregate identity (expected a UUID).', $id), previous: $cause);
    }

    public static function notAUuidV7(string $id, string $actualKind): self
    {
        return new self(sprintf(
            '"%s" is a valid UUID but not a v7 (%s) — this identity class promises time-ordering, so hydration refuses any other version.',
            $id,
            $actualKind,
        ));
    }
}
