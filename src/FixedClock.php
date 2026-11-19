<?php
declare(strict_types=1);

/** A clock that always returns the same time, for checks without waiting. */
final class FixedClock implements Clock
{
    public function __construct(private int $at)
    {
    }

    public function now(): int
    {
        return $this->at;
    }
}
