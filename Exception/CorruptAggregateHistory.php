<?php

declare(strict_types=1);

namespace Storm\Aggregate\Exception;

use RuntimeException;
use Storm\Contracts\Aggregate\CorruptAggregateHistory as CorruptAggregateHistoryContract;

/**
 * The replay-path corruption raised by the aggregate's own exactness check, on both read paths: a
 * full replay, where the trailing version header must equal the number of applied events, and a
 * snapshot tail, where it must equal the snapshot version plus the applied tail count.
 *
 * Surfaced at reconstitution, the cause, rather than laundered into a phantom aggregate or a wrong
 * optimistic-concurrency base that would only fail one store later.
 */
final class CorruptAggregateHistory extends RuntimeException implements CorruptAggregateHistoryContract
{
    public static function claimedVersionMismatch(string $aggregateClass, string $aggregateId, int $claimed, int $applied): self
    {
        return new self(sprintf(
            'Aggregate %s (id %s) has a corrupt history: the version header claims %d but %d event(s) were applied — a full replay must account for every event exactly.',
            $aggregateClass,
            $aggregateId,
            $claimed,
            $applied,
        ));
    }

    public static function emptyHistoryClaimingVersion(string $aggregateClass, string $aggregateId, int $claimed): self
    {
        return new self(sprintf(
            'Aggregate %s (id %s) has a corrupt history: no event was applied yet the version header claims %d — accepting it would resurrect a phantom aggregate its stream disproves.',
            $aggregateClass,
            $aggregateId,
            $claimed,
        ));
    }

    public static function snapshotTailMismatch(string $aggregateClass, string $aggregateId, int $snapshotVersion, int $claimed, int $applied): self
    {
        return new self(sprintf(
            'Aggregate %s (id %s) has a corrupt snapshot tail: the snapshot is at version %d and %d tail event(s) were applied, but the version header claims %d — expected exactly %d.',
            $aggregateClass,
            $aggregateId,
            $snapshotVersion,
            $applied,
            $claimed,
            $snapshotVersion + $applied,
        ));
    }
}
