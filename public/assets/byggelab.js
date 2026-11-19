// Byggelab (feature 023). Plain Three.js like the Control Tower, no framework, no build step.
// The browser only holds the list of product IDs and quantities. Every number shown (price, stock, specifications,
// compatibility) comes back from /api/byggelab/{theme}/sjekk, which reads the database.
import * as THREE from 'three';

const cfg = JSON.parse(document.getElementById('lab-cfg').textContent);
const L = cfg.L;
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const fill = (s, v) => String(s).replace(/\{(\w+)\}/g, (_, k) => (v[k] ?? ''));
const locale = document.documentElement.lang === 'en' ? 'en-GB' : 'nb-NO';
const kr = (ore) => new Intl.NumberFormat('nb-NO', { style: 'currency', currency: 'NOK' }).format(ore / 100);
const num = (n) => new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(n);
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
const wait = (ms) => new Promise((r) => setTimeout(r, reduce ? 0 : ms));
const INV = new Map(cfg.inventory.map((p) => [p.id, p]));
const short = (name) => String(name).split(/\s+[–-]\s+/u)[0];
const SVG = (d) => `<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d}</svg>`;
const I = {
  plus: SVG('<path d="M5 12h14"/><path d="M12 5v14"/>'),
  minus: SVG('<path d="M5 12h14"/>'),
  alert: SVG('<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>'),
  info: SVG('<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>'),
  x: SVG('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>'),
};

// Browser storage is a convenience only (saved configurations). It may be missing or blocked.
const store = {
  get(k, d) { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch { return d; } },
  set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* private window */ } },
};

// ---------- State and history ----------
const S = { lines: [], past: [], future: [], report: null, sel: null, link: null, cat: 'alle', q: '', run: null, running: false };
const snap = () => S.lines.map((l) => ({ ...l }));
const nameOf = (id) => short(INV.get(id)?.name ?? `#${id}`);

function commit(label, fn) {
  S.past.push({ lines: snap(), label });
  if (S.past.length > 60) S.past.shift();
  S.future = [];
  fn();
  changed();
}
function undo() {
  const p = S.past.pop();
  if (!p) return;
  S.future.push({ lines: snap(), label: p.label });
  S.lines = p.lines;
  changed();
}
function redo() {
  const f = S.future.pop();
  if (!f) return;
  S.past.push({ lines: snap(), label: f.label });
  S.lines = f.lines;
  changed();
}

function add(id, qty = 1) {
  const have = S.lines.find((l) => l.id === id);
  if (!have && S.lines.length >= cfg.max) return announce(`Maks ${cfg.max}`);
  if (have) return setQty(id, have.qty + qty);
  commit(fill(L.hAdd, { name: nameOf(id) }), () => { S.lines.push({ id, qty }); S.sel = id; });
  announce(fill(L.hAdd, { name: nameOf(id) }));
}
function remove(id) {
  commit(fill(L.hRemove, { name: nameOf(id) }), () => { S.lines = S.lines.filter((l) => l.id !== id); if (S.sel === id) S.sel = null; });
  announce(fill(L.hRemove, { name: nameOf(id) }));
}
function setQty(id, n) {
  n = Math.max(1, Math.min(99, n));
  commit(fill(L.hQty, { name: nameOf(id), n }), () => { S.lines = S.lines.map((l) => (l.id === id ? { ...l, qty: n } : l)); S.sel = id; });
}
function load(lines, label) {
  commit(label, () => { S.lines = Object.entries(lines).map(([id, qty]) => ({ id: +id, qty: +qty })).filter((l) => INV.has(l.id)); S.sel = null; S.link = null; });
}

function changed() {
  S.run = null;
  scene.clearRun();
  renderList();
  renderHistory();
  syncUrl();
  $('[data-act=undo]').disabled = !S.past.length;
  $('[data-act=redo]').disabled = !S.future.length;
  $('#lab-empty').hidden = S.lines.length > 0;
  check();
}

// The address carries the configuration, so a link can be shared and the chat assistant reads the same products
function syncUrl() {
  const c = S.lines.map((l) => `${l.id}:${l.qty}`).join(',');
  history.replaceState(null, '', c ? `?c=${c}` : location.pathname);
}
function fromUrl() {
  const c = new URLSearchParams(location.search).get('c');
  if (!c) return null;
  const lines = {};
  for (const part of c.split(',').slice(0, cfg.max)) {
    const [id, qty] = part.split(':').map((x) => parseInt(x, 10));
    if (INV.has(id)) lines[id] = Math.max(1, Math.min(99, qty || 1));
  }
  return Object.keys(lines).length ? lines : null;
}

const live = $('#lab-live');
function announce(text) { live.textContent = ''; requestAnimationFrame(() => { live.textContent = text; }); }

// ---------- Check against the server ----------
const side = $('#lab-side');
let seq = 0;
async function check(test = false) {
  const my = ++seq;
  side.setAttribute('aria-busy', 'true');
  try {
    const r = await fetch(`/api/byggelab/${cfg.theme}/sjekk`, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': cfg.csrf, Accept: 'application/json' },
      body: JSON.stringify({ lines: S.lines, test }),
    });
    if (!r.ok) throw new Error(String(r.status));
    const d = await r.json();
    if (my !== seq) return null;
    S.report = d;
    if (S.sel && !d.items.some((i) => i.id === S.sel)) S.sel = null;
    renderSide();
    scene.build(d);
    return d;
  } catch {
    if (my === seq) { announce(L.checkFail); side.innerHTML = `<p class="lab-note">${esc(L.checkFail)}</p>`; }
    return null;
  } finally {
    if (my === seq) side.setAttribute('aria-busy', 'false');
  }
}

