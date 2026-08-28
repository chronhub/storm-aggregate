<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Storm\Aggregate\Exception\CorruptAggregateHistory;
use Storm\Aggregate\Exception\InvalidAggregateIdentity;
use Storm\AggregateRepository\Exception\AggregateTypeMismatch;
use Storm\AggregateRepository\Exception\EventAggregateIdMismatch;
use Storm\Contracts\Aggregate\AggregateExceptionContract;
use Storm\Contracts\Aggregate\AggregateTypeMismatch as AggregateTypeMismatchContract;
use Storm\Contracts\Aggregate\CorruptAggregateHistory as CorruptAggregateHistoryContract;
use Storm\Contracts\Aggregate\EventAggregateIdMismatch as EventAggregateIdMismatchContract;
use Storm\Contracts\Aggregate\InvalidAggregateIdentity as InvalidAggregateIdentityContract;

/**
 * The exception seam of the Aggregate port: the contract's throws clause names the Contracts-owned
 * interface, and the implementation hierarchy carries it; both catch altitudes must hold.
 */
final class ContractedExceptionsTest extends TestCase
{
    #[Test]
    public function a_corrupt_history_is_catchable_by_the_contracted_type(): void
    {
        $failure = CorruptAggregateHistory::claimedVersionMismatch('App\Account', 'acc-1', 1, 3);

        $this->assertInstanceOf(CorruptAggregateHistoryContract::class, $failure);
        $this->assertInstanceOf(RuntimeException::class, $failure); // the SPL intent axis kept
    }

    #[Test]
    public function an_invalid_identity_is_catchable_by_the_contracted_type(): void
    {
        $failure = InvalidAggregateIdentity::notAValidUuid('not-a-uuid', new RuntimeException('parse failed'));

        $this->assertInstanceOf(InvalidAggregateIdentityContract::class, $failure);
        $this->assertInstanceOf(InvalidArgumentException::class, $failure); // the SPL intent axis kept
    }

    #[Test]
    public function a_type_mismatch_is_catchable_by_the_contracted_type(): void
    {
        $failure = AggregateTypeMismatch::id('App\OrderId', 'App\AccountId');

        $this->assertInstanceOf(AggregateTypeMismatchContract::class, $failure);
        $this->assertInstanceOf(InvalidArgumentException::class, $failure);
    }

    #[Test]
    public function an_event_aggregate_id_mismatch_is_catchable_by_the_contracted_type(): void
    {
        $failure = EventAggregateIdMismatch::of('App\OrderPlaced', 'order-1', 'order-2');

        $this->assertInstanceOf(EventAggregateIdMismatchContract::class, $failure);
        $this->assertInstanceOf(InvalidArgumentException::class, $failure);
    }

    #[Test]
    public function every_contracted_failure_rolls_up_to_the_module_umbrella(): void
    {
        // the high-level catch net: any aggregate-domain failure IS an AggregateExceptionContract;
        // break a leaf's `extends AggregateExceptionContract` and this fails.
        $this->assertInstanceOf(AggregateExceptionContract::class, CorruptAggregateHistory::claimedVersionMismatch('App\Account', 'acc-1', 1, 3));
        $this->assertInstanceOf(AggregateExceptionContract::class, InvalidAggregateIdentity::notAValidUuid('x', new RuntimeException('y')));
        $this->assertInstanceOf(AggregateExceptionContract::class, AggregateTypeMismatch::id('App\OrderId', 'App\AccountId'));
        $this->assertInstanceOf(AggregateExceptionContract::class, EventAggregateIdMismatch::of('App\OrderPlaced', 'order-1', 'order-2'));
    }
}
