<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture;

use DomainException;
use Storm\Aggregate\AggregateRootBehavior;
use Storm\Contracts\Aggregate\AggregateRoot;

/**
 * @implements AggregateRoot<ArticleId>
 */
final class ScenarioArticle implements AggregateRoot
{
    /** @use AggregateRootBehavior<ArticleId> */
    use AggregateRootBehavior;

    public private(set) string $title = '';

    public private(set) bool $published = false;

    public static function draft(): self
    {
        $article = new self(ArticleId::generate());
        $article->recordThat(ArticleDrafted::with($article->identity(), 'Initial'));

        return $article;
    }

    public function renameAndPublish(): void
    {
        $this->recordThat(ArticleDrafted::with($this->identity(), 'Revised'));
        $this->recordThat(ArticlePublished::with($this->identity()));
    }

    public function publish(): void
    {
        $this->recordThat(ArticlePublished::with($this->identity()));
    }

    public function publishThenReject(DomainException $rejection): void
    {
        $this->publish();

        throw $rejection;
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
