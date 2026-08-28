<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture;

use Storm\Aggregate\AggregateRootBehavior;
use Storm\Contracts\Aggregate\AggregateRoot;

/**
 * A deliberately broken factory: it returns the constructed aggregate without recording the
 * creation event, exposing the transient version 0 the trait must refuse to read.
 *
 * @implements AggregateRoot<ArticleId>
 */
final class UnrecordedArticle implements AggregateRoot
{
    /** @use AggregateRootBehavior<ArticleId> */
    use AggregateRootBehavior;

    public static function broken(ArticleId $id): self
    {
        return new self($id);
    }
}
