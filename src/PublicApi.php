<?php
declare(strict_types=1);

/**
 * Public read-only REST API, version 1 (feature 024). Lets another shop system (for example a WooCommerce store that
 * uses the same product IDs) reuse the knowledge layer: specifications with source and level, "goes well with" with
 * level and reason, and reviews from verified buyers.
 *
 * The API is a contract, not a view of the tables: field names and values are its own (English, stable), internal
 * columns, user IDs, sessions, stock and demo orders are never sent. It is stateless (no session, no cookie),
 * cacheable (ETag, If-None-Match, Cache-Control), self-describing (links in every body, Link headers, OpenAPI at
 * /api/v1/openapi.json) and answers errors as RFC 9457 problem details. Only GET, HEAD and OPTIONS exist.
 */
final class PublicApi
{
    public const BASE = '/api/v1';
    public const DOCS = '/api/docs';
    public const PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;
    public const MAX_AGE = 60;
    public const SPEC_FILE = __DIR__ . '/../docs/api/openapi.json';
    /**
     * Window for the question limits, per hashed client IP: config api_question_limit for every question, and
     * api_ask_limit for the questions that reach the model, which is the expensive part. A question the server answers
     * from the catalogue counts only against the first (feature 040).
     */
    public const ASK_WINDOW_S = 600;

    /** Internal values mapped to the public vocabulary. */
    public const SOURCE = ['sortre' => 'store', 'utledet' => 'derived', 'bruker' => 'buyer', 'ansatt' => 'staff'];
    public const VERSION = ['verifisert' => 'verified', 'apen' => 'open'];
    public const KIND = ['passer_sammen' => 'goes_with', 'passer_ikke' => 'does_not_fit', 'alternativ' => 'alternative', 'kjopt_sammen' => 'bought_together'];
    public const LEVEL = ['dokumentert' => 'documented', 'regelutledet' => 'rule_based', 'antatt' => 'assumed'];
    private const METHODS = 'GET, HEAD, OPTIONS';
    /** Every path the API answers, as regular expressions. Routing and the docs check both read this list. */
    public const PATHS = [
        'root' => '/api/v1', 'openapi' => '/api/v1/openapi.json', 'products' => '/api/v1/products',
        'product' => '/api/v1/products/(\\d{1,10})', 'specifications' => '/api/v1/products/(\\d{1,10})/specifications',
        'relations' => '/api/v1/products/(\\d{1,10})/relations', 'reviews' => '/api/v1/products/(\\d{1,10})/reviews',
        'themes' => '/api/v1/themes', 'theme' => '/api/v1/themes/([a-z]{2,16})', 'keys' => '/api/v1/specification-keys',
        'questions' => '/api/v1/products/(\\d{1,10})/questions', 'docs' => '/api/docs', 'index' => '/api',
    ];

