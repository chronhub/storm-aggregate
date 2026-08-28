<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture;

use Storm\Aggregate\AggregateRootBehavior;
use Storm\Aggregate\SnapshotBehavior;
use Storm\Contracts\Aggregate\SnapshotableAggregateRoot;

/**
 * The layer-supertype half of the extension-contract fixture pair: an abstract base composing the
 * behaviors, extended by ExtendedArticle. The shared aggregate shape lives here; the child proves
 * that every protected seam the traits advertise is reachable from a subclass.
 *
 * Note the class-level `@phpstan-consistent-constructor`: the traits' own tag does not traverse
 * composition, so a base aggregate declares it itself. That is part of the extension pattern:
 * subclasses keep the constructor signature, making the traits' `new static()` provably safe.
 *
 * @implements SnapshotableAggregateRoot<ArticleId>
 *
 * @phpstan-consistent-constructor
 *
 * @see ExtendedArticle
 */
abstract class ArticleBase implements SnapshotableAggregateRoot
{
    /** @use AggregateRootBehavior<ArticleId> */
    use AggregateRootBehavior;

    use SnapshotBehavior;

    protected string $title = '';

    public bool $published = false {
        get {
            return $this->published;
        }
    }

    public function publish(): void
    {
        $this->recordThat(new ArticlePublished($this->identity()->toString()));
    }

    public function title(): string
    {
        return $this->title;
    }

    public function toSnapshot(): array
    {
        return [
            self::SNAPSHOT_VERSION_KEY => static::currentSnapshotVersion(),
            'title' => $this->title,
            'published' => $this->published,
        ];
    }

    protected function restoreState(array $state): void
    {
        $this->title = (string) $state['title'];
        $this->published = (bool) $state['published'];
    }

    protected function applyArticleDrafted(ArticleDrafted $event): void
    {
        $this->title = $event->title;
    }

    protected function applyArticlePublished(ArticlePublished $event): void
    {
        $this->published = true;
    }
}
