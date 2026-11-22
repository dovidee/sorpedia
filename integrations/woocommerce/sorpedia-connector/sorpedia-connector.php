<?php
/**
 * Plugin Name:       Sørpedia Connector
 * Description:       Shows specifications with source, related products with level and a product assistant on WooCommerce product pages, read from the Sørpedia API.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * License:           GPL-2.0-or-later
 * Text Domain:       sorpedia-connector
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const SORPEDIA_OPTION = 'sorpedia_api_base';
const SORPEDIA_CACHE_S = 300;
/** Questions per visitor (hashed IP) per 10 minutes, checked here before the shop server forwards them. */
const SORPEDIA_ASK_LIMIT = 5;

/** The API base, for example https://guide.example.no/api/v1, without a trailing slash. Empty when not set. */
function sorpedia_base(): string
{
    return rtrim((string) get_option(SORPEDIA_OPTION, ''), '/');
}

/**
 * GET from the API with a short timeout. Answers are cached for five minutes, failures for one, so a slow API never
 * slows every page view. Returns the decoded body or null.
 */
function sorpedia_get(string $path): ?array
{
    $base = sorpedia_base();
    if ($base === '') {
        return null;
    }
    $key = 'sorpedia_' . md5($base . $path);
    $hit = get_transient($key);
    if (is_array($hit)) {
        return $hit['ok'] ? $hit['body'] : null;
    }
    $res = wp_remote_get($base . $path, ['timeout' => 4, 'headers' => ['Accept' => 'application/json']]);
    $ok = !is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200;
    $body = $ok ? json_decode(wp_remote_retrieve_body($res), true) : null;
    $ok = $ok && is_array($body);
    set_transient($key, ['ok' => $ok, 'body' => $body], $ok ? SORPEDIA_CACHE_S : 60);
    return $ok ? $body : null;
}

/** Label for where a value comes from. Only the verified value from the store or staff is called confirmed. */
function sorpedia_source_label(array $v): string
{
    if (!empty($v['confirmed'])) {
        return $v['source'] === 'staff' ? 'Godkjent av ansatt' : 'Bekreftet';
    }
    return match ($v['source'] ?? '') {
        'derived' => 'Utledet fra produkttekst',
        'buyer' => 'Fra kjøper',
        'staff' => 'Godkjent av ansatt',
        default => 'Ikke bekreftet',
    };
}

/** Text for how sure a relation is. Assumed is never presented as certain. */
function sorpedia_level_label(string $level): string
{
    return match ($level) {
        'documented' => 'Dokumentert',
        'rule_based' => 'Regelbasert',
        default => 'Antatt, ikke bekreftet',
    };
}

// ---------- Settings ----------

