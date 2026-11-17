<?php
declare(strict_types=1);

// Frontkontroller. Utviklingsserver: PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:8080 -t public public/index.php

if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false; // statiske filer
}

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/helpers.php';
Errors::install();     // ingen PHP-meldinger til kunden, alt logges med referanse

session_name('sorpedia');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']), 'use_strict_mode' => true]);
Privacy::ensure();
Guide::ensure();
Auth::ensure();
Errors::cleanInput();  // «?q[]=» og lignende gir 400, ikke krasj

// Sikkerhetshoder: ingen MIME-gjetting, ingen innramming på andre nettsteder, ingen adresser i Referer til andre
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';

if ($method === 'POST') {
    check_csrf();
}

function route(string $method, string $pattern): ?array
{
    global $path;
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        return null;
    }
    return preg_match('#^' . $pattern . '$#u', $path, $m) ? array_slice($m, 1) : null;
}

// ---------- Forside ----------
if (route('GET', '/') !== null) {
    render('home', ['popular' => Catalog::cards(Catalog::popular(8))], 'Sørpedia');
    exit;
}

// ---------- Varehus ----------
if (route('POST', '/varehus') !== null) {
    $id = (int) ($_POST['warehouse'] ?? 1);
    if (isset(warehouses()[$id])) {
        $_SESSION['warehouse'] = $id;
    }
    redirect(safe_back($_POST['back'] ?? '/'));
}

// ---------- Tema (kategori) med filtre ----------
if ($m = route('GET', '/tema/([a-z]+)')) {
    $theme = $m[0];
    if (!isset(Catalog::THEMES[$theme])) {
        http_response_code(404);
        render('notfound', [], 'Fant ikke siden');
        exit;
    }
    $f = ['pris_min' => $_GET['pris_min'] ?? '', 'pris_max' => $_GET['pris_max'] ?? '', 'v' => $_GET['v'] ?? [],
        'min' => $_GET['min'] ?? [], 'max' => $_GET['max'] ?? [], 'lager' => $_GET['lager'] ?? '', 'veiledning' => $_GET['veiledning'] ?? ''];
    $sort = $_GET['sort'] ?? 'populaer';
    $ids = Catalog::filter($theme, $f, $sort);
    render('theme', [
        'theme' => $theme, 'f' => $f, 'sort' => $sort,
        'facets' => Catalog::facets($theme, Catalog::themeIds($theme)),
        'products' => Catalog::cards($ids),
    ], Catalog::THEMES[$theme][0]);
    exit;
}

// ---------- Produkt ----------
if ($m = route('GET', '/produkt/([a-z0-9-]+)')) {
    $p = Catalog::product($m[0]);
    if (!$p) {
        http_response_code(404);
        render('notfound', [], 'Fant ikke produktet');
        exit;
    }
    $_SESSION['seen'] = array_slice(array_unique(array_merge([(int) $p['id']], $_SESSION['seen'] ?? [])), 0, 12);
    $u = current_user();
    $bought = false;
    if ($u) {
        $st = db()->prepare("SELECT 1 FROM orders o JOIN order_items i ON i.order_id = o.id WHERE o.user_id = ? AND i.product_id = ? AND o.status='betalt' LIMIT 1");
        $st->execute([$u['id'], $p['id']]);
        $bought = (bool) $st->fetchColumn();
    }
    $revs = db()->prepare('SELECT r.*, ' . Privacy::sql() . ' name FROM spec_revisions r LEFT JOIN users u ON u.id = r.user_id WHERE product_id = ? ORDER BY r.id DESC LIMIT 10');
    $revs->execute([$p['id']]);
    $keys = db()->query('SELECT `key`, label, unit FROM spec_keys ORDER BY sort, label')->fetchAll(PDO::FETCH_UNIQUE);
    render('product', [
        'p' => $p, 'images' => Catalog::images((int) $p['id']), 'specs' => Catalog::specs((int) $p['id']),
        'stock' => Catalog::stock((int) $p['id']), 'rel' => Catalog::relations((int) $p['id']),
        'reviews' => Catalog::reviews((int) $p['id']), 'verified' => is_verified($u), 'bought' => $bought,
        'revisions' => $revs->fetchAll(), 'allKeys' => $keys, 'manuals' => Catalog::manualsFor($p),
    ], $p['name']);
    exit;
}

