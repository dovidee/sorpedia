<?php
declare(strict_types=1);

/**
 * Which page the customer is on when they ask Søren. Read again on every question.
 *
 * Security: the browser only sends the address (path and query string), never page content, form fields
 * or cookies. We look the address up in a fixed list of page types and build the context ourselves from the
 * database. Everything not on the list (login, registration, inbox, cart, checkout, staff, customer service, admin)
 * gives no context at all.
 *
 * The customer's own order page and Innstillinger give the order and the error reports, looked up with the user id
 * from the session, never from the address. Name, email, phone, address and profile picture never go in.
 */
final class PageContext
{
    /**
     * @return array{kind:string, label:string, product:?int, ids:int[], text:string}
     */
    public static function resolve(?string $url): array
    {
        $none = ['kind' => 'ingen', 'label' => '', 'product' => null, 'ids' => [], 'text' => ''];
        $url = mb_substr(trim((string) $url), 0, 600);
        if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $none;
        }
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/') ?: '/';
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        if (preg_match('#^/produkt/([a-z0-9-]{1,200})$#', $path, $m)) {
            $p = Catalog::product($m[1]);
            if (!$p) {
                return $none;
            }
            $desc = trim(preg_replace('/\s+/u', ' ', (string) $p['plain_text']));
            return ['kind' => 'produkt', 'label' => $p['name'], 'product' => (int) $p['id'], 'ids' => [(int) $p['id']],
                'text' => "Kunden står nå på produktsiden til [{$p['id']}] {$p['name']}"
                    . ($p['brand'] ? " (merke: {$p['brand']})" : '') . ', i kategorien ' . Catalog::THEMES[$p['theme']][0] . ".\n"
                    . 'Produktbeskrivelse fra Sør-Tre: ' . mb_substr($desc, 0, 900)];
        }
        if (preg_match('#^/tema/([a-z]+)$#', $path, $m) && isset(Catalog::THEMES[$m[1]])) {
            [$name, $about] = Catalog::THEMES[$m[1]];
            return ['kind' => 'tema', 'label' => $name, 'theme' => $m[1], 'product' => null, 'ids' => Catalog::popular(4, $m[1]),
                'text' => "Kunden ser på kategorien {$name} ({$about})."];
        }
        // Byggelab (feature 023): the configuration is in the address as "c=id:qty,id:qty". Only products in the
        // category are kept; names and the check come from the database.
        if (preg_match('#^/byggelab/([a-z]+)$#', $path, $m) && isset(Byggelab::BUILDERS[$m[1]])) {
            $builder = Byggelab::BUILDERS[$m[1]][0];
            $rows = array_map(fn($p) => ['id' => (int) $p, 'qty' => (int) (explode(':', $p)[1] ?? 1)], explode(',', is_string($q['c'] ?? null) ? $q['c'] : ''));
            $lines = Byggelab::lines($m[1], $rows);
            if (!$lines) {
                return ['kind' => 'byggelab', 'label' => "Byggelab: $builder", 'product' => null, 'ids' => [], 'text' => "Kunden er i Byggelab ($builder) med et tomt oppsett."];
            }
            $r = Byggelab::report($m[1], $lines);
            $text = "Kunden setter sammen et oppsett i Byggelab ($builder):\n"
                . implode("\n", array_map(fn($i) => "[{$i['id']}] {$i['name']} × {$i['qty']}", $r['items']))
                . "\nKoblinger: " . (implode('; ', array_map(fn($l) => "{$l['a']} og {$l['b']}: {$l['status']} ({$l['reason']})", $r['links'])) ?: 'ingen')
                . "\nMangler: " . (implode('; ', array_column(array_merge($r['missing'], $r['warnings']), 'text')) ?: 'ingen');
            return ['kind' => 'byggelab', 'label' => "Byggelab: $builder", 'product' => null, 'ids' => array_slice(array_keys($lines), 0, 4), 'text' => mb_substr($text, 0, 1800)];
        }
        if ($path === '/sok' && is_string($q['q'] ?? null) && trim($q['q']) !== '') {
            $term = mb_substr(trim($q['q']), 0, 80);
            return ['kind' => 'sok', 'label' => "Søk: $term", 'product' => null, 'ids' => Search::run($term, 4)['ids'],
                'text' => "Kunden har søkt etter «{$term}» og ser på søkeresultatet."];
        }
        if ($path === '/guide') {
            $answers = Guide::answers($q);
            if (Guide::complete($answers)) {
                $res = Guide::recommend($answers);
                return ['kind' => 'guide', 'label' => 'Forslag fra produktguiden', 'product' => null, 'guide' => $answers,
                    'ids' => array_map(fn($c) => (int) $c['id'], $res['picks']), 'text' => Guide::brief($answers)];
            }
            return ['kind' => 'guide', 'label' => 'Produktguiden', 'product' => null, 'ids' => [], 'text' => Guide::progress($answers)];
        }
        if ($path === '/sammenlign' && is_string($q['ids'] ?? null)) {
            $ids = array_slice(array_values(array_filter(array_map('intval', explode(',', $q['ids'])))), 0, 4);
            $cards = Catalog::cards($ids);
            if (!$cards) {
                return $none;
            }
            return ['kind' => 'sammenlign', 'label' => 'Sammenligning', 'product' => null, 'ids' => array_column($cards, 'id'),
                'text' => 'Kunden sammenligner: ' . implode(', ', array_map(fn($c) => "[{$c['id']}] {$c['name']}", $cards)) . '.'];
        }
        if (preg_match('#^/veiledning/([a-z0-9-]{1,120})$#', $path, $m)) {
            $st = db()->prepare('SELECT title, intro FROM manuals WHERE slug = ?');
            $st->execute([$m[1]]);
            if ($man = $st->fetch()) {
                return ['kind' => 'veiledning', 'label' => $man['title'], 'product' => null, 'ids' => [],
                    'text' => "Kunden leser monteringsveiledningen «{$man['title']}». " . mb_substr((string) $man['intro'], 0, 400)];
            }
        }
        // The customer's own pages. The user id comes from the session; someone else's order gives nothing.
        $uid = (int) ($_SESSION['uid'] ?? 0);
        if ($uid && preg_match('#^/ordre/(\d{1,9})$#', $path, $m) && ($o = Account::order($uid, (int) $m[1]))) {
            return ['kind' => 'ordre', 'label' => "Ordre {$o['id']}", 'product' => null,
                'ids' => array_slice(array_map(fn($l) => (int) $l['product_id'], $o['lines']), 0, 4), 'text' => self::order($o)];
        }
        if ($uid && preg_match('#^/innstillinger(/ordrer|/meldinger)?$#', $path)) {
            return ['kind' => 'konto', 'label' => 'Ordrene og meldingene dine', 'product' => null, 'ids' => [], 'text' => self::account($uid)];
        }
        return $none;
    }

    private const PAYMENT = ['vipps' => 'Vipps', 'klarna' => 'Klarna', 'kort' => 'kort'];

    /** One order: what, when, how it was paid, and the right of withdrawal. Amounts stay on the page (see the price rule in Chat::ABOUT_DATA). */
    private static function order(array $o): string
    {
        $until = Account::withdrawUntil($o)->format('d.m.Y');
        $lines = implode(', ', array_map(fn($l) => "[{$l['product_id']}] {$l['name']} ({$l['qty']} stk)", $o['lines']));
        $status = $o['status'] === 'betalt' ? 'betalt' : 'kansellert';
        $right = $o['status'] !== 'betalt' ? 'Ordren er kansellert, så angreretten gjelder ikke lenger.'
            : (Account::canWithdraw($o) ? "Angrefristen er ikke ute: kunden kan angre til og med $until." : "Angrefristen gikk ut $until.");
        return "Kunden ser på sin egen ordre nr. {$o['id']} fra " . date('d.m.Y', strtotime($o['created_at'])) . ". Status: $status. "
            . 'Betalt med ' . (self::PAYMENT[$o['payment']] ?? $o['payment']) . '. Varehus: ' . ($o['warehouse'] ?? 'ukjent') . ".\n"
            . "Varer i ordren: $lines.\n"
            . "Beløpet kunden betalte står på ordresiden. Prisen i faktaene er dagens pris, ikke det kunden betalte, så du sier ikke hva kunden betalte.\n"
            . "Angrerett: 14 dager fra kunden mottok varene (angrerettloven). Demoen regner fra ordredatoen. $right "
            . 'Varene sendes tilbake i omtrent samme stand, og pengene kommer tilbake med samme betalingsmåte. '
            . 'Reklamasjon: 2 år, eller 5 år for ting som skal vare mye lenger (forbrukerkjøpsloven). '
            . 'Du kan ikke angre, refundere eller endre ordren selv. Det gjør kundeservice: kunden trykker «Snakk med et menneske i stedet» i chatten.';
    }

    /** Innstillinger: the latest orders and the error reports the customer has sent. */
    private static function account(int $uid): string
    {
        $orders = array_slice(Account::orders($uid), 0, 5);
        $reports = array_slice(Account::reports($uid), 0, 5);
        $out = 'Kunden ser på innstillingene sine, med ordrene og feilmeldingene sine.';
        $out .= $orders ? "\nSiste ordrer: " . implode('; ', array_map(fn($o) => "nr. {$o['id']} fra " . date('d.m.Y', strtotime($o['created_at']))
                . ', ' . ($o['first_item'] ?? 'ukjent vare') . ($o['items'] > 1 ? ' og ' . ($o['items'] - 1) . ' til' : '')
                . ', ' . ($o['status'] === 'betalt' ? (Account::canWithdraw($o) ? 'kan angres til og med ' . Account::withdrawUntil($o)->format('d.m.Y') : 'angrefristen er ute') : 'kansellert'), $orders)) . '.'
            : "\nKunden har ingen ordrer.";
        $out .= $reports ? "\nFeilmeldinger kunden har sendt om verdier i Åpen-versjonen: " . implode('; ', array_map(fn($r) => "{$r['product']}, "
                . ($r['label'] ?? $r['key']) . ': ' . Guard::forPrompt(trim($r['value_text'] . ' ' . $r['unit']), 60)
                . ', grunn: ' . Guard::forProse((string) $r['reason'], 160) . ', ' . ($r['status'] === 'apen' ? 'en ansatt har ikke sett på den ennå, og den kan trekkes tilbake' : 'behandlet av en ansatt'), $reports)) . '.'
            : "\nKunden har ikke meldt fra om feil.";
        return $out;
    }
}