// ---------- Inventory (left) ----------
const list = $('#lab-list');
function renderList() {
  const q = S.q.trim().toLowerCase();
  const inCfg = new Map(S.lines.map((l) => [l.id, l.qty]));
  const rows = cfg.inventory.filter((p) => (S.cat === 'alle' || p.cats.includes(+S.cat)) && (!q || p.name.toLowerCase().includes(q)));
  list.innerHTML = rows.length ? rows.map((p) => {
    const n = inCfg.get(p.id);
    const facts = p.facts.slice(0, 2).map((f) => `<span>${esc(f.label)} <b>${esc(f.value)}</b></span>`).join('');
    return `<li class="lab-item${n ? ' in' : ''}" draggable="true" data-id="${p.id}">
      ${p.img ? `<img src="${esc(p.img)}" alt="" loading="lazy" width="52" height="52">` : '<span class="noimg"></span>'}
      <span><span class="nm">${esc(p.name)}</span><span class="mt"><b>${kr(p.price_ore)}</b>${facts}${n ? `<span>× ${n}</span>` : ''}</span></span>
      <button type="button" data-add="${p.id}" aria-label="${esc(L.add)}: ${esc(p.name)}" title="${esc(n ? L.added : L.add)}">${I.plus}</button></li>`;
  }).join('') : `<li class="none">${esc(L.noHits)}</li>`;
}
list.addEventListener('click', (e) => { const b = e.target.closest('[data-add]'); if (b) add(+b.dataset.add); });
list.addEventListener('dragstart', (e) => {
  const li = e.target.closest('[data-id]');
  if (!li) return;
  e.dataTransfer.setData('text/plain', li.dataset.id);
  e.dataTransfer.effectAllowed = 'copy';
  li.classList.add('dragging');
});
list.addEventListener('dragend', (e) => e.target.closest('[data-id]')?.classList.remove('dragging'));
// Touch and pen have no HTML drag and drop: hold a product for 250 ms, then drag it onto the workbench
list.addEventListener('pointerdown', (e) => {
  const li = e.target.closest('[data-id]');
  if (!li || e.pointerType === 'mouse' || e.target.closest('button')) return;
  const id = +li.dataset.id, view = $('#lab-view');
  let ghost = null;
  const hold = setTimeout(() => {
    ghost = li.querySelector('img, .noimg')?.cloneNode(true) || document.createElement('span');
    Object.assign(ghost.style, { position: 'fixed', width: '56px', height: '56px', pointerEvents: 'none', zIndex: 99, opacity: '.85', left: `${e.clientX - 28}px`, top: `${e.clientY - 28}px` });
    document.body.append(ghost);
    li.classList.add('dragging');
    li.style.touchAction = 'none';
    li.setPointerCapture(e.pointerId);
  }, 250);
  const over = (x, y) => { const r = view.getBoundingClientRect(); return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom; };
  const move = (m) => {
    if (!ghost) { if (Math.hypot(m.clientX - e.clientX, m.clientY - e.clientY) > 8) end(); return; }
    m.preventDefault();
    ghost.style.left = `${m.clientX - 28}px`;
    ghost.style.top = `${m.clientY - 28}px`;
    view.classList.toggle('drop', over(m.clientX, m.clientY));
  };
  const end = (u) => {
    clearTimeout(hold);
    li.removeEventListener('pointermove', move);
    li.removeEventListener('pointerup', end);
    li.removeEventListener('pointercancel', end);
    li.style.touchAction = '';
    if (!ghost) return;
    ghost.remove();
    li.classList.remove('dragging');
    view.classList.remove('drop');
    if (u?.type === 'pointerup' && over(u.clientX, u.clientY)) add(id);
  };
  li.addEventListener('pointermove', move);
  li.addEventListener('pointerup', end);
  li.addEventListener('pointercancel', end);
});
list.addEventListener('contextmenu', (e) => { if (e.target.closest('.dragging')) e.preventDefault(); });
$('#lab-q').addEventListener('input', (e) => { S.q = e.target.value; renderList(); });
$$('.lab-chips [data-cat]').forEach((b) => b.addEventListener('click', () => {
  S.cat = b.dataset.cat;
  $$('.lab-chips [data-cat]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
  renderList();
}));
$$('[data-preset]').forEach((b) => b.addEventListener('click', () => {
  const p = cfg.presets[+b.dataset.preset];
  load(p.lines, fill(L.hPreset, { name: b.textContent }));
}));

// ---------- The check (right) ----------
function pairName(id) { return esc(nameOf(id)); }
function renderSide() {
  const r = S.report;
  if (!r) return;
  const t = r.totals;
  const weight = t.weight_g === null ? esc(L.weightNone) : `${num(t.weight_g >= 1000 ? t.weight_g / 1000 : t.weight_g)} ${t.weight_g >= 1000 ? 'kg' : 'g'} <small class="muted">(${esc(fill(L.weightOf, { k: t.weight_known, n: t.lines }))})</small>`;
  let h = `<section class="lab-sum" aria-label="${esc(L.total)}">
    <h2>${esc(L.total)}</h2>
    <div class="tot"><b>${kr(t.price_ore)}</b><span class="lab-verdict ${r.verdict}" title="${esc(L.verdictText[r.verdict])}">${esc(L.verdict[r.verdict])}</span></div>
    <dl><dt>${esc(L.weight)}</dt><dd>${weight}</dd><dt>${esc(L.qty)}</dt><dd>${esc(fill(L.lines, { l: t.lines, u: t.units }))}</dd></dl></section>`;

  if (S.run) {
    h += `<section class="lab-sec" id="lab-report"><h2>${esc(L.run)}</h2><ol class="lab-steps">${S.run.steps.map((s, i) =>
      `<li class="${s.state}${i < S.run.at ? ' done' : ''}"><b>${esc(s.title)}</b><span>${esc(s.text)}</span></li>`).join('')}</ol>
      <p class="lab-note">${esc(L.runNote)}</p>${S.running ? '' : `<button type="button" class="text-btn" data-report>${esc(L.download)}</button>`}</section>`;
  }

  const sel = r.items.find((i) => i.id === S.sel);
  if (sel) {
    const stock = sel.in_store > 0 ? fill(L.inStore, { n: sel.in_store }) : (sel.online > 0 ? L.online : L.out);
    h += `<section class="lab-sec"><h2>${esc(L.selected)}</h2><div class="lab-pick">
      ${sel.img ? `<img src="${esc(sel.img)}" alt="">` : '<span></span>'}
      <div><a class="nm" href="/produkt/${esc(sel.slug)}">${esc(sel.name)}</a><div class="muted small">${kr(sel.price_ore)} · ${esc(stock)}</div>
      <div class="row"><span class="lab-qty" role="group" aria-label="${esc(L.qty)}"><button type="button" data-q="-1" aria-label="−1">${I.minus}</button><output>${sel.qty}</output><button type="button" data-q="1" aria-label="+1">${I.plus}</button></span>
      <button type="button" class="text-btn" data-rm="${sel.id}">${esc(L.remove)}</button></div></div></div>
      ${sel.specs.length ? `<dl class="lab-specs">${sel.specs.map((s) => `<dt>${esc(s.label)}</dt><dd>${esc(s.value)} <span class="src src-${esc(s.source)}" title="${esc(s.evidence || '')}">${esc(L.source[s.source] || s.source)}</span></dd>`).join('')}</dl>` : `<p>${esc(L.specsNone)}</p>`}
    </section>`;
  }

  h += `<section class="lab-sec"><h2>${esc(L.compat)}</h2>${r.links.length ? `<ul class="lab-links">${r.links.map((l) => {
    const k = `${l.a}-${l.b}`;
    return `<li class="lab-link st-${l.status}${S.link === k ? ' sel' : ''}" data-link="${k}" tabindex="0">
      <span class="lab-st st-${l.status}">${esc(L.statusLong[l.status])}</span>
      <span class="pair">${pairName(l.a)} <span aria-hidden="true">↔</span><span class="sr">${esc(L.pair)}</span> ${pairName(l.b)}</span>
      <span class="why">${esc(l.reason)}</span>
      <details><summary>${esc(L.evidence)}</summary>${esc(L.basis[l.basis] || '')}${l.evidence ? `<br>${esc(l.evidence)}` : ''}</details></li>`;
  }).join('')}</ul>` : `<p>${esc(L.compatNone)}</p>`}</section>`;

  const miss = [...r.missing.map((m) => ({ ...m, cls: m.kind === 'data' ? 'data' : '' })), ...r.warnings.map((w) => ({ ...w, cls: '' }))];
  h += `<section class="lab-sec"><h2>${esc(L.missing)}</h2>${miss.length ? `<ul class="lab-miss">${miss.map((m) =>
    `<li class="${m.cls}">${m.cls === 'data' || m.kind === 'info' ? I.info : I.alert}<span>${esc(m.text)}</span></li>`).join('')}</ul>` : `<p>${esc(L.missingNone)}</p>`}</section>`;

  if (r.bars || r.stats.length || r.notes.length) {
    h += `<section class="lab-sec"><h2>${esc(L.stats)}</h2>`;
    if (r.bars) {
      const max = Math.max(1, ...r.bars.rows.map((x) => x.value || 0));
      h += `<ul class="lab-bars" aria-label="${esc(r.bars.label)}">${r.bars.rows.map((x) => `<li><span class="n">${esc(x.name)}</span><span class="v">${x.value === null ? esc(L.unknown) : `${num(x.value)} ${esc(r.bars.unit)}`}</span><span class="t"><span style="transform:scaleX(${(x.value || 0) / max})"></span></span></li>`).join('')}</ul>`;
    }
    if (r.stats.length) h += `<dl class="lab-stats">${r.stats.map((s) => `<dt>${esc(s.label)}</dt><dd>${esc(s.value)}</dd>${s.note ? `<span class="note">${esc(s.note)}</span>` : ''}`).join('')}</dl>`;
    h += r.notes.map((n) => `<p class="lab-note">${esc(n)}</p>`).join('') + '</section>';
  }

  const v = r.verification, all = v.bekreftet + v.utledet + v.mangler;
  if (all) {
    const pc = (n) => `${(n / all) * 100}%`;
    h += `<section class="lab-sec"><h2>${esc(L.verification)}</h2>
      <div class="lab-verbar" role="img" aria-label="${esc(`${L.verOk} ${v.bekreftet}, ${L.verDerived} ${v.utledet}, ${L.verMissing} ${v.mangler}`)}"><span class="a" style="width:${pc(v.bekreftet)}"></span><span class="b" style="width:${pc(v.utledet)}"></span><span class="c" style="width:${pc(v.mangler)}"></span></div>
      <ul class="lab-verkey"><li><i style="background:var(--sortre)"></i>${esc(L.verOk)} ${v.bekreftet}</li><li><i style="background:var(--utledet)"></i>${esc(L.verDerived)} ${v.utledet}</li><li><i style="background:var(--line)"></i>${esc(L.verMissing)} ${v.mangler}</li></ul></section>`;
  }
  side.innerHTML = h;
  $('#lab-ask').dataset.ask = fill(L.ask, { names: r.items.map((i) => `${short(i.name)}${i.qty > 1 ? ` × ${i.qty}` : ''}`).join(', ') });
}
side.addEventListener('click', (e) => {
  const q = e.target.closest('[data-q]');
  if (q && S.sel) return setQty(S.sel, (S.lines.find((l) => l.id === S.sel)?.qty || 1) + +q.dataset.q);
  const rm = e.target.closest('[data-rm]');
  if (rm) return remove(+rm.dataset.rm);
  if (e.target.closest('[data-report]')) return downloadReport();
  const li = e.target.closest('[data-link]');
  if (li && !e.target.closest('details')) selectLink(li.dataset.link);
});
side.addEventListener('keydown', (e) => {
  const li = e.target.closest('[data-link]');
  if (li && (e.key === 'Enter' || e.key === ' ') && e.target === li) { e.preventDefault(); selectLink(li.dataset.link); }
});
function selectLink(k) { S.link = S.link === k ? null : k; renderSide(); scene.highlight(); }
function selectItem(id) { S.sel = id; S.link = null; renderSide(); scene.highlight(); }

// ---------- History, saves, export, cart, test run (bottom) ----------
function renderHistory() {
  $('#lab-hcount').textContent = S.past.length;
  const done = S.past.map((p) => p.label).reverse();
  const undone = S.future.map((f) => f.label);
  $('#lab-history').innerHTML = done.length || undone.length
    ? [...undone.map((l) => `<li class="future">${esc(l)}</li>`), ...done.map((l, i) => `<li class="${i === 0 ? 'now' : ''}">${esc(l)}</li>`)].join('')
    : `<li class="muted">${esc(L.historyNone)}</li>`;
}
const SAVES = 'sorpedia-lab-saves';
function renderSaved() {
  const all = store.get(SAVES, {})[cfg.theme] || [];
  $('#lab-saved').innerHTML = all.length ? all.map((s, i) => `<li><span>${esc(s.name)}<br><small class="muted">${s.lines.length} ${esc(L.qty.toLowerCase())}</small></span>
    <span><button type="button" data-open-save="${i}">${esc(L.open)}</button><button type="button" data-del-save="${i}" aria-label="${esc(L.remove)}">${I.x}</button></span></li>`).join('')
    : `<li class="muted">${esc(L.savedNone)}</li>`;
}
function save() {
  if (!S.lines.length) return;
  const all = store.get(SAVES, {});
  const name = `${cfg.builder} ${new Intl.DateTimeFormat(locale, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date())}`;
  all[cfg.theme] = [{ name, lines: snap() }, ...(all[cfg.theme] || [])].slice(0, 12);
  store.set(SAVES, all);
  renderSaved();
  announce(fill(L.saveDone, { name }));
}
$('#lab-saved').addEventListener('click', (e) => {
  const all = store.get(SAVES, {});
  const saved = all[cfg.theme] || [];
  const o = e.target.closest('[data-open-save]'), d = e.target.closest('[data-del-save]');
  if (o) {
    const s = saved[+o.dataset.openSave];
    if (s) load(Object.fromEntries(s.lines.map((l) => [l.id, l.qty])), fill(L.hLoad, { name: s.name }));
    o.closest('details').open = false;
  } else if (d) {
    saved.splice(+d.dataset.delSave, 1);
    all[cfg.theme] = saved;
    store.set(SAVES, all);
    renderSaved();
  }
});

function download(name, text, type) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([text], { type }));
  a.download = name;
  document.body.append(a);
  a.click();
  setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 0);
}
const money = (ore) => (ore / 100).toFixed(2).replace('.', ',');
function exportList() {
  const r = S.report;
  if (!r?.items.length) return;
  const rows = r.items.map((i) => [i.id, `"${i.name.replace(/"/g, '""')}"`, i.qty, money(i.price_ore), money(i.price_ore * i.qty)].join(';'));
  download(`byggelab-${cfg.theme}.csv`, `﻿${L.csvHead}\n${rows.join('\n')}\n;;;;${money(r.totals.price_ore)}\n`, 'text/csv;charset=utf-8');
}
function downloadReport() {
  const r = S.report;
  if (!r || !S.run) return;
  const lines = [`${L.listTitle}: ${cfg.builder}`, new Date().toLocaleString(locale), '',
    ...r.items.map((i) => `${i.qty} × ${i.name} (${i.id})  ${kr(i.price_ore * i.qty)}`), `${L.total}: ${kr(r.totals.price_ore)}`, '',
    `${L.run}:`, ...S.run.steps.map((s, i) => `${i + 1}. ${s.title}: ${s.text}`), '',
    `${L.compat}:`, ...r.links.map((l) => `[${L.statusLong[l.status]}] ${nameOf(l.a)} / ${nameOf(l.b)}: ${l.reason}`), '',
    `${L.missing}:`, ...[...r.missing, ...r.warnings].map((m) => `* ${m.text}`), '', L.runNote];
  download(`byggelab-rapport-${cfg.theme}.txt`, lines.join('\n'), 'text/plain;charset=utf-8');
}
async function toCart() {
  if (!S.lines.length) return;
  const r = await fetch(`/api/byggelab/${cfg.theme}/kurv`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': cfg.csrf, Accept: 'application/json' }, body: JSON.stringify({ lines: S.lines }),
  }).then((x) => (x.ok ? x.json() : null)).catch(() => null);
  if (!r) return announce(L.cartFail);
  const c = $('#cart-count');
  if (c) c.textContent = r.count;
  const b = $('[data-act=cart]');
  const old = b.innerHTML;
  b.innerHTML = `<span>${esc(fill(L.cartDone, { n: S.lines.reduce((a, l) => a + l.qty, 0) }))}</span>`;
  announce(b.textContent);
  setTimeout(() => { b.innerHTML = old; }, 1800);
}