add_action('admin_init', function (): void {
    register_setting('sorpedia', SORPEDIA_OPTION, ['type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '']);
});

add_action('admin_menu', function (): void {
    add_options_page('Sørpedia', 'Sørpedia', 'manage_options', 'sorpedia', function (): void {
        $ping = sorpedia_base() !== '' ? sorpedia_get('') : null;
        ?>
        <div class="wrap">
            <h1>Sørpedia</h1>
            <form method="post" action="options.php">
                <?php settings_fields('sorpedia'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="sorpedia-base">API-adresse</label></th>
                        <td>
                            <input id="sorpedia-base" name="<?php echo esc_attr(SORPEDIA_OPTION); ?>" type="url" class="regular-text"
                                value="<?php echo esc_attr(sorpedia_base()); ?>" placeholder="https://guide.example.no/api/v1">
                            <p class="description">Adressen til Sørpedia sitt API, med /api/v1 til slutt.</p>
                            <?php if (sorpedia_base() !== '') : ?>
                                <p><?php echo $ping ? 'Tilkoblet: ' . esc_html((string) ($ping['name'] ?? 'API')) : 'Får ikke svar fra API-et.'; ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Lagre'); ?>
            </form>
        </div>
        <?php
    });
});

// ---------- Product page: specifications with source ----------

add_filter('woocommerce_product_tabs', function (array $tabs): array {
    global $product;
    if (!$product instanceof WC_Product || !sorpedia_get('/products/' . $product->get_id() . '/specifications')) {
        return $tabs;
    }
    $tabs['sorpedia_fakta'] = ['title' => 'Fakta og kilder', 'priority' => 25, 'callback' => 'sorpedia_render_specs'];
    return $tabs;
});

function sorpedia_render_specs(): void
{
    global $product;
    $data = sorpedia_get('/products/' . $product->get_id() . '/specifications');
    if (!$data) {
        return;
    }
    echo '<h2>Fakta og kilder</h2><table class="sorpedia-specs shop_attributes"><tbody>';
    foreach ($data['items'] ?? [] as $s) {
        $v = $s[$s['effective'] ?? ''] ?? null;
        if (!is_array($v)) {
            continue;
        }
        printf('<tr><th>%s</th><td>%s%s <span class="sorpedia-badge%s">%s</span></td></tr>',
            esc_html((string) $s['label']), esc_html((string) $v['value']), $s['unit'] ? ' ' . esc_html((string) $s['unit']) : '',
            empty($v['confirmed']) ? ' is-open' : '', esc_html(sorpedia_source_label($v)));
    }
    echo '</tbody></table>';
    $unknown = array_map(fn($u) => (string) $u['label'], $data['unknown'] ?? []);
    if ($unknown) {
        echo '<p class="sorpedia-unknown">Ukjent for dette produktet: ' . esc_html(implode(', ', $unknown)) . '.</p>';
    }
    echo '<p class="sorpedia-note">Bekreftet betyr oppgitt av butikken eller godkjent av ansatt. Andre verdier er merket med kilden.</p>';
}

// ---------- Product page: goes well with ----------

add_action('woocommerce_after_single_product_summary', function (): void {
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }
    $data = sorpedia_get('/products/' . $product->get_id() . '/relations?kind=goes_with');
    $items = array_slice($data['items'] ?? [], 0, 6);
    if (!$items) {
        return;
    }
    echo '<section class="sorpedia-related"><h2>Passer sammen med</h2><ul>';
    foreach ($items as $r) {
        $id = (int) ($r['product']['id'] ?? 0);
        $other = $id ? wc_get_product($id) : null;
        // Name and price from the shop's own product, which shares the ID; the API name only when the shop lacks it
        $name = $other ? $other->get_name() : (string) ($r['product']['name'] ?? '');
        $link = $other && $other->is_visible() ? get_permalink($id) : '';
        printf('<li>%s%s <span class="sorpedia-badge%s">%s</span><br><small>%s</small></li>',
            $link ? '<a href="' . esc_url($link) . '">' . esc_html($name) . '</a>' : esc_html($name),
            $other ? ' ' . wp_kses_post($other->get_price_html()) : '',
            empty($r['certain']) ? ' is-open' : '', esc_html(sorpedia_level_label((string) $r['level'])), esc_html((string) $r['reason']));
    }
    echo '</ul></section>';
}, 15);

// ---------- Product page: ask the assistant ----------

add_action('woocommerce_after_single_product_summary', function (): void {
    global $product;
    if (!$product instanceof WC_Product || !sorpedia_get('')) {
        return; // not set up, or the API does not answer (cached, so this costs no extra call per view)
    }
    ?>
    <section class="sorpedia-ask" data-product="<?php echo esc_attr((string) $product->get_id()); ?>">
        <h2>Spør Søren om produktet</h2>
        <form>
            <label for="sorpedia-q">Spørsmål</label>
            <textarea id="sorpedia-q" maxlength="500" rows="2" required placeholder="Hvilke batterier passer?"></textarea>
            <button type="submit" class="button">Spør</button>
        </form>
        <div class="sorpedia-answer" aria-live="polite"></div>
        <p class="sorpedia-note">Svarene lages av en språkmodell og kan ta litt tid. Pris og lager er alltid butikkens egne.</p>
    </section>
    <?php
}, 16);

add_action('wp_enqueue_scripts', function (): void {
    if (!is_product() || sorpedia_base() === '') {
        return;
    }
    wp_register_script('sorpedia-ask', '', [], '1.0.0', true);
    wp_enqueue_script('sorpedia-ask');
    wp_add_inline_script('sorpedia-ask', 'window.sorpediaAsk = ' . wp_json_encode(['url' => esc_url_raw(rest_url('sorpedia/v1/ask')), 'nonce' => wp_create_nonce('wp_rest')]) . ';' . <<<'JS'
document.addEventListener('submit', async (e) => {
  const box = e.target.closest('.sorpedia-ask');
  if (!box) return;
  e.preventDefault();
  const q = box.querySelector('textarea'), out = box.querySelector('.sorpedia-answer'), btn = box.querySelector('button');
  if (!q.value.trim()) return;
  btn.disabled = true;
  out.textContent = 'Søren tenker ...';
  try {
    const r = await fetch(window.sorpediaAsk.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.sorpediaAsk.nonce },
      body: JSON.stringify({ product: Number(box.dataset.product), question: q.value.trim() }) });
    const d = await r.json();
    out.replaceChildren();
    const p = document.createElement('p');
    p.textContent = r.ok ? d.answer : (d.message || 'Søren kan ikke svare akkurat nå.');
    out.append(p);
    if (r.ok && d.reasoning) {
      const det = document.createElement('details'), sum = document.createElement('summary'), pre = document.createElement('p');
      sum.textContent = 'Slik tenkte Søren';
      pre.textContent = d.reasoning;
      det.append(sum, pre);
      out.append(det);
    }
    if (r.ok && d.notice) { const n = document.createElement('small'); n.textContent = d.notice; out.append(n); }
    if (r.ok && d.products?.length) {
      const ul = document.createElement('ul');
      for (const it of d.products) {
        const li = document.createElement('li');
        if (it.url) { const a = document.createElement('a'); a.href = it.url; a.textContent = it.name; li.append(a); } else li.textContent = it.name;
        ul.append(li);
      }
      out.append(ul);
    }
  } catch {
    out.textContent = 'Søren kan ikke svare akkurat nå.';
  }
  btn.disabled = false;
});
JS);
    wp_register_style('sorpedia', false, [], '1.0.0');
    wp_enqueue_style('sorpedia');
    wp_add_inline_style('sorpedia', '.sorpedia-badge{display:inline-block;margin-left:.4em;padding:0 .4em;font-size:.75em;border:1px solid currentColor}'
        . '.sorpedia-badge.is-open{opacity:.75;border-style:dashed}.sorpedia-note,.sorpedia-unknown{font-size:.85em;opacity:.8}'
        . '.sorpedia-ask textarea{width:100%}.sorpedia-ask label{display:block;font-weight:600}.sorpedia-answer details{margin-top:.5em}');
});

