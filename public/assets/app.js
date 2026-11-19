// Sørpedia client code. No framework, no build step.
const $ = (s, el = document) => el.querySelector(s);
const $$ = (s, el = document) => [...el.querySelectorAll(s)];
const CSRF = document.querySelector('meta[name=csrf]')?.content;
// Text in the customer's language. The word list (src/I18nEn.php) is written into the page when the language is English.
const tr = (s, v = {}) => (window.I18N?.[s] ?? s).replace(/\{(\w+)\}/g, (m, k) => (k in v ? v[k] : m));
const LANG = document.querySelector('meta[name=lang]')?.content || 'no';
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const store = {
  get(k, d, s = localStorage) { try { return JSON.parse(s.getItem(k)) ?? d; } catch { return d; } },
  set(k, v, s = localStorage) { try { s.setItem(k, JSON.stringify(v)); } catch {} },
  del(k, s = localStorage) { try { s.removeItem(k); } catch {} },
};

// ---------- Search: inline autocomplete (grey suggestion, Tab accepts) and earlier searches ----------
(() => {
  const q = $('#q'), box = $('#suggest'), ghost = $('#ghost');
  if (!q) return;
  const RECENT = 'sorpedia-searches';
  let vocab = null, rest = '', timer, ctrl, idx = -1;
  const recent = () => store.get(RECENT, []);
  const remember = (v) => { v = v.trim().toLowerCase(); if (v.length >= 2) store.set(RECENT, [v, ...recent().filter((x) => x !== v)].slice(0, 12)); };

  const loadVocab = async () => {
    if (vocab) return vocab;
    vocab = store.get('sorpedia-vocab-v1', null, sessionStorage);
    if (vocab) return vocab;
    try {
      vocab = await fetch('/api/vocab').then((r) => r.json());
      store.set('sorpedia-vocab-v1', vocab, sessionStorage);
    } catch { vocab = { words: [], next: {}, phrases: [] }; }
    return vocab;
  };

  // What should be grey after the cursor? Earlier searches first, then known phrases, then words from the catalog.
  const complete = (v) => {
    const lv = v.toLowerCase();
    if (!lv.trim() || !vocab) return '';
    const pick = (list) => list.find((x) => x.startsWith(lv) && x.length > lv.length);
    const hit = pick(recent()) || pick(vocab.phrases);
    if (hit) return hit.slice(lv.length);
    const toks = lv.split(/\s+/);
    const last = toks[toks.length - 1];
    if (last === '') { // after a space: the next word that usually follows
      const prev = toks[toks.length - 2];
      return vocab.next[prev]?.[0] || '';
    }
    if (last.length >= 2) {
      const w = vocab.words.find(([x]) => x.startsWith(last) && x !== last);
      if (w) return w[0].slice(last.length);
      if (vocab.next[last]?.[0]) return ' ' + vocab.next[last][0]; // whole word: suggest the next one, so Tab can be chained
    }
    return '';
  };

  const drawGhost = () => {
    const atEnd = q.selectionStart === q.value.length && q.selectionEnd === q.value.length;
    rest = document.activeElement === q && atEnd && q.scrollWidth <= q.clientWidth + 1 ? complete(q.value) : '';
    ghost.innerHTML = rest ? `${esc(q.value)}<i>${esc(rest)}</i><kbd>Tab</kbd>` : '';
  };
  const accept = () => {
    if (!rest) return false;
    q.value += rest; rest = '';
    q.setSelectionRange(q.value.length, q.value.length);
    q.dispatchEvent(new Event('input'));
    return true;
  };

  const close = () => { box.hidden = true; idx = -1; };
  const showRecent = () => {
    const list = recent();
    if (!list.length) return close();
    box.innerHTML = `<div class="label">${tr('Tidligere søk')}</div>` + list.slice(0, 6).map((r) =>
      `<div class="recent"><a href="/sok?q=${encodeURIComponent(r)}">${esc(r)}</a><button type="button" data-forget="${esc(r)}" aria-label="${esc(tr('Fjern {x}', { x: r }))}">×</button></div>`).join('');
    box.hidden = false; idx = -1;
  };
  box.addEventListener('click', (e) => {
    const f = e.target.closest('[data-forget]');
    if (f) { e.preventDefault(); store.set(RECENT, recent().filter((x) => x !== f.dataset.forget)); showRecent(); q.focus(); return; }
    if (e.target.closest('a')) remember(q.value);
  });

  q.addEventListener('focus', () => { loadVocab().then(drawGhost); if (!q.value.trim()) showRecent(); });
  q.addEventListener('blur', () => { ghost.innerHTML = ''; rest = ''; });
  q.addEventListener('click', drawGhost);
  q.addEventListener('input', () => {
    drawGhost();
    clearTimeout(timer);
    if (!q.value.trim()) return showRecent();
    if (q.value.trim().length < 2) return close();
    timer = setTimeout(async () => {
      ctrl?.abort(); ctrl = new AbortController();
      try {
        const r = await fetch('/api/suggest?q=' + encodeURIComponent(q.value), { signal: ctrl.signal }).then((r) => r.json());
        let html = '';
        const lv = q.value.trim().toLowerCase();
        const past = recent().filter((x) => x.startsWith(lv) && x !== lv).slice(0, 3);
        if (past.length) html += `<div class="label">${tr('Tidligere søk')}</div>` + past.map((x) => `<a href="/sok?q=${encodeURIComponent(x)}">${esc(x)}</a>`).join('');
        if (r.corrected) html += `<div class="hint">${tr('Mente du <b>{x}</b>?', { x: esc(r.corrected) })}</div>`;
        if (r.usecases?.length) html += `<div class="hint">Oppgave: ${esc(r.usecases.join(', '))}</div>`;
        for (const t of r.themes) html += `<a href="${t.url}"><b>${tr('Se alt i {theme}', { theme: esc(t.name) })}</b></a>`;
        for (const p of r.products) html += `<a href="${p.url}">${p.img ? `<img src="${p.img}" alt="">` : ''}<span>${esc(p.name)}</span><span class="sp">${esc(p.price)}</span></a>`;
        if (!r.products.length && !r.themes.length && !past.length) html += `<div class="hint">${tr('Ingen treff. Trykk Enter for å søke, eller spør Søren.')}</div>`;
        box.innerHTML = html; box.hidden = false; idx = -1;
      } catch (e) { /* aborted */ }
    }, 150);
  });
  q.addEventListener('keydown', (e) => {
    if ((e.key === 'Tab' && !e.shiftKey) || (e.key === 'ArrowRight' && q.selectionStart === q.value.length)) {
      if (accept()) { e.preventDefault(); return; }
    }
    const links = $$('a', box);
    if (e.key === 'Escape') { rest = ''; ghost.innerHTML = ''; return close(); }
    if (box.hidden || !links.length) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      idx = (idx + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
      links.forEach((a, i) => a.classList.toggle('on', i === idx));
    } else if (e.key === 'Enter' && idx >= 0) { e.preventDefault(); remember(q.value); location = links[idx].href; }
  });
  q.addEventListener('keyup', (e) => { if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) drawGhost(); });
  q.form.addEventListener('submit', () => remember(q.value));
  document.addEventListener('click', (e) => { if (!e.target.closest('.search')) close(); });
})();

