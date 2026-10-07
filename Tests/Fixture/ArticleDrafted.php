<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture;

use Storm\Contracts\Message\DomainEvent;
use Storm\Message\HasConstructablePayload;

final class ArticleDrafted implements DomainEvent
{
    /** @use HasConstructablePayload<array{article_id: string, title: string}> */
    use HasConstructablePayload;

    public string $title { get => $this->payload['title']; }

    public function aggregateId(): string
    {
        return $this->payload['article_id'];
    }

    public static function with(ArticleId $articleId, string $title): self
    {
        return new self([
            'article_id' => $articleId->toString(),
            'title' => $title,
        ]);
    }
}
