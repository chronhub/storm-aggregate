<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture;

use Storm\Contracts\Message\DomainEvent;
use Storm\Message\HasConstructablePayload;

final class ArticlePublished implements DomainEvent
{
    /** @use HasConstructablePayload<array{article_id: string}> */
    use HasConstructablePayload;

    public function aggregateId(): string
    {
        return $this->payload['article_id'];
    }

    public static function with(ArticleId $articleId): self
    {
        return new self(['article_id' => $articleId->toString()]);
    }
}
