<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Storm\Aggregate\Exception\InvalidAggregateIdentity;
use Storm\Aggregate\GenericAggregateIdV7;
use Storm\Aggregate\Tests\Fixture\ArticleId;
use Storm\Contracts\Aggregate\InvalidAggregateIdentity as InvalidAggregateIdentityContract;

final class GenericAggregateIdV7Test extends TestCase
{
    #[Test]
    public function rejects_an_invalid_string_with_the_contracted_identity_failure(): void
    {
        try {
            GenericAggregateIdV7::fromString('not-a-uuid');
            $this->fail('expected the invalid identity string to be rejected');
        } catch (InvalidAggregateIdentity $e) {
            $this->assertInstanceOf(InvalidAggregateIdentityContract::class, $e); // the port's type
            $this->assertInstanceOf(InvalidArgumentException::class, $e);         // the SPL intent, a bug-class
            $this->assertStringContainsString('not-a-uuid', $e->getMessage());
            $this->assertNotNull($e->getPrevious());                              // vendor cause preserved
        }
    }

    #[Test]
    public function generates_a_uuid_v7(): void
    {
        $id = GenericAggregateIdV7::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id->toString(),
        );
    }

    #[Test]
    public function round_trips_from_string(): void
    {
        $id = GenericAggregateIdV7::generate();
        $same = GenericAggregateIdV7::fromString($id->toString());

        $this->assertTrue($id->equals($same));
        $this->assertSame($id->toString(), (string) $same);
    }

    #[Test]
    public function is_not_equal_to_a_different_id(): void
    {
        $this->assertFalse(
            GenericAggregateIdV7::generate()->equals(GenericAggregateIdV7::generate()),
        );
    }

    #[Test]
    public function ids_of_different_types_are_never_equal_even_with_the_same_uuid(): void
    {
        // identity is a type-and-uuid pair: the same UUID worn by two id types is two different identities.
        // `equals` enforces it via `$other instanceof static`, guarding against an OrderId matching an
        // AccountId that happens to share an uuid, a cross-aggregate id collision.
        $uuid = GenericAggregateIdV7::generate()->toString();

        $generic = GenericAggregateIdV7::fromString($uuid);
        $article = ArticleId::fromString($uuid);

        $this->assertSame($generic->toString(), $article->toString()); // same underlying uuid…
        $this->assertFalse($generic->equals($article));                // …yet not equal, the type differs
        $this->assertFalse($article->equals($generic));                // and symmetric
    }

    #[Test]
    #[Group('adversarial')]
    public function hydration_refuses_a_uuid_of_another_version(): void
    {
        // the V7 in the name is an invariant, not only a generation strategy: a parsed-back id must be
        // as time-ordered as a generated one, or the class name lies after hydration
        $this->expectException(InvalidAggregateIdentity::class);

        GenericAggregateIdV7::fromString('550e8400-e29b-41d4-a716-446655440000'); // a canonical v4
    }

    #[Test]
    public function hydration_round_trips_a_generated_v7(): void
    {
        $id = GenericAggregateIdV7::generate();

        $this->assertTrue($id->equals(GenericAggregateIdV7::fromString($id->toString())));
    }
}
