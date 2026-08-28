<?php

declare(strict_types=1);

namespace Storm\Aggregate\Exception;

use RuntimeException;
use Storm\Contracts\Aggregate\InvalidSnapshotState as InvalidSnapshotStateContract;

/**
 * Default implementation of the snapshot-restoration failure: thrown by an aggregate's
 * `restoreState()` when the stored state bag is semantically unusable, and converted into a cache
 * miss by the snapshot repository; the authoritative events replay instead. An app may throw its
 * own implementation of the contract; this one covers the common cases.
 */
final class InvalidSnapshotState extends RuntimeException implements InvalidSnapshotStateContract
{
    public static function missingField(string $aggregateClass, string $field): self
    {
        return new self(sprintf(
            'The snapshot state of %s is missing its "%s" field — a corrupt cache row, discarded for a full replay.',
            $aggregateClass,
            $field,
        ));
    }

    public static function malformedField(string $aggregateClass, string $field, string $expectedType, string $actualType): self
    {
        return new self(sprintf(
            'The snapshot state of %s carries a malformed "%s" field: expected %s, got %s — a corrupt cache row, discarded for a full replay.',
            $aggregateClass,
            $field,
            $expectedType,
            $actualType,
        ));
    }
}
