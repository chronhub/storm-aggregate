# Storm Aggregate

Event-sourced **aggregate roots** and typed **identities** — pure domain, fully
framework-agnostic (no `Message`, no headers, no transport; the repository handles
persistence). You reach for it in every aggregate you write: the behavior trait, the
identity trait, and the snapshot opt-in are the whole surface.

## Install

```bash
composer require chronhub/storm-aggregate
```

## Aggregate root

Use the `AggregateRootBehavior` trait. Construction is **protected** — aggregates are
created through business static factories (which record the creation event) or
reconstituted, so a usable aggregate is always at version ≥ 1.

```php
use Storm\Aggregate\AggregateRootBehavior;
use Storm\Contracts\Aggregate\AggregateRoot;

/** @implements AggregateRoot<OrderId> */
final class Order implements AggregateRoot
{
    /** @use AggregateRootBehavior<OrderId> */
    use AggregateRootBehavior;

    public static function place(OrderId $id, Money $total): self
    {
        $order = new self($id);
        $order->recordThat(new OrderPlaced($total));   // records + applies + version++

        return $order;
    }

    protected function applyOrderPlaced(OrderPlaced $event): void
    {
        // mutate state…
    }
}
```

- `apply` dispatches **by convention** to `apply{ShortEventClassName}` (**strict**: a
  missing method is a fail-fast error). The short name is the dispatch key, so event
  **basenames must be unique per aggregate**: two events named `Created` in different
  namespaces cannot have two distinct apply methods — an evolution cost to weigh when
  naming events.
- `recordThat()` applies + buffers + bumps the version; `releaseEvents()` returns and
  clears the buffer (the repository persists them — destructively: an instance whose
  store threw is spent, reload and re-decide); `reconstitute(id, history)` rebuilds
  from events (the claimed version must account for every applied event **exactly**,
  `null` if empty).
- `version(): positive-int` — also the **OCC token** at append time.

## Identity

```php
use Storm\Aggregate\ProvideAggregateIdentity;
use Storm\Contracts\Aggregate\AggregateIdentity;
use Symfony\Component\Uid\Uuid;

final readonly class OrderId implements AggregateIdentity
{
    use ProvideAggregateIdentity; // ctor + fromString/toString/equals/__toString

    public static function generate(): static
    {
        return new self(Uuid::v7());
    }
}
```

`GenericAggregateIdV7` is provided for aggregates that don't need a dedicated id.

## Snapshots (opt-in)

Implement `SnapshotableAggregateRoot` (Contracts) and add the `SnapshotBehavior` trait alongside
`AggregateRootBehavior`: the aggregate exports its state with `toSnapshot()`, imports it with a
strict `restoreState()`, and bumps `currentSnapshotVersion()` whenever the state shape changes
incompatibly — an older snapshot is then discarded and the repository falls back to a full replay.

`restoreState()` validates strictly on purpose: a snapshot is only a cache, so a missing or
mistyped field is a corrupt cache row, not a value to coerce. Throw the contracted
`InvalidSnapshotState` and the repository converts it into a cache miss; anything else (a
`TypeError`, a logic bug) stays loud.

## Repository

Persistence lives in a separate package, `chronhub/storm-aggregate-repository`: it depends on both
this package and the Chronicler, so neither depends on the other. It loads and stores aggregate
roots under optimistic concurrency and adds snapshot-accelerated reads.

## Resources

This package is developed in the `chronhub/storm` monorepo; a standalone repository for it is a
READ-ONLY subtree split. Report issues and open pull requests on the monorepo, where the tests,
the architecture gates and the full internal documentation live.

---

*Pre-version: this package changes without deprecation cycles — pin a commit if you need
stability, expect resets rather than migrations until the first tagged version.*
