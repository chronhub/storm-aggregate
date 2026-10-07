<?php

declare(strict_types=1);

namespace Storm\Aggregate\Testing;

use Closure;
use Storm\Contracts\Aggregate\AggregateIdentity;
use Storm\Contracts\Aggregate\AggregateRoot;
use Throwable;

/**
 * Runs one decision against a prepared aggregate and captures only the events that decision records.
 *
 * The aggregate comes from business factories and calls, never from a replayed history. Its
 * preparation events are released once and discarded, so the result holds the action's events
 * alone, in recording order. A rejection is never a rollback: events recorded before an expected
 * rejection stay in the result, and the returned instance keeps every mutation the action applied.
 */
final class AggregateScenario
{
    /**
     * Prepares the aggregate, invokes the action once, then releases its new events.
     *
     * Preparation runs outside the rejection handling, so a preparation failure always escapes
     * unchanged. An action throwable matches the expected rejection by `instanceof`; any other one
     * escapes unchanged.
     *
     * @template T of AggregateRoot<AggregateIdentity>
     *
     * @param  Closure(): T  $prepare
     * @param  Closure(T): void  $action
     * @param  class-string<Throwable>|null  $expectedRejection
     * @return AggregateScenarioResult<T>
     *
     * @throws ExpectedRejectionNotThrown when a rejection is expected and the action completes
     * @throws Throwable when the preparation or the action throws anything but the expected
     *                   rejection; both are arbitrary closures, their failures are unnameable here
     */
    public static function run(Closure $prepare, Closure $action, ?string $expectedRejection = null): AggregateScenarioResult
    {
        $aggregate = $prepare();
        $aggregate->releaseEvents();

        try {
            $action($aggregate);
        } catch (Throwable $rejection) {
            if ($expectedRejection === null || ! $rejection instanceof $expectedRejection) {
                throw $rejection;
            }

            return new AggregateScenarioResult($aggregate, $aggregate->releaseEvents(), $rejection);
        }

        if ($expectedRejection !== null) {
            throw ExpectedRejectionNotThrown::for($expectedRejection);
        }

        return new AggregateScenarioResult($aggregate, $aggregate->releaseEvents(), null);
    }
}
