<?php

namespace Tests\Concerns;

use Closure;
use Illuminate\Database\QueryException;

/**
 * Assertions that a write is refused by MySQL itself (not by PHP validation).
 */
trait AssertsDatabaseConstraints
{
    /**
     * Assert that $write fails because the named CHECK constraint rejected it, so the test
     * proves the right rule fired, not just that some error happened.
     */
    protected function assertCheckViolated(string $constraint, Closure $write): void
    {
        $this->assertQueryRejected("Check constraint '{$constraint}' is violated", $write);
    }

    /**
     * Assert that $write throws a QueryException whose message contains $fragment
     * (e.g. "Duplicate entry", "a foreign key constraint fails").
     */
    protected function assertQueryRejected(string $fragment, Closure $write): void
    {
        try {
            $write();
        } catch (QueryException $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());

            return;
        }

        $this->fail("Expected the database to reject the write with \"{$fragment}\", but it was saved.");
    }
}
