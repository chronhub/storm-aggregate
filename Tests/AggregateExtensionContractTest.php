<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests;

use Generator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Storm\Aggregate\Tests\Fixture\ArticleBase;
use Storm\Aggregate\Tests\Fixture\ArticleId;
use Storm\Aggregate\Tests\Fixture\ArticlePublished;
use Storm\Aggregate\Tests\Fixture\ExtendedArticle;
use Storm\Contracts\Message\DomainEvent;

/**
 * Pins the EXTENSION contract of the behaviors: an aggregate may extend a base class (layer
 * supertype) that composes AggregateRootBehavior / SnapshotBehavior, and every protected seam the
 * traits advertise stays reachable from the child scope. Tightening any of them to private would
 * break exactly one test here; the visibility is a contract, not an accident.
 *
 * @see ArticleBase
 */
final class AggregateExtensionContractTest extends TestCase
{
    #[Test]
    public function a_child_factory_constructs_and_records_through_the_inherited_seams(): void
    {
        $id = ArticleId::generate();

        $article = ExtendedArticle::draft($id, 'Hello');

        $this->assertSame('Hello', $article->title());
        $this->assertSame(1, $article->version());
        $this->assertCount(1, $article->releaseEvents());
        $this->assertTrue($article->identity()->equals($id));
    }

    #[Test]
    public function a_child_applies_an_upstream_fact_without_recording_it(): void
    {
        $article = ExtendedArticle::draft(ArticleId::generate(), 'Hello');
        $article->releaseEvents();

        $article->noteUpstreamArchive();

        $this->assertTrue($article->archived);
        $this->assertSame(1, $article->version());
        $this->assertSame([], $article->releaseEvents());
    }

    #[Test]
    public function a_child_restore_path_reaches_the_reconstitution_seam(): void
    {
        $id = ArticleId::generate();
        $article = ExtendedArticle::fromLegacyState(
            $id,
            'Hello',
            3,
            $this->after([new ArticlePublished($id->toString())], 4),
        );

        $this->assertSame('Hello', $article->title());
        $this->assertTrue($article->published);
        $this->assertSame(4, $article->version());
        $this->assertSame([], $article->releaseEvents());
    }

    #[Test]
    public function a_child_snapshot_shape_override_accepts_its_bumped_shape(): void
    {
        $article = ExtendedArticle::fromSnapshot(
            ArticleId::generate(),
            ['_snapshot_version' => 2, 'title' => 'Hello', 'published' => false],
            1,
            $this->after([], 1),
        );

        $this->assertInstanceOf(ExtendedArticle::class, $article);
        $this->assertSame('Hello', $article->title());
    }

    #[Test]
    public function a_child_snapshot_shape_override_discards_the_base_shape(): void
    {
        // the base shape v1 is stale FOR THE CHILD; its override v2 governs the discard decision
        $article = ExtendedArticle::fromSnapshot(
            ArticleId::generate(),
            ['_snapshot_version' => 1, 'title' => 'Hello', 'published' => false],
            1,
            $this->after([], 1),
        );

        $this->assertNull($article);
    }

    /**
     * @param  array<DomainEvent>  $events
     * @return Generator<DomainEvent>
     */
    private function after(array $events, int $version): Generator
    {
        yield from $events;

        return $version;
    }
}
