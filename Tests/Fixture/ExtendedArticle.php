<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture;

use Generator;
use Override;

/**
 * The subclass half of the extension-contract fixture pair; the base is `ArticleBase`. Each member
 * calls one protected seam from the CHILD scope, proving that `protected` on the behaviors is a
 * contract, not an accident:
 *
 * - The constructor, a child factory
 * - `recordThat`, recorded from a child method
 * - `apply`, an upstream fact applied without recording
 * - `reconstituteFromSnapshot`, a child-owned restore path
 * - The `currentSnapshotVersion` override, a bumped shape
 */
final class ExtendedArticle extends ArticleBase
{
    public bool $archived = false {
        get {
            return $this->archived;
        }
    }

    public static function draft(ArticleId $id, string $title): static
    {
        $article = new self($id);
        $article->recordThat(new ArticleDrafted($id->toString(), $title));

        return $article;
    }

    /**
     * A child-owned restore path for a legacy export, not a SnapshotBehavior shape: the caller seeds
     * the state, then the protected reconstitution seam seeds the version and replays the tail.
     *
     * @param  int<1, max>  $version  the seam's own precondition; a legacy export is versioned >= 1
     */
    public static function fromLegacyState(ArticleId $id, string $title, int $version, Generator $after): static
    {
        $article = new static($id);
        $article->title = $title;
        $article->reconstituteFromSnapshot($version, $after);

        return $article;
    }

    /** An upstream fact already recorded elsewhere: applied to move state, never re-recorded. */
    public function noteUpstreamArchive(): void
    {
        $this->apply(new ArticleArchived($this->identity()->toString()));
    }

    /** The child's snapshot shape diverged from the base's, so it is bumped RELATIVE to the base's version. */
    #[Override]
    protected static function currentSnapshotVersion(): int
    {
        return parent::currentSnapshotVersion() + 1;
    }

    protected function applyArticleArchived(ArticleArchived $event): void
    {
        $this->archived = true;
    }
}
