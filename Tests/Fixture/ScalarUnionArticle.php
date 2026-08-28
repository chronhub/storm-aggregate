<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture;

use Storm\Aggregate\AggregateRootBehavior;
use Storm\Contracts\Aggregate\AggregateRoot;

/**
 * A collision-guard fixture whose apply declaration carries a scalar union member beside its class.
 *
 * @implements AggregateRoot<ArticleId>
 */
final class ScalarUnionArticle implements AggregateRoot
{
    /** @use AggregateRootBehavior<ArticleId> */
    use AggregateRootBehavior;

    protected function applyArticlePublished(ArticlePublished|string $event): void {}
}
