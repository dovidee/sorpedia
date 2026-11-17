<?php
declare(strict_types=1);

/**
 * Extracts specifications from Sør-Tre's product title and text.
 *
 * Source level (constitution point 2):
 *   sortre  = the number or value is stated explicitly by Sør-Tre (e.g. "2500 Lumen"). Stored in Verified.
 *   utledet = our interpretation (e.g. "vf" means hot dip galvanized, color, use case). Stored in Open.
 * Every value gets a text snippet as evidence. Unknown is a valid answer: if we find nothing, nothing is stored.
 */
final class Extract
{
    /** spec_keys: key => [label, unit, datatype, is_facet, themes|null, sort] */
    public const KEYS = [
        'merke'            => ['Merke', null, 'text', 1, null, 1],
        'materiale'        => ['Materiale', null, 'text', 1, null, 2],
        'farge'            => ['Farge', null, 'text', 1, null, 3],
        'bruksomrade'      => ['Bruksområde', null, 'text', 1, null, 4],
        'lysstyrke_lm'     => ['Lysstyrke', 'lm', 'number', 1, 'lys', 10],
        'rekkevidde_m'     => ['Rekkevidde (lyskaster)', 'm', 'number', 1, 'lys', 11],
        'brenntid_t'       => ['Brenntid', 't', 'number', 0, 'lys', 12],
        'batteri'          => ['Batteri', null, 'text', 1, 'lys,tur,skog', 13],
        'ip_klasse'        => ['Vanntetthet (IP)', null, 'text', 1, 'lys,tur', 14],
        'sokkel'           => ['Sokkel', null, 'text', 1, 'lys', 15],
        'effekt_w'         => ['Effekt', 'W', 'number', 1, 'lys,skog', 16],
        'fallhoyde_m'      => ['Tåler fall', 'm', 'number', 0, 'lys', 17],
        'diameter_mm'      => ['Diameter', 'mm', 'number', 1, 'bygg', 20],
        'lengde_mm'        => ['Lengde', 'mm', 'number', 1, 'bygg,skog', 21],
        'bredde_mm'        => ['Bredde', 'mm', 'number', 1, 'maling', 22],
        'kornstorrelse'    => ['Kornstørrelse', null, 'number', 1, 'maling', 23],
        'pakning_stk'      => ['Antall i pakken', 'stk', 'number', 0, null, 24],
        'vekt_g'           => ['Vekt', 'g', 'number', 1, null, 25],
        'bredde_cm'        => ['Bredde', 'cm', 'number', 1, 'balkong', 30],
        'hoyde_cm'         => ['Høyde', 'cm', 'number', 1, 'balkong', 31],
        'tykkelse_mm'      => ['Glasstykkelse', 'mm', 'number', 1, 'balkong', 32],
        'dimensjon_mm'     => ['Dimensjon', 'mm', 'number', 1, 'kjetting', 40],
        'dimensjon_tomme'  => ['Dimensjon (tommer)', '″', 'text', 0, 'kjetting', 41],
        'arbeidslast_kg'   => ['Arbeidslast (WLL)', 'kg', 'number', 1, 'kjetting', 42],
        'bruddstyrke_kg'   => ['Bruddstyrke', 'kg', 'number', 0, 'kjetting', 43],
        'sverdlengde_tomme'=> ['Sverdlengde', '″', 'number', 1, 'skog', 50],
        'drivlenker'       => ['Drivlenker', 'stk', 'number', 1, 'skog', 51],
        'storrelse'        => ['Størrelse', null, 'text', 1, 'skog', 52],
        'standard'         => ['Standard', null, 'text', 0, 'skog', 53],
        'volum_l'          => ['Volum', 'l', 'number', 1, 'skog,tur', 54],
        'forstorrelse'     => ['Forstørrelse', 'x', 'number', 1, 'tur', 60],
        'objektiv_mm'      => ['Objektivdiameter', 'mm', 'number', 0, 'tur', 61],
    ];

    private const BRANDS = [
        'ledlenser' => 'Ledlenser', 'osean' => 'Osean', 'fiskars' => 'Fiskars', 'bahco' => 'Bahco',
        'jordan' => 'Jordan', 'qpt' => 'QPT', 'go paint' => 'Go Paint', 'hexagon' => 'Hexagon',
        'cal' => 'CAL', 'cutfix' => 'CutFix', 'vindfix' => 'VindFix', 'wondergrip' => 'WonderGrip',
        'showa' => 'Showa', 'magma' => 'Magma', 'gp' => 'GP', 'handi' => 'Handi', 'omnia' => 'Omnia',
        'eufab' => 'Eufab', 'optisan' => 'Optisan', '1852' => '1852', 'aqiila' => 'Aqiila',
        'einhell' => 'Einhell', 'husqvarna' => 'Husqvarna', 'stihl' => 'Stihl', 'peltor' => '3M Peltor',
        '3m' => '3M', 'stanley' => 'Stanley', 'hultafors' => 'Hultafors', 'gripit' => 'Gripit',
        'essve' => 'ESSVE', 'biltema' => 'Biltema', 'varta' => 'Varta', 'energizer' => 'Energizer',
        'duracell' => 'Duracell', 'airam' => 'Airam', 'mirka' => 'Mirka', 'tesa' => 'tesa',
        'anza' => 'Anza', 'colorex' => 'Colorex', 'jotun' => 'Jotun', 'kwik' => 'Kwik',
    ];

