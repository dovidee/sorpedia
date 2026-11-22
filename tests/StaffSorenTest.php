<?php
declare(strict_types=1);

// Ask Søren in the Control Tower (feature 022): the prompt carries only server facts and the read-only rules,
// and a cut-off answer is never shown. Uses a fixed model: no database and no network.

$fixed = fn(string $text, bool $answered = true, bool $cut = false) => new class ($text, $answered, $cut) implements DraftModel {
    public string $system = '';
    public string $user = '';
    public function __construct(private string $text, private bool $answered, private bool $cut)
    {
    }
    public function answer(string $system, string $user): array
    {
        $this->system = $system;
        $this->user = $user;
        return ['text' => $this->text, 'answered' => $this->answered, 'cutShort' => $this->cut];
    }
};
$store = new class implements DraftStore {
    public function ticket(int $id): ?array
    {
        return null;
    }
    public function report(int $id): ?array
    {
        return null;
    }
};

return [
    'the staff prompt holds the facts and forbids changes and new numbers' => function () {
        [$system, $user] = DraftPrompt::staff('Hva bør vi rette først?', 'Åpne saker: 2.');
        check(str_contains($user, 'Åpne saker: 2.') && str_contains($user, 'Hva bør vi rette først?'), 'question and facts must reach the model');
        check(str_contains($system, 'Bruk bare faktaene') && str_contains($system, 'kan ikke endre'), 'read-only and facts-only rules must be in the prompt');
    },
    'a long question is cut to 500 characters' => function () {
        [, $user] = DraftPrompt::staff(str_repeat('a', 900), 'x');
        check(mb_strlen(explode("\n", $user)[0]) <= 500 + mb_strlen('Spørsmål: '), 'question must be capped');
    },
    'dashes are replaced and a cut-off answer is dropped' => function () use ($fixed, $store) {
        check((new Drafts($store, $fixed('Sak #1 venter — svar den.')))->staff('q', 'f') === 'Sak #1 venter , svar den.', 'em dash must not reach staff text');
        check((new Drafts($store, $fixed('Halv', true, true)))->staff('q', 'f') === null, 'a cut-off answer must not be shown');
        check((new Drafts($store, $fixed('', false)))->staff('q', 'f') === null, 'no answer gives null');
    },
];