// Sørpedia: verifisert kjøper redigerer den åpne versjonen (feature 009)
if ($m = route('POST', '/produkt/([a-z0-9-]+)/rediger')) {
    $p = Catalog::product($m[0]) ?? exit('Fant ikke produktet');
    $u = current_user();
    if (!is_verified($u)) {
        flash('Bare verifiserte kjøpere (bekreftet e-post og minst ett kjøp) kan redigere.');
        redirect("/produkt/{$p['slug']}#spesifikasjoner");
    }
    $pdo = db();
    $specs = Catalog::specs((int) $p['id']);
    $diff = [];
    $incoming = array_map('trim', (array) ($_POST['spec'] ?? []));
    // Ny spesifikasjonstype: en ny RAD i spec_keys, ingen migrering (ADR-003)
    $newLabel = trim((string) ($_POST['new_label'] ?? ''));
    $newValue = trim((string) ($_POST['new_value'] ?? ''));
    // Vern mot prompt injection: en verdi skal være en verdi, ikke en ordre til Søren (src/Guard.php)
    $types = db()->query('SELECT `key`, datatype FROM spec_keys')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (array_merge($incoming, ['ny spesifikasjonstype' => $newLabel, 'ny verdi' => $newValue, 'enhet' => (string) ($_POST['new_unit'] ?? ''),
        'kommentar' => (string) ($_POST['comment'] ?? '')]) as $what => $val) {
        $why = Moderation::reason((string) $val)
            ?? ($what === 'kommentar' ? Guard::injectionInProse((string) $val) : Guard::suspicious((string) $val))
            // Tallfelt (vekt, lengde, lumen) tar bare tall
            ?? ((($types[$what] ?? '') === 'number' && trim((string) $val) !== '' && !Guard::isNumberish((string) $val)) ? 'må være et tall, for eksempel «1,5» eller «220»' : null);
        if ($why) {
            track('injection_blocked', ['product' => (int) $p['id'], 'field' => (string) $what]);
            flash("Endringen ble ikke lagret: «" . Moderation::censor(mb_substr((string) $val, 0, 60)) . "» $why. Skriv bare selve verdien, for eksempel «220» eller «Varmgalvanisert stål».");
            redirect("/produkt/{$p['slug']}?versjon=apen#spesifikasjoner");
        }
    }
    if ($newLabel !== '' && $newValue !== '') {
        $key = substr(trim((string) preg_replace('/[^a-z0-9]+/', '_', strtr(mb_strtolower($newLabel), ['æ' => 'ae', 'ø' => 'o', 'å' => 'a'])), '_'), 0, 40) ?: 'egenskap';
        $pdo->prepare('INSERT IGNORE INTO spec_keys (`key`, label, unit, datatype, is_facet, themes, sort, created_by) VALUES (?,?,?,?,0,?,200,?)')
            ->execute([$key, mb_substr($newLabel, 0, 80), trim((string) ($_POST['new_unit'] ?? '')) ?: null,
                is_numeric(str_replace(',', '.', $newValue)) ? 'number' : 'text', $p['theme'], $u['id']]);
        $incoming[$key] = $newValue;
    }
    foreach ($incoming as $key => $val) {
        $cur = isset($specs[$key]) ? Catalog::effective($specs[$key])['value_text'] ?? '' : '';
        $open = $specs[$key]['apen']['value_text'] ?? $cur;
        if ($val !== '' && $val !== $open && mb_strlen($val) <= 120) {
            $diff[] = ['key' => $key, 'from' => $open ?: null, 'to' => $val];
        }
    }
    if (!$diff) {
        flash('Ingen endringer å lagre.');
        redirect("/produkt/{$p['slug']}#spesifikasjoner");
    }
    $comment = mb_substr(trim((string) ($_POST['comment'] ?? '')), 0, 500) ?: null;
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO spec_revisions (product_id, user_id, diff, comment) VALUES (?,?,?,?)')
        ->execute([$p['id'], $u['id'], json_encode($diff, JSON_UNESCAPED_UNICODE), $comment]);
    $rid = (int) $pdo->lastInsertId();
    $up = $pdo->prepare('INSERT INTO spec_values (product_id, `key`, version, value_text, value_num, source, evidence, revision_id)
        VALUES (?,?,"apen",?,?,"bruker",?,?) ON DUPLICATE KEY UPDATE value_text=VALUES(value_text), value_num=VALUES(value_num),
        source="bruker", evidence=VALUES(evidence), revision_id=VALUES(revision_id)');
    foreach ($diff as $d) {
        $num = is_numeric(str_replace([',', ' '], ['.', ''], $d['to'])) ? (float) str_replace([',', ' '], ['.', ''], $d['to']) : null;
        $up->execute([$p['id'], $d['key'], $d['to'], $num, $comment, $rid]);
    }
    emit("product:{$p['id']}", 'revision', ['product' => (int) $p['id'], 'revision' => $rid, 'by' => Privacy::alias($u), 'diff' => $diff]);
    emit('staff', 'revision', ['product' => (int) $p['id'], 'name' => $p['name'], 'revision' => $rid, 'by' => Privacy::alias($u)]);
    $pdo->commit();
    flash('Takk! Endringen er synlig i den åpne versjonen. En ansatt vurderer om den skal inn i den verifiserte.');
    redirect("/produkt/{$p['slug']}#spesifikasjoner");
}

// Anmeldelse (feature 010): bare verifiserte kjøpere av akkurat dette produktet
if ($m = route('POST', '/produkt/([a-z0-9-]+)/anmeldelse')) {
    $p = Catalog::product($m[0]) ?? exit('Fant ikke produktet');
    $u = current_user();
    $st = db()->prepare("SELECT 1 FROM orders o JOIN order_items i ON i.order_id = o.id WHERE o.user_id = ? AND i.product_id = ? AND o.status='betalt'");
    $st->execute([$u['id'] ?? 0, $p['id']]);
    if (!is_verified($u) || !$st->fetchColumn()) {
        flash('Bare verifiserte kjøpere av dette produktet kan skrive anmeldelse.');
    } elseif ($why = Moderation::reason((string) ($_POST['title'] ?? '') . '. ' . (string) ($_POST['body'] ?? ''))
        ?? Guard::injectionInProse((string) ($_POST['title'] ?? '') . '. ' . (string) ($_POST['body'] ?? ''))) {
        // Vern mot prompt injection: Søren leser anmeldelser (src/Guard.php)
        track('injection_blocked', ['product' => (int) $p['id'], 'field' => 'anmeldelse']);
        flash("Anmeldelsen ble ikke lagret fordi den $why. Skriv om opplevelsen din med produktet, uten lenker og banning.");
    } else {
        $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
        db()->prepare('INSERT INTO reviews (product_id, user_id, rating, title, body) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE rating=VALUES(rating), title=VALUES(title), body=VALUES(body)')
            ->execute([$p['id'], $u['id'], $rating, mb_substr(trim((string) $_POST['title']), 0, 120), mb_substr(trim((string) $_POST['body']), 0, 2000)]);
        flash('Takk for anmeldelsen!');
    }
    redirect("/produkt/{$p['slug']}#anmeldelser");
}

// Meld fra om feil i den åpne versjonen. Alle kan melde fra, også uten å logge inn. Én melding per verdi per økt.
if ($m = route('POST', '/produkt/([a-z0-9-]+)/meld-feil')) {
    $p = Catalog::product($m[0]) ?? exit('Fant ikke produktet');
    $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'json');
    $key = (string) ($_POST['key'] ?? '');
    $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 300);
    $specs = Catalog::specs((int) $p['id']);
    $v = $specs[$key]['apen'] ?? $specs[$key]['verifisert'] ?? null;
    $answer = function (bool $ok, string $msg) use ($wantsJson, $p): never {
        if ($wantsJson) {
            json_out(['ok' => $ok, 'message' => $msg], $ok ? 200 : 422);
        }
        flash($msg);
        redirect("/produkt/{$p['slug']}?versjon=apen#spesifikasjoner");
    };
    if (!$v) {
        $answer(false, 'Fant ikke verdien du meldte fra om.');
    }
    if ($reason === '' || ($why = Moderation::reason($reason) ?? Guard::injectionInProse($reason))) {
        $answer(false, $reason === '' ? 'Skriv kort hva som er feil.' : "Meldingen ble ikke sendt fordi den $why.");
    }
    $st = db()->prepare('INSERT IGNORE INTO spec_reports (product_id, `key`, value_text, revision_id, reason, session_id, user_id) VALUES (?,?,?,?,?,?,?)');
    $st->execute([$p['id'], $key, $v['value_text'], $v['revision_id'], $reason, sid(), current_user()['id'] ?? null]);
    if (!$st->rowCount()) {
        $answer(true, 'Du har allerede meldt fra om denne verdien. En ansatt ser på det.');
    }
    emit('staff', 'report', ['product' => (int) $p['id'], 'name' => $p['name'], 'label' => $specs[$key]['label']]);
    track('spec_report', ['product' => (int) $p['id'], 'key' => $key]);
    $answer(true, 'Takk! En ansatt ser på verdien. Den er ikke endret ennå.');
}

// ---------- Søk ----------
if (route('GET', '/sok') !== null) {
    $q = trim((string) ($_GET['q'] ?? ''));
    $r = Search::run($q);
    track($r['ids'] ? 'search' : 'search_zero', ['q' => $q, 'n' => count($r['ids'])]);
    $fallback = !$r['ids'] ? Catalog::cards(Catalog::popular(4, $r['themes'][0] ?? null)) : [];
    render('search', ['q' => $q, 'r' => $r, 'products' => Catalog::cards($r['ids']), 'fallback' => $fallback], "Søk: $q");
    exit;
}