    /** @return list<array{key:string,text:string,num:?float,source:string,evidence:string}> */
    public static function specs(string $name, string $html, string $theme): array
    {
        $text = self::plain($html);
        $out = [];
        $add = function (string $key, string $txt, ?float $num, string $src, string $haystack, int $pos, int $len, ?string $label = null, ?string $unit = null) use (&$out): void {
            if (isset($out[$key]) || trim($txt) === '') {
                return;
            }
            $out[$key] = [
                'key' => $key,
                'text' => trim($txt),
                'num' => $num,
                'source' => $src,
                'evidence' => self::snippet($haystack, $pos, $len),
                'label' => $label,
                'unit' => $unit,
            ];
        };
        // Runs the regex against the name first, then the text. The callback gets the matches and returns [text, number] or null.
        $rule = function (string $key, string $re, callable $fn, string $src = 'sortre', bool $textToo = true)
            use ($name, $text, $add): void {
            foreach ($textToo ? [$name, $text] : [$name] as $hay) {
                if (preg_match($re, $hay, $m, PREG_OFFSET_CAPTURE)) {
                    $vals = array_map(fn($g) => $g[0], $m);
                    $r = $fn($vals);
                    if ($r !== null) {
                        $add($key, $r[0], $r[1], $src, $hay, $m[0][1], strlen($m[0][0]));
                        return;
                    }
                }
            }
        };
        $n = fn(string $s): float => (float) str_replace([' ', ','], ['', '.'], trim($s));
        $fmt = fn(float $f): string => rtrim(rtrim(number_format($f, 2, ',', ' '), '0'), ',');
        $is = fn(string ...$themes): bool => in_array($theme, $themes, true);
        $lname = mb_strtolower($name);

        // Sør-Tre's own tables ("Egenskap | Verdi"), "Key: value" lines and bullet lists. Run first, so they take precedence over the interpretations below.
        self::pairs(self::tableText($html), $add, $n, $fmt);
        self::pairs($text, $add, $n, $fmt);
        self::fromName($name, $add, $n, $fmt);
        self::included($text, $add);

        // Brand (interpreted from the title)
        foreach (self::BRANDS as $needle => $brand) {
            if (preg_match('/(^|[\s(])' . preg_quote((string) $needle, '/') . '($|[\s,)])/iu', $name, $m, PREG_OFFSET_CAPTURE)) {
                $add('merke', $brand, null, 'utledet', $name, $m[0][1], strlen($m[0][0]));
                break;
            }
        }

        if ($is('lys', 'tur', 'skog')) {
            $rule('lysstyrke_lm', '/(\d[\d .]*\d|\d)\s*(?:lumen|lm)\b/iu', fn($m) => [$fmt($n($m[1])), $n($m[1])]);
            $rule('batteri', '/\b(\d)\s*(?:x|×|stk\.?)\s*(AAA|AA|D|C)\b/u', fn($m) => ["{$m[1]}x{$m[2]}", null]);
            $rule('batteri', '/\b(21700|18650|CR2032|CR123A?)\b/u', fn($m) => [$m[1], null]);
            $rule('batteri', '/\boppladbar/iu', fn($m) => ['Oppladbart', null]);
            $rule('ip_klasse', '/\bIP\s?([X\d]\d)\b/u', fn($m) => ['IP' . $m[1], null]);
            $rule('brenntid_t', '/Brenntid:?\s*(\d+(?:[.,]\d+)?)\s*t/iu', fn($m) => [$fmt($n($m[1])), $n($m[1])]);
            $rule('fallhoyde_m', '/Støtsikker:?\s*(\d+(?:[.,]\d+)?)\s*m\b/iu', fn($m) => [$fmt($n($m[1])), $n($m[1])]);
        }
        if ($is('lys')) {
            $rule('sokkel', '/\b(E27|E14|GU10|G9|GU5[.,]3)\b/u', fn($m) => [$m[1], null]);
            $rule('effekt_w', '/(\d+(?:[.,]\d+)?)\s*W\b/u', fn($m) => [$fmt($n($m[1])), $n($m[1])], 'sortre', false);
        }
        if ($is('skog')) {
            $rule('effekt_w', '/(\d+(?:[.,]\d+)?)\s*W\b/u', fn($m) => [$fmt($n($m[1])), $n($m[1])], 'sortre', false);
            $rule('sverdlengde_tomme', '/(\d{2})\s*[″"]\s*[–-]/u', fn($m) => [$m[1], (float) $m[1]], 'sortre', false);
            $rule('drivlenker', '/(\d{2,3})\s*drivlenker/iu', fn($m) => [$m[1], (float) $m[1]], 'sortre', false);
            $rule('storrelse', '/\b(?:str\.?|størrelse)\s*:?\s*(\d{1,2}|XXL|XL|XS|S|M|L)\b/iu', fn($m) => [strtoupper($m[1]), null]);
            $rule('standard', '/\bEN\s?(\d{3,5})/u', fn($m) => ['EN ' . $m[1], null]);
        }
        if ($is('skog', 'tur')) {
            $rule('volum_l', '/(\d+(?:[.,]\d+)?)\s*(?:l|L|liter)\b/u', fn($m) => [$fmt($n($m[1])), $n($m[1])], 'sortre', false);
        }
        if ($is('tur') && preg_match('/kikkert/iu', $name)) {
            $rule('forstorrelse', '/\b(\d{1,2})\s*[x×]\s*(\d{2})(?!\d)/u', function ($m) {
                return [$m[1] . 'x' . $m[2], (float) $m[1]];
            });
            $rule('objektiv_mm', '/\b\d{1,2}\s*[x×]\s*(\d{2})(?!\d)/u', fn($m) => [$m[1], (float) $m[1]]);
        }
        if ($is('bygg')) {
            // 4,0x40 (screw) or 28-75 / 2,0-35 (nails and staples: diameter in tenths of a mm or with a comma)
            $rule('diameter_mm', '/\b(\d{1,2}(?:,\d)?)\s*[x×]\s*(\d{2,3})\b/u', fn($m) => $n($m[1]) <= 12 ? [$fmt($n($m[1])), $n($m[1])] : null, 'sortre', false);
            $rule('lengde_mm', '/\b\d{1,2}(?:,\d)?\s*[x×]\s*(\d{2,3})\b/u', fn($m) => [$m[1], (float) $m[1]], 'sortre', false);
            if (preg_match('/spiker|stift|murstift|tacks/u', $lname)) {
                $rule('diameter_mm', '/\b(\d+(?:,\d)?)-(\d{2,3})\b/u', function ($m) use ($n, $fmt) {
                    $d = str_contains($m[1], ',') ? $n($m[1]) : ((int) $m[1] >= 10 ? (int) $m[1] / 10 : (float) $m[1]);
                    return [$fmt($d), $d];
                }, 'sortre', false);
                $rule('lengde_mm', '/\b\d+(?:,\d)?-(\d{2,3})\b/u', fn($m) => [$m[1], (float) $m[1]], 'sortre', false);
            }
            $rule('vekt_g', '/(\d+(?:[.,]\d+)?)\s*kg\b/iu', fn($m) => [$fmt($n($m[1]) * 1000), $n($m[1]) * 1000], 'sortre', false);
            $rule('pakning_stk', '/\bP(\d{2,4})\b/u', fn($m) => [$m[1], (float) $m[1]], 'sortre', false);
        }
        if ($is('maling')) {
            $rule('bredde_mm', '/(\d{2,3})\s*mm\b/u', fn($m) => [$m[1], (float) $m[1]], 'sortre', false);
            $rule('kornstorrelse', '/\b(?:K|P|korn(?:ing)?)\s?(\d{2,4})\b/iu', fn($m) => [$m[1], (float) $m[1]]);
        }
        if ($is('balkong')) {
            $rule('bredde_cm', '/(\d{2,3})\s*[x×]\s*(\d{2,3})\s*cm/u', fn($m) => [$m[1], (float) $m[1]], 'sortre', false);
            $rule('hoyde_cm', '/(\d{2,3})\s*[x×]\s*(\d{2,3})\s*cm/u', fn($m) => [$m[2], (float) $m[2]], 'sortre', false);
            $rule('hoyde_cm', '/(\d{2,3}(?:,\d)?)\s*cm\b/u', fn($m) => [$fmt($n($m[1])), $n($m[1])], 'sortre', false);
            $rule('tykkelse_mm', '/(\d{1,2})\s*mm\s*herdet/u', fn($m) => [$m[1], (float) $m[1]], 'sortre', false);
        }
        if ($is('kjetting')) {
            $rule('dimensjon_mm', '/(\d+(?:[.,]\d+)?)\s*mm\s*$/u', fn($m) => [$fmt($n($m[1])), $n($m[1])], 'sortre', false);
            $rule('dimensjon_mm', '/(?:Bolt og godstykkelse|Godstykkelse):?\s*(\d+(?:[.,]\d+)?)\s*mm/iu', fn($m) => [$fmt($n($m[1])), $n($m[1])]);
            $rule('dimensjon_tomme', '/(\d+\/\d+|\d)\s*[″"]/u', fn($m) => [$m[1] . '″', null], 'sortre', false);
            $rule('arbeidslast_kg', '/Arbeidslast\s*(?:kg)?\s*:?\s*(\d[\d ]*\d|\d)/iu', fn($m) => [$fmt($n($m[1])), $n($m[1])]);
            $rule('bruddstyrke_kg', '/Bruddstyrke\s*(?:kg)?\s*:?\s*(\d[\d ]*\d|\d)/iu', fn($m) => [$fmt($n($m[1])), $n($m[1])]);
        }
        $rule('vekt_g', '/Vekt:?\s*(\d+(?:[.,]\d+)?)\s*(gr|g|gram|kg)\b/iu', function ($m) use ($n, $fmt) {
            $g = $n($m[1]) * (strtolower($m[2]) === 'kg' ? 1000 : 1);
            return [$fmt($g), $g];
        });
        $rule('pakning_stk', '/\b(\d{1,4})\s*(?:stk|pk)\b\.?/iu', fn($m) => (int) $m[1] > 1 ? [$m[1], (float) $m[1]] : null, 'sortre', false);

        // Derived: material (first hit in a prioritized list, title before text)
        $materials = [
            '/syrefast|\bA4\b/iu' => 'Syrefast stål',
            '/rustfri|\bA2\b/iu' => 'Rustfritt stål',
            '/varmgalv|galvanisert|varmforsinket|\bvf\b|\bvfz\b|\bhdg\b/iu' => 'Galvanisert stål',
            '/elforsinket|\bel\b|\bez\b/u' => 'Elforsinket stål',
            '/messing|\bMS\b/u' => 'Messing',
            '/kobber/iu' => 'Kobber',
            '/alumin|\balu\b/iu' => 'Aluminium',
            '/kompositt|glassfiber/iu' => 'Kompositt',
            '/balkongglass|herdet.*glass|\bglass\b/iu' => 'Glass',
            '/nitril/iu' => 'Nitril',
            '/\blær\b|skinn|geitelær|kuskinn/iu' => 'Lær',
            '/latex/iu' => 'Latex',
            '/naturbust/iu' => 'Naturbust',
            '/nylon|polyamid/iu' => 'Nylon',
            '/polyester/iu' => 'Polyester',
            '/\bplast\b|polypropylen|\bPP\b|\bABS\b/u' => 'Plast',
            '/treskaft|hickory|\bbjørk\b|\btre\b/iu' => 'Tre',
            '/gummi/iu' => 'Gummi',
            '/\bstål\b|herdet/iu' => 'Stål',
        ];
        // Title: prioritized order. Text: the material mentioned first, because
        // later hits often concern accessories ("leveres med skruer i A2 rustfritt").
        foreach ([$name, $text] as $hay) {
            $best = null; // [material, position, length]
            foreach ($materials as $re => $mat) {
                // "el" and "MS" are only safe as abbreviations in hardware titles
                if (($mat === 'Elforsinket stål' || $mat === 'Messing') && ($hay !== $name || !$is('bygg', 'kjetting'))) {
                    continue;
                }
                if ($mat === 'Tre' && $hay !== $name && !preg_match('/treskaft|hickory|\bbjørk/iu', $hay)) { // "tre" (wood) is often the number three
                    continue;
                }
                if (preg_match($re, $hay, $m, PREG_OFFSET_CAPTURE)) {
                    $hit = [$mat, $m[0][1], strlen($m[0][0])];
                    if ($hay === $name) {
                        $best = $hit;
                        break;
                    }
                    if ($best === null || $hit[1] < $best[1]) {
                        $best = $hit;
                    }
                }
            }
            if ($best !== null) {
                $add('materiale', $best[0], null, 'utledet', $hay, $best[1], $best[2]);
                break;
            }
        }

        // Derived: color (only from the title, to avoid random words in the text)
        $colors = [
            'hvit' => 'Hvit', 'svart' => 'Svart', 'sort' => 'Svart', 'rød' => 'Rød', 'blå' => 'Blå',
            'grønn' => 'Grønn', 'gul' => 'Gul', 'oransje' => 'Oransje', 'grå' => 'Grå',
            'klart glass' => 'Klar', 'frostet' => 'Frostet', 'sotet' => 'Sotet', 'kobberbelagt' => 'Kobber',
        ];
        foreach ($colors as $needle => $color) {
            if (preg_match('/(^|\P{L})' . preg_quote((string) $needle, '/') . '(\P{L}|$)/iu', $name, $m, PREG_OFFSET_CAPTURE)) {
                $add('farge', $color, null, 'utledet', $name, $m[0][1], strlen($m[0][0]));
                break;
            }
        }

        // Derived: use case from keywords, otherwise from the theme
        $uses = [
            '/\bINNE\b|innendørs|interiør/iu' => 'Innendørs',
            '/\bUTE\b|utendørs|terrasse|fasade|balkong/iu' => 'Utendørs',
            '/syrefast|marin|båt|sjø|watersport|vannsport|undervann/iu' => 'Marint',
            '/hodelykt|\btur(?:er|en)?\b|fisketur|camping|friluft/iu' => 'Tur og friluft',
            '/arbeidslys|verksted/iu' => 'Arbeid og verksted',
        ];
        foreach ([$name, $text] as $hay) {
            foreach ($uses as $re => $use) {
                if (preg_match($re, $hay, $m, PREG_OFFSET_CAPTURE)) {
                    $add('bruksomrade', $use, null, 'utledet', $hay, $m[0][1], strlen($m[0][0]));
                    break 2;
                }
            }
        }
        $themeUse = ['lys' => 'Belysning', 'bygg' => 'Bygg og snekring', 'maling' => 'Maling og overflate',
            'balkong' => 'Utendørs', 'kjetting' => 'Løft og sikring', 'skog' => 'Skog og hage', 'tur' => 'Tur og friluft'];
        $add('bruksomrade', $themeUse[$theme] ?? 'Annet', null, 'utledet', "Kategori: $theme", 0, 0);

        // Pack size in the title (P6) is more precise than "Antall i pakke: 1", which appears in many texts
        if (preg_match('/\bP(\d{1,4})\b/u', $name, $pm) && (int) $pm[1] > 1) {
            $out['pakning_stk'] = ['key' => 'pakning_stk', 'text' => $pm[1], 'num' => (float) $pm[1], 'source' => 'sortre',
                'evidence' => $name, 'label' => null, 'unit' => null];
        }
        return array_values($out);
    }

