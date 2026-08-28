<?php

declare(strict_types=1);

namespace Storm\Aggregate;

use Override;
use Storm\Aggregate\Exception\InvalidAggregateIdentity;
use Storm\Contracts\Aggregate\AggregateIdentity;
use Storm\Contracts\Aggregate\InvalidAggregateIdentity as InvalidAggregateIdentityContract;
use Symfony\Component\Uid\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Reusable `AggregateIdentity` implementation backed by a `Uuid`; a convenience example, not a
 * mandate, since an app may implement `AggregateIdentity` directly. The `Uuid` lives outside the
 * domain, and so does its parse exception: `fromString()` translates it into the contracted
 * `InvalidAggregateIdentity` at the boundary, preserving the vendor cause.
 *
 * Construction is private, so identities are built through `fromString()` and `generate()`. Private
 * rather than protected on purpose: a typed id is a `final`, terminal class, one id per aggregate
 * and not a base to subclass, so there is no subclass to open the seam to. Apps declare a typed id
 * by using this trait and supplying a `generate()` method:
 *
 * ```php
 * final readonly class OrderId implements AggregateIdentity
 * {
 *     use ProvideAggregateIdentity;
 *
 *     public static function generate(): static
 *     {
 *         return new self(Uuid::v7());
 *     }
 * }
 * ```
 *
 * @phpstan-require-implements AggregateIdentity
 *
 * @phpstan-consistent-constructor
 */
trait ProvideAggregateIdentity
{
    private function __construct(
        public readonly Uuid $id,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @throws InvalidAggregateIdentityContract when the string is not a valid UUID; the vendor parse
     *                                          failure is kept as the cause and never surfaces to the
     *                                          caller, which catches the contracted type
     */
    public static function fromString(string $id): static
    {
        try {
            return new static(Uuid::fromString($id));
        } catch (InvalidArgumentException $e) {
            throw InvalidAggregateIdentity::notAValidUuid($id, $e);
        }
    }

    public function toString(): string
    {
        return $this->id->toRfc4122();
    }

    public function equals(AggregateIdentity $other): bool
    {
        return $other instanceof static && $this->id->equals($other->id);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }
}
