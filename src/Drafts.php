<?php
declare(strict_types=1);

/**
 * Staff drafts from the chat assistant in the Control Tower (DELIVERY 5.10): reads the ticket or report from a
 * DraftStore, builds the prompt with DraftPrompt and asks a DraftModel. Nothing is sent and nothing in the catalogue
 * changes here.
 */
final class Drafts
{
    public function __construct(private DraftStore $store, private DraftModel $model)
    {
    }

    /** Reply draft for an open ticket, or null when the ticket is closed or the model gave no full answer. */
    public function ticket(int $id): ?string
    {
        $t = $this->store->ticket($id);
        if ($t === null) {
            return null;
        }
        return $this->ask(DraftPrompt::ticket($t['id'], $t['summary'], $t['chat'], $t['thread'], $t['facts']));
    }

    /** Assessment of an open error report, or null. */
    public function report(int $id): ?string
    {
        $r = $this->store->report($id);
        if ($r === null) {
            return null;
        }
        return $this->ask(DraftPrompt::report($r['label'], $r['unit'], $r['reports'], $r['facts']));
    }

    /** Answer to a staff question from facts the server gathered (StaffBrief), or null. */
    public function staff(string $question, string $facts): ?string
    {
        return $this->ask(DraftPrompt::staff($question, $facts));
    }

    /** @param array{0: string, 1: string} $prompt */
    private function ask(array $prompt): ?string
    {
        $a = $this->model->answer($prompt[0], $prompt[1]);
        return DraftPrompt::accept($a['text'], $a['answered'], $a['cutShort']);
    }
}
