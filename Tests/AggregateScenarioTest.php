<?php

declare(strict_types=1);

namespace Storm\Aggregate\Tests;

use DomainException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Storm\Aggregate\Testing\AggregateScenario;
use Storm\Aggregate\Testing\ExpectedRejectionNotThrown;
use Storm\Aggregate\Tests\Fixture\ArticleDrafted;
use Storm\Aggregate\Tests\Fixture\ArticlePublished;
use Storm\Aggregate\Tests\Fixture\ScenarioArticle;
use TypeError;

final class AggregateScenarioTest extends TestCase
{
    #[Test]
    public function preparation_events_are_not_returned_as_new_events(): void
    {
        $result = AggregateScenario::run(
            ScenarioArticle::draft(...),
            static fn (ScenarioArticle $article) => $article->publish(),
        );

        self::assertCount(1, $result->events);
        self::assertInstanceOf(ArticlePublished::class, $result->events[0]);
        self::assertTrue($result->aggregate->published);
        self::assertSame(2, $result->aggregate->version());
        self::assertNull($result->rejection);
    }

    #[Test]
    public function new_events_keep_their_recording_order(): void
    {
        $result = AggregateScenario::run(
            ScenarioArticle::draft(...),
            static fn (ScenarioArticle $article) => $article->renameAndPublish(),
        );

        self::assertCount(2, $result->events);
        self::assertInstanceOf(ArticleDrafted::class, $result->events[0]);
        self::assertSame('Revised', $result->events[0]->title);
        self::assertInstanceOf(ArticlePublished::class, $result->events[1]);
        self::assertSame($result->aggregate->identity()->toString(), $result->events[1]->aggregateId());
    }

    #[Test]
    public function an_action_that_does_not_reject_fails_an_expected_rejection(): void
    {
        $this->expectException(ExpectedRejectionNotThrown::class);

        AggregateScenario::run(
            ScenarioArticle::draft(...),
            static fn (ScenarioArticle $article) => $article->publish(),
            DomainException::class,
        );
    }

    #[Test]
    public function unexpected_throwable_is_not_accepted_as_business_rejection(): void
    {
        $failure = new TypeError('Broken action');

        try {
            AggregateScenario::run(
                ScenarioArticle::draft(...),
                static fn (ScenarioArticle $article) => throw $failure,
                DomainException::class,
            );
        } catch (TypeError $caught) {
            self::assertSame($failure, $caught);

            return;
        }

        self::fail('The unexpected throwable must escape unchanged.');
    }

    #[Test]
    public function events_recorded_before_rejection_remain_observable(): void
    {
        $rejection = new DomainException('Rejected after publication');
        $result = AggregateScenario::run(
            ScenarioArticle::draft(...),
            static fn (ScenarioArticle $article) => $article->publishThenReject($rejection),
            DomainException::class,
        );

        self::assertSame($rejection, $result->rejection);
        self::assertCount(1, $result->events);
        self::assertInstanceOf(ArticlePublished::class, $result->events[0]);
        self::assertTrue($result->aggregate->published);
        self::assertSame(2, $result->aggregate->version());
    }

    #[Test]
    public function a_rejection_subclass_matches_its_expected_parent(): void
    {
        $rejection = new DomainException('Rejected');
        $result = AggregateScenario::run(
            ScenarioArticle::draft(...),
            static fn (ScenarioArticle $article) => throw $rejection,
            LogicException::class,
        );

        self::assertSame($rejection, $result->rejection);
        self::assertSame([], $result->events);
    }

    #[Test]
    public function a_throwable_escapes_when_no_rejection_is_expected(): void
    {
        $failure = new DomainException('Not expected');

        try {
            AggregateScenario::run(ScenarioArticle::draft(...), static fn (ScenarioArticle $article) => throw $failure);
        } catch (DomainException $caught) {
            self::assertSame($failure, $caught);

            return;
        }

        self::fail('A throwable without an expected rejection must escape unchanged.');
    }

    #[Test]
    public function an_action_without_events_returns_an_empty_list(): void
    {
        $result = AggregateScenario::run(ScenarioArticle::draft(...), static function (ScenarioArticle $article): void {});

        self::assertSame([], $result->events);
        self::assertSame(1, $result->aggregate->version());
    }

    #[Test]
    public function preparation_failures_are_not_accepted_as_action_rejections(): void
    {
        $failure = new DomainException('Preparation failed');

        try {
            AggregateScenario::run(
                static fn () => throw $failure,
                static function (ScenarioArticle $article): void {},
                DomainException::class,
            );
        } catch (DomainException $caught) {
            self::assertSame($failure, $caught);

            return;
        }

        self::fail('Preparation failures must escape unchanged.');
    }
}