/**
 * The shop forwards questions to the API, so the browser never calls the guide or the language model directly.
 * Product names in the answer get the shop's own links.
 */
add_action('rest_api_init', function (): void {
    register_rest_route('sorpedia/v1', '/ask', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'args' => [
            'product' => ['type' => 'integer', 'required' => true, 'minimum' => 1],
            'question' => ['type' => 'string', 'required' => true, 'minLength' => 1, 'maxLength' => 500],
        ],
        'callback' => function (WP_REST_Request $req) {
            $base = sorpedia_base();
            if ($base === '') {
                return new WP_Error('sorpedia_off', 'Søren er ikke satt opp.', ['status' => 503]);
            }
            $visitor = 'sorpedia_ask_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            $count = (int) get_transient($visitor);
            if ($count >= SORPEDIA_ASK_LIMIT) {
                return new WP_Error('sorpedia_limit', 'Du har stilt mange spørsmål på kort tid. Prøv igjen om noen minutter.', ['status' => 429]);
            }
            set_transient($visitor, $count + 1, 10 * MINUTE_IN_SECONDS);
            $res = wp_remote_post($base . '/products/' . (int) $req['product'] . '/questions', [
                'timeout' => 120,
                'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body' => wp_json_encode(['question' => (string) $req['question']]),
            ]);
            $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
            $body = $code ? json_decode(wp_remote_retrieve_body($res), true) : null;
            if ($code === 404) {
                return new WP_Error('sorpedia_unknown', 'Søren kjenner ikke dette produktet.', ['status' => 404]);
            }
            if ($code !== 200 || !is_array($body)) {
                return new WP_Error('sorpedia_down', $code === 429 ? 'Søren har mange spørsmål akkurat nå. Prøv igjen litt senere.' : 'Søren kan ikke svare akkurat nå.',
                    ['status' => $code === 429 ? 429 : 502]);
            }
            $products = array_map(function ($p) {
                $id = (int) ($p['id'] ?? 0);
                $own = $id ? wc_get_product($id) : null;
                return ['name' => $own ? $own->get_name() : (string) ($p['name'] ?? ''), 'url' => $own && $own->is_visible() ? get_permalink($id) : null];
            }, $body['products'] ?? []);
            return ['answer' => (string) ($body['answer'] ?? ''), 'reasoning' => $body['reasoning'] ?? null, 'products' => $products,
                // The API's notice is for developers; visitors get a plain Norwegian note when the model did not answer
                'notice' => empty($body['model']['used']) ? 'Dette svaret kommer fra butikkens egne regler, ikke fra språkmodellen.' : null];
        },
    ]);
});
