<?php
declare(strict_types=1);

/**
 * The shop's answers about products, the cart and orders, written by PHP from the database (feature 041).
 * The language model only reads the question (Understand); this class decides which products qualify, filters them
 * on the customer's requirements against verified values, ranks them by fixed rules and writes every sentence from
 * templates. Prices and stock stay references ([[pris:ID]], [[lager:ID]]) that ProductRef fills in. A value that is
 * not verified is said to be not registered; derived knowledge (Usage) is marked as derived.
 *
 * Small talk with no product in it gets a fixed reply too, so the model writes nothing the customer sees.
 */
final class ShopAnswer
{
    private const SHOW = 3;

    /**
     * @param array $u Understand::read(); $found ProductRetriever::forChat(); $selected the public API's product
     * @return array{text: string, ids: int[], kind: string}
     */
    public static function answer(string $q, array $u, array $found, array $page, ?int $selected = null): array
    {
        if ($u['intent'] === 'handlekurv') {
            return self::cart();
        }
        if ($u['intent'] === 'ordre') {
            return self::orders($page);
        }
        $focus = FactAnswer::focus($found['ids'] ?? [], $found['why'] ?? [], $selected);
        // Small talk only when the question itself names no product and no product type; the page's product doesn't count
        $named = array_filter($found['why'] ?? [], fn($w) => array_intersect_key($w['signals'] ?? [], array_flip(['sku', 'model', 'exact_name'])));
        if (!$named && FactAnswer::aboutSet($q)) {
            // The products shown before, never a search for the words in the question
            $shown = array_values(array_filter($found['ids'] ?? [], fn($id) => isset($found['why'][$id]['signals']['history'])));
            return count($shown) > 1 ? self::compare($shown)
                : self::done(t('Hvilke varer tenker du på? Skriv navnene, eller spør meg om en type vare, så finner jeg forslag.'), [], 'avklaring');
        }
        if ($u['type'] !== null && !$named && ($short = self::shortFollowUp($q, $u, $page)) !== null) {
            return $short;
        }
        // Only a real greeting gets the greeting back; the model can label a question about the page's product as small talk
        if ($u['intent'] === 'smaprat' && Understand::greeting($q) && $u['type'] === null && !$named && !$u['requirements']) {
            return self::done(t('Hei! Jeg er her for å hjelpe deg med varene hos Sør-Tre, handlekurven og ordrene dine. Hva kan jeg hjelpe deg med?'), [], 'smaprat');
        }
        if ($named) {
            // A product named outright is the one asked about, even when its type is named too («Fortell om Øyeskrue 6 x 16»)
            $focus = array_map('intval', array_keys($named));
        }
        if ($u['type'] !== null && !$named && ($u['intent'] !== 'om_produktet' || !$focus)) {
            return self::recommend($u);
        }
        if ($focus && $selected === null && !$named) {
            // The question names no product: the product on the page, else the earlier products; more than one is asked about
            $why = $found['why'] ?? [];
            $onPage = array_values(array_filter($focus, fn($id) => isset($why[$id]['signals']['page'])));
            if ($onPage) {
                $focus = [$onPage[0]];
                $found['ids'] = $focus;
            } elseif (count($focus) > 1) {
                $names = array_map(fn($id) => FactAnswer::name($id, $focus), array_slice($focus, 0, 4));
                return self::done(t('Hvilken av dem mener du: {list}?', ['list' => implode(', ', $names)]), array_slice($focus, 0, 4), 'avklaring');
            }
        }
        if ($focus) {
            return self::about($q, $found, $selected, $focus);
        }
        if ($cards = array_slice($found['cards'] ?? [], 0, self::SHOW)) {
            $lines = array_map(fn($id) => self::line((int) $id, []), $cards);
            return self::done(t('Dette fant jeg i katalogen:') . "\n" . implode("\n", $lines) . "\n\n" . t('Si gjerne hva du skal bruke det til, så finner jeg riktig type vare.'), $cards, 'treff');
        }
        return self::done(self::clarify(), [], 'avklaring');
    }

