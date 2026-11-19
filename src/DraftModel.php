<?php
declare(strict_types=1);

/** The language model that writes staff drafts. OllamaDraftModel is the local model; checks pass a fixed answer. */
interface DraftModel
{
    /** @return array{text: string, answered: bool, cutShort: bool} */
    public function answer(string $system, string $user): array;
}