    /** Answers a request under /api/v1 or /api/docs and ends it. */
    public static function serve(string $method, string $path): never
    {
        $body = $method === 'POST' ? ['raw' => (string) file_get_contents('php://input'), 'type' => (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
            'client' => (string) ($_SERVER['REMOTE_ADDR'] ?? '')] : [];
        $res = self::handle($method, $path, $_GET, (string) ($_SERVER['HTTP_ACCEPT'] ?? ''), (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), $body);
        http_response_code($res['status']);
        foreach ($res['headers'] as $k => $v) {
            header("$k: $v");
        }
        if ($method !== 'HEAD' && $res['status'] !== 304) {
            echo $res['body'];
        }
        exit;
    }

    /**
     * The whole response for one request: status, headers and body. No output, no exit.
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public static function handle(string $method, string $path, array $query, string $accept = '', string $ifNoneMatch = '', array $post = []): array
    {
        // Asking Søren is the one resource that takes POST; everything else is read-only
        $ask = (bool) preg_match('#^' . self::PATHS['questions'] . '$#', $path, $am);
        $allow = $ask ? 'POST, OPTIONS' : self::METHODS;
        $cors = ['Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Methods' => $allow,
            'Access-Control-Allow-Headers' => 'Accept, Content-Type, If-None-Match', 'Access-Control-Expose-Headers' => 'ETag, Link, X-Total-Count, Retry-After',
            'Access-Control-Max-Age' => '86400', 'X-Content-Type-Options' => 'nosniff'];
        if ($method === 'OPTIONS') {
            return ['status' => 204, 'headers' => $cors + ['Allow' => $allow], 'body' => ''];
        }
        if (!in_array($method, array_map('trim', explode(',', $allow)), true) && !($method === 'HEAD' && !$ask)) {
            return self::problem(405, 'Method not allowed', $ask ? 'Questions are sent with POST.' : 'Only GET, HEAD and OPTIONS are supported here.', $cors + ['Allow' => $allow]);
        }
        if ($ask) {
            if (!self::accepts($accept, 'application/json')) {
                return self::problem(406, 'Not acceptable', 'Answers are only available as application/json.', $cors);
            }
            try {
                $r = self::ask((int) $am[1], $post['raw'] ?? '', $post['type'] ?? '', $post['client'] ?? '');
            } catch (\Throwable $e) {
                $ref = bin2hex(random_bytes(4));
                Errors::log("api $ref " . $e::class . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
                return self::problem(500, 'Internal error', "The question could not be answered. Reference $ref.", $cors);
            }
            if (isset($r['problem'])) {
                return self::problem($r['problem'][0], $r['problem'][1], $r['problem'][2], $cors + ($r['headers'] ?? []));
            }
            // The correlation ID of the trace (feature 039), so one answer can be found in data/traces
            return ['status' => 200, 'headers' => $cors + ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']
                + (config()['trace'] ? ['X-Trace-Id' => Trace::id()] : []),
                'body' => json_encode($r['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) . "\n"];
        }
        if ($path === self::DOCS) {
            $html = (string) file_get_contents(__DIR__ . '/../templates/api-docs.html');
            return self::cached(200, $cors + ['Content-Type' => 'text/html; charset=utf-8'], $html, $ifNoneMatch);
        }
        $json = $path === self::BASE . '/openapi.json' ? 'application/vnd.oai.openapi+json' : 'application/json';
        if (!self::accepts($accept, $json)) {
            return self::problem(406, 'Not acceptable', "This resource is only available as $json.", $cors);
        }
        try {
            $r = self::route($path, $query);
        } catch (\Throwable $e) {
            $ref = bin2hex(random_bytes(4));
            Errors::log("api $ref " . $e::class . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return self::problem(500, 'Internal error', "The request could not be answered. Reference $ref.", $cors);
        }
        if (isset($r['problem'])) {
            return self::problem($r['problem'][0], $r['problem'][1], $r['problem'][2], $cors);
        }
        $headers = $cors + ['Content-Type' => $json === 'application/json' ? 'application/json; charset=utf-8' : $json];
        if ($r['links'] ?? []) {
            $headers['Link'] = self::linkHeader($r['links']);
        }
        if (isset($r['total'])) {
            $headers['X-Total-Count'] = (string) $r['total'];
        }
        $body = is_string($r['body']) ? $r['body'] : json_encode($r['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . "\n";
        return self::cached(200, $headers, $body, $ifNoneMatch);
    }

    /** Matches the path to a resource. Returns ['body' => ..., 'links' => ..., 'total' => ...] or ['problem' => [status, title, detail]]. */
    private static function route(string $path, array $q): array
    {
        $name = null;
        $m = [];
        foreach (self::PATHS as $k => $re) {
            if (preg_match('#^' . $re . '$#', $path, $m)) {
                $name = $k;
                break;
            }
        }
        return match ($name) {
            'root' => self::root(),
            'openapi' => ['body' => (string) file_get_contents(self::SPEC_FILE)],
            'products' => self::products($q),
            'product' => self::product((int) $m[1]),
            'specifications' => self::specifications((int) $m[1]),
            'relations' => self::relations((int) $m[1], $q),
            'reviews' => self::reviews((int) $m[1]),
            'questions' => ['problem' => [405, 'Method not allowed', 'Questions are sent with POST.']],
            'themes' => self::themes(),
            'theme' => self::theme($m[1]),
            'keys' => self::keys(),
            default => ['problem' => [404, 'Not found', "No resource at $path. Start at " . self::BASE . '.']],
        };
    }

    // ---------- Resources ----------

    private static function root(): array
    {
        $links = [
            'self' => self::BASE, 'service-desc' => self::BASE . '/openapi.json', 'service-doc' => self::DOCS,
            'products' => self::BASE . '/products', 'themes' => self::BASE . '/themes', 'specification-keys' => self::BASE . '/specification-keys',
        ];
        return ['body' => [
            'name' => 'Sørpedia product knowledge API', 'version' => '1',
            'description' => 'Read-only access to product specifications with source and level, related products with level and reason, and reviews from verified buyers. Product IDs are the store IDs.',
            '_links' => self::hrefs($links),
        ], 'links' => ['service-desc' => $links['service-desc'], 'service-doc' => $links['service-doc']]];
    }

    private static function products(array $q): array
    {
        [$page, $per, $err] = self::paging($q);
        if ($err) {
            return ['problem' => [400, 'Invalid query parameter', $err]];
        }
        $theme = isset($q['theme']) ? (string) $q['theme'] : null;
        if ($theme !== null && !isset(Catalog::THEMES[$theme])) {
            return ['problem' => [400, 'Invalid query parameter', 'theme must be one of: ' . implode(', ', array_keys(Catalog::THEMES)) . '.']];
        }
        $text = trim((string) ($q['q'] ?? ''));
        if (mb_strlen($text) > 100) {
            return ['problem' => [400, 'Invalid query parameter', 'q is limited to 100 characters.']];
        }
        $sku = isset($q['sku']) ? trim((string) $q['sku']) : null;
        $where = [];
        $args = [];
        $order = 'p.id';
        $corrected = null;
        if ($text !== '') {
            $s = Search::run($text, 500);
            $corrected = $s['corrected'];
            $ids = array_map('intval', $s['ids']);
            if (!$ids) {
                $ids = [0];
            }
            $where[] = 'p.id IN (' . implode(',', $ids) . ')';
            $order = 'FIELD(p.id, ' . implode(',', $ids) . ')';
        }
        if ($theme !== null) {
            $where[] = 'p.theme = ?';
            $args[] = $theme;
        }
        if ($sku !== null && $sku !== '') {
            $where[] = 'p.sku = ?';
            $args[] = $sku;
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $st = db()->prepare("SELECT COUNT(*) FROM products p $w");
        $st->execute($args);
        $total = (int) $st->fetchColumn();
        $st = db()->prepare(self::productSql() . " $w ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per));
        $st->execute($args);
        $items = array_map([self::class, 'productSummary'], $st->fetchAll());
        $nav = self::pageLinks(self::BASE . '/products', array_filter(['q' => $text, 'theme' => $theme, 'sku' => $sku], fn($v) => $v !== null && $v !== ''), $page, $per, $total);
        $body = ['total' => $total, 'page' => $page, 'per_page' => $per, 'items' => $items, '_links' => self::hrefs($nav)];
        if ($text !== '') {
            $body = ['query' => $text, 'corrected' => $corrected] + $body;
        }
        return ['body' => $body, 'links' => $nav, 'total' => $total];
    }

    private static function product(int $id): array
    {
        $p = self::find($id);
        if (!$p) {
            return self::missing($id);
        }
        $self = self::BASE . "/products/$id";
        $links = ['self' => $self, 'specifications' => "$self/specifications", 'relations' => "$self/relations", 'reviews' => "$self/reviews", 'questions' => "$self/questions",
            'theme' => self::BASE . "/themes/{$p['theme']}", 'collection' => self::BASE . '/products'];
        $body = self::productSummary($p, false);
        $body['description'] = $p['plain_text'] !== null ? trim((string) $p['plain_text']) : null;
        $body['_links'] = self::hrefs($links) + ['page' => ['href' => '/produkt/' . rawurlencode($p['slug'])]]
            + ($p['source_url'] ? ['store_page' => ['href' => $p['source_url']]] : []);
        return ['body' => $body, 'links' => ['collection' => $links['collection']]];
    }

    private static function specifications(int $id): array
    {
        $p = self::find($id);
        if (!$p) {
            return self::missing($id);
        }
        $items = [];
        foreach (Catalog::specs($id) as $key => $s) {
            $items[] = self::spec((string) $key, $s);
        }
        $st = db()->prepare('SELECT k.`key`, k.label, k.unit FROM spec_keys k WHERE k.is_facet = 1 AND (k.themes IS NULL OR FIND_IN_SET(?, k.themes))
            AND NOT EXISTS (SELECT 1 FROM spec_values v WHERE v.product_id = ? AND v.`key` = k.`key`) ORDER BY k.sort, k.label');
        $st->execute([$p['theme'], $id]);
        $self = self::BASE . "/products/$id/specifications";
        return ['body' => [
            'product' => ['id' => $id, 'name' => $p['name'], '_links' => self::hrefs(['self' => self::BASE . "/products/$id"])],
            'items' => $items,
            'unknown' => array_map(fn($k) => ['key' => $k['key'], 'label' => $k['label'], 'unit' => $k['unit']], $st->fetchAll()),
            '_links' => self::hrefs(['self' => $self, 'up' => self::BASE . "/products/$id"]),
        ], 'links' => ['up' => self::BASE . "/products/$id"]];
    }

    private static function relations(int $id, array $q): array
    {
        if (!self::find($id)) {
            return self::missing($id);
        }
        $kind = isset($q['kind']) ? (string) $q['kind'] : null;
        $internal = $kind === null ? null : array_search($kind, self::KIND, true);
        if ($kind !== null && $internal === false) {
            return ['problem' => [400, 'Invalid query parameter', 'kind must be one of: ' . implode(', ', self::KIND) . '.']];
        }
        $st = db()->prepare('SELECT r.product_b, r.kind, r.level, r.reason, p.name FROM relations r JOIN products p ON p.id = r.product_b
            WHERE r.product_a = ?' . ($internal ? ' AND r.kind = ?' : '') . ' ORDER BY r.kind, r.level = "dokumentert" DESC, r.score DESC, r.product_b LIMIT 50');
        $st->execute($internal ? [$id, $internal] : [$id]);
        $items = array_map(fn($r) => self::relation($r), $st->fetchAll());
        $self = self::BASE . "/products/$id/relations";
        return ['body' => ['product_id' => $id, 'items' => $items,
            '_links' => self::hrefs(['self' => $kind ? "$self?kind=$kind" : $self, 'up' => self::BASE . "/products/$id"])],
            'links' => ['up' => self::BASE . "/products/$id"]];
    }

    private static function reviews(int $id): array
    {
        if (!self::find($id)) {
            return self::missing($id);
        }
        $st = db()->prepare('SELECT r.rating, r.title, r.body, r.is_demo_seed, r.created_at, ' . Privacy::sql() . ' author
            FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = ? ORDER BY r.created_at DESC, r.id DESC');
        $st->execute([$id]);
        $rows = $st->fetchAll();
        $items = array_map(fn($r) => ['author' => $r['author'], 'rating' => (int) $r['rating'], 'title' => $r['title'], 'body' => $r['body'],
            'verified_buyer' => true, 'demo' => (bool) $r['is_demo_seed'], 'created_at' => self::iso((string) $r['created_at'])], $rows);
        $avg = $rows ? round(array_sum(array_column($items, 'rating')) / count($items), 1) : null;
        return ['body' => ['product_id' => $id, 'count' => count($items), 'average' => $avg, 'items' => $items,
            '_links' => self::hrefs(['self' => self::BASE . "/products/$id/reviews", 'up' => self::BASE . "/products/$id"])],
            'links' => ['up' => self::BASE . "/products/$id"], 'total' => count($items)];
    }

    private static function themes(): array
    {
        $n = db()->query('SELECT theme, COUNT(*) FROM products GROUP BY theme')->fetchAll(\PDO::FETCH_KEY_PAIR);
        $items = [];
        foreach (Catalog::THEMES as $key => [$name, $desc]) {
            $items[] = self::themeBody($key, $name, $desc, (int) ($n[$key] ?? 0));
        }
        return ['body' => ['items' => $items, '_links' => self::hrefs(['self' => self::BASE . '/themes', 'up' => self::BASE])], 'total' => count($items)];
    }

    private static function theme(string $key): array
    {
        if (!isset(Catalog::THEMES[$key])) {
            return ['problem' => [404, 'Not found', "There is no theme \"$key\". The list is at " . self::BASE . '/themes.']];
        }
        $st = db()->prepare('SELECT COUNT(*) FROM products WHERE theme = ?');
        $st->execute([$key]);
        [$name, $desc] = Catalog::THEMES[$key];
        return ['body' => self::themeBody($key, $name, $desc, (int) $st->fetchColumn())];
    }

    private static function keys(): array
    {
        $items = array_map(fn($k) => ['key' => $k['key'], 'label' => $k['label'], 'unit' => $k['unit'], 'datatype' => $k['datatype'],
            'filterable' => (bool) $k['is_facet'], 'themes' => $k['themes'] === null ? null : explode(',', $k['themes'])],
            db()->query('SELECT `key`, label, unit, datatype, is_facet, themes FROM spec_keys ORDER BY sort, label')->fetchAll());
        return ['body' => ['items' => $items, '_links' => self::hrefs(['self' => self::BASE . '/specification-keys', 'up' => self::BASE])], 'total' => count($items)];
    }

    /**
     * Søren answers a question about one product, through the same pipeline as the shop's chat: fixed answers for
     * blocked input before the model, facts from the database, product references filled with price and stock by the
     * server, and the catalogue's rule-based answer when the model does not answer. The reasoning is returned too.
     */
    private static function ask(int $id, string $raw, string $type, string $client): array
    {
        $p = self::find($id);
        if (!$p) {
            return self::missing($id);
        }
        if (!str_starts_with(strtolower(trim($type)), 'application/json')) {
            return ['problem' => [415, 'Unsupported media type', 'Send the question as application/json: {"question": "..."}.']];
        }
        $in = json_decode($raw, true);
        $q = is_array($in) && is_string($in['question'] ?? null) ? trim($in['question']) : '';
        if ($q === '' || mb_strlen($q) > 500) {
            return ['problem' => [400, 'Invalid body', 'question must be a text of 1 to 500 characters.']];
        }
        $history = [];
        foreach (array_slice(is_array($in['history'] ?? null) ? $in['history'] : [], -6) as $h) {
            if (!is_array($h) || !in_array($h['role'] ?? null, ['user', 'assistant'], true) || !is_string($h['content'] ?? null) || mb_strlen($h['content']) > 2000) {
                return ['problem' => [400, 'Invalid body', 'history must be a list of {"role": "user" or "assistant", "content": text up to 2000 characters}.']];
            }
            $history[] = ['role' => $h['role'], 'content' => $h['content']];
        }
        self::questionLog();
        if ($wait = self::limited($client, 'question', config()['api_question_limit'])) {
            return ['problem' => [429, 'Too many questions', 'At most ' . config()['api_question_limit'] . ' questions per ' . (self::ASK_WINDOW_S / 60) . ' minutes from one client.'],
                'headers' => ['Retry-After' => (string) $wait]];
        }
        set_time_limit(600);
        Chat::$lang = I18n::$force = Lang::question($q, 'no');
        $self = self::BASE . "/products/$id";
        $links = self::hrefs(['product' => $self, 'specifications' => "$self/specifications", 'relations' => "$self/relations"]);
        $reply = fn(string $answer, bool $used, ?string $reasoning, array $ids, ?string $notice) => ['body' => [
            'product_id' => $id, 'question' => $q, 'answer' => trim((string) preg_replace('/\n{3,}/', "\n\n", $answer)), 'reasoning' => $reasoning !== null && trim($reasoning) !== '' ? trim($reasoning) : null,
            'model' => ['used' => $used, 'name' => $used ? config()['ollama_model'] : null], 'notice' => $notice,
            'products' => array_map(fn($c) => ['id' => (int) $c['id'], 'name' => $c['name'], '_links' => self::hrefs(['self' => self::BASE . "/products/{$c['id']}"])],
                $ids ? db()->query('SELECT id, name FROM products WHERE id IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY FIELD(id, ' . implode(',', array_map('intval', $ids)) . ')')->fetchAll() : []),
            '_links' => $links]];
        if ($blocked = Chat::blocked($q)) {
            return $reply($blocked[1], false, null, [], 'Fixed answer: the question was not sent to the model.');
        }
        $t0 = microtime(true);
        if ($quick = QuickReply::match($q)) {
            Trace::add('route', ['via' => 'api', 'route' => 'fast:' . $quick['route'], 'ms' => round((microtime(true) - $t0) * 1000, 2)]);
            return $reply($quick['text'], false, null, [], 'Fixed answer: the question was not sent to the model.');
        }
        $messages = [...$history, ['role' => 'user', 'content' => $q]];
        $page = PageContext::resolve('/produkt/' . $p['slug']);
        // The same product list as the shop chat (ProductRetriever). The API keeps no conversation, so there is no product memory:
        // a follow-up is read against the product this endpoint is about.
        $t = microtime(true);
        $found = ProductRetriever::forChat($messages, $page);
        $retrieveMs = round((microtime(true) - $t) * 1000, 1);
        $intent = $found['intent'];
        ['ids' => $ids, 'cards' => $cardIds] = self::grounding($id, $found);
        $out = ['token' => '', 'think' => ''];
        $collect = function (string $ev, mixed $data) use (&$out): void {
            if ($ev === 'answer_reset') {
                $out['token'] = ''; // the model's answer was rejected; the replacement follows
            } elseif (isset($out[$ev]) && is_string($data)) {
                $out[$ev] .= $data;
            }
        };
        // A model the catalogue doesn't have is named by the server first (feature 036); the answer field stays one text
        $notSold = AnswerGuard::notSold($found['unknown']);
        // Factual questions are answered by the server from the database, the model is not asked (feature 039)
        $t = microtime(true);
        $fact = FactAnswer::answer($q, $ids, $found['why'], $id, $intent);
        Trace::add('route', ['via' => 'api', 'product' => $id, 'question' => $q, 'ids' => $ids, 'cards' => $cardIds, 'intent' => $found['kind'] ?: null,
            'unknown' => $found['unknown'], 'retrieve_ms' => $retrieveMs, 'fact_ms' => round((microtime(true) - $t) * 1000, 1), 'server_answer' => $fact !== null]);
        if ($fact !== null) {
            $text = trim($notSold . "\n\n" . ProductRef::resolve($fact['text']));
            Trace::add('answer', ['by' => 'server', 'kind' => $fact['kind'], 'ids' => $fact['ids'], 'text' => $fact['text'], 'shown' => $text]);
            return $reply($text, false, null, $fact['ids'], 'Answered by the server from the catalogue: the language model was not asked.');
        }
        // Everything else (feature 041): the model only reads the question into fixed fields, and the server writes the answer.
        // Over the model allowance the same fields are read by rules, so the customer still gets an answer and no model call is made.
        $useModel = self::limited($client, 'model', config()['api_ask_limit']) === 0;
        $u = Understand::read($q, $useModel);
        $r = ShopAnswer::answer($q, $u, $found, $page, $id);
        Trace::add('understood', ['source' => $u['source'], 'intent' => $u['intent'], 'type' => $u['type'][1] ?? null, 'requirements' => $u['requirements'],
            'budget_ore' => $u['budget_ore'], 'dropped' => $u['dropped'], 'answer_kind' => $r['kind']]);
        $read = $u['source'] === 'modell'
            ? 'The language model only read the question; the server chose the products and wrote the answer from the catalogue.'
            : ($useModel ? 'The language model did not answer, so the question was read by rules; the server wrote the answer from the catalogue.'
                : 'The model allowance for this client is used up, so the question was read by rules; the server wrote the answer from the catalogue.');
        $text = trim($notSold . "\n\n" . ProductRef::resolve($r['text']));
        Trace::add('answer', ['by' => 'server', 'kind' => $r['kind'], 'ids' => $r['ids'], 'text' => $r['text'], 'shown' => $text]);
        return $reply($text, $u['source'] === 'modell', null, $r['ids'], $read);
    }

    /**
     * The products for the facts and the checks (feature 038): the endpoint's product always first, whatever the retriever
     * found, so a catalogue-wide question («den billigste») or an empty result never leaves the model without it.
     * @return array{ids: int[], cards: int[]}
     */
    public static function grounding(int $id, array $found): array
    {
        $first = fn(array $list) => array_slice(array_values(array_unique([$id, ...array_map('intval', $list)])), 0, 5);
        return ['ids' => $first($found['ids'] ?? []), 'cards' => $first($found['cards'] ?? [])];
    }

    /** The table the limits count in, made on first use. Kept out of limited() so that one can run inside a transaction. */
    private static function questionLog(): void
    {
        // Looked up first: a CREATE statement, even one that does nothing, ends an open transaction in MySQL
        if (db()->query("SHOW TABLES LIKE 'api_question_log'")->fetchColumn() === false) {
            db()->exec('CREATE TABLE IF NOT EXISTS api_question_log (client CHAR(64) NOT NULL, at INT UNSIGNED NOT NULL, KEY (client, at)) ENGINE=InnoDB');
        }
    }

    /**
     * Seconds to wait when this client has used its $limit for the window in $bucket ('question' or 'model'), else 0.
     * Counts the question in that bucket. Each bucket is its own hash of the client, so no column is needed.
     */
    public static function limited(string $client, string $bucket, int $limit): int
    {
        $pdo = db();
        $hash = hash('sha256', $bucket . '|' . $client . '|' . config()['db_name']);
        $now = time();
        $pdo->prepare('DELETE FROM api_question_log WHERE at < ?')->execute([$now - self::ASK_WINDOW_S]);
        $st = $pdo->prepare('SELECT COUNT(*), MIN(at) FROM api_question_log WHERE client = ?');
        $st->execute([$hash]);
        [$n, $first] = $st->fetch(\PDO::FETCH_NUM);
        if ((int) $n >= $limit) {
            return max(1, (int) $first + self::ASK_WINDOW_S - $now);
        }
        $pdo->prepare('INSERT INTO api_question_log (client, at) VALUES (?, ?)')->execute([$hash, $now]);
        return 0;
    }

    // ---------- Mapping (pure) ----------

    /**
     * One specification in the public shape. Both versions are given; "confirmed" is true only for the verified
     * version from the store or staff, so a derived value or a buyer's value is never presented as confirmed.
     * @param array{label: string, unit: ?string, verifisert: ?array, apen: ?array} $s
     */
    public static function spec(string $key, array $s): array
    {
        $v = fn(?array $r) => $r === null ? null : [
            'value' => (string) $r['value_text'],
            'number' => $r['value_num'] === null ? null : (float) $r['value_num'],
            'source' => self::SOURCE[$r['source']] ?? 'unknown',
            'confirmed' => $r['version'] === 'verifisert' && in_array($r['source'], ['sortre', 'ansatt'], true),
            'basis' => $r['evidence'] ?? null,
            'updated_at' => isset($r['updated_at']) ? self::iso((string) $r['updated_at']) : null,
        ];
        return ['key' => $key, 'label' => $s['label'], 'unit' => $s['unit'],
            'effective' => $s['verifisert'] !== null ? 'verified' : ($s['apen'] !== null ? 'open' : null),
            'verified' => $v($s['verifisert']), 'open' => $v($s['apen'])];
    }

    /** One related product. "certain" is true only for a documented relation; an assumed one never is. */
    public static function relation(array $r): array
    {
        $level = self::LEVEL[$r['level']] ?? 'assumed';
        return ['kind' => self::KIND[$r['kind']] ?? 'goes_with', 'level' => $level, 'certain' => $level === 'documented', 'reason' => $r['reason'],
            'product' => ['id' => (int) $r['product_b'], 'name' => $r['name'], '_links' => self::hrefs(['self' => self::BASE . "/products/{$r['product_b']}"])]];
    }

    /**
     * Page navigation links for a collection (RFC 8288 relation names first, prev, next, last).
     * @return array<string, string>
     */
    public static function pageLinks(string $path, array $params, int $page, int $per, int $total): array
    {
        $last = max(1, (int) ceil($total / $per));
        $url = fn(int $p) => $path . '?' . http_build_query($params + ['page' => $p, 'per_page' => $per], '', '&', PHP_QUERY_RFC3986);
        $links = ['self' => $url($page), 'first' => $url(1)];
        if ($page > 1) {
            $links['prev'] = $url(min($page - 1, $last));
        }
        if ($page < $last) {
            $links['next'] = $url($page + 1);
        }
        $links['last'] = $url($last);
        return $links;
    }

    /** RFC 8288 Link header from relation => target. */
    public static function linkHeader(array $links): string
    {
        return implode(', ', array_map(fn($rel, $href) => "<$href>; rel=\"$rel\"", array_keys($links), $links));
    }

    /** Does an Accept header allow this media type? An empty header accepts everything. */
    public static function accepts(string $accept, string $type): bool
    {
        if (trim($accept) === '') {
            return true;
        }
        [$main] = explode('/', $type);
        foreach (explode(',', $accept) as $part) {
            $bits = array_map('trim', explode(';', $part));
            $q = 1.0;
            foreach (array_slice($bits, 1) as $p) {
                if (str_starts_with($p, 'q=')) {
                    $q = (float) substr($p, 2);
                }
            }
            if ($q > 0 && in_array(strtolower($bits[0]), [$type, "$main/*", '*/*'], true)) {
                return true;
            }
        }
        return false;
    }

    /** Does If-None-Match name this entity tag (or "*")? Weak comparison, as RFC 9110 asks for GET. */
    public static function matches(string $ifNoneMatch, string $etag): bool
    {
        $plain = fn(string $t) => preg_replace('#^W/#', '', trim($t));
        foreach (explode(',', $ifNoneMatch) as $t) {
            if (trim($t) === '*' || $plain($t) === $plain($etag)) {
                return true;
            }
        }
        return false;
    }

    // ---------- Helpers ----------

    /** @return array{0: int, 1: int, 2: ?string} page, per page, error */
    private static function paging(array $q): array
    {
        $int = fn(string $k, int $def) => !isset($q[$k]) ? $def : (preg_match('/^\d{1,6}$/', (string) $q[$k]) ? (int) $q[$k] : -1);
        $page = $int('page', 1);
        $per = $int('per_page', self::PER_PAGE);
        if ($page < 1) {
            return [1, self::PER_PAGE, 'page must be a whole number from 1.'];
        }
        if ($per < 1 || $per > self::MAX_PER_PAGE) {
            return [1, self::PER_PAGE, 'per_page must be a whole number from 1 to ' . self::MAX_PER_PAGE . '.'];
        }
        return [$page, $per, null];
    }

    private static function productSql(): string
    {
        return "SELECT p.id, p.sku, p.name, p.slug, p.theme, p.brand, p.price_ore, p.regular_ore, p.source_url, p.plain_text,
              (SELECT path_m FROM product_images i WHERE i.product_id = p.id ORDER BY i.kind = 'sortre' DESC, i.sort, i.id LIMIT 1) img,
              (SELECT ROUND(AVG(rating), 1) FROM reviews r WHERE r.product_id = p.id) rating,
              (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) n_reviews
            FROM products p";
    }

    private static function find(int $id): ?array
    {
        $st = db()->prepare(self::productSql() . ' WHERE p.id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    private static function productSummary(array $p, bool $links = true): array
    {
        $out = ['id' => (int) $p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'brand' => $p['brand'], 'theme' => $p['theme'],
            'price' => ['amount' => (int) $p['price_ore'] / 100, 'regular' => $p['regular_ore'] === null ? null : (int) $p['regular_ore'] / 100,
                'currency' => 'NOK', 'includes_vat' => true],
            'image' => $p['img'] ? '/' . ltrim((string) $p['img'], '/') : null,
            'rating' => ['average' => $p['rating'] === null ? null : (float) $p['rating'], 'count' => (int) $p['n_reviews']]];
        if ($links) {
            $out['_links'] = self::hrefs(['self' => self::BASE . "/products/{$p['id']}"]);
        }
        return $out;
    }

    private static function themeBody(string $key, string $name, string $desc, int $n): array
    {
        return ['key' => $key, 'name' => $name, 'description' => $desc, 'product_count' => $n,
            '_links' => self::hrefs(['self' => self::BASE . "/themes/$key", 'products' => self::BASE . "/products?theme=$key"])];
    }

    private static function missing(int $id): array
    {
        return ['problem' => [404, 'Not found', "There is no product with ID $id."]];
    }

    /** @return array<string, array{href: string}> */
    private static function hrefs(array $links): array
    {
        return array_map(fn($h) => ['href' => $h], $links);
    }

    private static function iso(string $t): string
    {
        return (new \DateTimeImmutable($t, new \DateTimeZone('Europe/Oslo')))->format(DATE_ATOM);
    }

    /** A body with ETag and Cache-Control; a matching If-None-Match gives 304. */
    private static function cached(int $status, array $headers, string $body, string $ifNoneMatch): array
    {
        $etag = '"' . substr(hash('sha256', $body), 0, 32) . '"';
        $headers += ['ETag' => $etag, 'Cache-Control' => 'public, max-age=' . self::MAX_AGE, 'Vary' => 'Accept'];
        if ($ifNoneMatch !== '' && self::matches($ifNoneMatch, $etag)) {
            return ['status' => 304, 'headers' => $headers, 'body' => ''];
        }
        return ['status' => $status, 'headers' => $headers, 'body' => $body];
    }

    /** RFC 9457 problem details. The type points at the error section of the documentation. */
    private static function problem(int $status, string $title, string $detail, array $headers): array
    {
        $type = self::DOCS . '#error-' . $status;
        $body = json_encode(['type' => $type, 'title' => $title, 'status' => $status, 'detail' => $detail],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        return ['status' => $status, 'headers' => $headers + ['Content-Type' => 'application/problem+json; charset=utf-8', 'Cache-Control' => 'no-store'], 'body' => $body];
    }
}
