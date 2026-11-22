<?php
declare(strict_types=1);

/**
 * The facts Søren may use when staff ask a question in the Control Tower: counted from stored rows, written as short
 * Norwegian lines. Customers appear by alias only. Read-only.
 */
final class StaffBrief
{
    public static function facts(int $now): string
    {
        $pdo = db();
        $out = [];
        $ins = Insight::build($now);
        $t = $ins['tiles'];
        $out[] = sprintf('Siste 7 dager: %d søk, %d uten treff, %d spørsmål til Søren, %d sendt til menneske, %d lagt i kurv, %d kjøp.',
            $t['searches'][0], (int) round(($t['zeroRate'][0] ?? 0) * $t['searches'][0]), $t['chat'][0],
            (int) round(($t['escalateRate'][0] ?? 0) * $t['chat'][0]), $t['cart'][0], $t['purchases'][0]);

        $open = array_filter(Tower::desk(), fn($x) => $x['status'] !== 'lukket');
        $out[] = 'Åpne saker: ' . count($open) . '.';
        foreach (array_slice($open, 0, 6) as $x) {
            $out[] = sprintf('Sak #%d (%s), kunde %s%s, %s.', $x['id'], $x['status'] === 'apen' ? 'åpen' : 'pågår', $x['customer'] ?? 'gjest',
                $x['product'] ? ", gjelder {$x['product']}" : '', $x['last_from'] === 'support' ? 'besvart' : 'venter på svar');
        }

        $zero = Tower::failedSearches($now - 7 * 86400);
        $out[] = $zero ? 'Søk uten treff siste 7 dager: ' . implode(', ', array_map(fn($z) => "«{$z['q']}» {$z['n']} ganger", array_slice($zero, 0, 8))) . '.'
            : 'Ingen søk uten treff siste 7 dager.';

        $q = $ins['quality'];
        $out[] = "Datakvalitet: {$q['missingProducts']} av {$q['products']} produkter mangler minst en viktig spesifikasjon, {$q['missingValues']} verdier mangler, "
            . "{$q['unverified']} verdier er ukontrollerte, {$q['reports']} åpne feilmeldinger, {$q['noPrice']} produkter uten pris.";

        $low = array_filter($ins['cover'], fn($c) => $c['days'] !== null);
        if ($low) {
            $out[] = 'Lavest lager mot salg (nettlager, snitt siste 30 dager): ' . implode('; ', array_map(fn($c) => sprintf('%s: %d på lager, %s',
                $c['name'], $c['stock'], $c['days'] < 1 ? 'tomt' : 'ca. ' . (int) round($c['days']) . ' dager'), array_slice($low, 0, 5))) . '.';
        }

        $ships = $pdo->query("SELECT s.ref, s.direction, s.status, s.origin_city, s.dest_city, s.is_demo, DATE_FORMAT(s.eta, '%d.%m %H:%i') eta, s.delay_reason,
                (SELECT COALESCE(SUM(qty), 0) FROM shipment_items i WHERE i.shipment_id = s.id) units
            FROM shipments s WHERE s.status NOT IN ('levert', 'kansellert') ORDER BY s.eta")->fetchAll();
        foreach ($ships as $s) {
            $out[] = sprintf('Sending %s%s: %s til %s, %s, %d enheter%s%s.', $s['ref'], $s['is_demo'] ? ' (demo)' : '', $s['origin_city'], $s['dest_city'],
                str_replace('_', ' ', $s['status']), $s['units'], $s['eta'] ? ", ankomst ca. {$s['eta']}" : '', $s['delay_reason'] ? ", årsak: {$s['delay_reason']}" : '');
        }
        $flow = Insight::stockFlow($now, 7);
        $out[] = sprintf('Lagerflyt siste 7 dager: %d enheter inn, %d solgt, %d på vei inn.', array_sum($flow['in']), array_sum($flow['out']), $flow['coming']);
        return implode("\n", $out);
    }
}
