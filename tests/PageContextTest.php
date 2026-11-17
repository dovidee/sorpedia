<?php
declare(strict_types=1);

// What the chat gets from the page (feature 015): a fixed list of public pages, the same hits as the search page,
// and a product guide summary that follows until the customer starts a new conversation.

$slug = fn() => (string) db()->query('SELECT slug FROM products ORDER BY id LIMIT 1')->fetchColumn();

return [
    'private pages give no context' => function () {
        foreach (['/', '/logg-inn', '/handlekurv', '/kasse', '/innstillinger', '/ordrer', '/support', '/admin', '/innboks'] as $url) {
            $p = PageContext::resolve($url);
            check($p['kind'] === 'ingen' && $p['ids'] === [] && $p['text'] === '', "$url gave context {$p['kind']}");
        }
    },

    'a product page gives that product and its description' => function () use ($slug) {
        $p = PageContext::resolve('/produkt/' . $slug());
        $id = (int) db()->query('SELECT id FROM products WHERE slug = ' . db()->quote($slug()))->fetchColumn();
        check($p['kind'] === 'produkt' && $p['ids'] === [$id], 'wrong product');
    },

    'the search page context is the first hits the customer sees' => function () {
        foreach (['hammer', 'lommelykt', 'maling'] as $q) {
            $p = PageContext::resolve('/sok?q=' . rawurlencode($q));
            check($p['ids'] === array_slice(Search::run($q)['ids'], 0, 4), "\"$q\" differs from the search page");
        }
    },

    'the chat route reads only the new message from the request' => function () {
        $src = (string) file_get_contents(__DIR__ . '/../public/index.php');
        check(!preg_match('/\$in\[[\'"](?:messages|log|history)[\'"]\]/', $src, $m), 'route reads ' . ($m[0] ?? ''));
    },

    'account details never reach the prompt' => function () {
        $u = db()->query("SELECT id, email, name, phone FROM users WHERE email = 'kunde@sorpedia.test'")->fetch();
        $saved = $_SESSION;
        try {
            $_SESSION = ['uid' => (int) $u['id']];
            foreach (['/innstillinger', '/ordrer', '/handlekurv'] as $url) {
                $page = PageContext::resolve($url);
                $out = json_encode(Chat::prompt([['role' => 'user', 'content' => 'Hei']], $page, Chat::facts($page['ids'])), JSON_UNESCAPED_UNICODE);
                foreach (array_filter([$u['email'], $u['name'], $u['phone']]) as $private) {
                    check(!str_contains($out, (string) $private), "$url put account details in the prompt");
                }
            }
        } finally {
            $_SESSION = $saved;
        }
    },

    'the guide follows for three hours, until a new conversation' => function () {
        $saved = $_SESSION;
        try {
            $_SESSION = [];
            $now = time();
            check(ChatHistory::carriesGuide(['a' => [], 'at' => $now - 60], $now), 'recent guide dropped');
            check(!ChatHistory::carriesGuide(['a' => [], 'at' => $now - 3 * 3600 - 1], $now), 'old guide kept');
            check(!ChatHistory::carriesGuide(null, $now), 'no guide carried');
            $_SESSION['chat_fresh_at'] = $now - 30;
            check(!ChatHistory::carriesGuide(['a' => [], 'at' => $now - 60], $now), 'guide kept after a new conversation');
            check(ChatHistory::carriesGuide(['a' => [], 'at' => $now - 10], $now), 'guide taken after the new conversation dropped');
        } finally {
            $_SESSION = $saved;
        }
    },
];
