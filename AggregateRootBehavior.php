<?php

declare(strict_types=1);

namespace Storm\Aggregate;

use Generator;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Storm\Aggregate\Exception\CollidingApplyMethod;
use Storm\Aggregate\Exception\CorruptAggregateHistory;
use Storm\Aggregate\Exception\MissingApplyMethod;
use Storm\Aggregate\Exception\TransientAggregateExposed;
use Storm\Contracts\Aggregate\AggregateIdentity;
use Storm\Contracts\Aggregate\AggregateRoot;
use Storm\Contracts\Aggregate\CorruptAggregateHistory as CorruptAggregateHistoryContract;
use Storm\Contracts\Message\DomainEvent;

/**
 * Default behavior for an `AggregateRoot`: protected construction, event recording and applying by
 * convention, and reconstitution from history.
 *
 * Pure domain; it never touches `Message`, headers, or the transport. Construction is protected on
 * purpose: aggregates are created through business static factories that record the creation event,
 * or reconstituted, so a usable aggregate is always at version 1 or more; the transient 0 is never
 * exposed. State mutations live in `apply{ShortEventClassName}` methods, dispatched by convention;
 * a missing one is a fail-fast error. The dispatch key is the SHORT class name, so one aggregate's
 * event short names must be unique: two classes sharing a short name resolve to the same method,
 * refused loud when that method types its event parameter.
 *
 * @template TId of AggregateIdentity
 *
 * @phpstan-require-implements AggregateRoot<TId>
 *
 * @phpstan-consistent-constructor
 */
trait AggregateRootBehavior
{
    /** @var int<0, max> */
    private int $version = 0;

    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /** @var array<string, array{string, non-empty-list<string>|null}> per event class: the resolved apply method and its declared event classes, named or union, null when the declaration holds no comparable class */
    private static array $applyTargets = [];

    /**
     * @param  TId  $identity
     */
    protected function __construct(
        private readonly AggregateIdentity $identity,
    ) {}