// Ordliste for innebygd autofullføring (grå tekst, Tab for å godta). Bygges fra katalogen, ingen persondata.
if (route('GET', '/api/vocab') !== null) {
    $words = $next = [];
    foreach (db()->query('SELECT name FROM products') as $r) {
        $toks = preg_split('/[^\p{L}\d]+/u', mb_strtolower($r['name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($toks as $i => $t) {
            if (mb_strlen($t) < 3 || preg_match('/^\d+$/', $t)) {
                continue;
            }
            $words[$t] = ($words[$t] ?? 0) + 1;
            $n = $toks[$i + 1] ?? null;
            if ($n !== null && mb_strlen($n) >= 2) {
                $next[$t][$n] = ($next[$t][$n] ?? 0) + 1;
            }
        }
    }
    foreach ($next as $w => $ns) {
        arsort($ns);
        $next[$w] = array_map("strval", array_slice(array_keys($ns), 0, 3));
    }
    arsort($words);
    $phrases = db()->query('SELECT DISTINCT term FROM search_synonyms WHERE term LIKE "% %" ORDER BY term')->fetchAll(PDO::FETCH_COLUMN);
    // Populære søk med treff (anonymt, bare søkeord som ga resultat minst to ganger)
    $popular = db()->query("SELECT LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, '$.q'))) q, COUNT(*) n FROM analytics_events
        WHERE type = 'search' GROUP BY q HAVING n >= 2 AND CHAR_LENGTH(q) BETWEEN 3 AND 40 ORDER BY n DESC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
    // Populære søk blir grå forslag for alle, så bare familievennlige søk tas med
    $popular = array_values(array_filter($popular, fn($q) => Moderation::clean((string) $q)));
    header('Cache-Control: public, max-age=600');
    json_out(['words' => array_map(fn($w, $n) => [(string) $w, $n], array_keys($words), $words), 'next' => $next,
        'phrases' => array_values(array_unique(array_merge($popular, $phrases)))]);
}

if (route('GET', '/api/suggest') !== null) {
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) {
        json_out(['products' => [], 'themes' => []]);
    }
    $r = Search::run($q, 6, true);
    $themes = [];
    foreach ($r['themes'] as $t) {
        $themes[] = ['url' => "/tema/$t", 'name' => Catalog::THEMES[$t][0]];
    }
    json_out([
        'corrected' => $r['corrected'],
        'usecases' => array_map(fn($s) => $s['term'], array_filter($r['matched'], fn($s) => $s['kind'] === 'bruksomrade')),
        'themes' => $themes,
        'products' => array_map(fn($c) => ['url' => "/produkt/{$c['slug']}", 'name' => $c['name'], 'price' => kr((int) $c['price_ore']),
            'img' => $c['img'] ? "/{$c['img']}" : null], Catalog::cards($r['ids'])),
    ]);
}

// ---------- Produktguide ----------
if (route('GET', '/guide') !== null) {
    $answers = Guide::answers($_GET);
    if (Guide::complete($answers)) {
        $res = Guide::recommend($answers);
        track('guide_done', $answers);
        // Søren husker svarene resten av økten, så han kan hjelpe på produktsidene etterpå
        $_SESSION['guide'] = ['a' => $answers, 'at' => time()];
        render('guide_result', ['res' => $res, 'answers' => $answers], 'Forslag fra Søren');
    } else {
        render('guide', ['answers' => $answers], 'Produktguide');
    }
    exit;
}

// Kunden sier hvilket produkt som var riktig for svarene sine. Guiden lærer av det.
if (route('POST', '/guide/svar') !== null) {
    check_csrf();
    $a = Guide::answers(is_array($_POST['a'] ?? null) ? $_POST['a'] : []);
    // Fritekst: guiden lærer av beste søketreff i temaet, og kunden ser søkeresultatene for det de skrev
    $wanted = trim(mb_substr((string) ($_POST['wanted'] ?? ''), 0, 120));
    if ($wanted !== '') {
        $theme = array_map('intval', Catalog::themeIds($a['oppgave'] ?? ''));
        $hit = current(array_intersect(Search::run($wanted)['ids'], $theme));
        if ($hit && Guide::learn($a, (int) $hit)) {
            track('guide_feedback', ['product' => (int) $hit, 'wanted' => $wanted] + $a);
        }
        flash('Takk! Guiden lærer av svaret ditt.');
        redirect('/sok?' . http_build_query(['q' => $wanted]));
    }
    $pid = (int) ($_POST['product'] ?? 0);
    if (!Guide::learn($a, $pid)) {
        redirect('/guide?' . http_build_query($a));
    }
    track('guide_feedback', ['product' => $pid] + $a);
    flash('Takk! Guiden lærer av svaret ditt.');
    redirect('/produkt/' . (string) db()->query('SELECT slug FROM products WHERE id = ' . $pid)->fetchColumn());
}

// ---------- Handlekurv ----------
if (route('POST', '/handlekurv/legg-til') !== null) {
    $pid = (int) ($_POST['product'] ?? 0);
    $via = preg_replace('/[^a-z_]/', '', (string) ($_POST['via'] ?? 'produkt'));
    db()->prepare('INSERT INTO cart_items (session_id, product_id, qty, added_via) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)')
        ->execute([sid(), $pid, max(1, (int) ($_POST['qty'] ?? 1)), $via]);
    track('add_to_cart', ['product' => $pid, 'via' => $via]);
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
        json_out(['count' => cart_count()]);
    }
    flash('Lagt i handlekurven.');
    redirect(safe_back($_POST['back'] ?? '/handlekurv', '/handlekurv'));
}

if (route('POST', '/handlekurv/endre') !== null) {
    $pid = (int) ($_POST['product'] ?? 0);
    $qty = (int) ($_POST['qty'] ?? 0);
    if ($qty <= 0) {
        db()->prepare('DELETE FROM cart_items WHERE session_id = ? AND product_id = ?')->execute([sid(), $pid]);
    } else {
        db()->prepare('UPDATE cart_items SET qty = ? WHERE session_id = ? AND product_id = ?')->execute([min($qty, 99), sid(), $pid]);
    }
    redirect('/handlekurv');
}

if (route('GET', '/handlekurv') !== null) {
    $st = db()->prepare('SELECT product_id, qty, added_via FROM cart_items WHERE session_id = ?');
    $st->execute([sid()]);
    $items = $st->fetchAll(PDO::FETCH_UNIQUE);
    $cards = Catalog::cards(array_keys($items));
    // Kaninhull: det som passer sammen med innholdet i kurven, men som ikke alt ligger der
    $suggest = [];
    $warn = [];
    foreach ($cards as $c) {
        $rel = Catalog::relations($c['id'], 6);
        foreach ($rel['passer'] as $r) {
            if (!isset($items[$r['id']]) && !isset($suggest[$r['id']])) {
                $r['for'] = $c['name'];
                $suggest[$r['id']] = $r;
            }
        }
        foreach ($rel['ikke'] as $r) {
            if (isset($items[$r['id']])) {
                $warn[] = [$c, $r];
            }
        }
    }
    render('cart', ['items' => $items, 'cards' => $cards, 'suggest' => array_slice($suggest, 0, 8), 'warn' => $warn], 'Handlekurv');
    exit;
}

// ---------- Kasse (demo) ----------
if (route('GET', '/kasse') !== null) {
    if (!current_user()) {
        flash('Logg inn for å fullføre kjøpet. Testbrukerne har passordet «admin».');
        redirect('/logg-inn?back=/kasse');
    }
    if (!cart_count()) {
        redirect('/handlekurv');
    }
    $st = db()->prepare('SELECT c.qty, p.* FROM cart_items c JOIN products p ON p.id = c.product_id WHERE c.session_id = ?');
    $st->execute([sid()]);
    render('checkout', ['lines' => $st->fetchAll()], 'Kasse');
    exit;
}

if (route('POST', '/kasse') !== null) {
    $u = current_user() ?? redirect('/logg-inn');
    $st = db()->prepare('SELECT c.product_id, c.qty, c.added_via, p.price_ore FROM cart_items c JOIN products p ON p.id = c.product_id WHERE c.session_id = ?');
    $st->execute([sid()]);
    $lines = $st->fetchAll();
    if (!$lines) {
        redirect('/handlekurv');
    }
    $wasVerified = is_verified($u);
    $pdo = db();
    $pdo->beginTransaction();
    $total = array_sum(array_map(fn($l) => $l['qty'] * $l['price_ore'], $lines));
    $pay = in_array($_POST['payment'] ?? '', ['vipps', 'klarna', 'kort'], true) ? $_POST['payment'] : 'vipps';
    $pdo->prepare('INSERT INTO orders (user_id, warehouse_id, total_ore, payment) VALUES (?,?,?,?)')->execute([$u['id'], warehouse_id(), $total, $pay]);
    $oid = (int) $pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, qty, price_ore, added_via) VALUES (?,?,?,?,?)');
    $dec = $pdo->prepare('UPDATE stock SET online = GREATEST(online - ?, 0) WHERE product_id = ? AND warehouse_id = ?');
    foreach ($lines as $l) {
        $ins->execute([$oid, $l['product_id'], $l['qty'], $l['price_ore'], $l['added_via']]);
        $dec->execute([$l['qty'], $l['product_id'], warehouse_id()]);
        emit('stock', 'stock', ['product' => (int) $l['product_id']]);
    }
    $pdo->prepare('DELETE FROM cart_items WHERE session_id = ?')->execute([sid()]);
    $pdo->commit();
    track('purchase', ['order' => $oid, 'total' => $total, 'lines' => count($lines),
        'accessory_lines' => count(array_filter($lines, fn($l) => in_array($l['added_via'], ['passer_sammen', 'kurv_forslag', 'guide'], true)))]);
    if (!$wasVerified && is_verified($u)) {
        flash('Kjøpet er registrert. Du er nå en verifisert kjøper og kan redigere produktinfo og skrive anmeldelser!');
    } elseif (!$u['email_verified_at']) {
        flash('Kjøpet er registrert. Bekreft e-posten din for å bli verifisert kjøper.');
    }
    redirect("/ordre/$oid");
}

