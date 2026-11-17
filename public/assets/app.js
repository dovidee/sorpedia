// Sørpedia klientkode. Ingen rammeverk, ingen byggesteg.
const $ = (s, el = document) => el.querySelector(s);
const $$ = (s, el = document) => [...el.querySelectorAll(s)];
const CSRF = document.querySelector('meta[name=csrf]')?.content;
// Tekst på kundens språk. Ordlisten (src/I18nEn.php) skrives inn i siden når språket er engelsk.
const tr = (s, v = {}) => (window.I18N?.[s] ?? s).replace(/\{(\w+)\}/g, (m, k) => (k in v ? v[k] : m));
const LANG = document.querySelector('meta[name=lang]')?.content || 'no';
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const store = {
  get(k, d, s = localStorage) { try { return JSON.parse(s.getItem(k)) ?? d; } catch { return d; } },
  set(k, v, s = localStorage) { try { s.setItem(k, JSON.stringify(v)); } catch {} },
  del(k, s = localStorage) { try { s.removeItem(k); } catch {} },
};

// ---------- Søk: innebygd autofullføring (grått forslag, Tab godtar) og tidligere søk ----------
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

  // Hva skal stå grått etter markøren? Tidligere søk først, så kjente fraser, så ord fra katalogen.
  const complete = (v) => {
    const lv = v.toLowerCase();
    if (!lv.trim() || !vocab) return '';
    const pick = (list) => list.find((x) => x.startsWith(lv) && x.length > lv.length);
    const hit = pick(recent()) || pick(vocab.phrases);
    if (hit) return hit.slice(lv.length);
    const toks = lv.split(/\s+/);
    const last = toks[toks.length - 1];
    if (last === '') { // etter mellomrom: neste ord som pleier å følge
      const prev = toks[toks.length - 2];
      return vocab.next[prev]?.[0] || '';
    }
    if (last.length >= 2) {
      const w = vocab.words.find(([x]) => x.startsWith(last) && x !== last);
      if (w) return w[0].slice(last.length);
      if (vocab.next[last]?.[0]) return ' ' + vocab.next[last][0]; // helt ord: foreslå neste, så Tab kan kjedes
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
      } catch (e) { /* avbrutt */ }
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

// ---------- Bildegalleri: klikk for å zoome inn, klikk igjen for å zoome ut ----------
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

// ---------- Filtre: virker med en gang du klikker, uten egen knapp ----------
(() => {
  const form = $('form[data-auto]');
  if (!form) return;
  const results = $('#results');
  const OPEN = 'sorpedia-facets:' + location.pathname;
  // Husk hvilke grupper kunden har åpnet eller lukket
  const saved = store.get(OPEN, null, sessionStorage);
  if (saved) $$('details.facet', form).forEach((d) => { if (!d.querySelector('.n-on')) d.open = saved.includes(d.dataset.key); });
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
      $$('details.facet', form).forEach((d) => {
        const n = doc.querySelector(`details.facet[data-key="${CSS.escape(d.dataset.key)}"] summary`);
        if (n) d.querySelector('summary').innerHTML = n.innerHTML;
      });
      history.replaceState(null, '', url);
      document.dispatchEvent(new Event('sorpedia:content'));
    } catch (e) { if (e.name !== 'AbortError') form.submit(); }
    results.classList.remove('busy');
  };
  form.addEventListener('change', (e) => { if (e.target.type === 'checkbox') apply(); else { clearTimeout(timer); apply(); } });
  form.addEventListener('input', (e) => { if (e.target.type !== 'checkbox') { clearTimeout(timer); timer = setTimeout(apply, 650); } });
  form.addEventListener('submit', (e) => { e.preventDefault(); clearTimeout(timer); apply(); });
})();

// ---------- Produktguide: ett spørsmål om gangen, neste spørsmål avhenger av svaret ----------
(() => {
  const form = $('#quiz');
  if (!form) return;
  form.addEventListener('change', () => setTimeout(() => form.submit(), 150));
})();

// ---------- Demo-brukere: klikk for å fylle inn ----------
$$('.demo-users tr').forEach((tr) => tr.addEventListener('click', () => {
  $('input[name=email]').value = tr.dataset.email; $('input[name=password]').value = 'admin'; $('input[name=password]').focus();
}));

// ---------- Sammenligning (lagres i nettleseren) ----------
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

// ---------- Legg i kurv uten sidelasting ----------
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