async function run() {
  if (S.running || !S.lines.length) return;
  S.running = true;
  const btn = $('[data-act=run]');
  btn.setAttribute('aria-busy', 'true');
  const bar = $('#lab-runbar');
  const d = await check(true);
  if (!d) { S.running = false; btn.removeAttribute('aria-busy'); return; }
  const kinds = ['inspect', 'validate', 'compat', 'missing', ...(d.module ? ['module'] : []), 'result'];
  S.run = { steps: d.steps, at: 0 };
  renderSide();
  $('#lab-report')?.scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' });
  bar.hidden = false;
  for (let i = 0; i < d.steps.length; i++) {
    if (S.report !== d || !S.run) break; // the configuration changed while running
    bar.textContent = `${fill(L.step, { i: i + 1, n: d.steps.length })}: ${d.steps[i].title}`;
    scene.runStep(kinds[i], d);
    S.run.at = i + 1;
    $$('#lab-report .lab-steps li')[i]?.classList.add('done');
    await wait(kinds[i] === 'compat' || kinds[i] === 'module' ? 1100 : 650);
  }
  S.running = false;
  btn.removeAttribute('aria-busy');
  if (S.run) { renderSide(); announce(`${L.run}: ${L.verdict[d.verdict]}. ${L.verdictText[d.verdict]}`); }
  bar.textContent = L.runNote;
  setTimeout(() => { if (!S.running) bar.hidden = true; }, 5000);
}