if ($m = route('GET', '/ordre/(\d+)')) {
    $u = current_user() ?? redirect('/logg-inn');
    $st = db()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
    $st->execute([(int) $m[0], $u['id']]);
    $o = $st->fetch() ?: redirect('/');
    $st = db()->prepare('SELECT i.qty, i.price_ore, p.name, p.slug FROM order_items i JOIN products p ON p.id = i.product_id WHERE order_id = ?');
    $st->execute([$o['id']]);
    render('order', ['o' => $o, 'lines' => $st->fetchAll(), 'verified' => is_verified($u)], "Ordre {$o['id']}");
    exit;
}

// ---------- Innlogging ----------
if (route('GET', '/logg-inn') !== null) {
    $demo = db()->query("SELECT email, name, role FROM users WHERE is_demo_seed = 1 AND name NOT LIKE '%(demo)%' ORDER BY id")->fetchAll();
    render('login', ['demo' => $demo, 'back' => $_GET['back'] ?? '/'], 'Logg inn');
    exit;
}

if (route('POST', '/logg-inn') !== null) {
    // Samme melding for ukjent e-post, feil passord og låst konto (src/Auth.php)
    $u = Auth::attempt(mb_strtolower(trim((string) ($_POST['email'] ?? ''))), (string) ($_POST['password'] ?? ''));
    if (!$u) {
        flash('Feil e-post eller passord.');
        redirect('/logg-inn');
    }
    session_regenerate_id(true);
    // Behold handlekurven fra før innlogging
    $_SESSION['uid'] = (int) $u['id'];
    redirect(safe_back($_POST['back'] ?? '/'));
}

if (route('POST', '/logg-ut') !== null) {
    unset($_SESSION['uid']);
    session_regenerate_id(true);
    redirect('/');
}

// ---------- Monteringsveiledninger (feature 011) ----------
if ($m = route('GET', '/veiledning/([a-z0-9-]+)')) {
    $st = db()->prepare('SELECT * FROM manuals WHERE slug = ?');
    $st->execute([$m[0]]);
    $man = $st->fetch() ?: null;
    if (!$man) {
        http_response_code(404);
        render('notfound', [], 'Fant ikke veiledningen');
        exit;
    }
    $steps = db()->prepare('SELECT * FROM manual_steps WHERE manual_id = ? ORDER BY step_no');
    $steps->execute([$man['id']]);
    // Produkter veiledningen gjelder, for "kjøp alt du trenger"
    $ids = [];
    $names = db()->prepare('SELECT id, name FROM products WHERE theme = ?');
    $names->execute([$man['theme']]);
    foreach ($names->fetchAll() as $row) {
        if (in_array($man['id'], array_column(Catalog::manualsFor($row), 'id'))) {
            $ids[] = (int) $row['id'];
        }
    }
    track('manual_view', ['manual' => $man['slug']]);
    render('manual', ['man' => $man, 'steps' => $steps->fetchAll(), 'products' => Catalog::cards(array_slice($ids, 0, 8))], $man['title']);
    exit;
}

// ---------- Registrering og e-postbekreftelse (feature 005) ----------
if (route('GET', '/registrer') !== null) {
    render('register', [], 'Registrer deg');
    exit;
}

if (route('POST', '/registrer') !== null) {
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
    $pw = (string) ($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($pw) < 8) {
        flash('Fyll inn navn, gyldig e-post og passord på minst 8 tegn.');
        redirect('/registrer');
    } elseif ($why = Moderation::reason($name) ?? Moderation::reason(explode('@', $email)[0])) {
        // Fornavnet kan vises ved bidrag hvis brukeren velger det, så det må passe for alle aldre
        flash("Navnet eller e-postadressen $why. Velg noe annet.");
        redirect('/registrer');
    }
    $token = bin2hex(random_bytes(16));
    try {
        db()->prepare('INSERT INTO users (email, name, alias_salt, password_hash, verify_token, warehouse_id) VALUES (?,?,?,?,?,?)')
            ->execute([$email, $name, Privacy::newSalt(), password_hash($pw, PASSWORD_ARGON2ID), $token, warehouse_id()]);
    } catch (PDOException) {
        flash('Det finnes allerede en bruker med den e-posten.');
        redirect('/registrer');
    }
    $_SESSION['uid'] = (int) db()->lastInsertId();
    $link = "/bekreft/$token";
    db()->prepare('INSERT INTO mail_outbox (to_email, subject, body_html) VALUES (?,?,?)')->execute([$email, 'Bekreft e-posten din hos Sørpedia',
        '<p>Hei ' . h($name) . '!</p><p>Trykk på lenken for å bekrefte e-posten din:</p><p><a href="' . $link . '">Bekreft e-post</a></p>'
        . '<p>Når e-posten er bekreftet og du har handlet hos oss, blir du verifisert kjøper. Da kan du fylle inn produktinfo og skrive anmeldelser.</p>']);
    flash('Konto opprettet. Vi har sendt en bekreftelse til innboksen.');
    redirect('/innboks');
}

if (route('GET', '/innboks') !== null) {
    $u = current_user();
    $mails = $u ? db()->prepare('SELECT * FROM mail_outbox WHERE to_email = ? ORDER BY id DESC') : null;
    $mails?->execute([$u['email']]);
    render('inbox', ['mails' => $mails ? $mails->fetchAll() : []], 'Innboks');
    exit;
}

if ($m = route('GET', '/bekreft/([a-f0-9]{32})')) {
    $st = db()->prepare('UPDATE users SET email_verified_at = NOW(), verify_token = NULL WHERE verify_token = ?');
    $st->execute([$m[0]]);
    flash($st->rowCount() ? 'E-posten er bekreftet. Etter første kjøp blir du verifisert kjøper.' : 'Lenken er brukt eller ugyldig.');
    redirect('/');
}

