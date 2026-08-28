<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests\Fixture\Doppelganger;

use Storm\Contracts\Message\DomainEvent;

/**
 * Shares its short name with the fixture `ArticlePublished` on purpose: convention dispatch
 * resolves both to `applyArticlePublished()`, the collision the trait must refuse loud.
 */
final class ArticlePublished implements DomainEvent
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