// ---------- Image gallery: click to zoom in, click again to zoom out ----------
(() => {
  const img = $('#main-img');
  if (!img) return;
  const stage = img.parentElement, btn = $('#zoom-btn');
  let zoomed = false;
  const aim = (x, y) => {
    const r = stage.getBoundingClientRect();
    img.style.transformOrigin = `${Math.min(100, Math.max(0, ((x - r.left) / r.width) * 100))}% ${Math.min(100, Math.max(0, ((y - r.top) / r.height) * 100))}%`;
  };
  const zoom = (on, x, y) => {
    zoomed = on;
    if (on && x !== undefined) aim(x, y); else if (on) img.style.transformOrigin = '50% 50%';
    img.style.transform = on ? 'scale(2.5)' : '';
    stage.classList.toggle('zoomed', on);
    if (btn) { btn.textContent = tr(on ? 'Zoom ut' : 'Zoom inn'); btn.setAttribute('aria-pressed', on); }
  };
  stage.addEventListener('click', (e) => {
    if (e.target === btn) return;
    zoom(!zoomed, e.clientX, e.clientY);
  });
  btn?.addEventListener('click', (e) => { e.stopPropagation(); zoom(!zoomed); });
  stage.addEventListener('mousemove', (e) => { if (zoomed) aim(e.clientX, e.clientY); });
  stage.addEventListener('touchmove', (e) => { if (zoomed) { e.preventDefault(); aim(e.touches[0].clientX, e.touches[0].clientY); } }, { passive: false });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && zoomed) zoom(false); });
  $$('.thumbs button').forEach((b) => b.addEventListener('click', () => {
    zoom(false);
    img.src = b.dataset.src; $('#img-kind').textContent = b.dataset.kind;
    $$('.thumbs button').forEach((x) => x.classList.toggle('on', x === b));
  }));
})();