    /**
     * Products of the type the customer named, filtered on budget and requirements against verified values, ranked:
     * in stock in the store first, then on the web stock, then the lowest price.
     */
    public static function recommend(array $u): array
    {
        [$pool, $word] = $u['type'];
        $cards = Catalog::cards(array_map('intval', $pool));
        $total = count($cards);
        $criteria = [];
        if ($u['budget_ore'] !== null) {
            $cards = array_values(array_filter($cards, fn($c) => (int) $c['price_ore'] > 0 && (int) $c['price_ore'] <= $u['budget_ore']));
            $criteria[] = t('pris høyst {kr}', ['kr' => kr($u['budget_ore'])]);
        }
        $unknown = [];
        $unused = [];
        foreach ($u['requirements'] as $r) {
            // A value no product of the type has verified («hiking» against Norwegian values) would leave nothing: not used
            // and is left out quietly: neither as a filter nor as a column of "not registered" on every line
            if ($r['value'] !== null && $r['value'] !== '*' && !array_filter($cards, fn($c) => self::meets((int) $c['id'], $r) === true)) {
                $unused[$r['key']] = true;
                continue;
            }
            $criteria[] = self::criterion($r);
            $keep = [];
            foreach ($cards as $c) {
                $ok = self::meets((int) $c['id'], $r);
                if ($ok === true) {
                    $keep[] = $c;
                } elseif ($ok === null) {
                    $unknown[(int) $c['id']] = true;
                }
            }
            $cards = $keep;
        }
        usort($cards, fn($a, $b) => [(int) ($b['in_store'] > 0), (int) ($b['online'] > 0), (int) $a['price_ore']]
            <=> [(int) ($a['in_store'] > 0), (int) ($a['online'] > 0), (int) $b['price_ore']]);
        $top = array_slice($cards, 0, self::SHOW);
        $ids = array_map(fn($c) => (int) $c['id'], $top);
        $keys = array_values(array_filter(array_column($u['requirements'], 'key'), fn($k) => !isset($unused[$k])));
        $out = [];
        if (!$top) {
            $out[] = self::voice('tomt', $word) . ' ' . ($criteria
                ? t('Ingen av {total} varer med «{word}» i navnet hos Sør-Tre passer det du ba om ({criteria}) ut fra kontrollerte verdier.', ['total' => $total, 'word' => $word, 'criteria' => implode(', ', $criteria)])
                : t('Sør-Tre har ingen varer med «{word}» i navnet på lager eller nettlager.', ['word' => $word]));
            if ($unknown) {
                $out[] = t('Disse mangler en registrert verdi, så de kan passe, men det er ikke kontrollert:') . "\n"
                    . implode("\n", array_map(fn($id) => self::line($id, $keys), array_slice(array_keys($unknown), 0, self::SHOW)));
                $ids = array_slice(array_keys($unknown), 0, self::SHOW);
            }
            return self::done(implode("\n\n", $out), $ids, 'anbefaling');
        }
        $out[] = self::voice('anbefaling', $word) . ' ' . ($criteria
            ? t('{n} av {total} varer med «{word}» i navnet hos Sør-Tre passer det du ba om ({criteria}) ut fra kontrollerte verdier.', ['n' => count($cards), 'total' => $total, 'word' => $word, 'criteria' => implode(', ', $criteria)])
            : ($total === 1 ? t('Sør-Tre har én vare med «{word}» i navnet.', ['word' => $word]) : t('Sør-Tre har {total} varer med «{word}» i navnet.', ['total' => $total, 'word' => $word])))
            . ' ' . t('På lager i butikken først, så billigst:');
        $out[] = implode("\n", array_map(fn($id) => self::line($id, $keys), $ids));
        $rule = Usage::rule(mb_strtolower((string) $top[0]['name']));
        if ($rule !== null) {
            $out[] = t('Om typen (utledet fra produkttypen, ikke oppgitt av Sør-Tre):') . ' ' . t($rule[0]);
        }
        if (!$u['requirements'] && ($labels = self::labels(Usage::keys(mb_strtolower((string) $top[0]['name']))))) {
            $out[] = t('Si gjerne hva som er viktigst for deg ({list}), så snevrer jeg inn.', ['list' => implode(', ', $labels)]);
        }
        return self::done(implode("\n\n", $out), $ids, 'anbefaling');
    }

