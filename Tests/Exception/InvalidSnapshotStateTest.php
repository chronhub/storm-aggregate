<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Storm\Aggregate\Exception\InvalidSnapshotState;

/**
 * The snapshot-state guard an app aggregate raises from its `restoreState()` when a cache row is
 * corrupt. Both factories are app-facing helpers, so the message names the field precisely enough
 * for an operator to see WHICH snapshot column drifted.
 */
final class InvalidSnapshotStateTest extends TestCase
{
    #[Test]
    public function missing_field_names_the_aggregate_and_the_field(): void
    {
        $e = InvalidSnapshotState::missingField('App\\Account', 'balance');

        $this->assertStringContainsString('App\\Account', $e->getMessage());
        $this->assertStringContainsString('missing its "balance" field', $e->getMessage());
    }

    #[Test]
    public function malformed_field_names_the_expected_and_the_actual_type(): void
    {
        $e = InvalidSnapshotState::malformedField('App\\Account', 'balance', 'int', 'string');

        $this->assertStringContainsString('malformed "balance" field', $e->getMessage());
        $this->assertStringContainsString('expected int, got string', $e->getMessage());
    }
}