// ---------- Innstillinger og personvern (GDPR) ----------
function settings_user(): array
{
    $u = current_user();
    if (!$u) {
        redirect('/logg-inn?back=/innstillinger');
    }
    return $u;
}

if (route('GET', '/innstillinger') !== null) {
    $u = settings_user();
    render('settings', ['u' => $u, 'verified' => is_verified($u)], 'Innstillinger');
    exit;
}

if (route('POST', '/innstillinger/profil') !== null) {
    $u = settings_user();
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $display = ($_POST['display_name'] ?? '') === 'fornavn' ? 'fornavn' : 'anonym';
    if ($name === '') {
        flash('Navnet kan ikke være tomt.');
    } elseif ($why = Moderation::reason($name)) {
        flash("Navnet $why. Velg noe annet.");
    } elseif ($phone !== '' && !preg_match('/^\+?[0-9 ]{8,16}$/', $phone)) {
        flash('Telefonnummeret ser ikke riktig ut. Bruk bare tall, for eksempel 912 34 567.');
    } else {
        db()->prepare('UPDATE users SET name = ?, phone = ?, display_name = ? WHERE id = ?')->execute([$name, $phone ?: null, $display, $u['id']]);
        flash('Profilen er lagret.');
    }
    redirect('/innstillinger#profil');
}

if (route('POST', '/innstillinger/bilde') !== null) {
    $u = settings_user();
    if (($_POST['action'] ?? '') === 'fjern') {
        Privacy::deleteAvatar($u['avatar']);
        db()->prepare('UPDATE users SET avatar = NULL WHERE id = ?')->execute([$u['id']]);
        flash('Profilbildet er slettet.');
        redirect('/innstillinger#profil');
    }
    $f = $_FILES['avatar'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        flash(($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'Bildet må være mindre enn 3 MB.' : 'Velg et bilde først.');
        redirect('/innstillinger#profil');
    }
    try {
        $name = Privacy::saveAvatar($f['tmp_name'], (int) $f['size']);
        Privacy::deleteAvatar($u['avatar']);
        db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$name, $u['id']]);
        flash('Profilbildet er lagret. Posisjon og annen metadata i bildet ble fjernet.');
    } catch (RuntimeException $e) {
        flash($e->getMessage());
    }
    redirect('/innstillinger#profil');
}

if (route('POST', '/innstillinger/utseende') !== null) {
    $u = settings_user();
    $theme = in_array($_POST['theme'] ?? '', ['system', 'lys', 'mork'], true) ? $_POST['theme'] : 'system';
    db()->prepare('UPDATE users SET theme = ? WHERE id = ?')->execute([$theme, $u['id']]);
    flash(t('Utseendet er lagret.'));
    redirect('/innstillinger#utseende');
}

