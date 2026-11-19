<?php
declare(strict_types=1);

/**
 * What a staff draft is written from (DELIVERY 5.10). PdoDraftStore reads the database; checks pass their own.
 * ticket(): {id, summary, chat: list<{role, content}>, thread: list<{sender, body}> oldest first, facts} for an open
 *           ticket, else null. facts is '' when the ticket has no product.
 * report(): {label, unit, reports: list<{reason, value_text}>, facts} for an open error report, else null.
 */
interface DraftStore
{
    public function ticket(int $id): ?array;

    public function report(int $id): ?array;
}
