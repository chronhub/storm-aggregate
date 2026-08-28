<?php

declare(strict_types=1);

namespace Storm\Aggregate;

use Storm\Aggregate\Exception\InvalidAggregateIdentity;
use Storm\Contracts\Aggregate\AggregateIdentity;
use Storm\Contracts\Aggregate\InvalidAggregateIdentity as InvalidAggregateIdentityContract;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * A generic UUID v7 aggregate identity, for aggregates that don't need a dedicated
 * typed id. UUID v7 is time-ordered, keeping streams roughly creation-ordered.
 *
 * The V7 in the name is an INVARIANT, not only a generation strategy: hydration refuses any other
 * UUID version, so an id of this class is time-ordered whether it was generated or parsed back.
 */
final readonly class GenericAggregateIdV7 implements AggregateIdentity
{
    use ProvideAggregateIdentity {
        fromString as private parseUuid;
    }

    public static function generate(): static
    {
        return new self(Uuid::v7());
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidAggregateIdentityContract when the string is not a valid UUID, or a valid UUID
     *                                          of a version other than 7; the class name promises
     *                                          time-ordering
     */
    public static function fromString(string $id): static
    {
        $identity = self::parseUuid($id);

        if (! $identity->id instanceof UuidV7) {
            throw InvalidAggregateIdentity::notAUuidV7($id, get_debug_type($identity->id));
        }

        return $identity;
    }
}
