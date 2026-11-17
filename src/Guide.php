<?php
declare(strict_types=1);

/**
 * The product guide (feature 003). Deterministic: every suggestion gets a list of reasons,
 * so the customer sees exactly why (white box, constitution point 5).
 *
 * The questions go from broad to narrow, like 20 questions: first the task, then the product type (Sør-Tre's own
 * categories), then whichever remaining question splits the remaining products best (most information).
 * Questions that wouldn't separate anything are skipped, and only answers that actually have products are shown.
 * The guide is done when three or fewer products are left, or no question can narrow it down further.
 */
final class Guide
{
    public const QUESTIONS = [
        'oppgave' => ['Hva skal du gjøre?', [
            'lys' => 'Se bedre i mørket', 'bygg' => 'Snekre eller feste noe', 'maling' => 'Male eller sparkle',
            'balkong' => 'Skjerme balkongen', 'kjetting' => 'Løfte, sikre eller fortøye', 'skog' => 'Sage eller hogge ved',
            'tur' => 'Tur, båt eller grilling',
        ]],
        'sted' => ['Hvor skal det brukes?', ['inne' => 'Inne', 'ute' => 'Ute', 'sjo' => 'Ved sjøen eller i båten', 'begge' => 'Litt av hvert']],
        'budsjett' => ['Hva vil du bruke?', ['200' => 'Under 200 kr', '1000' => '200 til 1 000 kr', '99999' => 'Over 1 000 kr', '0' => 'Spiller ingen rolle']],
        'erfaring' => ['Hvor vant er du med dette?', ['ny' => 'Første gang', 'litt' => 'Har gjort det før', 'proff' => 'Gjør det ofte, gjerne proff']],
    ];