    /** Synonyms in Sør-Tre's text => our key and how the value is read. */
    private const SYNONYMS = [
        'vekt' => 'vekt_g', 'vekt inkl. koffert' => 'vekt_g', 'hovedmateriale' => 'materiale', 'materiale' => 'materiale',
        'batteri' => 'batteri', 'batteritype' => 'batteri', 'lysstyrke' => 'lysstyrke_lm', 'effekt' => 'effekt_w',
        'maks effekt' => 'effekt_w', 'forstørrelse' => 'forstorrelse', 'objektivdiameter' => 'objektiv_mm',
        'vanntetthet' => 'ip_klasse', 'lengde' => 'lengde_mm', 'diameter' => 'diameter_mm', 'bredde' => 'bredde_mm',
        'dimensjon' => 'dimensjon_mm', 'sverdlengde' => 'sverdlengde_tomme', 'korning' => 'kornstorrelse',
        'standard' => 'standard', 'oppfyller følgende standarder' => 'standard', 'sertifisering' => 'standard',
        'bruddstyrke' => 'bruddstyrke_kg', 'bruddstyrke kg' => 'bruddstyrke_kg', 'arbeidslast' => 'arbeidslast_kg', 'arbeidslast kg' => 'arbeidslast_kg',
        'godstykkelse' => 'dimensjon_mm', 'bolt og godstykkelse' => 'dimensjon_mm', 'farge' => 'farge', 'størrelse' => 'storrelse',
        'produkttype' => 'x_produkttype', 'senterhull' => 'x_senterhull',
        'merke' => 'merke', 'bruksområde' => 'bruksomrade', 'bruksområder' => 'bruksomrade', 'sokkel' => 'sokkel', 'brenntid' => 'brenntid_t', 'brukstid pr oppladning' => 'brenntid_t',
    ];
    /** Abbreviations Sør-Tre uses for materials. */
    private const MATERIAL_ABBR = ['mess' => 'Messing', 'ms' => 'Messing', 'el' => 'Elforsinket stål', 'elf' => 'Elforsinket stål',
        'vf' => 'Varmforsinket stål', 'fzv' => 'Varmforsinket stål', 'rf' => 'Rustfritt stål', 'a2' => 'Rustfritt stål', 'a4' => 'Syrefast stål'];

