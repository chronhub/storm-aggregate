<?php

declare(strict_types=1);

namespace Storm\Aggregate;

use Generator;
use Storm\Aggregate\Exception\CollidingApplyMethod;
use Storm\Aggregate\Exception\MissingApplyMethod;
use Storm\Contracts\Aggregate\AggregateIdentity;
use Storm\Contracts\Aggregate\InvalidSnapshotState;
use Storm\Contracts\Aggregate\SnapshotableAggregateRoot;
use Storm\Contracts\Message\DomainEvent;

/**
 * Default `SnapshotableAggregateRoot` reconstruction. Use alongside `AggregateRootBehavior`, which
 * provides `reconstituteFromSnapshot`; seed the version, then replay the later events.
 *
 * The aggregate implements `SnapshotableAggregateRoot::toSnapshot()` for export and the protected
 * `restoreState()` for import, and overrides `currentSnapshotVersion()` whenever the state shape
 * changes incompatibly; a snapshot whose stored `_snapshot_version` no longer matches is discarded,
 * so `fromSnapshot()` returns null and the repository falls back to a full event replay.
 *
 * @phpstan-require-implements SnapshotableAggregateRoot
 *
 * @see AggregateRootBehavior
 */
trait SnapshotBehavior
{
    /**
     * Restore the aggregate's fields from a snapshot state array, the inverse of
     * `SnapshotableAggregateRoot::toSnapshot()`. The identity is already set by the constructor, and
     * the version is seeded by `fromSnapshot()`.
     *
     * Validate strictly: a snapshot is only a cache, so a missing or mistyped field is a corrupt
     * cache row, not a value to coerce. Throw an implementation of the `InvalidSnapshotState`
     * contract and the snapshot repository converts it into a cache miss, replaying the
     * authoritative events instead. Anything ELSE thrown here, a `TypeError` or a logic bug, stays
     * loud on purpose: only the declared contract means "discard the cache".
     *
     * @param  array<string, mixed>  $state
     *
     * @throws InvalidSnapshotState when the state bag is semantically unusable, converted to a cache miss
     */
    abstract protected function restoreState(array $state): void;

    /**
     * Seed version and replay post-snapshot events; provided by `AggregateRootBehavior`.
     *
     * @param  int<1, max>  $version
     * @param  Generator<int, DomainEvent, null, int>  $after
     */
    abstract protected function reconstituteFromSnapshot(int $version, Generator $after): void;

    /**
     * The current snapshot-state shape version. Override and bump it whenever `toSnapshot()` or
     * `restoreState()` change incompatibly, so older snapshots are discarded rather than misread.
     *
     * @see \Storm\Contracts\Aggregate\SnapshotableAggregateRoot::toSnapshot()
     */
    protected static function currentSnapshotVersion(): int
    {
        return 1;
    }

    /**
     * {@inheritDoc}
     *
     * @throws MissingApplyMethod when a replayed post-snapshot event has no apply method on this aggregate
     * @throws CollidingApplyMethod when a replayed post-snapshot event resolves to an apply method
     *                              declared for another event class
     */
    public static function fromSnapshot(AggregateIdentity $aggregateId, array $state, int $version, Generator $after): ?static
    {
        // strict on purpose, and the value is expected to survive the store's encoding as an INT: a
        // driver handing back numeric strings would turn every snapshot into a silent always-miss
        if (($state[SnapshotableAggregateRoot::SNAPSHOT_VERSION_KEY] ?? 1) !== static::currentSnapshotVersion()) {
            return null; // stale shape, so caller reconstitutes from the full history
        }

        // The writer only ever snapshots at version >= 1, so a row claiming less is corrupt cache,
        // not state to restore. Discarded like a stale shape, never clamped: a clamp would hand back
        // a "usable" aggregate at version 0, the transient the whole package promises to never expose.
        if ($version < 1) {
            return null;
        }

        $aggregate = new static($aggregateId); // @phpstan-ignore argument.type
        $aggregate->restoreState($state);
        $aggregate->reconstituteFromSnapshot($version, $after);

        return $aggregate;
    }
}
