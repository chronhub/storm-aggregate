<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests;

use Generator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Storm\Aggregate\Exception\CollidingApplyMethod;
use Storm\Aggregate\Exception\CorruptAggregateHistory;
use Storm\Aggregate\Exception\MissingApplyMethod;
use Storm\Aggregate\Exception\TransientAggregateExposed;
use Storm\Aggregate\Tests\Fixture\Article;
use Storm\Aggregate\Tests\Fixture\ArticleArchived;
use Storm\Aggregate\Tests\Fixture\ArticleDrafted;
use Storm\Aggregate\Tests\Fixture\ArticleId;
use Storm\Aggregate\Tests\Fixture\ArticlePublished;
use Storm\Aggregate\Tests\Fixture\Doppelganger\ArticlePublished as DoppelgangerArticlePublished;
use Storm\Aggregate\Tests\Fixture\LenientArticle;
use Storm\Aggregate\Tests\Fixture\NullableUnionArticle;
use Storm\Aggregate\Tests\Fixture\ScalarUnionArticle;
use Storm\Aggregate\Tests\Fixture\UnionArticle;
use Storm\Aggregate\Tests\Fixture\UnrecordedArticle;
use Storm\Contracts\Message\DomainEvent;
use TypeError;

final class AggregateRootBehaviorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Recording
    // -------------------------------------------------------------------------

    #[Test]
    public function factory_records_the_creation_event_and_sets_version_to_one(): void
    {
        $article = Article::draft(ArticleId::generate(), 'Hello');

        $this->assertSame(1, $article->version());
        $this->assertSame('Hello', $article->title());
    }

    #[Test]
    public function recording_applies_state_and_increments_version(): void
    {
        $article = Article::draft(ArticleId::generate(), 'Hello');
        $article->publish();

        $this->assertSame(2, $article->version());
        $this->assertTrue($article->published);
    }

    #[Test]
    #[Group('adversarial')]
    public function reading_the_version_of_a_transient_aggregate_refuses_loud(): void
    {
        // a factory that forgot recordThat: the version 0 the package promises to never expose
        // must refuse at the read, never leak into a repository as a wrong OCC base
        $this->expectException(TransientAggregateExposed::class);

        UnrecordedArticle::broken(ArticleId::generate())->version();
    }

    #[Test]
    public function exposes_its_identity(): void
    {
        $id = ArticleId::generate();
        $article = Article::draft($id, 'Hello');

        $this->assertTrue($article->identity()->equals($id));
    }

    // -------------------------------------------------------------------------
    // releaseEvents
    // -------------------------------------------------------------------------

    #[Test]
    public function release_events_returns_recorded_then_clears(): void
    {
        $article = Article::draft(ArticleId::generate(), 'Hello');
        $article->publish();

        $events = $article->releaseEvents();

        $this->assertCount(2, $events);
        $this->assertInstanceOf(ArticleDrafted::class, $events[0]);
        $this->assertInstanceOf(ArticlePublished::class, $events[1]);
        $this->assertSame([], $article->releaseEvents());
    }

    // -------------------------------------------------------------------------
    // reconstitute
    // -------------------------------------------------------------------------

    #[Test]
    public function reconstitute_rebuilds_state_and_version_from_history(): void
    {
        $id = ArticleId::generate();
        $article = Article::reconstitute(
            $id,
            $this->history([new ArticleDrafted($id->toString(), 'Hello'), new ArticlePublished($id->toString())], 2),
        );

        $this->assertInstanceOf(Article::class, $article);
        $this->assertSame('Hello', $article->title());
        $this->assertTrue($article->published);
        $this->assertSame(2, $article->version());
    }

    #[Test]
    public function reconstitute_does_not_record_replayed_events(): void
    {
        $id = ArticleId::generate();
        $article = Article::reconstitute(
            $id,
            $this->history([new ArticleDrafted($id->toString(), 'Hello')], 1),
        );

        $this->assertInstanceOf(Article::class, $article);
        $this->assertSame([], $article->releaseEvents());
    }

    #[Test]
    public function reconstitute_returns_null_for_empty_history(): void
    {
        $article = Article::reconstitute(ArticleId::generate(), $this->history([], 0));

        $this->assertNull($article);
    }

    #[Test]
    public function reconstitute_throws_for_an_event_with_no_apply_method(): void
    {
        $this->expectException(MissingApplyMethod::class);

        $id = ArticleId::generate();
        Article::reconstitute($id, $this->history([new ArticleArchived($id->toString())], 1));
    }

    #[Test]
    #[Group('adversarial')]
    public function reconstitute_rejects_any_nonzero_claimed_version_on_an_empty_history(): void
    {
        // no events yet a claimed version, positive OR negative, is a history that contradicts
        // itself: accepting it would resurrect a phantom aggregate its stream disproves. A positive
        // claim hands back a USABLE aggregate at that version, with zero state applied.
        $this->expectException(CorruptAggregateHistory::class);

        Article::reconstitute(ArticleId::generate(), $this->history([], 5));
    }

    #[Test]
    #[Group('adversarial')]
    public function reconstitute_rejects_a_negative_claimed_version_on_an_empty_history(): void
    {
        $this->expectException(CorruptAggregateHistory::class);

        Article::reconstitute(ArticleId::generate(), $this->history([], -1));
    }

    #[Test]
    #[Group('adversarial')]
    public function reconstitute_throws_when_the_version_header_is_above_the_applied_event_count(): void
    {
        // the direction the contract always called corruption: a header above the applied count
        // means events are missing, and a decision on the partial state could pass the store's CAS
        // and become a durable fact
        $this->expectException(CorruptAggregateHistory::class);

        $id = ArticleId::generate();
        Article::reconstitute($id, $this->history([new ArticleDrafted($id->toString(), 'partial')], 5));
    }

    #[Test]
    public function reconstitute_throws_when_the_version_header_is_below_the_applied_event_count(): void
    {
        $this->expectException(CorruptAggregateHistory::class);

        // two events applied, but the trailing header claims version 1, a malformed / truncated history.
        $id = ArticleId::generate();
        Article::reconstitute(
            $id,
            $this->history([new ArticleDrafted($id->toString(), 'Hello'), new ArticlePublished($id->toString())], 1),
        );
    }

    // -------------------------------------------------------------------------
    // apply dispatch on the short class name
    // -------------------------------------------------------------------------

    #[Test]
    #[Group('adversarial')]
    public function a_short_name_collision_refuses_loud_instead_of_applying_the_wrong_handler(): void
    {
        // Doppelganger\ArticlePublished shares its short name with the fixture event, so dispatch
        // resolves applyArticlePublished(), declared for the OTHER class: refusing here is what
        // keeps a colliding event from mutating state silently into a wrong durable fact
        $this->expectException(CollidingApplyMethod::class);
        $this->expectExceptionMessageIsOrContains('event short names must be unique per aggregate');

        $id = ArticleId::generate();
        Article::reconstitute(
            $id,
            $this->history([new ArticleDrafted($id->toString(), 'Hello'), new DoppelgangerArticlePublished($id->toString())], 2),
        );
    }

    #[Test]
    #[Group('adversarial')]
    public function a_union_typed_apply_method_keeps_the_collision_inside_the_aggregate_error_contract(): void
    {
        // a union declaration joins the collision guard like a named one: a doppelganger matching
        // no member is refused as CollidingApplyMethod, never a raw TypeError from invocation
        $this->expectException(CollidingApplyMethod::class);

        $id = ArticleId::generate();
        UnionArticle::reconstitute(
            $id,
            $this->history([new DoppelgangerArticlePublished($id->toString())], 1),
        );
    }

    #[Test]
    #[Group('adversarial')]
    public function a_nullable_union_apply_method_still_guards_its_class_members(): void
    {
        // a `null` member is skipped, never a reason to disarm: an applied event is never null, so
        // the remaining class members keep the collision guard armed
        $this->expectException(CollidingApplyMethod::class);

        $id = ArticleId::generate();
        NullableUnionArticle::reconstitute(
            $id,
            $this->history([new DoppelgangerArticlePublished($id->toString())], 1),
        );
    }

    #[Test]
    #[Group('adversarial')]
    public function a_scalar_union_member_returns_the_judgment_to_invocation(): void
    {
        // the guard's other declared boundary: a scalar member widens the declaration beyond what
        // the guard can hold whole, so invocation judges, exactly as for an untyped parameter
        $this->expectException(TypeError::class);

        $id = ArticleId::generate();
        ScalarUnionArticle::reconstitute(
            $id,
            $this->history([new DoppelgangerArticlePublished($id->toString())], 1),
        );
    }

    #[Test]
    public function an_untyped_apply_parameter_applies_as_declared(): void
    {
        // the guard's declared boundary: an untyped parameter cannot be told apart from a
        // collision, so dispatch trusts the declaration
        $id = ArticleId::generate();
        $article = LenientArticle::reconstitute($id, $this->history([new ArticleDrafted($id->toString(), 'Hello')], 1));

        $this->assertInstanceOf(LenientArticle::class, $article);
        $this->assertSame('Hello', $article->title);
    }

    #[Test]
    public function a_parameterless_apply_method_applies_as_declared(): void
    {
        $id = ArticleId::generate();
        $article = LenientArticle::reconstitute($id, $this->history([new ArticleArchived($id->toString())], 1));

        $this->assertInstanceOf(LenientArticle::class, $article);
        $this->assertTrue($article->archived);
    }

    #[Test]
    #[Group('adversarial')]
    public function an_anonymous_event_class_fails_loud_without_raw_control_bytes(): void
    {
        // an anonymous class name carries a NUL byte after its @anonymous marker; the diagnostic
        // escapes it rather than letting it reach logs raw
        $id = ArticleId::generate();
        $event = new class($id->toString()) implements DomainEvent
        {
            public function __construct(
                public string $articleId,
            ) {}

            public function aggregateId(): string
            {
                return $this->articleId;
            }

            public function toPayload(): array
            {
                return ['article_id' => $this->articleId];
            }

            public static function fromPayload(array $payload): static
            {
                return new self((string) $payload['article_id']);
            }
        };

        try {
            Article::reconstitute($id, $this->history([$event], 1));
            $this->fail('expected MissingApplyMethod');
        } catch (MissingApplyMethod $e) {
            $this->assertStringNotContainsString("\0", $e->getMessage());
            $this->assertStringContainsString('\000', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<DomainEvent>  $events
     * @return Generator<DomainEvent>
     */
    private function history(array $events, int $version): Generator
    {
        yield from $events;

        return $version;
    }
}
