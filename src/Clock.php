<?php
declare(strict_types=1);

/** Current time as a Unix timestamp. Injected so time-based rules can be checked with a fixed time. */
interface Clock
{
    public function now(): int;
}