    /** A product the customer is looking at or named: the server's factual answer, else a summary from verified values. */
    private static function about(string $q, array $found, ?int $selected, array $focus): array
    {
        // «Fortell om …», «hva bør jeg tenke på»: an overview of the product, not one fact or one pairing
        $basis = FactAnswer::overview($q) ? null : FactAnswer::basis($q, $found['ids'] ?? [], $found['why'] ?? [], $selected, $found['intent'] ?? null);
        if ($basis !== null) {
            return self::done($basis['text'], $basis['ids'], 'fakta:' . $basis['kind']);
        }
        $id = $focus[0];
        $name = (string) db()->query('SELECT name FROM products WHERE id = ' . $id)->fetchColumn();
        $out = [self::voice('om', '') . "\n" . self::line($id, [], 6)];
        $rule = Usage::rule(mb_strtolower($name));
        if ($rule !== null) {
            $out[] = t('Om typen (utledet fra produkttypen, ikke oppgitt av Sør-Tre):') . ' ' . t($rule[0]);
        }
        $specs = Catalog::specs($id);
        if (preg_match('/øyeskrue|krok|sjakkel|kjetting|stropp|tau\b|wire|løft|karabin/u', mb_strtolower($name))
            && empty($specs['arbeidslast_kg']['verifisert']) && empty($specs['bruddstyrke_kg']['verifisert'])) {
            $out[] = t('Sør-Tre oppgir ingen arbeidslast for den, så sjekk med produsenten før du henger noe tungt i den.');
        }
        $rel = db()->prepare("SELECT r.kind, p.id FROM relations r JOIN products p ON p.id = r.product_b WHERE r.product_a = ? AND r.level = 'dokumentert'
            AND r.kind IN ('passer_sammen', 'passer_ikke') ORDER BY r.kind, r.score DESC LIMIT 4");
        $rel->execute([$id]);
        $fits = $not = [];
        foreach ($rel as $r) {
            $label = FactAnswer::name((int) $r['id'], [$id, (int) $r['id']]);
            $r['kind'] === 'passer_ikke' ? $not[] = $label : $fits[] = $label . ' (' . ProductRef::price((int) $r['id']) . ')';
        }
        if ($fits) {
            $out[] = t('Dokumentert av Sør-Tre som passende: {list}.', ['list' => implode('; ', $fits)]);
        }
        if ($not) {
            $out[] = t('Passer ikke, dokumentert av Sør-Tre: {list}.', ['list' => implode('; ', $not)]);
        }
        if (Catalog::hasManual($id)) {
            $out[] = t('Det finnes en veiledning for denne varen, se kortet under.');
        }
        $out[] = t('Spør gjerne om pris, lager, en bestemt egenskap eller hva som passer til den.');
        return self::done(implode("\n\n", $out), [$id], 'om_produktet');
    }

    /** One product: name, price, stock, and the verified values of the keys that decide for its type. */
    public static function line(int $id, array $keys, int $max = 4): string
    {
        $name = (string) db()->query('SELECT name FROM products WHERE id = ' . $id)->fetchColumn();
        $specs = Catalog::specs($id);
        $asked = $keys;
        $keys = array_values(array_unique(array_merge($keys, Usage::keys(mb_strtolower($name)))));
        $known = $missing = [];
        foreach ($keys as $k) {
            $v = $specs[$k]['verifisert'] ?? null;
            $label = I18n::spec($k, (string) ($specs[$k]['label'] ?? db()->query('SELECT label FROM spec_keys WHERE `key` = ' . db()->quote($k))->fetchColumn()));
            $label = mb_strtolower(mb_substr($label, 0, 1)) . mb_substr($label, 1);
            if ($v) {
                $known[] = $label . ' ' . trim(I18n::value((string) $v['value_text']) . ' ' . ($specs[$k]['unit'] ?? '')) . ($v['source'] === 'ansatt' ? ' (' . t('godkjent av en ansatt') . ')' : '');
            } elseif (in_array($k, $asked, true)) {
                // Only a value the customer asked about is said to be missing; the rest is left out
                $missing[] = $label;
            }
            if (count($known) >= $max) {
                break;
            }
        }
        $out = FactAnswer::name($id, [$id]) . ': ' . ProductRef::price($id) . ', ' . ProductRef::stock($id) . '.';
        if ($known) {
            $out .= ' ' . t('Sør-Tre oppgir {list}.', ['list' => self::listing($known)]);
        }
        if ($missing) {
            $out .= ' ' . t('{list} er ikke oppgitt.', ['list' => mb_strtoupper(mb_substr($m = self::listing($missing), 0, 1)) . mb_substr($m, 1)]);
        }
        return $out;
    }

    /** «a, b og c» */
    private static function listing(array $items): string
    {
        $last = array_pop($items);
        return $items ? implode(', ', $items) . ' ' . t('og') . ' ' . $last : (string) $last;
    }

    /** true: the verified value meets the requirement; false: it doesn't; null: no verified value. */
    public static function meets(int $id, array $r): ?bool
    {
        $v = Catalog::specs($id)[$r['key']]['verifisert'] ?? null;
        if ($v === null) {
            return null;
        }
        if ($r['min'] !== null || $r['max'] !== null) {
            $n = $v['value_num'] !== null ? (float) $v['value_num'] : (preg_match('/\d+(?:[.,]\d+)?/', str_replace(' ', '', (string) $v['value_text']), $m) ? (float) str_replace(',', '.', $m[0]) : null);
            if ($n === null) {
                return null;
            }
            return ($r['min'] === null || $n >= $r['min']) && ($r['max'] === null || $n <= $r['max']);
        }
        return $r['value'] === null || $r['value'] === '*' || str_contains(mb_strtolower((string) $v['value_text']), $r['value']);
    }

    /** The cart of this browser session, from cart_items. */
    private static function cart(): array
    {
        $sid = session_status() === PHP_SESSION_ACTIVE || session_id() !== '' ? session_id() : '';
        if ($sid === '') {
            return self::done(t('Her er ingen handlekurv. Handlekurven finnes i nettbutikken.'), [], 'handlekurv');
        }
        $st = db()->prepare('SELECT c.product_id, c.qty, p.price_ore FROM cart_items c JOIN products p ON p.id = c.product_id WHERE c.session_id = ?');
        $st->execute([$sid]);
        $rows = $st->fetchAll();
        if (!$rows) {
            return self::done(t('Handlekurven din er tom.'), [], 'handlekurv');
        }
        $ids = array_map(fn($r) => (int) $r['product_id'], $rows);
        $lines = array_map(fn($r) => (int) $r['qty'] . ' × ' . FactAnswer::name((int) $r['product_id'], $ids) . ' (' . ProductRef::price((int) $r['product_id']) . ')', $rows);
        $sum = array_sum(array_map(fn($r) => (int) $r['qty'] * (int) $r['price_ore'], $rows));
        return self::done(t('I handlekurven: {list}. Til sammen {sum} med dagens priser. Du går videre fra handlekurven øverst på siden.',
            ['list' => implode('; ', $lines), 'sum' => kr($sum)]), $ids, 'handlekurv');
    }

    /** The customer's own orders, from orders. Changes go to customer service. */
    private static function orders(array $page): array
    {
        $user = current_user();
        $service = t('Angring, retur, refusjon og reklamasjon tar kundeservice seg av: trykk «Snakk med et menneske i stedet» i chatten.');
        if ($user === null) {
            return self::done(t('Logg inn for å se ordrene dine. De ligger under Innstillinger, Ordrer.') . ' ' . $service, [], 'ordre');
        }
        $list = array_slice(Account::orders((int) $user['id']), 0, 3);
        if (!$list) {
            return self::done(t('Du har ingen ordrer ennå.'), [], 'ordre');
        }
        $status = ['betalt' => t('betalt'), 'kansellert' => t('kansellert'), 'refundert' => t('refundert')];
        $lines = array_map(fn($o) => t('Ordre {id} fra {date}: {status}, {total}.', ['id' => $o['id'], 'date' => date('d.m.Y', strtotime((string) $o['created_at'])),
            'status' => $status[$o['status']] ?? $o['status'], 'total' => kr((int) $o['total_ore'])]), $list);
        return self::done(t('Dine siste ordrer:') . "\n" . implode("\n", $lines) . "\n\n" . $service, [], 'ordre');
    }

    /** The products shown before, side by side: why they were picked (the ranking rule) and their verified values. */
    private static function compare(array $ids): array
    {
        $ids = array_slice($ids, 0, self::SHOW);
        $keys = [];
        foreach ($ids as $id) {
            $keys = array_merge($keys, Usage::keys(mb_strtolower((string) db()->query('SELECT name FROM products WHERE id = ' . (int) $id)->fetchColumn())));
        }
        $keys = array_values(array_unique($keys));
        $out = [t('Jeg valgte dem fordi de er av typen du spurte om og passet det du ba om, med det som er på lager i butikken først og deretter pris. Slik skiller de seg, etter det Sør-Tre oppgir:')];
        $out[] = implode("\n", array_map(fn($id) => self::line((int) $id, array_slice($keys, 0, 4)), $ids));
        $out[] = t('Si hva som er viktigst for deg, så peker jeg ut én.');
        return self::done(implode("\n\n", $out), $ids, 'sammenligning');
    }

    /**
     * A one word question that names a type («batteri») while the customer looks at a product or a theme: the
     * active product's verified value if it has one, and a question back instead of a search on the shared word.
     */
    private static function shortFollowUp(string $q, array $u, array $page): ?array
    {
        $words = preg_split('/\s+/u', trim(QuickReply::normalize($q))) ?: [];
        if (count($words) > 1 || !in_array($page['kind'] ?? '', ['produkt', 'tema'], true)) {
            return null;
        }
        $word = (string) $u['type'][1];
        $pid = $page['product'] ?? null;
        if ($pid) {
            $name = FactAnswer::name((int) $pid, [(int) $pid]);
            $specs = Catalog::specs((int) $pid);
            $fact = '';
            foreach ($specs as $k => $sp) {
                $label = (string) ($sp['label'] ?? '');
                if (!empty($sp['verifisert']) && str_contains(mb_strtolower($label . ' ' . $k), mb_substr($word, 0, 5))) {
                    $fact = t('Sør-Tre oppgir {label} {value} for {name}.', ['label' => mb_strtolower(I18n::spec($k, $label)), 'value' => trim(I18n::value((string) $sp['verifisert']['value_text']) . ' ' . ($sp['unit'] ?? '')), 'name' => $name]) . ' ';
                    break;
                }
            }
            return self::done($fact . t('Tenker du på {word} til {name}, eller leter du etter {word} som egen vare?', ['word' => $word, 'name' => $name]), [], 'oppfolging');
        }
        return self::done(t('Tenker du på {word} til en av varene i {theme}, eller leter du etter {word} som egen vare?', ['word' => $word, 'theme' => mb_strtolower((string) $page['label'])]), [], 'oppfolging');
    }

    /**
     * Søren's voice around the facts (feature 015): one short line before a product answer, never a fact itself.
     * A few wordings per kind so a conversation does not repeat itself.
     */
    private static function voice(string $kind, string $word): string
    {
        $pool = match ($kind) {
            'anbefaling' => ['Da har jeg kikket i hylla etter «{word}».', '«{word}», sier du? Det har vi.', 'Her er det jeg fant av «{word}».'],
            'tomt' => ['Her må jeg skuffe deg litt.', 'Jeg har lett i alle hyllene, men her ble det tomt.'],
            default => ['Den kjenner jeg godt.', 'Godt valg å se nærmere på!', 'Her er det katalogen sier om den.'],
        };
        return t($pool[random_int(0, count($pool) - 1)], ['word' => $word]);
    }

    /** A question back when nothing in the question names a product or a product type. */
    private static function clarify(): string
    {
        $themes = array_map(fn($t) => t($t[0]), Catalog::THEMES);
        return t('Jeg er klar, jeg trenger bare litt mer å gå på.') . ' ' . t('Hva slags vare leter du etter? Sør-Tre har blant annet {list}. Skriv gjerne typen vare, hva den skal brukes til og et budsjett.', ['list' => implode(', ', $themes)]);
    }

    private static function criterion(array $r): string
    {
        $label = mb_strtolower(I18n::spec($r['key'], (string) (Understand::keys()[$r['key']] ?? $r['key'])));
        $unit = (string) db()->query('SELECT unit FROM spec_keys WHERE `key` = ' . db()->quote($r['key']))->fetchColumn();
        $n = fn(float $x) => trim(rtrim(rtrim(number_format($x, 2, ',', ' '), '0'), ',') . ' ' . $unit);
        return match (true) {
            $r['min'] !== null && $r['max'] !== null => t('{label} mellom {a} og {b}', ['label' => $label, 'a' => $n($r['min']), 'b' => $n($r['max'])]),
            $r['min'] !== null => t('{label} på minst {a}', ['label' => $label, 'a' => $n($r['min'])]),
            $r['max'] !== null => t('{label} på høyst {a}', ['label' => $label, 'a' => $n($r['max'])]),
            $r['value'] !== null && $r['value'] !== '*' => t('{label} «{v}»', ['label' => $label, 'v' => $r['value']]),
            default => t('kontrollert {label}', ['label' => $label]),
        };
    }

    /** @return string[] */
    private static function labels(array $keys): array
    {
        $all = db()->query('SELECT `key`, label FROM spec_keys')->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_map(fn($k) => mb_strtolower(I18n::spec($k, (string) ($all[$k] ?? $k))), array_values(array_filter($keys, fn($k) => isset($all[$k]))));
    }

    private static function done(string $text, array $ids, string $kind): array
    {
        return ['text' => trim($text), 'ids' => array_values(array_unique(array_map('intval', $ids))), 'kind' => $kind];
    }
}
