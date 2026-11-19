<?php
declare(strict_types=1);

/** Drafts from the local model (OLLAMA_URL). Price and stock references are written out from the database, as in the chat. */
final class OllamaDraftModel implements DraftModel
{
    public function answer(string $system, string $user): array
    {
        // Reasoning models think before they answer even when asked not to, so the budget covers both (as in the chat)
        $out = '';
        $limits = ['seconds' => 180, 'predict' => 1500] + (Chat::loaded() ? [] : ['first' => 90]);
        $ok = Chat::streamOllama([['role' => 'user', 'content' => $user]], $system, Chat::nativeThinker() ? true : null, fn() => null,
            function (string $tok) use (&$out): void {
                $out .= $tok;
            }, $limits);
        return ['text' => ProductRef::resolve($out), 'answered' => $ok, 'cutShort' => Chat::$cutShort];
    }
}
