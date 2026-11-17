<?php
// Demo-monteringsveiledninger (feature 011). Tekstene er skrevet av gruppen som eksempler og er merket som demo.
// Kjøres fra import.php. Lager også PDF-er med LibreOffice hvis det finnes.

declare(strict_types=1);

$svg = fn(string $inner) => '<svg viewBox="0 0 240 140" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
$arrow = fn(int $x1, int $y1, int $x2, int $y2) => "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2'/><polyline points='" . ($x2 - 8) . ',' . ($y2 - 6) . " $x2,$y2 " . ($x2 - 8) . ',' . ($y2 + 6) . "'/>";

$manuals = [
    [
        'slug' => 'balkongglass-vindfix', 'title' => 'Montere balkongglass med VindFix stendere', 'theme' => 'balkong',
        'intro' => 'Slik setter du opp et glassfelt med stendere og dekklist. Regn med cirka en time per felt for to personer.',
        'tools' => 'Vater, tommestokk, blyant, drill med 6 mm bor, skrutrekker, gummiklubbe',
        'match' => 'cat:7678,7680,7679',
        'steps' => [
            ['Mål opp og merk', 'Mål bredden på glasset og legg til 2 cm. Merk senter for hver stender langs rekkverket. Sjekk at stenderhøyden passer glasshøyden, for eksempel 80,1 cm stender til 80 cm glass.',
                $svg("<rect x='20' y='90' width='200' height='12'/><line x1='60' y1='70' x2='60' y2='110'/><line x1='180' y1='70' x2='180' y2='110'/><line x1='60' y1='60' x2='180' y2='60' stroke-dasharray='5 5'/><text x='98' y='52' fill='currentColor' stroke='none' font-size='14'>bredde + 2 cm</text>")],
            ['Skru fast stenderne', 'Forbor med 6 mm bor. Skru stenderne fast og kontroller med vater at de står i lodd begge veier før du strammer helt.',
                $svg("<rect x='20' y='110' width='200' height='12'/><rect x='70' y='20' width='14' height='90'/><rect x='160' y='20' width='14' height='90'/><rect x='110' y='40' width='30' height='10'/><circle x='0' cx='125' cy='45' r='2'/>")],
            ['Sett inn glasset', 'Legg gummilisten i sporet. Løft glasset inn ovenfra, to personer. Banke forsiktig med gummiklubbe til glasset sitter jevnt.',
                $svg("<rect x='20' y='110' width='200' height='12'/><rect x='70' y='20' width='14' height='90'/><rect x='160' y='20' width='14' height='90'/><rect x='88' y='28' width='68' height='80' stroke-dasharray='4 4'/>" . $arrow(122, 4, 122, 24))],
            ['Monter dekklisten', 'Klikk dekklisten på toppen av glasset og stenderne. Den beskytter kanten og gir et ryddig avslutt.',
                $svg("<rect x='20' y='110' width='200' height='12'/><rect x='70' y='30' width='14' height='80'/><rect x='160' y='30' width='14' height='80'/><rect x='88' y='34' width='68' height='74'/><rect x='64' y='20' width='116' height='10' stroke-width='4'/>")],
        ],
    ],
    [
        'slug' => 'file-sagkjede', 'title' => 'File sagkjedet på motorsagen', 'theme' => 'skog',
        'intro' => 'Et skarpt kjede kutter raskere og tryggere. Bruk riktig rundfil: 4,0 mm til 3/8″ LP og 4,8 mm til .325″.',
        'tools' => 'Rundfil i riktig størrelse, filholder, flatfil og dybdemåler, hansker',
        'match' => 're:sagkjede|rundfil|filsett',
        'steps' => [
            ['Lås sverdet', 'Sett sagen fast i en skrustikke eller på en stubbe. Stram kjedet litt så det ikke vipper. Bruk hansker.',
                $svg("<rect x='30' y='60' width='180' height='26' rx='13'/><rect x='20' y='86' width='40' height='40'/><line x1='40' y1='86' x2='40' y2='126'/>")],
            ['File hver tann', 'Hold filen i vinkel på 30 grader, merket på filholderen. Fil innenfra og ut, 3 til 5 strøk per tann. File annenhver tann, snu sagen og ta resten.',
                $svg("<path d='M30 90 L60 70 L80 90 L110 70 L130 90 L160 70 L180 90'/><line x1='60' y1='40' x2='120' y2='76'/><text x='126' y='40' fill='currentColor' stroke='none' font-size='14'>30°</text>")],
            ['Juster dybdemålerne', 'Legg dybdemåleren over kjedet og fil ned det som stikker opp med flatfilen. Rund av forkanten.',
                $svg("<path d='M30 90 L60 70 L80 90 L110 70 L130 90 L160 70 L180 90'/><rect x='50' y='58' width='140' height='8'/>")],
            ['Sjekk og smør', 'Alle tenner skal være like lange. Sjekk kjedestramming og fyll kjedeolje før du starter.',
                $svg("<rect x='30' y='60' width='180' height='26' rx='13'/><path d='M200 30 q10 15 0 25 q-10 -10 0 -25'/>")],
        ],
    ],
    [
        'slug' => 'sjakkel-og-kjetting', 'title' => 'Koble sjakkel og kjetting trygt', 'theme' => 'kjetting',
        'intro' => 'Velg sjakkel med samme eller én dimensjon større enn kjettingen, og sjekk at arbeidslasten holder for jobben.',
        'tools' => 'Tang eller fastnøkkel, eventuelt stålvaier eller strips for å sikre bolten',
        'match' => 'cat:7504,7499,7220',
        'steps' => [
            ['Velg riktig dimensjon', 'Kjetting på 8 mm passer sjakkel på 8 til 10 mm. Sjekk arbeidslasten (WLL) på begge. Den laveste gjelder.',
                $svg("<ellipse cx='70' cy='70' rx='26' ry='16'/><ellipse cx='110' cy='70' rx='26' ry='16'/><path d='M150 45 q40 0 40 25 q0 25 -40 25'/><line x1='150' y1='45' x2='150' y2='95'/>")],
            ['Før bolten gjennom', 'Legg siste kjettingledd inn i sjakkelen. Skru bolten helt inn til den bunner, så en kvart omdreining tilbake.',
                $svg("<path d='M90 30 q50 0 50 40 q0 40 -50 40'/><line x1='90' y1='20' x2='90' y2='120' stroke-width='5'/><ellipse cx='150' cy='70' rx='26' ry='16'/>")],
            ['Sikre bolten', 'Til fortøyning og fast montering: sikre bolten med strips eller tråd gjennom hullet så den ikke kan skru seg ut.',
                $svg("<path d='M90 30 q50 0 50 40 q0 40 -50 40'/><line x1='90' y1='20' x2='90' y2='120' stroke-width='5'/><path d='M84 22 q-14 -10 0 -16 q14 6 6 16' stroke-dasharray='3 3'/>")],
            ['Belast rett', 'Sjakkelen skal belastes i lengderetningen. Sidebelastning reduserer styrken kraftig.',
                $svg("<path d='M100 30 q40 0 40 40 q0 40 -40 40'/><line x1='100' y1='20' x2='100' y2='120'/>" . $arrow(150, 70, 220, 70))],
        ],
    ],
    [
        'slug' => 'male-tak', 'title' => 'Male tak inne uten striper', 'theme' => 'maling',
        'intro' => 'Med riktig forarbeid og rulleteknikk blir taket jevnt. Mal i én økt uten pause så kantene ikke tørker.',
        'tools' => 'Rulle med skaft, rullebrett, pensel, malertape, sparkel, sandpapir korn 120',
        'match' => 'cat:7364,7179,7193,7206,7253',
        'steps' => [
            ['Sparkle og slip', 'Fyll hull og sprekker med sparkel. La tørke, slip med korn 120 og tørk av støvet.',
                $svg("<rect x='20' y='20' width='200' height='30'/><circle cx='80' cy='35' r='4'/><path d='M120 70 l40 -20 l10 10 l-40 20 z'/>")],
            ['Masker kantene', 'Tape langs vegger, lister og lamper. Trykk tapen godt ned så malingen ikke kryper under.',
                $svg("<line x1='20' y1='30' x2='220' y2='30'/><line x1='20' y1='30' x2='20' y2='130'/><rect x='24' y='34' width='190' height='8' stroke-dasharray='6 3'/>")],
            ['Skjær inn med pensel', 'Mal en stripe på 5 cm langs kantene med pensel før du ruller.',
                $svg("<line x1='20' y1='30' x2='220' y2='30'/><rect x='24' y='34' width='190' height='10' fill='currentColor' opacity='.25'/><path d='M120 60 l0 40 M110 100 h20 v20 h-20 z'/>")],
            ['Rull vått i vått', 'Rull i felt på cirka en kvadratmeter, først på kryss, så glatt ut i samme retning, mot lyset fra vinduet.',
                $svg("<rect x='20' y='20' width='200' height='60'/><path d='M40 40 L80 70 M80 40 L40 70' /><rect x='120' y='90' width='60' height='16' rx='8'/><line x1='150' y1='106' x2='150' y2='134'/>")],
        ],
    ],
    [
        'slug' => 'hodelykt-lading', 'title' => 'Lade og bytte batteri i Ledlenser Core-hodelykt', 'theme' => 'lys',
        'intro' => 'Core-modellene bruker magnetisk ladekabel for H/HF og P Core. MH-kabelen passer ikke.',
        'tools' => 'Magnetisk ladekabel for Core-serien, USB-lader',
        'match' => 're:Ledlenser.*Core|Ledlenser Batteri|Ladekabel',
        'steps' => [
            ['Velg riktig kabel', 'Bruk «Ladekabel USB/magnetisk lading H/HF og P Core». Kabelen for MH-serien passer ikke Core.',
                $svg("<rect x='30' y='50' width='70' height='40' rx='10'/><circle cx='65' cy='70' r='10'/><path d='M100 70 h40 q20 0 20 20 v30'/><circle cx='140' cy='70' r='5'/>")],
            ['Koble til', 'Hold magneten mot ladepunktet bak på batteriboksen. Lampen lyser rødt under lading og grønt når den er full.',
                $svg("<rect x='60' y='40' width='120' height='60' rx='12'/><circle cx='160' cy='70' r='8'/>" . $arrow(220, 70, 172, 70))],
            ['Bytt batteri', 'Åpne batterilokket, ta ut 21700-cellen og sett inn ny med pluss mot merket. Bruk Ledlenser Batteri 21700 for H7R Core.',
                $svg("<rect x='60' y='40' width='120' height='60' rx='12'/><rect x='80' y='58' width='80' height='24' rx='6'/><text x='112' y='75' fill='currentColor' stroke='none' font-size='14'>+</text>")],
        ],
    ],
];

