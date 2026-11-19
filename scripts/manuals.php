<?php
// Demo assembly guides (feature 011). The texts are written as examples and are marked as demo.
// Run from import.php, or from manual_pdfs.php to print only the PDFs. The PDFs are printed with headless Chrome
// (scripts/manual_pdf.php); without a browser the PDFs already in public/media/manuals are kept.

declare(strict_types=1);

$svg = fn(string $inner) => '<svg viewBox="0 0 240 140" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
$arrow = fn(int $x1, int $y1, int $x2, int $y2) => "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2'/><polyline points='" . ($x2 - 8) . ',' . ($y2 - 6) . " $x2,$y2 " . ($x2 - 8) . ',' . ($y2 + 6) . "'/>";

$manuals = [
    [
        'slug' => 'balkongglass-vindfix', 'title' => 'Montere balkongglass med VindFix stendere', 'theme' => 'balkong',
        'intro' => 'Slik setter du opp et glassfelt med stendere og dekklist. Regn med cirka en time per felt for to personer.',
        'tools' => 'Vater, tommestokk, blyant, drill med 6 mm bor, skrutrekker, gummiklubbe',
        'match' => 'cat:7678,7680,7679',
        'en' => ['title' => 'Installing balcony glass with VindFix posts', 'intro' => 'How to put up one glass panel with posts and a top cover. Allow about an hour per panel for two people.',
            'tools' => 'Spirit level, folding rule, pencil, drill with 6 mm bit, screwdriver, rubber mallet', 'steps' => [
            ['Measure and mark', 'Measure the width of the glass and add 2 cm. Mark the centre of each post along the railing. Check that the post height suits the glass height, for example an 80.1 cm post for 80 cm glass.'],
            ['Fix the posts', 'Pre-drill with a 6 mm bit. Screw the posts in place and check with a spirit level that they are plumb both ways before tightening fully.'],
            ['Fit the glass', 'Lay the rubber strip in the groove. Lift the glass in from above, two people. Tap gently with a rubber mallet until the glass sits evenly.'],
            ['Fit the top cover', 'Click the top cover onto the glass and the posts. It protects the edge and gives a neat finish.'],
        ]],
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
        'en' => ['title' => 'Sharpening the chainsaw chain', 'intro' => 'A sharp chain cuts faster and more safely. Use the right round file: 4.0 mm for 3/8″ LP and 4.8 mm for .325″.',
            'tools' => 'Round file in the right size, file holder, flat file and depth gauge, gloves', 'steps' => [
            ['Lock the bar', 'Clamp the saw in a vice or on a stump. Tension the chain slightly so it does not tip. Wear gloves.'],
            ['File each tooth', 'Hold the file at 30 degrees, as marked on the file holder. File from the inside out, 3 to 5 strokes per tooth. File every other tooth, turn the saw and do the rest.'],
            ['Adjust the depth gauges', 'Lay the depth gauge over the chain and file down what sticks up with the flat file. Round off the front edge.'],
            ['Check and lubricate', 'All teeth should be the same length. Check the chain tension and fill chain oil before you start.'],
        ]],
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
        'en' => ['title' => 'Connecting a shackle and chain safely', 'intro' => 'Choose a shackle of the same size as the chain or one size larger, and check that the working load is enough for the job.',
            'tools' => 'Pliers or spanner, optionally steel wire or cable ties to secure the pin', 'steps' => [
            ['Choose the right size', 'An 8 mm chain fits an 8 to 10 mm shackle. Check the working load limit (WLL) on both. The lower one applies.'],
            ['Insert the pin', 'Place the last chain link in the shackle. Screw the pin all the way in until it bottoms out, then back a quarter turn.'],
            ['Secure the pin', 'For mooring and fixed installations: secure the pin with a cable tie or wire through the hole so it cannot unscrew.'],
            ['Load it straight', 'The shackle must be loaded along its length. Side loading greatly reduces its strength.'],
        ]],
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
        'en' => ['title' => 'Painting an indoor ceiling without streaks', 'intro' => 'With the right preparation and rolling technique the ceiling comes out even. Paint in one session without breaks so the edges do not dry.',
            'tools' => 'Roller with pole, roller tray, brush, masking tape, filler, sandpaper grit 120', 'steps' => [
            ['Fill and sand', 'Fill holes and cracks with filler. Let it dry, sand with grit 120 and wipe off the dust.'],
            ['Mask the edges', 'Tape along walls, mouldings and lamps. Press the tape down firmly so the paint does not creep under.'],
            ['Cut in with a brush', 'Paint a 5 cm strip along the edges with a brush before you roll.'],
            ['Roll wet on wet', 'Roll in sections of about one square metre, first crosswise, then smooth out in one direction, towards the light from the window.'],
        ]],
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
        'en' => ['title' => 'Charging and replacing the battery in a Ledlenser Core headlamp', 'intro' => 'The Core models use a magnetic charging cable for H/HF and P Core. The MH cable does not fit.',
            'tools' => 'Magnetic charging cable for the Core series, USB charger', 'steps' => [
            ['Choose the right cable', 'Use «Ladekabel USB/magnetisk lading H/HF og P Core». The cable for the MH series does not fit Core.'],
            ['Connect', 'Hold the magnet against the charging point on the back of the battery box. The lamp shows red while charging and green when full.'],
            ['Replace the battery', 'Open the battery cover, take out the 21700 cell and insert a new one with plus towards the mark. Use Ledlenser Batteri 21700 for H7R Core.'],
        ]],
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

require_once __DIR__ . '/manual_pdf.php';
$pdfDir = APP_ROOT . '/public/media/manuals';
@mkdir($pdfDir, 0775, true);
$tmp = APP_ROOT . '/data/manuals-tmp';
@mkdir($tmp, 0775, true);
// One PDF per language: {slug}.pdf in Norwegian and {slug}-en.pdf in English. The page links the one matching the language toggle.
$labels = [
    'no' => ['lang' => 'nb', 'guide' => 'Veiledning', 'steps' => 'Steg', 'kit' => 'Utstyr', 'need' => 'Du trenger', 'count' => '{n} steg', 'width' => 'bredde',
        'demo' => 'Demoveiledning, ikke fra produsenten. Følg alltid produsentens anvisning.',
        'back' => 'Sørpedia er en prototype og er ikke tilknyttet Sør-Tre AS. Veiledningen er laget for demoen.'],
    'en' => ['lang' => 'en', 'guide' => 'Guide', 'steps' => 'Steps', 'kit' => 'Equipment', 'need' => 'You need', 'count' => '{n} steps', 'width' => 'width',
        'demo' => 'Demo guide, not from the manufacturer. Always follow the manufacturer\'s instructions.',
        'back' => 'Sørpedia is a prototype and is not affiliated with Sør-Tre AS. This guide was made for the demo.'],
];
foreach ($manuals as $m) {
    $en = $m['en'] + ['theme' => $m['theme']];
    $en['steps'] = array_map(fn($t, $s) => [$t[0], $t[1], str_replace('bredde', $labels['en']['width'], $s[2])], $en['steps'], $m['steps']);
    manual_pdf($m, $labels['no'], $tmp, "$pdfDir/{$m['slug']}.pdf");
    manual_pdf($en, $labels['en'], $tmp, "$pdfDir/{$m['slug']}-en.pdf");
}
if (defined('MANUALS_PDF_ONLY')) {
    return count($manuals);
}

$pdo = db();
$insM = $pdo->prepare('INSERT INTO manuals (slug, title, theme, intro, tools, pdf_path, match_rule) VALUES (?,?,?,?,?,?,?)');
$insS = $pdo->prepare('INSERT INTO manual_steps (manual_id, step_no, title, body, svg) VALUES (?,?,?,?,?)');
foreach ($manuals as $m) {
    $pdf = is_file("$pdfDir/{$m['slug']}.pdf") ? "media/manuals/{$m['slug']}.pdf" : null;
    $insM->execute([$m['slug'], $m['title'], $m['theme'], $m['intro'], $m['tools'], $pdf, $m['match']]);
    $id = (int) $pdo->lastInsertId();
    foreach ($m['steps'] as $i => [$t, $b, $s]) {
        $insS->execute([$id, $i + 1, $t, $b, $s]);
    }
}
return count($manuals);