    /** Lines that aren't properties of the product. */
    private const SKIP = ['kort sagt', 'artikkelnummer', 'ean', 'varenummer', 'pris', 'obs', 'nb', 'tips', 'merk', 'les mer',
        'tekniske spesifikasjoner', 'spesifikasjoner', 'brukstips', 'egenskap', 'produsent', 'produktfamilie'];

    /** "Maks effekt: 2,5 kW", "Vekt: 2087 gram", dash bullets like "Piezo tenning". Everything Sør-Tre writes about the product becomes properties. */
    private static function pairs(string $text, callable $add, callable $n, callable $fmt): void
    {
        // Find every "Key:" first, also when the text is glued together ("4tDim: 48x78mm"). The value is the text up to the next key.
        $start = '(?:^|(?<=[\s–•.;)])|(?<=[\d\p{Ll}])(?=[A-ZÆØÅ][a-zæøå]{2}))';
        preg_match_all('/' . $start . '([A-ZÆØÅ](?:[\p{Ll} ,\/()-]|(?<=[\s(\/-])\p{Lu}){1,34}?):(?!\/)\s*/u', $text, $keys, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $all = [];
        foreach ($keys as $i => $km) {
            $from = $km[0][1] + strlen($km[0][0]);
            $to = isset($keys[$i + 1]) ? $keys[$i + 1][0][1] : strlen($text);
            $val = substr($text, $from, $to - $from);
            // The value ends at the end of a sentence, a standalone dash bullet, or a new capitalized word glued on
            $val = preg_split('/\.\s|\.$|\s[–•]\s|(?<=[\d\p{Ll}])(?=[A-ZÆØÅ][a-zæøå]{2})/u', $val)[0];
            if (preg_match('/^\s*[\d.,]/u', $val)) { // number with unit: stop before the next sentence ("16 mm Benyttes til ...")
                $val = preg_split('/\s(?=[A-ZÆØÅ][a-zæøå]{2,})/u', $val)[0];
            }
            $all[] = [$km[0][1], $km[1][0], $val, strlen($km[0][0])];
        }
        foreach ($all as [$pos, $keyRaw, $val, $klen]) {
            $m = [[str_repeat(' ', $klen)]]; // the length of the key, for the text snippet
            $val = trim($val, " ,;.");
            $label = trim(preg_replace('/\s+/u', ' ', $keyRaw) ?? '');
            // "25mm Ø sokkel Sokkel": use the last capitalized part if it is a known key
            if (preg_match('/\s(\p{Lu}[\p{Ll}]+(?:\s[\p{Ll},\/()-]+)*)$/u', $label, $suf) && isset(self::SYNONYMS[mb_strtolower($suf[1])])) {
                $label = $suf[1];
            }
            $k = mb_strtolower($label);
            // "Øyeskrue antall i pakke", "CAL Sjakkel D materiale": the end of the key decides
            if (preg_match('/antall (?:i pakke|i pakken|per pakke|batterier per pakke)$/u', $k)) {
                $k = 'antall i pakke';
            } elseif (preg_match('/\bmateriale$/u', $k)) {
                $k = 'materiale';
            }
            if ($val === '' || in_array($k, self::SKIP, true) || mb_strlen($label) > 34 || str_word_count($k) > 5 || mb_strlen($val) > 140) {
                continue;
            }
            $num = preg_match('/(\d[\d ]*(?:[.,]\d+)?)/u', $val, $nm) ? $n($nm[1]) : null;
            $target = self::SYNONYMS[$k] ?? ($k === 'antall i pakke' ? 'pakning_stk' : null);
            $unitIn = mb_strtolower(trim((string) (preg_match('/\d\s*([\p{L}°″"\/]+)/u', $val, $um) ? $um[1] : '')));
            $mapped = null;
            switch ($target) {
                case 'vekt_g':
                    $mapped = $num === null ? null : [$fmt($g = $num * (str_starts_with($unitIn, 'kg') ? 1000 : 1)), $g];
                    break;
                case 'effekt_w':
                    $mapped = $num === null ? null : [$fmt($w = $num * ($unitIn === 'kw' ? 1000 : 1)), $w];
                    break;
                case 'lengde_mm': case 'diameter_mm': case 'bredde_mm': case 'dimensjon_mm':
                    $mapped = $num === null || !in_array($unitIn, ['mm', 'cm', ''], true) ? null : [$fmt($mm = $num * ($unitIn === 'cm' ? 10 : 1)), $mm];
                    break;
                case 'bruddstyrke_kg': case 'arbeidslast_kg':
                case 'lysstyrke_lm': case 'forstorrelse': case 'objektiv_mm': case 'sverdlengde_tomme': case 'kornstorrelse': case 'pakning_stk': case 'brenntid_t':
                    $mapped = $num === null ? null : [$fmt($num), $num];
                    break;
                case 'ip_klasse':
                    $mapped = preg_match('/IP\s?([X\d]\d)/u', $val, $ip) ? ['IP' . $ip[1], null] : null;
                    break;
                case null:
                    break;
                case 'sokkel':
                    $mapped = preg_match('/^([A-Za-z]{1,4}\d[\dA-Za-z,.]*)/u', $val, $so) ? [str_replace(',', '.', $so[1]), null] : null;
                    break;
                case 'x_produkttype':
                    $add('x_produkttype', $val, null, 'sortre', $text, $pos, strlen($m[0][0]), 'Produkttype', null);
                    continue 2;
                case 'x_senterhull':
                    $add('x_senterhull', $val, $num, 'sortre', $text, $pos, strlen($m[0][0]), 'Senterhull', null);
                    continue 2;
                case 'materiale':
                    $mapped = [self::MATERIAL_ABBR[mb_strtolower($val)] ?? $val, null];
                    break;
                default:
                    $mapped = [$val, null];
            }
            if ($mapped !== null) {
                $add($target, $mapped[0], $mapped[1], 'sortre', $text, $pos, strlen($m[0][0]));
            } else {
                // New property as its own row (constitution point 4: rows, not columns)
                $slug = 'x_' . trim((string) preg_replace('/[^a-z0-9]+/', '_', strtr($k, ['æ' => 'ae', 'ø' => 'o', 'å' => 'a'])), '_');
                $add($slug, $val, $num, 'sortre', $text, $pos, strlen($m[0][0]), mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1), null);
            }
        }
        // Bullets without a colon, like "Piezo tenning" or "CE-godkjent". Collected as Properties.
        if (preg_match_all('/[–•]\s*([^:–•.]{4,60}?)(?=\s*[–•]|\.\s|$)/u', $text, $bm, PREG_OFFSET_CAPTURE)) {
            $items = [];
            foreach ($bm[1] as [$b]) {
                $b = trim($b);
                if (str_word_count($b) >= 1 && str_word_count($b) <= 8 && !preg_match('/^\d/u', $b) && !in_array($b, $items, true)) {
                    $items[] = $b;
                }
            }
            if ($items) {
                $add('egenskaper', implode('; ', array_slice($items, 0, 8)), null, 'sortre', $text, $bm[0][0][1], strlen($bm[0][0][0]), 'Egenskaper', null);
            }
        }
    }