    public function identity(): AggregateIdentity
    {
        return $this->identity;
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransientAggregateExposed when read at the transient version 0, exposed by a factory
     *                                   that returned without recording its creation event
     */
    public function version(): int
    {
        if ($this->version === 0) {
            throw TransientAggregateExposed::by(static::class, $this->identity->toString());
        }

        return $this->version;
    }

    /**
     * {@inheritDoc}
     *
     * @internal repository-facing plumbing
     */
    public function releaseEvents(): array
    {
        $released = $this->recordedEvents;
        $this->recordedEvents = [];

        return $released;
    }

    /**
     * {@inheritDoc}
     *
     * @throws MissingApplyMethod when a replayed event has no apply method on this aggregate
     * @throws CollidingApplyMethod when a replayed event resolves to an apply method declared for
     *                              another event class
     *
     * @internal repository-facing plumbing
     */
    public static function reconstitute(AggregateIdentity $aggregateId, Generator $events): ?static
    {
        $aggregate = new static($aggregateId); // @phpstan-ignore argument.type

        $applied = 0;
        foreach ($events as $event) {
            $aggregate->apply($event);
            $applied++;
        }

        $claimed = $events->getReturn();

        // The claimed version must account for every applied event EXACTLY. Below is a tampered or
        // over-applied history; above means events are missing, and a decision on that partial state
        // could pass the store's CAS and become a durable fact. Both fail loud at the cause, never
        // laundered into a phantom aggregate or a wrong OCC base that only explodes one store later.
        if ($applied === 0) {
            if ($claimed !== 0) {
                throw CorruptAggregateHistory::emptyHistoryClaimingVersion(static::class, $aggregateId->toString(), $claimed);
            }

            return null; // a genuinely absent aggregate, the empty stream and its version agree
        }

        if ($claimed !== $applied) {
            throw CorruptAggregateHistory::claimedVersionMismatch(static::class, $aggregateId->toString(), $claimed, $applied);
        }

        $aggregate->version = $applied;

        return $aggregate;
    }

    /**
     * Seed the version after a snapshot restore, then replay the post-snapshot events,
     * applied but never re-recorded. The `SnapshotBehavior` reconstruction path uses this;
     * `$after`'s return value is the aggregate's true current version, provided by the
     * repository.
     *
     * The same exactness as the full replay, seeded: the claimed final version must equal the
     * snapshot version plus every applied tail event; no clamping, no `max()` that would mask a
     * lagging or runaway header. A non-positive seed never reaches here: `fromSnapshot` discards
     * such a row as a cache miss.
     *
     * @param  int<1, max>  $version  the snapshot's stored version, guarded positive by `fromSnapshot`
     * @param  Generator<int, DomainEvent, null, int>  $after  the return value is the aggregate's
     *                                                         true current version
     *
     * @throws CorruptAggregateHistoryContract when the claimed final version does not equal the
     *                                         snapshot version plus the applied tail count exactly
     * @throws MissingApplyMethod when a replayed event has no apply method on this aggregate
     * @throws CollidingApplyMethod when a replayed event resolves to an apply method declared for
     *                              another event class
     *
     * @see SnapshotBehavior
     */
    protected function reconstituteFromSnapshot(int $version, Generator $after): void
    {
        $applied = 0;
        foreach ($after as $event) {
            $this->apply($event);
            $applied++;
        }

        $claimed = $after->getReturn();

        if ($claimed !== $version + $applied) {
            throw CorruptAggregateHistory::snapshotTailMismatch(static::class, $this->identity->toString(), $version, $claimed, $applied);
        }

        $this->version = $version + $applied;
    }

    protected function recordThat(DomainEvent $event): void
    {
        $this->apply($event);

        $this->version++;

        $this->recordedEvents[] = $event;
    }

    /**
     * Dispatch the event to its `apply{ShortEventClassName}` method.
     *
     * The dispatch key is the short class name, so the short names of one aggregate's events must
     * be unique: two classes sharing one short name resolve to the same method. When that method
     * declares its event parameter, the collision refuses loud here instead of surfacing as a raw
     * `TypeError`; an untyped or wide-typed parameter cannot be told apart and applies as declared.
     * The declared parameter, not the first event seen, anchors the refusal on purpose: it is
     * deterministic under any arrival order and always names the right culprit.
     *
     * Method and declared class resolve ONCE per event class; the replay hot path pays one lookup
     * and one `instanceof`, never the name derivation or the reflection again.
     *
     * @throws MissingApplyMethod when no apply method is declared for the event
     * @throws CollidingApplyMethod when the resolved method is declared for another event class
     */
    protected function apply(DomainEvent $event): void
    {
        $key = static::class.'|'.$event::class;

        if (! isset(self::$applyTargets[$key])) {
            $parts = explode('\\', $event::class);
            $method = 'apply'.end($parts);

            if (! method_exists($this, $method)) {
                throw MissingApplyMethod::on(static::class, $event::class, $this->identity->toString(), $method);
            }

            $type = (new ReflectionMethod($this, $method)->getParameters()[0] ?? null)?->getType();
            self::$applyTargets[$key] = [$method, self::declaredEventClasses($type)];
        }

        [$method, $declared] = self::$applyTargets[$key];

        if ($declared !== null && ! array_any($declared, static fn (string $class): bool => $event instanceof $class)) {
            throw CollidingApplyMethod::between(static::class, $event::class, implode('|', $declared), $this->identity->toString(), $method);
        }

        $this->{$method}($event);
    }

    /**
     * The collision guard's comparison set: the classes an apply parameter declares, one for a named
     * type, each class member for a union. A `null` member is skipped, since an applied event is
     * never null. Returns null when no comparable class remains, an untyped or builtin-typed
     * parameter, a scalar union member, or an intersection member the guard cannot hold whole;
     * invocation is then the only check left.
     *
     * @return non-empty-list<string>|null
     */
    private static function declaredEventClasses(?ReflectionType $type): ?array
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->isBuiltin() ? null : [$type->getName()];
        }

        if (! $type instanceof ReflectionUnionType) {
            return null;
        }

        $classes = [];

        foreach ($type->getTypes() as $member) {
            if (! $member instanceof ReflectionNamedType || ($member->isBuiltin() && $member->getName() !== 'null')) {
                return null;
            }

            if (! $member->isBuiltin()) {
                $classes[] = $member->getName();
            }
        }

        return $classes === [] ? null : $classes;
    }
}
