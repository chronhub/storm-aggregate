<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture;

use Storm\Aggregate\AggregateRootBehavior;
use Storm\Contracts\Aggregate\AggregateRoot;

/**
 * A collision-guard fixture whose apply declaration is a union rather than a named type.
 *
 * @implements AggregateRoot<ArticleId>
 */
final class UnionArticle implements AggregateRoot
{
    /** @use AggregateRootBehavior<ArticleId> */
    use AggregateRootBehavior;

    protected function applyArticlePublished(ArticlePublished|ArticleArchived $event): void {}
}