// Språk: flagget i toppstripen. Funksjonell informasjonskapsel, lagrer bare «no» eller «en».
if ($m = route('GET', '/sprak/(no|en)')) {
    setcookie('lang', $m[0], ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax', 'httponly' => false]);
    $back = (string) ($_GET['back'] ?? '/');
    // Aldri tilbake til /sprak selv, ellers kan lenken legge seg inni seg selv for hver gang
    redirect(str_starts_with($back, '/sprak') ? '/' : safe_back($back));
}

// Profilbildet vises bare for brukeren selv (i menyen og i chatten med Søren), aldri for andre
if (route('GET', '/profilbilde') !== null) {
    $u = current_user();
    $file = $u && $u['avatar'] ? Privacy::avatarDir() . '/' . basename($u['avatar']) : null;
    if (!$file || !is_file($file)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . (str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg'));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

if (route('POST', '/innstillinger/epost') !== null) {
    $u = settings_user();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!password_verify((string) ($_POST['password'] ?? ''), $u['password_hash'])) {
        flash('Feil passord. E-posten er ikke endret.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('Skriv en gyldig e-postadresse.');
    } elseif ($email === $u['email']) {
        flash('Det er den samme e-posten som før.');
    } else {
        $token = bin2hex(random_bytes(16));
        try {
            db()->prepare('UPDATE users SET email = ?, email_verified_at = NULL, verify_token = ? WHERE id = ?')->execute([$email, $token, $u['id']]);
        } catch (PDOException) {
            flash('Den e-posten er allerede i bruk.');
            redirect('/innstillinger#epost');
        }
        db()->prepare('INSERT INTO mail_outbox (to_email, subject, body_html) VALUES (?,?,?)')->execute([$email, 'Bekreft den nye e-posten din',
            '<p>Du har endret e-posten din hos Sørpedia. Trykk på lenken for å bekrefte den:</p><p><a href="/bekreft/' . $token . '">Bekreft e-post</a></p>'
            . '<p>Var det ikke deg? Bytt passord under Innstillinger.</p>']);
        flash('E-posten er endret. Bekreft den nye adressen fra innboksen for å være verifisert kjøper igjen.');
        redirect('/innboks');
    }
    redirect('/innstillinger#epost');
}

if (route('POST', '/innstillinger/passord') !== null) {
    $u = settings_user();
    $new = (string) ($_POST['new'] ?? '');
    if (!password_verify((string) ($_POST['current'] ?? ''), $u['password_hash'])) {
        flash('Nåværende passord er feil.');
    } elseif (strlen($new) < 8) {
        flash('Det nye passordet må ha minst 8 tegn.');
    } elseif ($new !== (string) ($_POST['repeat'] ?? '')) {
        flash('Passordene er ikke like.');
    } else {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_ARGON2ID), $u['id']]);
        session_regenerate_id(true);
        flash('Passordet er endret.');
    }
    redirect('/innstillinger#passord');
}

// Innsyn og dataportabilitet (GDPR art. 15 og 20): alt vi har lagret om brukeren, som JSON
if (route('GET', '/innstillinger/eksport') !== null) {
    $u = settings_user();
    $q = function (string $sql) use ($u): array {
        $st = db()->prepare($sql);
        $st->execute([$u['id']]);
        return $st->fetchAll();
    };
    $data = [
        'eksportert' => date('c'),
        'konto' => array_diff_key($u, array_flip(['password_hash', 'verify_token', 'alias_salt', 'is_demo_seed'])) + ['offentlig_navn' => Privacy::alias($u)],
        'ordrer' => $q('SELECT o.id, o.created_at, o.total_ore, o.payment, o.status, w.name varehus FROM orders o LEFT JOIN warehouses w ON w.id = o.warehouse_id WHERE o.user_id = ?'),
        'ordrelinjer' => $q('SELECT i.order_id, p.name produkt, i.qty, i.price_ore FROM order_items i JOIN orders o ON o.id = i.order_id JOIN products p ON p.id = i.product_id WHERE o.user_id = ?'),
        'anmeldelser' => $q('SELECT p.name produkt, r.rating, r.title, r.body, r.created_at FROM reviews r JOIN products p ON p.id = r.product_id WHERE r.user_id = ?'),
        'bidrag_i_apen_versjon' => $q('SELECT p.name produkt, r.diff, r.comment, r.status, r.created_at FROM spec_revisions r JOIN products p ON p.id = r.product_id WHERE r.user_id = ?'),
        'feilmeldinger' => $q('SELECT p.name produkt, r.`key`, r.reason, r.status, r.created_at FROM spec_reports r JOIN products p ON p.id = r.product_id WHERE r.user_id = ?'),
        'kundeservice' => $q('SELECT t.id, t.status, t.summary, t.chat_log, t.created_at FROM support_tickets t WHERE t.user_id = ?'),
        'eposter' => (function () use ($u) { $st = db()->prepare('SELECT subject, created_at FROM mail_outbox WHERE to_email = ?'); $st->execute([$u['email']]); return $st->fetchAll(); })(),
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="sorpedia-mine-data.json"');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Slett konto (GDPR art. 17). Personopplysninger fjernes. Ordrer må beholdes i fem år (bokføringsloven), men knyttes ikke lenger til et navn.
if (route('POST', '/innstillinger/slett') !== null) {
    $u = settings_user();
    if (!password_verify((string) ($_POST['password'] ?? ''), $u['password_hash'])) {
        flash('Feil passord. Kontoen er ikke slettet.');
        redirect('/innstillinger#slett');
    }
    if ($u['is_demo_seed']) {
        flash('Testbrukerne kan ikke slettes, ellers slutter demoen å virke. Registrer en egen bruker for å prøve sletting.');
        redirect('/innstillinger#slett');
    }
    $pdo = db();
    $pdo->beginTransaction();
    $id = (int) $u['id'];
    $pdo->prepare('DELETE FROM reviews WHERE user_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM mail_outbox WHERE to_email = ?')->execute([$u['email']]);
    $pdo->prepare('UPDATE spec_reports SET user_id = NULL WHERE user_id = ?')->execute([$id]);
    $pdo->prepare('UPDATE support_tickets SET user_id = NULL, chat_log = NULL WHERE user_id = ?')->execute([$id]);
    $pdo->prepare('UPDATE support_messages SET user_id = NULL WHERE user_id = ?')->execute([$id]);
    $pdo->prepare("UPDATE users SET email = ?, name = ?, phone = NULL, avatar = NULL, display_name = 'anonym', verify_token = NULL,
        password_hash = ?, email_verified_at = NULL, deleted_at = NOW() WHERE id = ?")
        ->execute(["slettet-$id@sorpedia.invalid", Privacy::DELETED, password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID), $id]);
    $pdo->commit();
    Privacy::deleteAvatar($u['avatar']);
    unset($_SESSION['uid']);
    session_regenerate_id(true);
    flash('Kontoen din er slettet. Bidrag i den åpne versjonen står igjen som «Slettet bruker».');
    redirect('/');
}

// ---------- Ansatt: flettekø for åpen versjon (feature 009) ----------
if (route('GET', '/ansatt') !== null) {
    if (!has_role('ansatt', 'admin')) {
        redirect('/logg-inn?back=/ansatt');
    }
    $revs = db()->query("SELECT r.*, " . Privacy::sql() . " author, p.name product, p.slug FROM spec_revisions r JOIN products p ON p.id = r.product_id
        LEFT JOIN users u ON u.id = r.user_id WHERE r.status = 'aktiv' ORDER BY r.created_at")->fetchAll();
    $keys = db()->query('SELECT `key`, label, unit FROM spec_keys')->fetchAll(PDO::FETCH_UNIQUE);
    $gaps = db()->query("SELECT * FROM (SELECT k.label, COUNT(DISTINCT p.id) - COUNT(DISTINCT v.product_id) missing, COUNT(DISTINCT p.id) total
        FROM spec_keys k JOIN products p ON FIND_IN_SET(p.theme, k.themes)
        LEFT JOIN spec_values v ON v.`key` = k.`key` AND v.product_id = p.id
        WHERE k.is_facet = 1 GROUP BY k.`key`, k.label) g WHERE missing > 0 ORDER BY missing / total DESC LIMIT 12")->fetchAll();
    $reports = db()->query("SELECT r.product_id, r.`key`, MAX(r.id) id, COUNT(*) n, MIN(r.created_at) created_at,
            GROUP_CONCAT(r.reason ORDER BY r.id SEPARATOR '\n') reasons, p.name product, p.slug, k.label, k.unit, v.value_text, v.source
        FROM spec_reports r JOIN products p ON p.id = r.product_id JOIN spec_keys k ON k.`key` = r.`key`
        LEFT JOIN spec_values v ON v.product_id = r.product_id AND v.`key` = r.`key` AND v.version = 'apen'
        WHERE r.status = 'apen' GROUP BY r.product_id, r.`key` ORDER BY n DESC, created_at")->fetchAll();
    render('staff', ['revs' => $revs, 'keys' => $keys, 'gaps' => $gaps, 'reports' => $reports], 'Flettekø');
    exit;
}

if ($m = route('POST', '/ansatt/revisjon/(\d+)')) {
    if (!has_role('ansatt', 'admin')) {
        http_response_code(403);
        exit;
    }
    $rid = (int) $m[0];
    $r = db()->query("SELECT * FROM spec_revisions WHERE id = $rid AND status = 'aktiv'")->fetch();
    if ($r) {
        $pdo = db();
        $pdo->beginTransaction();
        $approve = ($_POST['action'] ?? '') === 'flett';
        if ($approve) {
            // Godkjent verdi flyttes til Verifisert med kilde "ansatt"
            $up = $pdo->prepare('INSERT INTO spec_values (product_id, `key`, version, value_text, value_num, source, evidence, revision_id)
                SELECT product_id, `key`, "verifisert", value_text, value_num, "ansatt", CONCAT("Godkjent av en ansatt. ", COALESCE(evidence, "")), revision_id
                FROM spec_values WHERE product_id = ? AND `key` = ? AND version = "apen" AND revision_id = ?
                ON DUPLICATE KEY UPDATE value_text = VALUES(value_text), value_num = VALUES(value_num), source = "ansatt", evidence = VALUES(evidence), revision_id = VALUES(revision_id)');
            foreach (json_decode($r['diff'], true) as $d) {
                $up->execute([$r['product_id'], $d['key'], $rid]);
            }
        } else {
            // Avvist: fjern verdiene fra åpen versjon hvis de fortsatt er fra denne revisjonen
            $pdo->prepare('DELETE FROM spec_values WHERE product_id = ? AND version = "apen" AND revision_id = ?')->execute([$r['product_id'], $rid]);
        }
        $pdo->prepare('UPDATE spec_revisions SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
            ->execute([$approve ? 'flettet' : 'avvist', current_user()['id'], $rid]);
        emit("product:{$r['product_id']}", 'revision', ['product' => (int) $r['product_id'], 'status' => $approve ? 'flettet' : 'avvist']);
        $pdo->commit();
        flash($approve ? 'Flettet inn i verifisert versjon.' : 'Avvist og fjernet fra åpen versjon.');
    }
    redirect('/ansatt');
}

// Ansatt behandler en feilmelding: lukk den, eller fjern verdien fra den åpne versjonen
if ($m = route('POST', '/ansatt/rapport/(\d+)')) {
    if (!has_role('ansatt', 'admin')) {
        http_response_code(403);
        exit;
    }
    $st = db()->prepare("SELECT * FROM spec_reports WHERE id = ? AND status = 'apen'");
    $st->execute([(int) $m[0]]);
    if ($r = $st->fetch()) {
        $remove = ($_POST['action'] ?? '') === 'fjern';
        if ($remove) {
            db()->prepare("DELETE FROM spec_values WHERE product_id = ? AND `key` = ? AND version = 'apen'")->execute([$r['product_id'], $r['key']]);
            emit("product:{$r['product_id']}", 'revision', ['product' => (int) $r['product_id'], 'status' => 'fjernet']);
        }
        // Samme verdi kan være meldt av flere: alle meldingene om den lukkes
        db()->prepare("UPDATE spec_reports SET status = 'lukket', handled_by = ? WHERE product_id = ? AND `key` = ? AND status = 'apen'")
            ->execute([current_user()['id'], $r['product_id'], $r['key']]);
        flash($remove ? 'Verdien er fjernet fra den åpne versjonen.' : 'Meldingen er lukket. Verdien står som før.');
    }
    redirect('/ansatt#meldinger');
}

// ---------- Kundeservice-panel (feature 006) ----------
if (route('GET', '/support') !== null) {
    if (!has_role('support', 'ansatt', 'admin')) {
        redirect('/logg-inn?back=/support');
    }
    $tickets = db()->query("SELECT t.*, " . Privacy::sql() . " customer FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id
        WHERE t.status <> 'lukket' ORDER BY t.id DESC LIMIT 30")->fetchAll();
    $msgs = [];
    if ($tickets) {
        $in = implode(',', array_column($tickets, 'id'));
        foreach (db()->query("SELECT * FROM support_messages WHERE ticket_id IN ($in) ORDER BY id") as $mm) {
            $msgs[$mm['ticket_id']][] = $mm;
        }
    }
    render('support', ['tickets' => $tickets, 'msgs' => $msgs], 'Kundeservice');
    exit;
}

if ($m = route('POST', '/support/(\d+)/lukk')) {
    if (has_role('support', 'ansatt', 'admin')) {
        db()->prepare("UPDATE support_tickets SET status = 'lukket' WHERE id = ?")->execute([(int) $m[0]]);
        emit('ticket:' . (int) $m[0], 'message', ['from' => 'support', 'name' => 'Kundeservice', 'body' => 'Saken er lukket. Takk for at du tok kontakt!']);
    }
    redirect('/support');
}

// ---------- Admin: måltall (business-context KPI) ----------
if (route('GET', '/admin') !== null) {
    if (!has_role('admin')) {
        redirect('/logg-inn?back=/admin');
    }
    $pdo = db();
    $count = fn(string $type) => (int) $pdo->query('SELECT COUNT(*) FROM analytics_events WHERE type = ' . $pdo->quote($type))->fetchColumn();
    $kpi = [
        'searches' => $count('search') + $count('search_zero'),
        'zero' => $count('search_zero'),
        'guide' => $count('guide_done'),
        'chat' => $count('chat'),
        'escalate' => $count('escalate'),
        'compare' => $count('compare'),
        'purchases' => $count('purchase'),
    ];
    $zeroTerms = $pdo->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(data, '$.q')) q, COUNT(*) n FROM analytics_events WHERE type = 'search_zero' GROUP BY q ORDER BY n DESC LIMIT 10")->fetchAll();
    $via = $pdo->query("SELECT COALESCE(added_via, 'ukjent') via, COUNT(*) n, SUM(qty * price_ore) ore FROM order_items GROUP BY via ORDER BY n DESC")->fetchAll();
    $coverage = $pdo->query("SELECT version, source, COUNT(*) n FROM spec_values GROUP BY version, source ORDER BY version, n DESC")->fetchAll();
    $rel = $pdo->query('SELECT level, kind, COUNT(*) n FROM relations GROUP BY level, kind ORDER BY level')->fetchAll();
    $users = $pdo->query("SELECT role, COUNT(*) n, SUM(email_verified_at IS NOT NULL) verified FROM users GROUP BY role")->fetchAll();
    $contrib = $pdo->query("SELECT status, COUNT(*) n FROM spec_revisions GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    render('admin', compact('kpi', 'zeroTerms', 'via', 'coverage', 'rel', 'users', 'contrib'), 'Admin');
    exit;
}

// ---------- Sammenlign (feature 007) ----------
if (route('GET', '/sammenlign') !== null) {
    $ids = array_slice(array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? '')))), 0, 4);
    $cards = Catalog::cards($ids);
    $specs = [];
    foreach ($cards as $c) {
        $specs[$c['id']] = Catalog::specs($c['id']);
    }
    $keys = [];
    foreach ($specs as $s) {
        foreach ($s as $k => $v) {
            $keys[$k] = $v['label'] . ($v['unit'] ? " ({$v['unit']})" : '');
        }
    }
    track('compare', ['ids' => $ids]);
    render('compare', ['cards' => $cards, 'specs' => $specs, 'keys' => $keys], 'Sammenlign');
    exit;
}

// ---------- Søren (feature 008) ----------
function sse(string $event, mixed $data): void
{
    ChatJob::append($event, $data); // så en ny side kan lese videre hvis kunden bytter side
    if (connection_aborted()) {
        return;
    }
    echo "event: $event\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush();
    flush();
}

// Les videre på et svar Søren holder på med (eller er ferdig med) etter at kunden byttet side
if ($m = route('GET', '/api/chat/([a-f0-9]{24})')) {
    $from = max(0, (int) ($_GET['from'] ?? 0));
    session_write_close();
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    while (ob_get_level()) {
        ob_end_flush();
    }
    $found = ChatJob::follow($m[0], $from, function (string $event, mixed $data): void {
        echo "event: $event\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        @ob_flush();
        flush();
    });
    if (!$found) {
        echo "event: gone\ndata: true\n\n";
    }
    exit;
}

// Laster språkmodellen inn i minnet når chatten åpnes, så første svar kommer raskt
if (route('POST', '/api/chat/warm') !== null) {
    session_write_close();
    json_out(['ok' => Chat::warm()]);
}

if (route('POST', '/api/chat') !== null) {
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $messages = array_values(array_filter(array_map(fn($m) => [
        'role' => ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user',
        'content' => mb_substr((string) ($m['content'] ?? ''), 0, 1500),
    ], array_slice((array) ($in['messages'] ?? []), -8)), fn($m) => $m['content'] !== ''));
    // Bare adressen kommer fra nettleseren. Konteksten bygges på serveren fra en fast liste med offentlige sider.
    $page = PageContext::resolve(is_string($in['page'] ?? null) ? $in['page'] : null);
    // Produktguiden kunden tok tidligere i økten (de siste tre timene) følger med til andre sider
    $g = $_SESSION['guide'] ?? null;
    if ($page['kind'] !== 'guide' && is_array($g) && time() - (int) $g['at'] < 3 * 3600) {
        $page['guide'] = $g['a'];
        $page['text'] = trim($page['text'] . "\n\n" . Guide::brief($g['a'], $page['product'], false));
    }
    // Språk per melding: engelsk bare når kunden tydelig skriver engelsk. Ellers språket valgt med flagget, ellers norsk.
    Chat::$lang = I18n::$force = Lang::question(Chat::lastUser($messages), I18n::chosen() ? I18n::lang() : 'no');
    // Søren svarer ferdig selv om kunden bytter side. Siden spørsmålet ble stilt på er låst i $page over.
    $job = ChatJob::start();
    ignore_user_abort(true);
    set_time_limit(600);
    session_write_close(); // ikke blokker andre forespørsler mens modellen svarer
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    while (ob_get_level()) {
        ob_end_flush();
    }
    echo "event: job\ndata: " . json_encode($job) . "\n\n"; // ikke i loggen: nettleseren trenger den bare én gang
    sse('page', ['kind' => $page['kind'], 'label' => $page['label']]);
    // Vern før modellen: selvskading, banning og forsøk på å overstyre Søren får et fast svar. Modellen spørres ikke.
    $q = Chat::lastUser($messages);
    $blocked = match (true) {
        Chat::crisis($q) => ['krise', Chat::crisisReply()],
        Chat::violence($q) => ['vold', Chat::violenceReply()],
        !Moderation::clean($q) => ['banning', t('Jeg svarer ikke på meldinger med banning eller stygge ord. Spør gjerne på nytt.')],
        Guard::chatInjection($q) => ['overstyring', t('Jeg kan bare hjelpe med produkter og prosjekter hos Sør-Tre.')],
        default => null,
    };
    if ($blocked) {
        sse('token', $blocked[1]);
        track('chat', ['product' => $page['product'], 'page' => $page['kind'], 'llm' => false, 'intent' => $blocked[0], 'lang' => Chat::$lang]);
        sse('done', true);
        exit;
    }
    // Spørsmål om hele katalogen (veiledninger, best vurdert, billigst): databasen finner svaret, Søren forklarer det
    $intent = ($kind = CatalogIntent::detect(Chat::lastUser($messages))) ? CatalogIntent::build($kind, Chat::lastUser($messages), $page) : null;
    $ids = $intent ? array_slice($intent['ids'], 0, 3) : Chat::context($messages, $page);
    $cardIds = $intent ? $intent['ids'] : Chat::cardIds($messages, $page);
    sse('products', array_map(fn($c) => [
        'url' => "/produkt/{$c['slug']}", 'name' => $c['name'], 'price' => kr((int) $c['price_ore']),
        'img' => $c['img'] ? "/{$c['img']}" : null,
        'stock' => '· ' . ($c['in_store'] > 0 ? t('{n} i butikk', ['n' => $c['in_store']]) : t($c['online'] > 0 ? 'nettlager' : 'ikke på lager'))
            . (Catalog::hasManual((int) $c['id']) ? ' · ' . t('Veiledning') : ''),
    ], Catalog::cards($cardIds)));
    $mode = 'rask';
    [$ok, $answer] = Chat::answer($messages, $page, $ids, 'sse', $mode, $intent['facts'] ?? '');
    if (!$ok) {
        sse('info', t(match (Chat::$lastError) {
            'treg' => 'Søren brukte for lang tid, så her er svaret fra katalogen i stedet.',
            default => 'Språkmodellen (Ollama) svarer ikke. Søren bruker enkle regler i stedet.',
        }));
        $answer = trim($intent['answer'] ?? Chat::fallback($cardIds, Chat::lastUser($messages), $page));
        if ($answer !== '') {
            sse('token', $answer);
        }
    }
    track('chat', ['product' => $page['product'], 'page' => $page['kind'], 'llm' => $ok, 'found' => count($ids), 'intent' => $kind ?: null, 'lang' => Chat::$lang]);
    sse('done', true);
    exit;
}

// ---------- Kundeservice: eskalering fra Søren til menneske (feature 006) ----------
if (route('POST', '/api/support') !== null) {
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $log = array_slice((array) ($in['log'] ?? []), -20);
    $first = '';
    foreach ($log as $m) {
        if (($m['role'] ?? '') === 'user') {
            $first = mb_substr((string) $m['content'], 0, 280);
            break;
        }
    }
    $pid = ($in['product'] ?? null) ? (int) $in['product'] : null;
    $summary = ($first ?: 'Kunden ba om å snakke med et menneske') . ($pid ? " (produkt $pid)" : '');
    db()->prepare('INSERT INTO support_tickets (session_id, user_id, summary, chat_log) VALUES (?,?,?,?)')
        ->execute([sid(), current_user()['id'] ?? null, $summary, json_encode($log, JSON_UNESCAPED_UNICODE)]);
    $tid = (int) db()->lastInsertId();
    $_SESSION['tickets'][] = $tid;
    emit('support', 'ticket', ['ticket' => $tid, 'summary' => $summary]);
    track('escalate', ['ticket' => $tid]);
    json_out(['ticket' => $tid]);
}

if ($m = route('POST', '/api/support/(\d+)')) {
    $tid = (int) $m[0];
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $body = mb_substr(trim((string) ($in['body'] ?? $_POST['body'] ?? '')), 0, 2000);
    $staff = has_role('support', 'ansatt', 'admin');
    if ($body === '' || (!$staff && !in_array($tid, $_SESSION['tickets'] ?? [], true))) {
        json_out(['error' => 'Ingen tilgang'], 403);
    }
    $u = current_user();
    db()->prepare('INSERT INTO support_messages (ticket_id, sender, user_id, body) VALUES (?,?,?,?)')
        ->execute([$tid, $staff ? 'support' : 'kunde', $u['id'] ?? null, $body]);
    if ($staff) {
        db()->prepare("UPDATE support_tickets SET status = 'pagar', assigned_to = COALESCE(assigned_to, ?) WHERE id = ?")->execute([$u['id'], $tid]);
    }
    emit("ticket:$tid", 'message', ['from' => $staff ? 'support' : 'kunde', 'name' => $staff ? explode(' ', $u['name'])[0] : 'Kunde', 'body' => $body]);
    json_out(['ok' => true]);
}

// ---------- Sanntid: Server-Sent Events over hendelsestabellen (ADR-004, erstatter LISTEN/NOTIFY) ----------
if (route('GET', '/api/stream') !== null) {
    $channels = array_filter(explode(',', (string) ($_GET['ch'] ?? '')), fn($c) => preg_match('/^[a-z]+(:\d+)?$/', $c));
    $staffOnly = array_filter($channels, fn($c) => in_array($c, ['support', 'staff'], true));
    $mine = $_SESSION['tickets'] ?? [];
    foreach ($channels as $c) {
        if (preg_match('/^ticket:(\d+)$/', $c, $mm) && !in_array((int) $mm[1], $mine, true) && !has_role('support', 'ansatt', 'admin')) {
            http_response_code(403);
            exit;
        }
    }
    if ($staffOnly && !has_role('support', 'ansatt', 'admin')) {
        http_response_code(403);
        exit;
    }
    session_write_close();
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    while (ob_get_level()) {
        ob_end_flush();
    }
    $last = (int) ($_SERVER['HTTP_LAST_EVENT_ID'] ?? $_GET['since'] ?? 0);
    if ($last === 0) {
        $last = (int) db()->query('SELECT COALESCE(MAX(id),0) FROM events')->fetchColumn();
    }
    $in = implode(',', array_fill(0, count($channels), '?'));
    $st = db()->prepare("SELECT * FROM events WHERE id > ? AND channel IN ($in) ORDER BY id LIMIT 50");
    echo "retry: 2000\n\n";
    $until = time() + 25; // korte tilkoblinger, nettleseren kobler til igjen med Last-Event-ID
    while (time() < $until && !connection_aborted()) {
        $st->execute([$last, ...$channels]);
        foreach ($st->fetchAll() as $e) {
            $last = (int) $e['id'];
            echo "id: {$e['id']}\nevent: message\ndata: " . json_encode(['channel' => $e['channel'], 'type' => $e['type']] + json_decode($e['payload'], true), JSON_UNESCAPED_UNICODE) . "\n\n";
        }
        echo ": ping\n\n";
        @ob_flush();
        flush();
        usleep(700000);
    }
    exit;
}

http_response_code(404);
render('notfound', [], 'Fant ikke siden');