// ---------- Filters: apply as soon as you click, no separate button ----------
(() => {
  const form = $('form[data-auto]');
  if (!form) return;
  const results = $('#results');
  const OPEN = 'sorpedia-facets:' + location.pathname;
  // Remember which groups the customer has opened or closed
  const saved = store.get(OPEN, null, sessionStorage);
  if (saved) $$('details.facet', form).forEach((d) => { if (!d.querySelector('.n-on')) d.open = saved.includes(d.dataset.key); });
  // Phones: groups without an active filter start closed, so the products are not pushed below a long form
  else if (matchMedia('(max-width: 899px)').matches) $$('details.facet', form).forEach((d) => { if (!d.querySelector('.n-on')) d.open = false; });
  form.addEventListener('toggle', () => store.set(OPEN, $$('details.facet', form).filter((d) => d.open).map((d) => d.dataset.key), sessionStorage), true);

  let ctrl, timer;
  const apply = async () => {
    const params = new URLSearchParams();
    for (const [k, v] of new FormData(form)) if (String(v).trim() !== '' && !(k === 'sort' && v === 'populaer')) params.append(k, v);
    const url = location.pathname + (params.toString() ? '?' + params : '');
    ctrl?.abort(); ctrl = new AbortController();
    results.classList.add('busy');
    try {
      const html = await fetch(url, { signal: ctrl.signal }).then((r) => r.text());
      const doc = new DOMParser().parseFromString(html, 'text/html');
      results.innerHTML = doc.getElementById('results').innerHTML;
      $('#result-count').textContent = doc.getElementById('result-count').textContent;
      $('#filters-reset').hidden = doc.getElementById('filters-reset').hidden;
      // Facet values and counts depend on the other filters, so refresh them. A text field being typed in keeps its node.
      const active = document.activeElement;
      const focus = active && form.contains(active) && active.type === 'checkbox' ? [active.name, active.value] : null;
      $$('details.facet', form).forEach((d) => {
        const n = doc.querySelector(`details.facet[data-key="${CSS.escape(d.dataset.key)}"]`);
        if (!n) return;
        d.hidden = n.hidden;
        if (d.contains(active) && active.type !== 'checkbox') d.querySelector('summary').innerHTML = n.querySelector('summary').innerHTML;
        else d.innerHTML = n.innerHTML;
      });
      if (focus) $$('input[type=checkbox]', form).find((i) => i.name === focus[0] && i.value === focus[1])?.focus();
      history.replaceState(null, '', url);
      document.dispatchEvent(new Event('sorpedia:content'));
    } catch (e) { if (e.name !== 'AbortError') form.submit(); }
    results.classList.remove('busy');
  };
  form.addEventListener('change', (e) => { if (e.target.type === 'checkbox') apply(); else { clearTimeout(timer); apply(); } });
  form.addEventListener('input', (e) => { if (e.target.type !== 'checkbox') { clearTimeout(timer); timer = setTimeout(apply, 650); } });
  form.addEventListener('submit', (e) => { e.preventDefault(); clearTimeout(timer); apply(); });
})();

// ---------- Product guide: one question at a time, the next question depends on the answer ----------
(() => {
  const form = $('#quiz');
  if (!form) return;
  form.addEventListener('change', () => setTimeout(() => form.submit(), 150));
})();

// ---------- Demo users: click to fill in ----------
$$('.demo-users tr').forEach((tr) => tr.addEventListener('click', () => {
  $('input[name=email]').value = tr.dataset.email; $('input[name=password]').value = 'admin'; $('input[name=password]').focus();
}));

// ---------- Compare (stored in the browser) ----------
(() => {
  const bar = $('#compare-bar');
  const KEY = 'sorpedia-compare';
  const load = () => store.get(KEY, []);
  const save = (v) => store.set(KEY, v);
  const draw = () => {
    const list = load();
    $$('[data-compare]').forEach((c) => { c.checked = list.some((x) => x.id === c.dataset.compare); });
    if (!bar) return;
    bar.hidden = !list.length;
    bar.innerHTML = `<span>${tr('Sammenlign')} (${list.length}/4): ${list.map((x) => esc(x.name.slice(0, 28))).join(', ')}</span>`
      + (list.length >= 2 ? `<a class="btn small" href="/sammenlign?ids=${list.map((x) => x.id).join(',')}">${tr('Sammenlign')}</a>` : `<span>${tr('Velg minst to')}</span>`)
      + `<button class="link" style="color:inherit" data-clear>${tr('Tøm')}</button>`;
    $('[data-clear]', bar)?.addEventListener('click', () => { save([]); draw(); });
  };
  document.addEventListener('change', (e) => {
    const c = e.target.closest('[data-compare]');
    if (!c) return;
    let list = load().filter((x) => x.id !== c.dataset.compare);
    if (c.checked) list = [...list, { id: c.dataset.compare, name: c.dataset.name }].slice(-4);
    save(list); draw();
  });
  document.addEventListener('sorpedia:content', draw);
  draw();
})();

