<?php
declare(strict_types=1);

/**
 * Prompts for the staff drafts in the Control Tower (DELIVERY 5.10) and the rule for accepting the model's text.
 * Pure functions over plain values: no database and no model call (see Drafts for those).
 */
final class DraftPrompt
{
    /** Messages kept from the chat log and from the ticket thread, newest last. */
    public const CHAT_LINES = 8;
    public const THREAD_LINES = 6;

    /**
     * Reply draft for a ticket.
     * @param array<array{role?: string, content?: string}> $chat stored chat log
     * @param array<array{sender: string, body: string}> $thread ticket messages, oldest first
     * @return array{0: string, 1: string} system and user text
     */
    public static function ticket(int $id, string $summary, array $chat, array $thread, string $facts): array
    {
        $lines = [];
        foreach (array_slice($chat, -self::CHAT_LINES) as $m) {
            $lines[] = (($m['role'] ?? '') === 'user' ? 'Kunde' : 'Søren') . ': ' . mb_substr((string) ($m['content'] ?? ''), 0, 600);
        }
        foreach (array_slice($thread, -self::THREAD_LINES) as $m) {
            $lines[] = ($m['sender'] === 'kunde' ? 'Kunde' : 'Kundeservice') . ': ' . mb_substr($m['body'], 0, 600);
        }
        $system = 'Du skriver et kort utkast til svar fra Sør-Tre kundeservice til en kunde. En ansatt leser og retter utkastet før det sendes. '
            . 'Bruk bare faktaene under. Pris, lager, produktnavn og spesifikasjoner skal komme derfra. Står noe ikke der, si at du sjekker det. '
            . 'Ikke lov levering, refusjon eller noe annet som ikke står i faktaene. Norsk bokmål, høflig og konkret, høyst fem setninger. Ren tekst uten Markdown, lister eller produkt-ID-er. Ingen tankestreker.';
        $user = "Sak #$id: $summary\n\nSamtale:\n" . implode("\n", $lines) . "\n\nFakta:\n" . ($facts !== '' ? $facts : 'Ingen produktfakta.');
        return [$system, $user];
    }

    /**
     * Assessment of an error report for the staff member who decides it. Reporters are anonymous, so it is never sent.
     * @param array<array{reason: string, value_text: ?string}> $reports open reports on the same value
     * @return array{0: string, 1: string}
     */
    public static function report(string $label, ?string $unit, array $reports, string $facts): array
    {
        $reasons = array_map(fn($x) => '- ' . mb_substr($x['reason'], 0, 300) . ($x['value_text'] !== null ? " (meldt verdi: {$x['value_text']})" : ''), $reports);
        $system = 'Du hjelper en ansatt i Sør-Tre med å vurdere en feilmelding om en produktspesifikasjon. Den ansatte bestemmer selv. '
            . 'Bruk bare faktaene under. Si om verdien ser riktig ut, om den bør fjernes fra den åpne versjonen, eller at det ikke kan avgjøres ut fra faktaene. '
            . 'Begrunn kort. Norsk bokmål, høyst fire setninger. Ren tekst uten Markdown. Ingen tankestreker.';
        $user = "Spesifikasjon: $label" . ($unit ? " ($unit)" : '') . "\n\nMeldinger:\n" . implode("\n", $reasons) . "\n\nFakta:\n$facts";
        return [$system, $user];
    }

    /**
     * The text staff get, or null. A text cut off by the budget or the deadline is dropped, so staff never get half
     * a draft. Dashes are replaced (no em or en dashes in customer text).
     */
    public static function accept(string $text, bool $answered, bool $cutShort): ?string
    {
        $text = trim(str_replace(['—', '–'], ',', $text));
        return $answered && !$cutShort && $text !== '' ? $text : null;
    }
}
