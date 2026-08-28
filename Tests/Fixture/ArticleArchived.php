<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture;

use Storm\Contracts\Message\DomainEvent;

/**
 * An event that Article and SnapshotArticle deliberately declare no `apply` handler for; used to
 * exercise the missing-handler path in AggregateRootBehavior::apply().
 */
final class ArticleArchived implements DomainEvent
{
    public function __construct(
        public string $articleId,
    ) {}

    public function aggregateId(): string
    {
        return $this->articleId;
    }

    public function toPayload(): array
    {
        return ['article_id' => $this->articleId];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) $payload['article_id']);
    }
}