    /** The feedback table, for databases built before it existed. */
    public static function ensure(): void
    {
        db()->exec("CREATE TABLE IF NOT EXISTS guide_feedback (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, theme VARCHAR(32) NOT NULL,
            answers VARCHAR(255) NOT NULL, product_id INT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY (theme))
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** The customer says which product was the right one for their answers. */
    public static function learn(array $a, int $productId): bool
    {
        $a = self::answers($a);
        if (!isset($a['oppgave']) || !in_array($productId, array_map('intval', array_column(self::candidates(['oppgave' => $a['oppgave']]), 'id')), true)) {
            return false;
        }
        db()->prepare('INSERT INTO guide_feedback (theme, answers, product_id) VALUES (?,?,?)')->execute([$a['oppgave'], json_encode($a), $productId]);
        return true;
    }

    /**
     * What earlier customers with the same answers said was right: [product id => weight]. Every matching answer counts,
     * so a match on product type and budget weighs more than just the same task.
     */
    public static function learned(array $a): array
    {
        if (!isset($a['oppgave'])) {
            return [];
        }
        $st = db()->prepare('SELECT answers, product_id FROM guide_feedback WHERE theme = ? ORDER BY id DESC LIMIT 2000');
        $st->execute([$a['oppgave']]);
        $w = [];
        foreach ($st->fetchAll() as $row) {
            $old = json_decode($row['answers'], true) ?: [];
            $same = 0;
            foreach ($a as $k => $v) {
                if ($k !== 'oppgave' && ($old[$k] ?? null) === $v) {
                    $same++;
                }
            }
            $w[(int) $row['product_id']] = ($w[(int) $row['product_id']] ?? 0) + 1 + $same;
        }
        return $w;
    }

    /** The product type question. The answers come from the categories in the task the customer chose. */
    public const TYPE_Q = 'Hva slags?';

    /** The product types (Sør-Tre's categories) in a theme: [category id => name], largest first. */
    public static function types(string $theme): array
    {
        $st = db()->prepare('SELECT c.id, c.name FROM products p JOIN product_categories pc ON pc.product_id = p.id JOIN categories c ON c.id = pc.category_id
            WHERE p.theme = ? GROUP BY c.id, c.name ORDER BY COUNT(*) DESC, c.name');
        $st->execute([$theme]);
        return array_map('strval', $st->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** The products still relevant after the answers: theme, product type and budget. */
    public static function candidates(array $a): array
    {
        if (!isset($a['oppgave'])) {
            return [];
        }
        $sql = 'SELECT p.id, p.name, p.price_ore, (SELECT value_text FROM spec_values v WHERE v.product_id = p.id AND v.`key` = \'bruksomrade\' LIMIT 1) AS bruk
            FROM products p WHERE p.theme = ?';
        $args = [$a['oppgave']];
        if (isset($a['type'])) {
            $sql .= ' AND EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?)';
            $args[] = (int) $a['type'];
        }
        $st = db()->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll();
        $b = $a['budsjett'] ?? '0';
        return array_values(array_filter($rows, fn($r) => self::bracket((int) $r['price_ore']) === $b || $b === '0'));
    }

    /** Which budget answer a price belongs to. */
    private static function bracket(int $ore): string
    {
        return $ore < 20000 ? '200' : ($ore <= 100000 ? '1000' : '99999');
    }

    /** Which answer to a question a product belongs to, to work out how well the question splits. */
    private static function bucket(string $key, array $r): string
    {
        return match ($key) {
            'budsjett' => self::bracket((int) $r['price_ore']),
            'sted' => ['Innendørs' => 'inne', 'Utendørs' => 'ute', 'Marint' => 'sjo'][$r['bruk'] ?? ''] ?? '?',
            'erfaring' => preg_match('/sett|komplett|kit/iu', $r['name']) && !preg_match('/reservesett/iu', $r['name']) ? 'ny'
                : (preg_match('/proff|pro\b|industri|ultimate|core/iu', $r['name']) ? 'proff' : '?'),
        };
    }

    /** Entropy (in bits) of how the answers to the question split the products. 0 means the question separates nothing. */
    private static function entropy(string $key, array $rows, array $prior = []): float
    {
        $counts = [];
        $n = 0.0;
        foreach ($rows as $r) {
            $w = 1 + ($prior[(int) $r['id']] ?? 0); // what customers actually found counts more
            $b = self::bucket($key, $r);
            $counts[$b] = ($counts[$b] ?? 0) + $w;
            $n += $w;
        }
        $h = 0.0;
        foreach ($counts as $c) {
            $h -= ($c / $n) * log($c / $n, 2);
        }
        return $h;
    }

    /**
     * Next question: [key, question, [value => answer]], or null when the guide is done.
     * Broad to narrow: task, product type, then the question that splits the remaining products best.
     */
    public static function next(array $a): ?array
    {
        if (!isset($a['oppgave'])) {
            return ['oppgave', self::QUESTIONS['oppgave'][0], self::QUESTIONS['oppgave'][1]];
        }
        $types = self::types($a['oppgave']);
        if (!isset($a['type']) && count($types) > 1) {
            return ['type', self::TYPE_Q, $types];
        }
        $rows = self::candidates($a);
        if (count($rows) <= 3) {
            return null;
        }
        $best = null;
        $bestH = 0.0;
        $prior = self::learned($a);
        foreach (['budsjett', 'sted', 'erfaring'] as $k) {
            if (isset($a[$k])) {
                continue;
            }
            $h = self::entropy($k, $rows, $prior);
            if ($h > $bestH + 1e-9) {
                [$best, $bestH] = [$k, $h];
            }
        }
        if ($best === null) {
            return null;
        }
        // Only answers that have products, plus "doesn't matter"
        $have = array_flip(array_map(fn($r) => self::bucket($best, $r), $rows));
        $opts = array_filter(self::QUESTIONS[$best][1], fn($v) => isset($have[(string) $v]) || in_array((string) $v, ['0', 'begge', 'litt'], true), ARRAY_FILTER_USE_KEY);
        return [$best, self::QUESTIONS[$best][0], $opts];
    }

    /** The questions the customer has answered, in order: [key, question, answer text]. */
    public static function asked(array $a): array
    {
        $out = [];
        $so = [];
        foreach (['oppgave', 'type', 'budsjett', 'sted', 'erfaring'] as $_) {
            $q = self::next($so);
            if ($q === null || !isset($a[$q[0]])) {
                break;
            }
            $out[] = [$q[0], $q[1], $q[2][$a[$q[0]]] ?? $a[$q[0]]];
            $so[$q[0]] = $a[$q[0]];
        }
        return $out;
    }

    /** @return array{picks:array, bundle:array, trace:array} */
    public static function recommend(array $a): array
    {
        $theme = array_key_exists($a['oppgave'] ?? '', Catalog::THEMES) ? $a['oppgave'] : 'lys';
        $max = (int) ($a['budsjett'] ?? 0);
        $min = match ($max) { 1000 => 20000, 99999 => 100000, default => 0 };
        $sted = $a['sted'] ?? 'begge';
        $erf = $a['erfaring'] ?? 'litt';
        $trace = [t('Tema: {theme} (fra svaret ditt på hva du skal gjøre)', ['theme' => theme_name($theme)])];

        $ids = array_map(fn($r) => (int) $r['id'], self::candidates(['oppgave' => $theme] + $a));
        $cards = Catalog::cards($ids);
        if (isset($a['type'])) {
            $trace[] = t('Type: {type}', ['type' => t(self::types($theme)[$a['type']] ?? '')]);
        }
        $use = db()->query("SELECT product_id, value_text FROM spec_values WHERE `key` = 'bruksomrade'")->fetchAll(PDO::FETCH_KEY_PAIR);
        $docRel = db()->query("SELECT DISTINCT product_a FROM relations WHERE level IN ('dokumentert','regelutledet') AND kind = 'passer_sammen'")->fetchAll(PDO::FETCH_COLUMN);
        $docRel = array_flip($docRel);

        $learned = self::learned($a);
        $scored = [];
        foreach ($cards as $c) {
            $s = 0;
            $why = [];
            if ($max > 0) {
                if ($c['price_ore'] <= $max * 100 && $c['price_ore'] >= $min) {
                    $s += 4;
                    $why[] = t('Innenfor budsjettet ditt');
                } elseif ($c['price_ore'] > $max * 100) {
                    $s -= 6;
                }
            }
            $u = $use[$c['id']] ?? '';
            $wantUse = ['inne' => 'Innendørs', 'ute' => 'Utendørs', 'sjo' => 'Marint'][$sted] ?? null;
            // 12 and 24 volts are power in cars, boats and caravans, not in the wall socket at home
            $vehicle = $wantUse === 'Innendørs' && preg_match('/\b(12|24) ?v\b/iu', $c['name']);
            if ($wantUse && $u === $wantUse && !$vehicle) {
                $s += 3;
                $why[] = t('Merket for {use} bruk (utledet fra produkttekst)', ['use' => mb_strtolower(I18n::value($u))]);
            } elseif ($vehicle || $wantUse === 'Innendørs' && in_array($u, ['Utendørs', 'Marint'], true)
                || in_array($wantUse, ['Utendørs', 'Marint'], true) && $u === 'Innendørs') {
                $s -= 3;
            }
            if ($erf === 'ny' && preg_match('/sett|komplett|kit/iu', $c['name']) && !preg_match('/reservesett/iu', $c['name'])) {
                $s += 3;
                $why[] = t('Komplett sett, fint første gang');
            }
            if ($erf === 'proff' && preg_match('/proff|pro\b|industri|ultimate|core/iu', $c['name'])) {
                $s += 2;
                $why[] = t('Profesjonell kvalitet');
            }
            if ($c['rating'] !== null) {
                $s += (float) $c['rating'] - 3;
                $why[] = t('Snitt {r} av 5 fra {n} kjøpere', ['r' => number_format((float) $c['rating'], 1, lang() === 'en' ? '.' : ',', ''), 'n' => $c['n_reviews']]);
            }
            if ($c['in_store'] > 0) {
                $s += 1;
                $why[] = t('På lager i {wh}', ['wh' => warehouses()[warehouse_id()]['name'] ?? t('valgt varehus')]);
            }
            if (isset($docRel[$c['id']])) {
                $s += 1;
                $why[] = t('Vi vet hva som passer sammen med den');
            }
            if ($c['img']) {
                $s += 0.5;
            }
            if ($l = $learned[$c['id']] ?? 0) {
                $s += min(6, $l);
                $why[] = t('Andre med lignende svar fant det de lette etter her');
            }
            $scored[] = ['score' => $s, 'why' => $why] + $c;
        }
        usort($scored, fn($x, $y) => $y['score'] <=> $x['score']);
        $picks = array_slice($scored, 0, 3);

        if ($max > 0) {
            $trace[] = t('Budsjett: {b} (bare produkter i dette prisområdet)', ['b' => t(self::QUESTIONS['budsjett'][1][(string) $max])]);
        }
        if (isset($a['sted'])) {
            $trace[] = t('Bruksområde: {u} (+3 ved treff, minus 3 ved motsatt)', ['u' => t(self::QUESTIONS['sted'][1][$sted])])
                . ($sted === 'inne' ? ' ' . t('12 og 24 volt er for bil og båt, så de teller som motsatt.') : '');
        }
        if (isset($a['erfaring'])) {
            $trace[] = t('Erfaring: {e}', ['e' => t(self::QUESTIONS['erfaring'][1][$erf])]) . ($erf === 'ny' ? ' ' . t('(sett prioriteres)') : '');
        }
        $trace[] = t('Deretter vurderinger fra kjøpere, lager i ditt varehus og kjente koblinger. {n} produkter ble vurdert.', ['n' => count($cards)]);
        if ($learned) {
            $trace[] = t('Til slutt: hva tidligere kunder med lignende svar sa var riktig (opptil +6).');
        }

        $bundle = $picks ? Catalog::relations($picks[0]['id'], 6)['passer'] : [];
        return ['picks' => $picks, 'bundle' => $bundle, 'trace' => $trace, 'theme' => $theme, 'cards' => $cards];
    }

    // ---------- Tracking: Søren knows what the customer answered in the guide ----------

    /** Known answers only. Unknown keys and values are discarded. */
    public static function answers(array $in): array
    {
        $a = [];
        foreach (self::QUESTIONS as $k => [, $opts]) {
            if (is_string($in[$k] ?? null) && isset($opts[$in[$k]])) {
                $a[$k] = $in[$k];
            }
        }
        if (isset($a['oppgave']) && is_string($in['type'] ?? null) && isset(self::types($a['oppgave'])[$in['type']])) {
            $a['type'] = $in['type'];
        }
        return $a;
    }

    public static function complete(array $a): bool
    {
        $a = self::answers($a);
        return isset($a['oppgave']) && self::next($a) === null;
    }

    private static function chosen(array $a): string
    {
        return implode(' ', array_map(fn($q) => "{$q[1]} «{$q[2]}»", self::asked($a)));
    }

    /** The customer's answers and the suggestions, for Søren (in Norwegian). */
    public static function brief(array $a, ?int $focus = null, bool $here = true): string
    {
        return I18n::with('no', function () use ($a, $focus, $here) {
            $a = self::answers($a);
            $res = self::recommend($a);
            $lines = ['PRODUKTGUIDEN: ' . ($here ? 'Kunden ser på forslagene fra produktguiden.' : 'Kunden tok produktguiden tidligere.') . ' Kunden svarte: ' . self::chosen($a)];
            foreach ($res['picks'] as $i => $c) {
                $lines[] = 'Forslag ' . ($i + 1) . ": [{$c['id']}] {$c['name']}, " . kr((int) $c['price_ore']) . '. Grunner: ' . ($c['why'] ? implode('; ', $c['why']) : 'populær i kategorien') . '.'
                    . ($focus === (int) $c['id'] ? ' (Kunden står på denne produktsiden nå.)' : '');
            }
            $lines[] = 'Slik ble de valgt: ' . implode(' ', $res['trace']);
            return implode("\n", $lines);
        });
    }

    /** The customer is in the middle of the guide: what has been answered and which question they are on. */
    public static function progress(array $a): string
    {
        return I18n::with('no', function () use ($a) {
            $a = self::answers($a);
            $lines = ['PRODUKTGUIDEN: Kunden er midt i guiden.' . ($a ? ' Svart så langt: ' . self::chosen($a) : '')];
            if ($q = self::next($a)) {
                $lines[] = "Neste spørsmål: {$q[1]} Valgene er: " . implode(', ', array_map(fn($o) => "«{$o}»", $q[2])) . '.';
                if (isset($a['oppgave'])) {
                    $lines[] = count(self::candidates($a)) . ' produkter passer så langt.';
                }
            }
            return implode("\n", $lines);
        });
    }
}
