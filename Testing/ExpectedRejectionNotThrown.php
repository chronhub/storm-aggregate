<?php

declare(strict_types=1);

namespace Storm\Aggregate\Testing;

use LogicException;
use Throwable;

/**
 * Signals a scenario whose action completed although it expected a rejection.
 */
final class ExpectedRejectionNotThrown extends LogicException
{
    /**
     * @param  class-string<Throwable>  $expectedRejection
     */
    public static function for(string $expectedRejection): self
    {
        return new self(sprintf('The scenario action completed without throwing the expected rejection %s.', $expectedRejection));
    }
}
