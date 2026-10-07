<?php

declare(strict_types=1);

namespace Storm\Aggregate\Testing;

use Storm\Contracts\Aggregate\AggregateIdentity;
use Storm\Contracts\Aggregate\AggregateRoot;
use Storm\Contracts\Message\DomainEvent;
use Throwable;

/**
 * Outcome of one `AggregateScenario` action, inspectable with plain assertions.
 *
 * @template T of AggregateRoot<AggregateIdentity>
 */
final readonly class AggregateScenarioResult
{
    /**
     * @param  T  $aggregate
     * @param  list<DomainEvent>  $events
     */
    public function __construct(
        public AggregateRoot $aggregate,
        /** The events the action recorded, oldest first, preparation events excluded. */
        public array $events,
        /** The expected rejection the action threw, null when it completed. */
        public ?Throwable $rejection,
    ) {}
}