$pdo = db();
$insM = $pdo->prepare('INSERT INTO manuals (slug, title, theme, intro, tools, pdf_path, match_rule) VALUES (?,?,?,?,?,?,?)');
$insS = $pdo->prepare('INSERT INTO manual_steps (manual_id, step_no, title, body, svg) VALUES (?,?,?,?,?)');
$pdfDir = APP_ROOT . '/public/media/manuals';
@mkdir($pdfDir, 0775, true);
$soffice = trim((string) shell_exec('command -v soffice 2>/dev/null'));
$tmp = APP_ROOT . '/data/manuals-tmp';
@mkdir($tmp, 0775, true);
foreach ($manuals as $m) {
    $pdf = null;
    if ($soffice) {
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:sans-serif;margin:30px}svg{width:300px;color:#1f6b3a}h2{margin-top:28px}.demo{color:#a23a2a}</style></head><body>'
            . '<h1>' . h($m['title']) . '</h1><p class="demo">Demo-veiledning laget av IS-219 gruppe 4. Ikke fra produsenten.</p><p>' . h($m['intro']) . '</p><p><b>Du trenger:</b> ' . h($m['tools']) . '</p>';
        foreach ($m['steps'] as $i => [$t, $b, $s]) {
            // LibreOffice dropper innebygd SVG, så illustrasjonen legges som bildefil
            $file = "{$m['slug']}-" . ($i + 1) . '.svg';
            file_put_contents("$tmp/$file", str_replace(['currentColor', '<svg '], ['#1f6b3a', '<svg width="360" height="210" '], $s));
            $html .= '<h2>' . ($i + 1) . '. ' . h($t) . '</h2><p>' . h($b) . '</p><img src="' . $file . '" width="360" height="210">';
        }
        file_put_contents("$tmp/{$m['slug']}.html", $html . '</body></html>');
        shell_exec('cd ' . escapeshellarg($tmp) . ' && timeout 60 ' . escapeshellarg($soffice) . ' --headless --convert-to pdf:writer_web_pdf_Export ' . escapeshellarg("{$m['slug']}.html") . ' --outdir ' . escapeshellarg($pdfDir) . ' >/dev/null 2>&1');
        if (is_file("$pdfDir/{$m['slug']}.pdf")) {
            $pdf = "media/manuals/{$m['slug']}.pdf";
        }
    }
    if (!$pdf && is_file("$pdfDir/{$m['slug']}.pdf")) {
        $pdf = "media/manuals/{$m['slug']}.pdf"; // ferdig generert, f.eks. i Docker uten LibreOffice
    }
    $insM->execute([$m['slug'], $m['title'], $m['theme'], $m['intro'], $m['tools'], $pdf, $m['match']]);
    $id = (int) $pdo->lastInsertId();
    foreach ($m['steps'] as $i => [$t, $b, $s]) {
        $insS->execute([$id, $i + 1, $t, $b, $s]);
    }
}
return count($manuals);