// ---------- Add to cart without a page load ----------
document.addEventListener('submit', async (e) => {
  const f = e.target;
  if (!f.matches('form.add')) return;
  e.preventDefault();
  const r = await fetch(f.action, { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json' } });
  if (r.ok) {
    const d = await r.json();
    $('#cart-count').textContent = d.count;
    const b = f.querySelector('button'); const t = b.textContent;
    b.textContent = tr('Lagt i kurv') + ' ✓'; setTimeout(() => (b.textContent = t), 1400);
  } else f.submit();
});

// ---------- Søren chat ----------
// The server owns the conversation (src/ChatHistory.php). The browser keeps only what it shows, so the panel survives
// page changes. Each message sends the new text and the address of the page you are on; the server adds the earlier
// messages and decides what Søren gets to see (only public product pages, never account, cart, orders or login).
// "Ny samtale" (new conversation), a language switch and logout start a new conversation on the server too.
window.Soren = (() => {
  const KEY = 'sorpedia-chat-v3';
  const pageLabel = () => document.querySelector('meta[name=soren-page]')?.content || '';
  const pageUrl = () => location.pathname + location.search;
  let st = store.get(KEY, null) || { open: false, items: [], ticket: null, since: 0, ctx: null, lang: LANG };
  // Switched language with the flag: new conversation in the new language
  if ((st.lang || 'no') !== LANG) st = { open: st.open, items: [], ticket: null, since: 0, ctx: null, lang: LANG, fresh: true };
  let panel, log, es, busy = false;
  let mascot = { set() {}, wave() {}, gust() {}, happy() {}, sad() {}, surprised() {}, state: 'idle' };

  // Cards carry only name, price and stock from the database, so they are labelled as coming from the catalogue
  const cardsHtml = (cards) => cards?.length ? `<div class="chat-cards"><span class="chat-k">${tr('Fra produktkatalogen')}</span>` + cards.map((c) =>
    `<a href="${esc(c.url)}">${c.img ? `<img src="${esc(c.img)}" alt="">` : ''}<span><span class="cn">${esc(c.name)}</span><span class="cm"><b>${esc(c.price)}</b><span>${esc(c.stock)}</span>${c.manual ? `<span>${tr('Veiledning')}</span>` : ''}</span></span></a>`).join('') + '</div>' : '';
  const html = (it) => {
    if (it.t !== 'bot') return it.t === 'support' ? `<b>${esc(it.name)} (${tr('kundeservice')}):</b> ${esc(it.text)}` : esc(it.text);
    const think = it.think || it.note
      ? `<details class="think"${it.pending && !it.text ? ' open' : ''}><summary>${tr('Sørens resonnement (generert, kan være feil)')}</summary>${it.note ? `<span class="note">${esc(it.note)}</span>` : ''}${esc(it.think)}${it.cut ? `<span class="note cut">${esc(it.cut)}</span>` : ''}</details>` : '';
    // Quotes around the whole answer are removed
    const text = it.pending ? it.text : it.text.replace(/^\s*[“"«„]+/u, '').replace(/[”"»]+\s*$/u, '');
    const body = it.text ? esc(text) : (it.wait ? `<span class="muted">${esc(it.wait)}</span>` : (it.pending ? `<span class="muted">${tr('Søren tenker …')}</span>` : ''));
    return think + body + cardsHtml(it.cards);
  };
  // Søren stands next to his answers as a small head. Only the newest head is alive (thinks, blinks),
  // the older ones stand still, so the chat doesn't fill up with movement.
  const NOOP = { set() {}, wave() {}, gust() {}, happy() {}, sad() {}, surprised() {}, state: 'idle' };
  let liveIt = null;
  const liveAvatar = (it) => {
    if (!window.Soren2D) return;
    if (liveIt && liveIt !== it && liveIt.av) window.Soren2D.mount(liveIt.av, { kind: 'head', still: true });
    liveIt = it;
    mascot = window.Soren2D.mount(it.av, { kind: 'head' });
  };
  const draw = (it) => {
    if (!it.el) {
      it.el = document.createElement('div'); log.append(it.el);
      if (it.t === 'bot') {
        it.el.className = 'row';
        it.el.innerHTML = '<div class="av" aria-hidden="true"></div><div class="msg bot"></div>';
        it.av = it.el.firstChild; it.box = it.el.lastChild;
        liveAvatar(it);
      }
    }
    if (it.box) it.box.innerHTML = html(it);
    else { it.el.className = 'msg ' + (it.t === 'support' ? 'bot' : it.t); it.el.innerHTML = html(it); }
    log.scrollTop = log.scrollHeight;
  };
  const push = (it) => { st.items.push(it); if (log) draw(it); persist(); return it; };
  // el is a DOM node and must not be stored
  const plain = () => ({ ...st, items: st.items.slice(-60).map(({ el, av, box, ...rest }) => rest) });
  const persist = () => store.set(KEY, plain());

  const intro = () => {
    push({ t: 'bot', text: tr('Hei, jeg er Søren! Jeg kan finne produkter, forklare hva som passer sammen og svare på spørsmål om spesifikasjoner. Priser og lager henter jeg alltid fra butikken, ikke fra hukommelsen.') });
    push({ t: 'sys', text: tr('Søren er en språkmodell som kjører lokalt på denne maskinen. Han ser offentlig produktinfo fra siden du er på. På en ordre eller i Innstillinger ser han også ordrene dine, men aldri navn, e-post, telefon, adresse eller passord. Det han tenker er generert og kan være feil.') });
  };
  // While an answer streams the log is marked busy, so screen readers read the finished answer once instead of every token
  const status = (s) => { const el = $('.chat-status', panel); if (el) el.textContent = s; log?.setAttribute('aria-busy', s ? 'true' : 'false'); };
  // Phones: the panel follows the visible viewport, so the on-screen keyboard never covers the message field
  const phone = matchMedia('(max-width: 767px)');
  const fit = () => {
    if (!panel || panel.hidden) return;
    const vv = window.visualViewport;
    panel.style.top = phone.matches && vv ? vv.offsetTop + 'px' : '';
    panel.style.height = phone.matches && vv ? vv.height + 'px' : '';
    if (log) log.scrollTop = log.scrollHeight;
  };
  window.visualViewport?.addEventListener('resize', fit);
  window.visualViewport?.addEventListener('scroll', fit);
  // What Søren gets from this page. The explanation opens on tap, since a hover title never shows on a phone.
  const chip = () => {
    const c = $('.page-chip', panel), label = pageLabel();
    const own = document.querySelector('meta[name=soren-scope]')?.content === 'konto';
    c.hidden = !label;
    c.innerHTML = `<span>${tr('Søren ser:')}</span> <b>${esc(label)}</b><button type="button" class="lock" aria-expanded="false" aria-controls="soren-scope">${own ? tr('dine ordrer') : tr('bare produktinfo')}</button>`
      + `<p class="scope" id="soren-scope" hidden>${own
        ? tr('Søren ser varene, datoen, status, betalingsmåten og angrefristen, så han kan svare på spørsmål om ordren. Han ser aldri navn, e-post, telefon, adresse, passord eller hva du betalte.')
        : tr('Søren får bare offentlig produktinfo fra denne siden. Han ser aldri passord, handlekurv, navn, e-post eller adresse.')}</p>`;
    const b = $('.lock', c), p = $('.scope', c);
    b.onclick = () => { p.hidden = !p.hidden; b.setAttribute('aria-expanded', String(!p.hidden)); };
  };
  // Remember the last page (the field above the message box shows what Søren sees)
  const noteContext = () => {
    const label = pageLabel();
    st.ctx = label || st.ctx; persist();
  };

  const build = () => {
    panel = document.createElement('section');
    panel.className = 'chat'; panel.hidden = true;
    panel.setAttribute('aria-label', tr('Chat med Søren'));
    panel.innerHTML = `<header>
        <div class="chat-title"><b>Søren</b><span class="chat-sys"><span class="sys-t">${tr('KI-assistent, kjører lokalt')}</span><span class="chat-status" aria-live="polite"></span></span></div>
        <div class="chat-btns"><button class="new" type="button" title="${tr('Start en ny samtale')}">${tr('Ny samtale')}</button><button class="x" type="button" aria-label="${tr('Lukk')}">×</button></div></header>
      <div class="log" role="log"></div>
      <div class="page-chip" hidden></div>
      <form><label class="sr" for="soren-m">${tr('Spør Søren')}</label>
        <div class="ask"><input id="soren-m" name="m" placeholder="${tr('Spør om produkter, mål eller hva som passer')}" autocomplete="off"><button class="send" aria-label="${tr('Send')}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg></button></div></form>
      <p class="human"><a href="#" data-human>${tr('Snakk med et menneske i stedet')}</a></p>`;
    document.body.append(panel);
    log = $('.log', panel);
    $('.x', panel).onclick = close;
    $('.new', panel).onclick = reset;
    $('form', panel).onsubmit = (e) => {
      e.preventDefault(); const i = $('input', panel); const v = i.value.trim();
      if (!v || busy) return;
      i.value = ''; st.ticket ? toHuman(v) : send(v);
    };
    $('[data-human]', panel).onclick = (e) => { e.preventDefault(); human(); };
    if (!st.items.length) intro();
    st.items = st.items.filter((it) => it.t !== 'ctx');
    for (const it of st.items) {
      if (it.pending && !it.job) { it.pending = false; it.wait = ''; if (!it.text) it.text = tr('Svaret ble avbrutt fordi du byttet side. Spør gjerne igjen.'); }
      draw(it);
    }
    const unfinished = st.items.find((it) => it.pending && it.job);
    if (unfinished) resume(unfinished);
    if (st.ticket) listen();
    persist();
  };

  // Load the model into memory as soon as the chat opens (once per page)
  let warmed = false;
  const warm = () => { if (warmed) return; warmed = true; fetch('/api/chat/warm', { method: 'POST', headers: { 'X-CSRF': CSRF } }).catch(() => {}); };
  const open = (opts = {}) => {
    warm();
    if (!panel) build();
    const wasHidden = panel.hidden || panel.classList.contains('closing');
    panel.classList.remove('closing');
    panel.hidden = false;
    aim();
    document.body.classList.add('chat-open');
    st.open = true;
    chip(); noteContext();
    if (wasHidden && !opts.silent) { mascot.surprised(); mascot.wave(); }
    const i = $('input', panel);
    if (opts.ask && !i.value) { i.value = opts.ask; i.select(); }
    if (pageLabel()) i.placeholder = tr('Spør om {x}', { x: pageLabel() });
    if (!opts.silent) i.focus();
    fit();
    log.scrollTop = log.scrollHeight;
    persist();
  };
  // The panel grows out of Søren's head and is pulled back into it: the transform origin sits on the launcher
  const aim = () => {
    const f = $('#soren-open')?.getBoundingClientRect(); // offsetLeft/Top ignore the running transform
    if (f && f.width) panel.style.transformOrigin = `${f.left + f.width / 2 - panel.offsetLeft}px ${f.top + f.height / 2 - panel.offsetTop}px`;
  };
  const close = () => {
    const inside = panel.contains(document.activeElement);
    document.body.classList.remove('chat-open'); st.open = false; persist();
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) { panel.hidden = true; }
    else {
      aim(); panel.classList.add('closing');
      panel.addEventListener('animationend', () => { if (panel.classList.contains('closing')) { panel.hidden = true; panel.classList.remove('closing'); } }, { once: true });
    }
    if (inside) $('#soren-open')?.focus(); // focus goes back to the launcher
  };
  const reset = () => {
    es?.close(); es = null;
    st = { open: true, items: [], ticket: null, since: 0, ctx: null, lang: LANG, fresh: true };
    log.innerHTML = ''; liveIt = null; mascot = NOOP; intro(); noteContext(); mascot.gust(4);
  };

  // Reads a stream from Søren into the message it. it.seen counts the events shown, so a new page can keep reading
  // from the same place (GET /api/chat/{job}?from=seen) if the customer changes page in the middle of the answer.
  let leaving = false;
  addEventListener('pagehide', () => { leaving = true; });
  addEventListener('beforeunload', () => { leaving = true; });
  const consume = async (it, r) => {
    const reader = r.body.getReader(); const dec = new TextDecoder(); let buf = '';
    for (;;) {
      const { value, done } = await reader.read();
      if (done) break;
      buf += dec.decode(value, { stream: true });
      let i;
      while ((i = buf.indexOf('\n\n')) >= 0) {
        const chunk = buf.slice(0, i); buf = buf.slice(i + 2);
        const ev = /^event: (.+)$/m.exec(chunk)?.[1] || 'message';
        const data = JSON.parse(/^data: (.*)$/m.exec(chunk)?.[1] || 'null');
        if (ev === 'job') { it.job = data; it.seen = 0; persist(); continue; }
        if (ev === 'gone') return false;
        it.seen = (it.seen || 0) + 1;
        if (ev === 'think') { it.think += data; it.wait = ''; if (mascot.state === 'think') status(tr('tenker …')); }
        else if (ev === 'think_note') { it.note = data; it.think = ''; }
        else if (ev === 'token') { it.text += data; it.wait = ''; if (mascot.state !== 'talk') { mascot.set('talk'); status(tr('svarer …')); } }
        else if (ev === 'answer_reset') { it.text = ''; it.wait = data || ''; }
        else if (ev === 'products') it.cards = data;
        else if (ev === 'think_cut') it.cut = data;
        else if (ev === 'status') { it.wait = data; status(tr('våkner …')); }
        else if (ev === 'info') { push({ t: 'sys', text: data }); if (/katalogen|svarer ikke|catalogue|not responding/.test(data)) it.fellBack = true; }
        else if (ev === 'done') { draw(it); return true; }
        draw(it);
      }
    }
    return true;
  };
  const finish = (it) => {
    it.pending = false; it.wait = ''; draw(it);
    mascot.set('idle');
    it.fellBack ? mascot.sad() : mascot.happy();
    status(''); busy = false; persist();
  };
  const failed = (it) => {
    if (leaving) { persist(); return; } // the customer is changing page: the next page keeps reading, don't show an error
    it.pending = false; if (!it.text) it.text = tr('Beklager, noe gikk galt. Prøv igjen, eller snakk med et menneske.'); draw(it);
    mascot.set('idle'); mascot.sad(); status(''); busy = false; persist();
  };

  const send = async (text) => {
    busy = true;
    noteContext();
    push({ t: 'me', text });
    const it = push({ t: 'bot', text: '', think: '', note: '', cards: [], pending: true });
    mascot.set('think'); status(tr('tenker …'));
    try {
      const r = await fetch('/api/chat', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
        body: JSON.stringify({ message: text, page: pageUrl(), fresh: !!st.fresh }) });
      st.fresh = false; persist();
      if (!r.ok || !r.body) throw new Error('http ' + r.status);
      await consume(it, r);
      finish(it);
    } catch (e) { failed(it); }
  };

  // New page: keep reading the answer Søren was working on when the customer changed page
  const resume = async (it) => {
    busy = true; mascot.set(it.text ? 'talk' : 'think'); status(it.text ? tr('svarer …') : tr('tenker …'));
    try {
      const r = await fetch(`/api/chat/${encodeURIComponent(it.job)}?from=${it.seen || 0}`);
      if (!r.ok || !r.body) throw new Error('http ' + r.status);
      if ((await consume(it, r)) === false && !it.text) it.text = tr('Svaret ble avbrutt fordi du byttet side. Spør gjerne igjen.');
      finish(it);
    } catch (e) { failed(it); }
  };

  // ---------- On to customer service ----------
  const listen = () => {
    es?.close();
    es = new EventSource(`/api/stream?ch=ticket:${st.ticket}` + (st.since ? `&since=${st.since}` : ''));
    es.addEventListener('message', (e) => {
      st.since = +e.lastEventId || st.since;
      const d = JSON.parse(e.data);
      if (d.from === 'support') { push({ t: 'support', name: d.name, text: d.body }); mascot.wave(); }
      persist();
    });
    es.onerror = () => { if (es.readyState === EventSource.CLOSED) push({ t: 'sys', text: tr('Mistet kontakten med kundeservice.') }); };
  };
  // The case is tied to one product from the catalog. Søren does not search for it: the customer confirms the product
  // on the page, or picks one with the same suggestions as the search field.
  let picking = false;
  const pickProduct = () => new Promise((done) => {
    const box = document.createElement('div');
    box.className = 'msg bot pick';
    log.append(box);
    const finish = (p) => { box.remove(); picking = false; done(p); };
    const ask = (p) => {
      box.innerHTML = `<p>${tr('Gjelder saken dette produktet?')}</p><p><b>${esc(p.name)}</b></p>
        <div class="pick-btns"><button type="button" class="btn small" data-yes>${tr('Ja')}</button><button type="button" class="btn small ghost" data-other>${tr('Et annet produkt')}</button></div>`;
      $('[data-yes]', box).onclick = () => finish(p);
      $('[data-other]', box).onclick = search;
      log.scrollTop = log.scrollHeight;
    };
    const search = () => {
      box.innerHTML = `<label for="pick-q">${tr('Hvilket produkt gjelder saken? Velg fra listen.')}</label><input id="pick-q" autocomplete="off">
        <ul class="pick-list"></ul><button type="button" class="btn small ghost" data-cancel>${tr('Avbryt')}</button>`;
      const q = $('#pick-q', box), ul = $('.pick-list', box);
      let ctrl;
      q.oninput = async () => {
        ctrl?.abort();
        if (q.value.trim().length < 2) { ul.innerHTML = ''; return; }
        ctrl = new AbortController();
        const r = await fetch('/api/suggest?q=' + encodeURIComponent(q.value), { signal: ctrl.signal }).then((r) => r.json()).catch(() => null);
        if (r) ul.innerHTML = r.products.map((p) => `<li><button type="button" data-id="${+p.id}">${esc(p.name)}</button></li>`).join('');
      };
      ul.onclick = (e) => { const b = e.target.closest('button[data-id]'); if (b) ask({ id: +b.dataset.id, name: b.textContent }); };
      $('[data-cancel]', box).onclick = () => finish(null);
      q.focus();
      log.scrollTop = log.scrollHeight;
    };
    fetch('/api/support/produkt?page=' + encodeURIComponent(location.pathname)).then((r) => r.json()).catch(() => ({}))
      .then((c) => (c.product ? ask(c.product) : search()));
  });
  const human = async () => {
    if (st.ticket) return push({ t: 'sys', text: `Du er allerede koblet til kundeservice (sak #${st.ticket}).` });
    if (picking) return;
    picking = true;
    const p = await pickProduct();
    if (!p) return;
    const r = await fetch('/api/support', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
      body: JSON.stringify({ product: p.id }) }).then((r) => (r.ok ? r.json() : null)).catch(() => null);
    if (!r) return push({ t: 'sys', text: tr('Kunne ikke kontakte kundeservice.') });
    st.ticket = r.ticket;
    push({ t: 'sys', text: `Sak #${r.ticket} er opprettet. En fra kundeservice ser hele samtalen og svarer her.` });
    listen();
  };
  const toHuman = async (body) => {
    push({ t: 'me', text: body });
    await fetch(`/api/support/${st.ticket}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF }, body: JSON.stringify({ body }) });
  };

  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-open-soren], #soren-open');
    if (!t) return;
    e.preventDefault();
    if (t.id === 'soren-open' && panel && !panel.hidden && !panel.classList.contains('closing')) close(); else open({ ask: t.dataset.ask });
  });
  $('#soren-open')?.addEventListener('pointerenter', warm, { once: true });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && panel && !panel.hidden && panel.contains(document.activeElement)) close(); });
  // Logging out deletes the conversation, so the next person on the same machine doesn't see it
  document.addEventListener('submit', (e) => { if (e.target.matches('[data-logout]')) store.del(KEY); }, true);
  addEventListener('pagehide', persist);
  if (st.open) open({ silent: true });
  return { open };
})();

// ---------- Help menu in the category bar: a native popover placed under its button ----------
// The bar scrolls sideways on small screens, so the menu lives in the top layer instead of inside the bar.
(() => {
  const pop = document.getElementById('help-menu');
  const btn = document.querySelector('[popovertarget="help-menu"]');
  if (!pop || !btn || !pop.showPopover) return;
  btn.setAttribute('aria-expanded', 'false');
  const close = () => pop.hidePopover();
  pop.addEventListener('toggle', (e) => {
    const open = e.newState === 'open';
    btn.setAttribute('aria-expanded', String(open));
    if (!open) return removeEventListener('scroll', close);
    const r = btn.getBoundingClientRect();
    pop.style.top = `${r.bottom + 6}px`;
    pop.style.left = `${Math.max(8, Math.min(r.left, innerWidth - pop.offsetWidth - 8))}px`;
    addEventListener('scroll', close, { passive: true, once: true });
  });
})();

// ---------- Account menu: closes on click outside and with Escape ----------
(() => {
  const menus = () => document.querySelectorAll('details[data-menu][open]');
  document.addEventListener('click', (e) => menus().forEach((m) => { if (!m.contains(e.target)) m.open = false; }));
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    menus().forEach((m) => { m.open = false; m.querySelector('summary').focus(); });
  });
})();

// ---------- Report an error in the open version: the triangle on the row, or right click on the source ----------
(() => {
  const dlg = document.getElementById('report-dialog');
  if (!dlg) return;
  const form = dlg.querySelector('form'), msg = dlg.querySelector('[data-r-msg]'), reason = form.elements.reason;
  const open = (btn) => {
    form.elements.key.value = btn.dataset.report;
    dlg.querySelector('[data-r-label]').textContent = btn.dataset.label;
    dlg.querySelector('[data-r-value]').textContent = btn.dataset.value;
    reason.value = ''; msg.textContent = ''; form.querySelector('button:not([type])').disabled = false;
    dlg.showModal(); reason.focus();
  };
  document.addEventListener('click', (e) => { const b = e.target.closest('[data-report]'); if (b) open(b); });
  document.addEventListener('contextmenu', (e) => {
    const cell = e.target.closest('[data-report-cell]');
    if (!cell) return;
    e.preventDefault();
    open(cell.querySelector('[data-report]'));
  });
  dlg.querySelector('[data-r-cancel]').addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
  dlg.querySelectorAll('.quick button').forEach((b) => b.addEventListener('click', () => { reason.value = b.textContent + (reason.value ? '. ' + reason.value : ''); reason.focus(); }));
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const send = form.querySelector('button:not([type])');
    send.disabled = true;
    try {
      const r = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
      const d = await r.json();
      if (!d.ok) { msg.textContent = d.message; send.disabled = false; return; }
      dlg.close();
      // Logged in: a receipt is in the inbox, so the counter goes up and the notice links to it
      const count = document.getElementById('inbox-count');
      if (count && d.unread !== null) { count.textContent = d.unread; count.hidden = !d.unread; }
      toast(d.message, d.unread !== null ? { href: '/innboks', label: tr('Se innboksen') } : null);
    } catch { msg.textContent = tr('Klarte ikke å sende. Prøv igjen.'); send.disabled = false; }
  });
})();

// ---------- Short notice in the bottom left corner. Goes away by itself, or with the cross. ----------
function toast(text, link = null) {
  document.querySelector('.toast')?.remove();
  const el = document.createElement('div');
  el.className = 'toast';
  el.setAttribute('role', 'status');
  const p = document.createElement('span');
  p.textContent = text + ' ';
  if (link) { const a = document.createElement('a'); a.href = link.href; a.textContent = link.label; p.append(a); }
  const x = document.createElement('button');
  x.type = 'button'; x.textContent = '×'; x.setAttribute('aria-label', tr('Lukk'));
  x.addEventListener('click', () => el.remove());
  el.append(p, x);
  document.body.append(el);
  setTimeout(() => el.remove(), 9000);
}

// ---------- Home banner: the sawdust sequence starts only once every frame is decoded, so no layer pops in late ----------
(() => {
  const hero = $('[data-saw]');
  if (!hero) return;
  const loaded = (img) => img.complete ? Promise.resolve() : new Promise((r) => { img.addEventListener('load', r, { once: true }); img.addEventListener('error', r, { once: true }); });
  Promise.all($$('img', hero).map(loaded)).then(() => requestAnimationFrame(() => hero.classList.add('go')));
})();

// ---------- Section reveal: each marked section fades in once when it reaches the viewport ----------
(() => {
  const els = $$('.reveal');
  if (!els.length || !('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const io = new IntersectionObserver((entries) => entries.forEach((e) => {
    if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
  }), { rootMargin: '0px 0px -10% 0px', threshold: 0.05 });
  els.forEach((el) => { if (el.getBoundingClientRect().top > innerHeight) { el.classList.add('pending'); io.observe(el); } });
})();
