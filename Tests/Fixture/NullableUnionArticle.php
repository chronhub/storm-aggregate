<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture;

use Storm\Aggregate\AggregateRootBehavior;
use Storm\Contracts\Aggregate\AggregateRoot;

/**
 * A collision-guard fixture whose apply declaration carries a `null` union member beside its
 * classes. Three members on purpose: a two-member `X|null` collapses to the `?X` shorthand, a named
 * type, and would exercise the named branch instead of the union walk this fixture exists to reach.
 *
 * @implements AggregateRoot<ArticleId>
 */
final class NullableUnionArticle implements AggregateRoot
{
    /** @use AggregateRootBehavior<ArticleId> */
    use AggregateRootBehavior;

    protected function applyArticlePublished(ArticlePublished|ArticleArchived|null $event): void {}
}
