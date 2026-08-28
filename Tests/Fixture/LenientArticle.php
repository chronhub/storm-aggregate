<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture;

use Storm\Aggregate\AggregateRootBehavior;
use Storm\Contracts\Aggregate\AggregateRoot;

/**
 * The declared boundary of the collision guard, on purpose: an untyped apply parameter and a
 * parameterless apply method cannot be told apart from a collision, so dispatch trusts the
 * declaration and applies.
 *
 * @implements AggregateRoot<ArticleId>
 */
final class LenientArticle implements AggregateRoot
{
    /** @use AggregateRootBehavior<ArticleId> */
    use AggregateRootBehavior;

    public string $title = '';

    public bool $archived = false;

    /**
     * @param  ArticleDrafted  $event
     */
    protected function applyArticleDrafted($event): void
    {
        $this->title = $event->title;
    }

    protected function applyArticleArchived(): void
    {
        $this->archived = true;
    }
}