$('.lab-bar').addEventListener('click', (e) => {
  const b = e.target.closest('[data-act]');
  if (!b) return;
  ({ undo, redo, save, export: exportList, cart: toCart, run })[b.dataset.act]?.();
});
document.addEventListener('keydown', (e) => {
  if (!(e.ctrlKey || e.metaKey) || e.target.closest('input, textarea, [contenteditable]')) return;
  const k = e.key.toLowerCase();
  if (k === 'z' && !e.shiftKey) { e.preventDefault(); undo(); } else if ((k === 'z' && e.shiftKey) || k === 'y') { e.preventDefault(); redo(); }
});

// ---------- 3D workbench (centre) ----------
const scene = (() => {
  const view = $('#lab-view');
  const labels = $('#lab-labels');
  let gl = null;
  try {
    const r = new THREE.WebGLRenderer({ antialias: true });
    if (!r.getContext()) throw new Error('no context');
    gl = r;
  } catch { gl = null; }

  const COL = { dokumentert: 0x6cc08b, regel: 0xe2c064, ukjent: 0x7d8a84, ikke: 0xe5483b };
  const LANE = { kilde: 0xe9b949, pare: 0xd8c49c, strom: 0x9cc7b4, tilbehor: 0x8a918d, last: 0xc0c6cf, kjede: 0x8fc79a, fil: 0xd8c49c, annet: 0x8a918d, del: 0xd8c49c };

  if (!gl) {
    // No WebGL: the same content as a list, so nothing is lost
    const box = document.createElement('div');
    box.className = 'lab-fallback';
    view.append(box);
    $('.lab-tools').hidden = true;
    return {
      build(r) {
        const byLane = {};
        r.items.forEach((i) => (byLane[i.lane] ||= []).push(i));
        box.innerHTML = `<p>${esc(L.noGl)}</p>` + cfg.lanes.filter((k) => byLane[k]).map((k) => `<h3>${esc(L.lanes[k])}</h3><ul>${byLane[k].map((i) =>
          `<li><button type="button" class="text-btn" data-pick="${i.id}">${esc(i.name)}</button> × ${i.qty}</li>`).join('')}</ul>`).join('')
          + (r.links.length ? `<h3>${esc(L.compat)}</h3><ul>${r.links.map((l) => `<li>${esc(L.status[l.status])}: ${esc(nameOf(l.a))} / ${esc(nameOf(l.b))}</li>`).join('')}</ul>` : '');
        box.querySelectorAll('[data-pick]').forEach((b) => b.addEventListener('click', () => selectItem(+b.dataset.pick)));
      },
      highlight() {}, runStep() {}, clearRun() {},
    };
  }

  gl.setPixelRatio(Math.min(devicePixelRatio, 2));
  view.prepend(gl.domElement);
  const sc = new THREE.Scene();
  sc.background = new THREE.Color(0x141a17);
  const cam = new THREE.OrthographicCamera(-1, 1, 1, -1, 0.1, 400);
  sc.add(new THREE.HemisphereLight(0xf2efe6, 0x1b231f, 1.7));
  const sun = new THREE.DirectionalLight(0xffffff, 1.3);
  sun.position.set(14, 30, 10);
  sc.add(sun);
  const grid = new THREE.GridHelper(120, 60, 0x26302b, 0x1d2622);
  grid.position.y = -0.4;
  sc.add(grid);

  const world = new THREE.Group();
  sc.add(world);
  const fx = new THREE.Group(); // test run visuals, removed when the configuration changes
  world.add(fx);
  const loader = new THREE.TextureLoader();
  const tex = new Map();
  const nodes = new Map(); // id -> { group, plinth, edge, card, pos, item, top }
  const links = new Map(); // "a-b" -> { meshes, hub, curve, mat, link }
  const anims = [];
  let bench = null, dirty = true, theta = Math.PI / 4, autoRotate = false, userZoom = false, laneLabels = [], beams = [], weakest = null, weakestLabel = '';
  const halfH = 11;

  const placeCam = () => {
    const d = 60;
    cam.position.set(Math.cos(theta) * d, d * 0.82, Math.sin(theta) * d);
    cam.lookAt(0, 0, 0);
    dirty = true;
  };
  const resize = () => {
    const w = view.clientWidth || 1, h = view.clientHeight || 1;
    gl.setSize(w, h, false);
    const a = w / h;
    cam.left = -halfH * a; cam.right = halfH * a; cam.top = halfH; cam.bottom = -halfH;
    cam.updateProjectionMatrix();
    if (!userZoom) fit(); // keep the bench framed until the customer zooms by hand
    dirty = true;
  };
  new ResizeObserver(resize).observe(view);

  function texture(url) {
    if (!url) return null;
    if (!tex.has(url)) {
      const t = loader.load(url, () => { nodes.forEach((n) => n.fit()); dirty = true; });
      t.colorSpace = THREE.SRGBColorSpace;
      tex.set(url, t);
    }
    return tex.get(url);
  }
  function dispose(o) {
    o.traverse((c) => {
      c.geometry?.dispose();
      if (c.material) [].concat(c.material).forEach((m) => m.dispose());
    });
  }

  function fit() {
    userZoom = false;
    if (!nodes.size) { cam.zoom = 1; cam.updateProjectionMatrix(); dirty = true; return; }
    const box = new THREE.Box3();
    nodes.forEach((n) => box.expandByPoint(n.pos.clone().add(new THREE.Vector3(2, 4.5, 2))).expandByPoint(n.pos.clone().add(new THREE.Vector3(-2, 0, -2))));
    laneLabels.forEach((l) => box.expandByPoint(l.pos.clone().add(new THREE.Vector3(-2.5, 0, 0))));
    cam.zoom = 1;
    cam.updateProjectionMatrix();
    cam.updateMatrixWorld();
    let m = 0;
    for (const x of [box.min.x, box.max.x]) for (const y of [box.min.y, box.max.y]) for (const z of [box.min.z, box.max.z]) {
      const v = new THREE.Vector3(x, y, z).project(cam);
      m = Math.max(m, Math.abs(v.x), Math.abs(v.y) * 1.1);
    }
    cam.zoom = Math.min(2.4, 0.94 / Math.max(m, 0.05));
    cam.updateProjectionMatrix();
    dirty = true;
  }

  function linkMeshes(l, a, b) {
    const s = a.clone().setY(0.45), e = b.clone().setY(0.45);
    const mid = s.clone().lerp(e, 0.5);
    mid.y = 1.4 + s.distanceTo(e) * 0.12;
    const curve = new THREE.QuadraticBezierCurve3(s, mid, e);
    const mat = new THREE.MeshStandardMaterial({ color: COL[l.status], emissive: COL[l.status], emissiveIntensity: 0.25, roughness: 0.5, transparent: true, opacity: l.status === 'ukjent' ? 0.75 : 1 });
    const meshes = [];
    if (l.status === 'ukjent') {
      // Dashed: short tubes along the curve, so unknown never reads as a solid connection
      const pts = curve.getPoints(40);
      for (let i = 0; i < pts.length - 2; i += 4) {
        meshes.push(new THREE.Mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(pts.slice(i, i + 3)), 4, 0.05, 6), mat));
      }
    } else {
      meshes.push(new THREE.Mesh(new THREE.TubeGeometry(curve, 48, l.status === 'ikke' ? 0.09 : 0.07, 8), mat));
    }
    const hub = new THREE.Mesh(l.status === 'ikke' ? new THREE.OctahedronGeometry(0.34) : new THREE.SphereGeometry(0.24, 16, 12),
      new THREE.MeshStandardMaterial({ color: COL[l.status], emissive: COL[l.status], emissiveIntensity: 0.4 }));
    hub.position.copy(curve.getPoint(0.5));
    return { meshes, hub, curve, mat };
  }

  function build(r) {
    // Remove the previous bench; textures stay cached
    nodes.forEach((n) => { world.remove(n.group); dispose(n.group); });
    links.forEach((k) => { k.meshes.forEach((m) => { world.remove(m); m.geometry.dispose(); }); world.remove(k.hub); dispose(k.hub); k.mat.dispose(); });
    nodes.clear();
    links.clear();
    if (bench) { world.remove(bench); dispose(bench); bench = null; }

    const used = cfg.lanes.filter((k) => r.items.some((i) => i.lane === k));
    const rows = used.map((k) => r.items.filter((i) => i.lane === k));
    const gapX = 4.6, gapZ = 5.6;
    const widest = Math.max(1, ...rows.map((x) => x.length));
    laneLabels = [];
    rows.forEach((row, li) => {
      const z = (li - (rows.length - 1) / 2) * gapZ;
      laneLabels.push({ k: used[li], pos: new THREE.Vector3(-((widest - 1) / 2) * gapX - 2.6, 0, z) });
      row.forEach((it, i) => {
        const pos = new THREE.Vector3((i - (row.length - 1) / 2) * gapX, 0, z);
        const g = new THREE.Group();
        g.position.copy(pos);
        const plinth = new THREE.Mesh(new THREE.BoxGeometry(3.2, 0.36, 3.2), new THREE.MeshStandardMaterial({ color: 0x232c27, roughness: 0.95 }));
        plinth.userData.id = it.id;
        const edge = new THREE.LineSegments(new THREE.EdgesGeometry(plinth.geometry), new THREE.LineBasicMaterial({ color: LANE[it.lane] ?? 0xd8c49c }));
        g.add(plinth, edge);
        // Quantity: one thin plate per extra unit, at most five
        for (let q = 1; q < Math.min(it.qty, 6); q++) {
          const p = new THREE.Mesh(new THREE.BoxGeometry(3.2 - q * 0.18, 0.1, 3.2 - q * 0.18), new THREE.MeshStandardMaterial({ color: 0x2d3832 }));
          p.position.y = 0.23 + q * 0.12;
          p.userData.id = it.id;
          g.add(p);
        }
        const top = 0.2 + Math.min(it.qty - 1, 5) * 0.12;
        const shadow = new THREE.Mesh(new THREE.CircleGeometry(1.15, 32), new THREE.MeshBasicMaterial({ color: 0x000000, transparent: true, opacity: 0.35, depthWrite: false }));
        shadow.rotation.x = -Math.PI / 2;
        shadow.position.y = top + 0.02;
        g.add(shadow);
        const t = texture(it.img);
        const card = new THREE.Sprite(new THREE.SpriteMaterial({ map: t, color: t ? 0xffffff : 0x3a463f }));
        card.userData.id = it.id;
        card.position.y = top + 1.6;
        const n = { group: g, plinth, edge, card, pos, item: it, top };
        n.fit = () => {
          const img = t?.image;
          const a = img && img.width ? img.width / img.height : 1;
          card.scale.set(a >= 1 ? 2.7 : 2.7 * a, a >= 1 ? 2.7 / a : 2.7, 1);
        };
        n.fit();
        g.add(card);
        world.add(g);
        nodes.set(it.id, n);
      });
    });
    if (nodes.size) {
      bench = new THREE.Mesh(new THREE.BoxGeometry(widest * gapX + 1.2, 0.2, rows.length * gapZ - 0.6), new THREE.MeshStandardMaterial({ color: 0x1a221e, roughness: 1 }));
      bench.position.y = -0.3;
      bench.add(new THREE.LineSegments(new THREE.EdgesGeometry(bench.geometry), new THREE.LineBasicMaterial({ color: 0x2f3b35 })));
      world.add(bench);
    }
    for (const l of r.links) {
      const a = nodes.get(l.a), b = nodes.get(l.b);
      if (!a || !b) continue;
      const k = { ...linkMeshes(l, a.pos, b.pos), link: l };
      const key = `${l.a}-${l.b}`;
      k.hub.userData.link = key;
      k.meshes.forEach((m) => { m.userData.link = key; world.add(m); });
      world.add(k.hub);
      links.set(key, k);
    }
    highlight();
    fit();
  }

  function highlight() {
    const focus = S.link ? links.get(S.link)?.link : null;
    nodes.forEach((n, id) => {
      const on = S.sel === id || (focus && (focus.a === id || focus.b === id));
      n.edge.material.color.set(on ? 0xf0b44c : LANE[n.item.lane] ?? 0xd8c49c);
      n.plinth.material.color.set(on ? 0x34302a : 0x232c27);
    });
    links.forEach((k, key) => {
      const dim = (S.link && S.link !== key) || (S.sel && k.link.a !== S.sel && k.link.b !== S.sel);
      k.mat.opacity = dim ? 0.18 : (k.link.status === 'ukjent' ? 0.75 : 1);
      k.hub.scale.setScalar(S.link === key ? 1.5 : 1);
      k.hub.material.transparent = !!dim;
      k.hub.material.opacity = dim ? 0.25 : 1;
    });
    dirty = true;
  }

  // ---------- Test run visuals. They show what the report checked; they are not a physical simulation. ----------
  function clearRun() {
    while (fx.children.length) { const c = fx.children.pop(); dispose(c); }
    nodes.forEach((n) => { n.group.scale.set(1, 1, 1); n.group.position.y = 0; });
    beams = [];
    weakest = null;
    anims.length = 0;
    dirty = true;
  }
  const ease = (t) => 1 - Math.pow(1 - t, 4);
  function anim(dur, fn, delay = 0) {
    if (reduce) { fn(1, 1); dirty = true; return; }
    const start = performance.now() + delay;
    anims.push((now) => {
      const t = Math.min(1, Math.max(0, (now - start) / dur));
      fn(ease(t), t);
      return t < 1;
    });
  }
  function ring(n, color, r = 1.9) {
    const m = new THREE.Mesh(new THREE.RingGeometry(r - 0.12, r, 48), new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.9, side: THREE.DoubleSide, depthWrite: false }));
    m.rotation.x = -Math.PI / 2;
    m.position.copy(n.pos).setY(0.25);
    fx.add(m);
    return m;
  }
  function runStep(kind, r) {
    const all = [...nodes.values()];
    if (kind === 'inspect') {
      all.forEach((n, i) => anim(420, (e) => { const s = 1 + Math.sin(e * Math.PI) * 0.12; n.group.scale.set(s, s, s); }, i * 90));
    } else if (kind === 'validate') {
      all.forEach((n, i) => {
        const v = r.verification.per[n.item.id] || { mangler: 0 };
        const m = ring(n, v.mangler ? 0xe2c064 : 0x6cc08b);
        anim(500, (e) => { m.scale.setScalar(0.4 + e * 0.6); m.material.opacity = 0.9 * e; }, i * 80);
      });
    } else if (kind === 'compat') {
      [...links.values()].forEach((k, i) => {
        const dot = new THREE.Mesh(new THREE.SphereGeometry(0.2, 12, 10), new THREE.MeshBasicMaterial({ color: COL[k.link.status] }));
        dot.position.copy(k.curve.getPoint(reduce ? 1 : 0));
        fx.add(dot);
        anim(900, (e) => dot.position.copy(k.curve.getPoint(e)), i * 160);
        anim(400, (e) => k.hub.scale.setScalar(1 + Math.sin(e * Math.PI) * 0.8), i * 160 + 700);
      });
    } else if (kind === 'module') {
      if (cfg.theme === 'lys') {
        // Beam length follows the stated lumen on a log scale. An illustration, and labelled as one.
        r.items.filter((i) => i.lane === 'kilde').forEach((it, i) => {
          const n = nodes.get(it.id);
          if (!n) return;
          const lm = r.bars?.rows.find((x) => x.id === it.id)?.value;
          if (!lm) { beams.push({ pos: n.pos.clone().setY(n.top + 3.9), text: '? lm' }); return; }
          const len = 1.5 + Math.log10(lm) * 1.6;
          const cone = new THREE.Mesh(new THREE.ConeGeometry(len * 0.38, len, 32, 1, true),
            new THREE.MeshBasicMaterial({ color: 0xffd98a, transparent: true, opacity: 0.16, depthWrite: false, side: THREE.DoubleSide }));
          cone.geometry.translate(0, -len / 2, 0);
          cone.rotation.x = Math.PI; // opens upwards from the product
          cone.position.copy(n.pos).setY(n.top + 2.9);
          fx.add(cone);
          anim(700, (e) => cone.scale.set(1, Math.max(0.01, e), 1), i * 120);
          beams.push({ pos: n.pos.clone().setY(n.top + 3 + len), text: `${num(lm)} lm` });
        });
        $('#lab-runbar').textContent += ` · ${L.illustration}`;
      } else if (cfg.theme === 'kjetting') {
        const w = r.stats.find((s) => s.weakest);
        const n = w && nodes.get(w.weakest);
        if (n) {
          weakest = n;
          weakestLabel = w.label; // "lowest known" while any part lacks a working load
          const m = ring(n, 0xe5483b, 2.1);
          anim(1100, (e, t) => m.scale.setScalar(1 + Math.sin(t * Math.PI * 3) * 0.08));
        }
      } else {
        all.forEach((n, i) => anim(420, (e) => { n.group.position.y = Math.sin(e * Math.PI) * 0.3; }, i * 80));
      }
    } else if (kind === 'result' && bench) {
      const color = { klar: 0x6cc08b, mangler: 0xe2c064, konflikt: 0xe5483b, tom: 0x7d8a84 }[r.verdict];
      const e = new THREE.LineSegments(new THREE.EdgesGeometry(bench.geometry), new THREE.LineBasicMaterial({ color, transparent: true, opacity: reduce ? 1 : 0 }));
      e.position.copy(bench.position);
      fx.add(e);
      anim(600, (k) => { e.material.opacity = k; });
    }
    dirty = true;
  }

  // ---------- Labels written as HTML over the canvas ----------
  const v3 = new THREE.Vector3();
  function project(p) {
    v3.copy(p).applyMatrix4(world.matrixWorld).project(cam);
    return [((v3.x + 1) / 2) * view.clientWidth, ((1 - v3.y) / 2) * view.clientHeight];
  }
  function drawLabels() {
    let h = '';
    nodes.forEach((n) => {
      const [x, y] = project(n.pos.clone().setY(n.top + 3.1));
      const it = n.item;
      h += `<span class="lab-lbl${S.sel === it.id ? ' sel' : ''}${weakest === n ? ' warn' : ''}" style="left:${x}px;top:${y}px">${esc(short(it.name))}<small>${it.qty > 1 ? `${it.qty} × ` : ''}${kr(it.price_ore)}${weakest === n ? ` · ${esc(weakestLabel)}` : ''}</small></span>`;
    });
    laneLabels.forEach((l) => { const [x, y] = project(l.pos); h += `<span class="lab-lane" style="left:${x}px;top:${y}px">${esc(L.lanes[l.k] || l.k)}</span>`; });
    beams.forEach((b) => { const [x, y] = project(b.pos); h += `<span class="lab-beam" style="left:${x}px;top:${y}px">${esc(b.text)}</span>`; });
    labels.innerHTML = h;
  }

  function frame(now) {
    for (let i = anims.length - 1; i >= 0; i--) if (!anims[i](now)) anims.splice(i, 1);
    if (autoRotate) { theta += 0.004; placeCam(); }
    if (dirty || anims.length) {
      world.updateMatrixWorld();
      gl.render(sc, cam);
      drawLabels();
      dirty = false;
    }
    requestAnimationFrame(frame);
  }

  // ---------- Pointer: drag to turn, click to pick, wheel to zoom ----------
  const ray = new THREE.Raycaster(), mouse = new THREE.Vector2();
  let down = null;
  gl.domElement.addEventListener('pointerdown', (e) => { down = { x: e.clientX, t: theta, moved: false }; gl.domElement.setPointerCapture(e.pointerId); });
  gl.domElement.addEventListener('pointermove', (e) => {
    if (!down) return;
    const dx = e.clientX - down.x;
    if (Math.abs(dx) > 4) down.moved = true;
    if (down.moved) { theta = down.t - dx * 0.008; placeCam(); }
  });
  gl.domElement.addEventListener('pointerup', (e) => {
    if (!down) return;
    const moved = down.moved;
    down = null;
    if (moved) return;
    const r = gl.domElement.getBoundingClientRect();
    mouse.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
    ray.setFromCamera(mouse, cam);
    const hit = ray.intersectObjects(world.children, true).find((h) => h.object.userData.id || h.object.userData.link);
    if (hit?.object.userData.link) selectLink(hit.object.userData.link);
    else selectItem(hit ? hit.object.userData.id : null);
  });
  gl.domElement.addEventListener('wheel', (e) => {
    e.preventDefault();
    cam.zoom = Math.max(0.3, Math.min(4, cam.zoom * (e.deltaY > 0 ? 0.9 : 1.1)));
    userZoom = true;
    cam.updateProjectionMatrix();
    dirty = true;
  }, { passive: false });
  view.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; view.classList.add('drop'); });
  view.addEventListener('dragleave', () => view.classList.remove('drop'));
  view.addEventListener('drop', (e) => {
    e.preventDefault();
    view.classList.remove('drop');
    const id = parseInt(e.dataTransfer.getData('text/plain'), 10);
    if (INV.has(id)) add(id);
  });
  $('.lab-tools').addEventListener('click', (e) => {
    const b = e.target.closest('[data-view]');
    if (!b) return;
    const a = b.dataset.view;
    if (a === 'iso') { theta = Math.PI / 4; autoRotate = false; $('[data-view=rotate]').setAttribute('aria-pressed', 'false'); placeCam(); fit(); }
    if (a === 'rotate') { autoRotate = !autoRotate && !reduce; b.setAttribute('aria-pressed', String(autoRotate)); }
    if (a === 'in' || a === 'out') { userZoom = true; cam.zoom = Math.max(0.3, Math.min(4, cam.zoom * (a === 'in' ? 1.2 : 1 / 1.2))); cam.updateProjectionMatrix(); dirty = true; }
    if (a === 'fit') fit();
  });

  placeCam();
  resize();
  requestAnimationFrame(frame);
  return { build, highlight, runStep, clearRun };
})();

// ---------- Start: the address, else the first example, so the lab never opens empty ----------
renderSaved();
const start = fromUrl() || cfg.presets[0]?.lines || {};
S.lines = Object.entries(start).map(([id, qty]) => ({ id: +id, qty: +qty })).filter((l) => INV.has(l.id));
changed();
