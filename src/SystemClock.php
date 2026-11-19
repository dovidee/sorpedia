<?php
declare(strict_types=1);

final class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