    /** Tables in HTML become "Key: value." lines, so pairs() can read them. */
    private static function tableText(string $html): string
    {
        if (!preg_match_all('/<tr[^>]*>(.*?)<\/tr>/isu', $html, $rows)) {
            return '';
        }
        $out = [];
        foreach ($rows[1] as $row) {
            preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/isu', $row, $c);
            $cells = array_map(fn($x) => trim(self::plain($x)), $c[1]);
            if (count($cells) === 2 && $cells[0] !== '' && $cells[1] !== '' && mb_strtolower($cells[0]) !== 'egenskap') {
                $out[] = mb_strtoupper(mb_substr($cells[0], 0, 1)) . mb_substr($cells[0], 1) . ': ' . rtrim($cells[1], '.') . '.';
            }
        }
        return implode(' ', $out);
    }

    /** Properties in the product name, for all themes: size, volume, dimensions, length, number, weight. */
    private static function fromName(string $name, callable $add, callable $n, callable $fmt): void
    {
        $try = function (string $key, string $re, callable $fn, ?string $label = null, ?string $unit = null) use ($name, $add): void {
            if (preg_match($re, $name, $m, PREG_OFFSET_CAPTURE)) {
                $r = $fn(array_map(fn($g) => $g[0], $m));
                if ($r !== null) {
                    $add($key, $r[0], $r[1], 'sortre', $name, $m[0][1], strlen($m[0][0]), $label, $unit);
                }
            }
        };
        if (preg_match('/hanske/iu', $name)) {
            $try('storrelse', '/\b(?:str\.?\s*)?(\d{1,2})\s*\/\s*(XXL|XL|L|M|S)\b/iu', fn($m) => ["{$m[1]} / " . strtoupper($m[2]), null]);
            $try('storrelse', '/\bstr\.?\s*(\d{1,2})\b/iu', fn($m) => [$m[1], null]);
            $try('storrelse', '/\b(Small \/ Medium|Large \/ Xlarge)\b/iu', fn($m) => [$m[1], null]);
        }
        $try('volum_l', '/(\d+(?:[.,]\d+)?)\s*(ml|l|liter)\b/iu', function ($m) use ($n, $fmt) {
            $l = $n($m[1]) / (mb_strtolower($m[2]) === 'ml' ? 1000 : 1);
            return [$fmt($l), $l];
        });
        $try('x_mal', '/(\d+(?:[.,]\d+)?\s*(?:mm|cm|m)?\s*[x×]\s*\d+(?:[.,]\d+)?\s*(?:mm|cm|m))(?!\w)/u', fn($m) => [$m[1], null], 'Mål');
        $try('x_lengde_m', '/(?<![\dx×,.])(\d+(?:[.,]\d+)?)\s*(?:m|meter)\b(?!\s*[x×])/u', fn($m) => [$fmt($n($m[1])), $n($m[1])], 'Lengde', 'm');
        $try('x_nummer', '/\bnr\.?\s?(\d{1,4})\b/iu', fn($m) => [$m[1], (float) $m[1]], 'Nummer');
        $try('vekt_g', '/(\d+(?:[.,]\d+)?)\s*oz\b/iu', fn($m) => [$fmt(round($n($m[1]) * 28.35)), round($n($m[1]) * 28.35)]);
        $try('vekt_g', '/(\d+(?:[.,]\d+)?)\s*kg\b/iu', fn($m) => [$fmt($n($m[1]) * 1000), $n($m[1]) * 1000]);
        $try('vekt_g', '/(\d+(?:[.,]\d+)?)\s*gr\b/iu', fn($m) => [$fmt($n($m[1])), $n($m[1])]);
        $try('diameter_mm', '/Ø\s*(\d+(?:[.,]\d+)?)\s*(?:mm)?/u', fn($m) => [$fmt($n($m[1])), $n($m[1])]);
        $try('bredde_mm', '/(?<![x×\d])(\d{2,3})\s*mm\b(?!\s*[x×])/u', fn($m) => [$m[1], (float) $m[1]]);
    }

