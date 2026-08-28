<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests;

use Generator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Storm\Aggregate\Exception\CorruptAggregateHistory;
use Storm\Aggregate\Tests\Fixture\ArticleDrafted;
use Storm\Aggregate\Tests\Fixture\ArticleId;
use Storm\Aggregate\Tests\Fixture\ArticlePublished;
use Storm\Aggregate\Tests\Fixture\SnapshotArticle;
use Storm\Contracts\Message\DomainEvent;

final class SnapshotBehaviorTest extends TestCase
{
    #[Test]
    public function to_snapshot_exports_state_with_the_shape_version(): void
    {
        $article = SnapshotArticle::draft(ArticleId::generate(), 'Hello');

        $snapshot = $article->toSnapshot();

        $this->assertSame(1, $snapshot['_snapshot_version']);
        $this->assertSame('Hello', $snapshot['title']);
        $this->assertFalse($snapshot['published']);
    }

    #[Test]
    public function from_snapshot_restores_state_and_seeds_version(): void
    {
        $id = ArticleId::generate();
        $state = ['_snapshot_version' => 1, 'title' => 'Hello', 'published' => false];

        $article = SnapshotArticle::fromSnapshot($id, $state, 1, $this->after([], 1));

        $this->assertInstanceOf(SnapshotArticle::class, $article);
        $this->assertSame('Hello', $article->title());
        $this->assertFalse($article->published);
        $this->assertSame(1, $article->version());
        $this->assertTrue($article->identity()->equals($id));
    }

    #[Test]
    public function from_snapshot_replays_only_events_after_the_snapshot(): void
    {
        $state = ['_snapshot_version' => 1, 'title' => 'Hello', 'published' => false];

        // drafted snapshot @1, then publish @2 replayed on top
        $id = ArticleId::generate();
        $article = SnapshotArticle::fromSnapshot($id, $state, 1, $this->after([new ArticlePublished($id->toString())], 2));

        $this->assertInstanceOf(SnapshotArticle::class, $article);
        $this->assertTrue($article->published);
        $this->assertSame(2, $article->version());
    }

    #[Test]
    public function from_snapshot_does_not_record_replayed_events(): void
    {
        $id = ArticleId::generate();
        $article = SnapshotArticle::fromSnapshot(
            $id,
            ['_snapshot_version' => 1, 'title' => 'Hello', 'published' => false],
            1,
            $this->after([new ArticlePublished($id->toString())], 2),
        );

        $this->assertInstanceOf(SnapshotArticle::class, $article);
        $this->assertSame([], $article->releaseEvents());
    }

    #[Test]
    public function snapshot_plus_after_equals_full_replay(): void
    {
        $id = ArticleId::generate();

        $full = SnapshotArticle::reconstitute($id, $this->after([new ArticleDrafted($id->toString(), 'Hello'), new ArticlePublished($id->toString())], 2));

        $fromSnapshot = SnapshotArticle::fromSnapshot(
            $id,
            ['_snapshot_version' => 1, 'title' => 'Hello', 'published' => false],
            1,
            $this->after([new ArticlePublished($id->toString())], 2),
        );

        $this->assertInstanceOf(SnapshotArticle::class, $full);
        $this->assertInstanceOf(SnapshotArticle::class, $fromSnapshot);
        $this->assertSame($full->toSnapshot(), $fromSnapshot->toSnapshot());
        $this->assertSame($full->version(), $fromSnapshot->version());
    }

    #[Test]
    #[Group('adversarial')]
    public function from_snapshot_discards_a_corrupt_non_positive_stored_version(): void
    {
        $state = ['_snapshot_version' => 1, 'title' => 'Hello', 'published' => false];

        // the writer only ever snapshots at version >= 1; a row claiming less is corrupt cache, and
        // it is DISCARDED for a full replay, never clamped: a clamp would hand back a "usable"
        // aggregate at version 0, the transient the package promises to never expose
        $this->assertNull(SnapshotArticle::fromSnapshot(ArticleId::generate(), $state, -2, $this->after([], -2)));
        $this->assertNull(SnapshotArticle::fromSnapshot(ArticleId::generate(), $state, 0, $this->after([], 0)));
    }

    #[Test]
    #[Group('adversarial')]
    public function a_tail_whose_claimed_version_disagrees_with_its_count_is_corruption(): void
    {
        // seeded exactness: final version === snapshot version + applied tail events; a lagging
        // return leaves state newer than its OCC token
        $id = ArticleId::generate();
        $state = ['_snapshot_version' => 1, 'title' => 'Hello', 'published' => false];

        $this->expectException(CorruptAggregateHistory::class);
        // snapshot at v3 + 1 applied tail event, so the header must claim exactly 3 + 1; it claims 3
        $this->expectExceptionMessageIsOrContains('expected exactly 4');

        SnapshotArticle::fromSnapshot($id, $state, 3, $this->after([new ArticlePublished($id->toString())], 3));
    }

    #[Test]
    public function a_tail_advances_the_version_by_exactly_its_count(): void
    {
        $id = ArticleId::generate();
        $state = ['_snapshot_version' => 1, 'title' => 'Hello', 'published' => false];

        $article = SnapshotArticle::fromSnapshot($id, $state, 3, $this->after([new ArticlePublished($id->toString())], 4));

        $this->assertInstanceOf(SnapshotArticle::class, $article);
        $this->assertSame(4, $article->version());
        $this->assertTrue($article->published);
    }

    #[Test]
    public function from_snapshot_treats_a_pre_stamp_snapshot_as_shape_v1(): void
    {
        // legacy rows predate the _snapshot_version stamp: an absent key means shape v1, restored and
        // not discarded, mirroring the projection-generation convention where an unstamped 0 is adopted.
        $article = SnapshotArticle::fromSnapshot(
            ArticleId::generate(),
            ['title' => 'Hello', 'published' => false],
            1,
            $this->after([], 1),
        );

        $this->assertInstanceOf(SnapshotArticle::class, $article);
        $this->assertSame('Hello', $article->title());
    }

    #[Test]
    public function from_snapshot_discards_a_stale_shape(): void
    {
        // stored _snapshot_version 0 != current 1, so discarded, caller full-replays
        $article = SnapshotArticle::fromSnapshot(
            ArticleId::generate(),
            ['_snapshot_version' => 0, 'title' => 'Hello', 'published' => false],
            1,
            $this->after([], 1),
        );

        $this->assertNull($article);
    }

    /**
     * @param  array<DomainEvent>  $events
     * @return Generator<int, DomainEvent, null, int>
     */
    private function after(array $events, int $version): Generator
    {
        yield from $events;

        return $version;
    }
}