// ---------- Søren-chat ----------
// Samtalen lagres i nettleseren, så den overlever sidebytte og at panelet lukkes. Slettes ved utlogging
// eller med «Ny samtale». Hver melding sender bare adressen til siden du står på; serveren avgjør selv
// hva Søren får se (bare offentlige produktsider, aldri konto, kurv, ordre eller innlogging).
window.Soren = (() => {
  const KEY = 'sorpedia-chat-v2';
  const pageLabel = () => document.querySelector('meta[name=soren-page]')?.content || '';
  const pageUrl = () => location.pathname + location.search;
  let st = store.get(KEY, null) || { open: false, items: [], history: [], ticket: null, since: 0, ctx: null, lang: LANG };
  // Byttet språk med flagget: ny samtale på det nye språket
  if ((st.lang || 'no') !== LANG) st = { open: st.open, items: [], history: [], ticket: null, since: 0, ctx: null, lang: LANG };
  let panel, log, es, busy = false;
  let mascot = { set() {}, wave() {}, gust() {}, happy() {}, sad() {}, surprised() {}, state: 'idle' };

  const cardsHtml = (cards) => cards?.length ? '<div class="chat-cards">' + cards.map((c) =>
    `<a href="${esc(c.url)}">${c.img ? `<img src="${esc(c.img)}" alt="">` : ''}<span>${esc(c.name)}<br><b>${esc(c.price)}</b> ${esc(c.stock)}</span></a>`).join('') + '</div>' : '';
  const html = (it) => {
    if (it.t !== 'bot') return it.t === 'support' ? `<b>${esc(it.name)} (${tr('kundeservice')}):</b> ${esc(it.text)}` : esc(it.text);
    const think = it.think || it.note
      ? `<details class="think"${it.pending && !it.text ? ' open' : ''}><summary>${tr('Sørens resonnement (generert, kan være feil)')}</summary>${it.note ? `<span class="note">${esc(it.note)}</span>` : ''}${esc(it.think)}${it.cut ? `<span class="note cut">${esc(it.cut)}</span>` : ''}</details>` : '';
    // Anførselstegn rundt hele svaret fjernes
    const text = it.pending ? it.text : it.text.replace(/^\s*[“"«„]+/u, '').replace(/[”"»]+\s*$/u, '');
    const body = it.text ? esc(text) : (it.wait ? `<span class="muted">${esc(it.wait)}</span>` : (it.pending ? `<span class="muted">${tr('Søren tenker …')}</span>` : ''));
    return think + body + cardsHtml(it.cards);
  };
  // Søren står ved siden av svarene sine som et lite hode. Bare det nyeste hodet lever (tenker, blunker),
  // de eldre står stille, så chatten ikke blir full av bevegelse.
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
  // el er en DOM-node og skal ikke lagres
  const plain = () => ({ ...st, items: st.items.slice(-60).map(({ el, av, box, ...rest }) => rest) });
  const persist = () => store.set(KEY, plain());

  const intro = () => {
    push({ t: 'bot', text: tr('Hei, jeg er Søren! Jeg kan finne produkter, forklare hva som passer sammen og svare på spørsmål om spesifikasjoner. Priser og lager henter jeg alltid fra butikken, ikke fra hukommelsen.') });
    push({ t: 'sys', text: tr('Søren er en språkmodell som kjører lokalt på denne maskinen. Han ser bare offentlig produktinfo fra siden du er på, aldri kontoen din. Det han tenker er generert og kan være feil.') });
  };
  const status = (s) => { const el = $('.chat-status', panel); if (el) el.textContent = s; };
  const chip = () => {
    const c = $('.page-chip', panel), label = pageLabel();
    c.hidden = !label;
    c.innerHTML = `<span>${tr('Søren ser:')}</span> <b>${esc(label)}</b><span class="lock" title="${esc(tr('Søren får bare offentlig produktinfo fra denne siden. Han ser aldri passord, konto, handlekurv eller ordre.'))}">${tr('bare produktinfo')}</span>`;
  };
  // Husk siste side (feltet over meldingsboksen viser hva Søren ser)
  const noteContext = () => {
    const label = pageLabel();
    st.ctx = label || st.ctx; persist();
  };

  const build = () => {
    panel = document.createElement('section');
    panel.className = 'chat'; panel.hidden = true;
    panel.setAttribute('aria-label', tr('Chat med Søren'));
    panel.innerHTML = `<header><div class="chat-title"><b>Søren</b><br><small>${tr('KI-assistent, kjører lokalt')}</small><span class="chat-status" aria-live="polite"></span></div>
        <div class="chat-btns"><button class="new" type="button" title="${tr('Start en ny samtale')}">${tr('Ny samtale')}</button><button class="x" type="button" aria-label="${tr('Lukk')}">×</button></div></header>
      <div class="log" role="log"></div>
      <div class="page-chip" hidden></div>
      <form><input name="m" placeholder="${tr('Spør om produkter, mål eller hva som passer')}" autocomplete="off" aria-label="${tr('Melding til Søren')}"><button class="btn small">${tr('Send')}</button></form>
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

  // Last modellen inn i minnet med en gang chatten åpnes (én gang per side)
  let warmed = false;
  const warm = () => { if (warmed) return; warmed = true; fetch('/api/chat/warm', { method: 'POST', headers: { 'X-CSRF': CSRF } }).catch(() => {}); };
  const open = (opts = {}) => {
    warm();
    if (!panel) build();
    const wasHidden = panel.hidden;
    panel.hidden = false;
    document.body.classList.add('chat-open');
    st.open = true;
    chip(); noteContext();
    if (wasHidden && !opts.silent) { mascot.surprised(); mascot.wave(); }
    const i = $('input', panel);
    if (opts.ask && !i.value) { i.value = opts.ask; i.select(); }
    if (pageLabel()) i.placeholder = tr('Spør om {x}', { x: pageLabel() });
    if (!opts.silent) i.focus();
    log.scrollTop = log.scrollHeight;
    persist();
  };
  const close = () => { panel.hidden = true; document.body.classList.remove('chat-open'); st.open = false; persist(); };
  const reset = () => {
    es?.close(); es = null;
    st = { open: true, items: [], history: [], ticket: null, since: 0, ctx: null, lang: LANG };
    log.innerHTML = ''; liveIt = null; mascot = NOOP; intro(); noteContext(); mascot.gust(4);
  };

  // Leser en strøm fra Søren inn i meldingen it. it.seen teller hendelsene som er vist, så en ny side kan lese
  // videre fra samme sted (GET /api/chat/{jobb}?from=seen) hvis kunden bytter side midt i svaret.
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
    if (it.text) st.history.push({ role: 'assistant', content: it.text });
    mascot.set('idle');
    it.fellBack ? mascot.sad() : mascot.happy();
    status(''); busy = false; persist();
  };
  const failed = (it) => {
    if (leaving) { persist(); return; } // kunden bytter side: neste side leser videre, ikke vis feil
    it.pending = false; if (!it.text) it.text = tr('Beklager, noe gikk galt. Prøv igjen, eller snakk med et menneske.'); draw(it);
    mascot.set('idle'); mascot.sad(); status(''); busy = false; persist();
  };

  const send = async (text) => {
    busy = true;
    noteContext();
    push({ t: 'me', text });
    st.history.push({ role: 'user', content: text });
    const it = push({ t: 'bot', text: '', think: '', note: '', cards: [], pending: true });
    mascot.set('think'); status(tr('tenker …'));
    try {
      const r = await fetch('/api/chat', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
        body: JSON.stringify({ messages: st.history.slice(-8), page: pageUrl() }) });
      if (!r.ok || !r.body) throw new Error('http ' + r.status);
      await consume(it, r);
      finish(it);
    } catch (e) { failed(it); }
  };

  // Ny side: les videre på svaret Søren holdt på med da kunden byttet side
  const resume = async (it) => {
    busy = true; mascot.set(it.text ? 'talk' : 'think'); status(it.text ? tr('svarer …') : tr('tenker …'));
    try {
      const r = await fetch(`/api/chat/${encodeURIComponent(it.job)}?from=${it.seen || 0}`);
      if (!r.ok || !r.body) throw new Error('http ' + r.status);
      if ((await consume(it, r)) === false && !it.text) it.text = tr('Svaret ble avbrutt fordi du byttet side. Spør gjerne igjen.');
      finish(it);
    } catch (e) { failed(it); }
  };

  // ---------- Videre til kundeservice ----------
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
  const human = async () => {
    if (st.ticket) return push({ t: 'sys', text: `Du er allerede koblet til kundeservice (sak #${st.ticket}).` });
    const r = await fetch('/api/support', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
      body: JSON.stringify({ log: st.history, product: null }) }).then((r) => r.json()).catch(() => null);
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
    if (t) { e.preventDefault(); open({ ask: t.dataset.ask }); }
  });
  $('#soren-open')?.addEventListener('pointerenter', warm, { once: true });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && panel && !panel.hidden && panel.contains(document.activeElement)) close(); });
  // Utlogging sletter samtalen, så neste person på samme maskin ikke ser den
  document.addEventListener('submit', (e) => { if (e.target.matches('[data-logout]')) store.del(KEY); }, true);
  addEventListener('pagehide', persist);
  if (st.open) open({ silent: true });
  return { open };
})();

// ---------- Kontomenyen: lukkes ved klikk utenfor og med Escape ----------
(() => {
  const menus = () => document.querySelectorAll('details[data-menu][open]');
  document.addEventListener('click', (e) => menus().forEach((m) => { if (!m.contains(e.target)) m.open = false; }));
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    menus().forEach((m) => { m.open = false; m.querySelector('summary').focus(); });
  });
})();

// ---------- Meld fra om feil i den åpne versjonen: trekanten på raden, eller høyreklikk på kilden ----------
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
      msg.textContent = d.message;
      if (d.ok) setTimeout(() => dlg.close(), 1800); else send.disabled = false;
    } catch { msg.textContent = tr('Klarte ikke å sende. Prøv igjen.'); send.disabled = false; }
  });
})();