    /** What is and isn't included, and how the product is charged. Important for what the customer must buy in addition. */
    private static function included(string $text, callable $add): void
    {
        $sent = '[^.!?–•:]';
        if (preg_match("/([A-ZÆØÅ]$sent{2,70}?)\s+(?:medfølger|følger med|følger)\s+ikke/u", $text, $m, PREG_OFFSET_CAPTURE)
            || preg_match("/\b(?:eksklusiv|ekskl\.?|må kjøpes separat:?|selges separat:?)\s+($sent{3,60})/u", $text, $m, PREG_OFFSET_CAPTURE)) {
            $add('folger_ikke_med', trim($m[1][0]), null, 'sortre', $text, $m[0][1], strlen($m[0][0]), 'Følger ikke med', null);
        }
        if (preg_match("/(?:leveres|levert|kommer|selges)\s+(?:komplett\s+)?(?:med|i)\s+([^.!?•:]{3,70})/u", $text, $m, PREG_OFFSET_CAPTURE)
            || preg_match("/(?<!\()\b(?:inkl\.?|inkludert|inkluderer)\s+([^.!?–•:()]{3,70})/u", $text, $m, PREG_OFFSET_CAPTURE)
            || preg_match("/([A-ZÆØÅ]$sent{2,70}?)\s+(?:medfølger|følger med)(?!\s+ikke)/u", $text, $m, PREG_OFFSET_CAPTURE)) {
            $add('folger_med', trim($m[1][0]), null, 'sortre', $text, $m[0][1], strlen($m[0][0]), 'Følger med', null);
        }
        // Charging: the sentence that says how it is charged (USB-C, magnetic cable, solar cell)
        if (preg_match("/($sent*\b(?:lades|lading|ladekabel|ladbar|oppladbar)\b$sent*)/iu", $text, $m, PREG_OFFSET_CAPTURE)
            && preg_match('/usb|magnet|solcelle|kabel|ladestasjon|ladeboks/iu', $m[1][0])) {
            $add('lading', trim(mb_substr(trim((string) preg_replace('/^\S*?(?=[A-ZÆØÅ][a-zæøå])/u', '', trim($m[1][0]))), 0, 110)), null, 'sortre', $text, $m[0][1], strlen($m[0][0]), 'Lading', null);
        }
    }

    public static function plain(string $html): string
    {
        // Sør-Tre's HTML often lacks line breaks between fields. Add spaces at tags.
        $t = preg_replace('/<[^>]+>/', ' ', $html) ?? '';
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $t) ?? '');
    }

    private static function snippet(string $hay, int $bytePos, int $byteLen): string
    {
        $start = max(0, $bytePos - 30);
        $s = substr($hay, $start, $byteLen + 60);
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8'); // cut off half characters
        return ($start > 0 ? '…' : '') . trim($s) . (strlen($hay) > $start + $byteLen + 60 ? '…' : '');
    }
}
