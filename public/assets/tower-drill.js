// KPI drill-downs (feature 032): the records behind a number. A sheet over the page it was opened from, so closing it
// returns to the same place. Every value comes from /api/tarn/innsyn; when a record lacks something it says unknown.
import { cfg, L, $, esc, dFmt, num, kr, fillIn, post, reduce } from 'tower-lib';

const D = L.dr;
const sheet = $('tw-drill');
const can = cfg.role === 'ansatt' || cfg.role === 'admin';
const when = (t) => (t ? dFmt.format(new Date(t * 1000)) : D.unknown);
const thumb = (p, cls = 'dr-img') => (p?.img ? `<img class="${cls}" src="/${esc(p.img)}" alt="" loading="lazy">` : `<span class="${cls} dr-noimg" title="${esc(D.noImg)}" aria-hidden="true"></span>`);
const flag = (on, yes, no) => `<span class="dr-flag ${on ? 'on' : ''}">${esc(on ? yes : no)}</span>`;
// Review state of an observation, apart from task status (CONTROLTOWER-TRIAGE.md 3): a linked task reads «Oppgave #4 · Åpen»
const reviewFlag = (r) => (r.review === 'oppgave' && r.tasks.length
  ? `<span class="dr-flag on">${esc(fillIn(D.taskState, { id: r.tasks[0].id, s: L.g.status[r.tasks[0].status] ?? r.tasks[0].status }))}</span>`
  : `<span class="dr-flag ${r.review === 'vurdert' ? 'on' : ''} st-${esc(r.review)}">${esc(D.states[r.review] ?? r.review)}</span>`);
let state = { kind: null, days: 30, rows: [], pick: null, back: null };

function frame(body) {
  const K = D.kinds[state.kind];
  sheet.innerHTML = `<header class="dr-head"><button type="button" class="tw-btn dr-back" data-close>${esc(D.back)}</button>
      <div><h1>${esc(K.title)}</h1><p class="tw-muted">${esc(K.sub)}</p></div>
      <div class="dr-days" role="group" aria-label="${esc(D.period)}">${[7, 30].map((d) => `<button type="button" class="tw-btn" data-days="${d}" aria-pressed="${state.days === d}">${esc(fillIn(D.days, { n: d }))}</button>`).join('')}</div></header>
    <div class="dr-body">${body}</div>`;
}

// ---------- Searches ----------
function searchList() {
  const z = state.rows.filter((r) => r.zero), hit = state.rows.filter((r) => !r.zero);
  const row = (r) => `<li><button type="button" class="dr-row${state.pick === r.q + r.zero ? ' on' : ''}" data-pick="${esc(r.q + r.zero)}">
      <b class="dr-q">«${esc(r.q)}»</b><span class="dr-n">${num(r.n)}</span>
      <span class="tw-muted">${esc(fillIn(D.searchMeta, { c: num(r.customers), t: when(r.last) }))}${r.zero ? '' : `, ${esc(fillIn(D.hits, { n: num(r.hits) }))}`}</span>
      ${r.zero ? `<span class="dr-tags">${reviewFlag(r)}${r.mapped.length ? `<span class="dr-flag on">${esc(D.mapped)}</span>` : ''}</span>` : ''}</button></li>`;
  return `<section><h2>${esc(D.zero)} <span class="dr-count">${z.length}</span></h2>${z.length ? `<ul class="dr-list">${z.map(row).join('')}</ul>` : `<p class="tw-muted">${esc(D.noneZero)}</p>`}</section>
    <section><h2>${esc(D.withHit)} <span class="dr-count">${hit.length}</span></h2>${hit.length ? `<ul class="dr-list">${hit.map(row).join('')}</ul>` : `<p class="tw-muted">${esc(D.none)}</p>`}</section>`;
}
function searchDetail(r) {
  if (!r) return `<p class="tw-muted dr-hint">${esc(D.pickSearch)}</p>`;
  return `<p class="tw-kicker">${esc(r.zero ? D.zero : D.withHit)}</p><h2 class="dr-big">«${esc(r.q)}»</h2>
    <dl class="tw-dl"><dt>${esc(D.occ)}</dt><dd>${num(r.n)}</dd><dt>${esc(D.customers)}</dt><dd>${num(r.customers)}</dd>
      <dt>${esc(D.first)}</dt><dd>${esc(when(r.first))}</dd><dt>${esc(D.last)}</dt><dd>${esc(when(r.last))}</dd>
      <dt>${esc(D.before)}</dt><dd>${esc(r.before ? D.yes : D.no)}</dd>
      ${r.zero ? `<dt>${esc(D.match)}</dt><dd>${r.mapped.length ? r.mapped.map((p) => `${esc(p.name)} <span class="tw-muted">#${+p.id}</span>`).join('<br>') : esc(D.noMatch)}</dd>
      <dt>${esc(D.status)}</dt><dd>${reviewFlag(r)}</dd>` : `<dt>${esc(D.hitsL)}</dt><dd>${num(r.hits)}</dd>`}
      ${r.tasks.length ? `<dt>${esc(D.tasks)}</dt><dd>${r.tasks.map((t) => `#${+t.id} ${esc(t.title)} <span class="tw-muted">(${esc(L.g.status[t.status] ?? t.status)})</span>`).join('<br>')}</dd>` : ''}</dl>
    <div class="tw-act"><a class="tw-btn" href="/sok?q=${encodeURIComponent(r.q)}" target="_blank" rel="noopener">${esc(D.searchCat)}</a>
      ${r.zero && can ? (r.tasks.length ? `<button type="button" class="tw-btn" data-open-task="${+r.tasks[0].id}">${esc(fillIn(D.openTask, { id: r.tasks[0].id }))}</button>`
        : `${r.review !== 'vurdert' ? `<button type="button" class="tw-btn" data-seen="${+r.event}" data-outcome="vurdert">${esc(D.markSeen)}</button>` : ''}
        ${r.review !== 'ignorert' ? `<button type="button" class="tw-btn" data-seen="${+r.event}" data-outcome="ignorert">${esc(D.ignore)}</button>` : ''}
        ${r.review === 'vurdert' ? `<button type="button" class="tw-btn" data-task="${+r.event}" data-title="${esc(fillIn(D.taskTitle, { q: r.q }))}">${esc(D.makeTask)}</button>` : ''}`) : ''}</div>
    ${r.zero && can && !r.tasks.length ? `<p class="tw-muted dr-why">${esc(r.review === 'vurdert' ? D.taskWhen : D.reviewFirst)}</p>` : ''}<p class="tw-note" aria-live="polite"></p>`;
}

// ---------- Søren conversations ----------
function chatList() {
  if (!state.rows.length) return `<p class="tw-muted">${esc(D.noneChat)}</p>`;
  return `<ul class="dr-list">${state.rows.map((r) => `<li><button type="button" class="dr-row dr-chat${state.pick === r.kind + r.id ? ' on' : ''}" data-pick="${esc(r.kind + r.id)}">
    <b>${r.question ? esc(r.question) : `<span class="tw-muted">${esc(D.noQuestion)}</span>`}</b>
    ${r.answer ? `<span class="dr-answer">${esc(D.soren)}: ${esc(r.answer)}</span>` : ''}
    <span class="dr-tags"><time class="tw-muted">${esc(when(r.last))}</time><span class="tw-muted">${esc(fillIn(D.turns, { n: r.asks }))}</span>
      ${r.ticket ? `<span class="dr-flag warn">${esc(fillIn(D.escalated, { id: r.ticket }))}</span>` : `<span class="dr-flag">${esc(D.notEscalated)}</span>`}
      ${r.reviewed ? `<span class="dr-flag on">${esc(D.read)}</span>` : ''}</span></button></li>`).join('')}</ul>`;
}
async function chatDetail(row) {
  const box = sheet.querySelector('.dr-detail');
  if (!row) { box.innerHTML = `<p class="tw-muted dr-hint">${esc(D.pickChat)}</p>`; return; }
  box.innerHTML = `<p class="tw-muted">${esc(D.loading)}</p>`;
  const r = await fetch(`/api/tarn/innsyn/samtale/${row.kind}/${row.id}`);
  const c = r.ok ? await r.json() : null;
  if (state.pick !== row.kind + row.id) return;
  if (!c) { box.innerHTML = `<p class="tw-err">${esc(D.failed)}</p>`; return; }
  const p = c.product;
  box.innerHTML = `<p class="tw-kicker">${esc(row.kind === 'ticket' ? D.fromTicket : fillIn(D.convo, { id: c.id }))}</p>
    ${p ? `<div class="dr-prod">${thumb(p)}<div><b>${esc(p.name)}</b><span class="tw-muted">#${+p.id} · ${esc(kr(p.price_ore))}</span>
      <a href="/produkt/${encodeURIComponent(p.slug)}" target="_blank" rel="noopener">${esc(D.viewProduct)}</a></div></div>` : `<p class="tw-muted">${esc(D.noProduct)}</p>`}
    ${c.ticket ? `<p class="dr-ticket"><span class="dr-flag warn">${esc(fillIn(D.escalated, { id: c.ticket.id }))}</span> ${esc(L.tstatus?.[c.ticket.status] ?? c.ticket.status)}, ${esc(when(c.ticket.at))}
      <button type="button" class="tw-btn" data-ticket="${+c.ticket.id}">${esc(D.openTicket)}</button></p>` : `<p class="tw-muted">${esc(D.notEscalatedLong)}</p>`}
    <ol class="dr-msgs">${c.messages.length ? c.messages.map((m) => `<li class="${m.role === 'user' ? 'me' : 'bot'}"><span class="tw-muted">${esc(m.role === 'user' ? D.customer : D.soren)}${m.at ? ` · ${esc(when(m.at))}` : ''}</span><p>${esc(m.content)}</p></li>`).join('')
      : `<li class="tw-muted">${esc(D.noMessages)}</li>`}</ol>
    ${row.kind === 'chat' && can ? (c.reviewed ? `<p class="dr-flag on">${esc(D.read)}</p>` : `<div class="tw-act"><button type="button" class="tw-btn" data-read="${+c.id}">${esc(D.markRead)}</button></div>`) : ''}
    <p class="tw-note" aria-live="polite"></p>`;
}

// ---------- Byggelab sessions ----------
const verdictCls = { klar: 'on', mangler: 'warn', konflikt: 'bad', tom: '' };
function labList() {
  if (!state.rows.length) return `<p class="tw-muted">${esc(D.noneLab)}</p>`;
  return `<ul class="dr-lab">${state.rows.map((s, i) => `<li><button type="button" class="dr-labcard${state.pick === String(i) ? ' on' : ''}" data-pick="${i}">
    <span class="dr-stack">${s.products.slice(0, 4).map((p) => thumb(p, 'dr-st')).join('') || thumb(null, 'dr-st')}${s.products.length > 4 ? `<i>+${s.products.length - 4}</i>` : ''}</span>
    <b>${esc(s.name)}</b><span class="tw-muted">${esc(fillIn(D.labMeta, { n: s.products.length, t: when(s.last) }))}${s.price_ore ? ` · ${esc(kr(s.price_ore))}` : ''}</span>
    <span class="dr-tags"><span class="dr-flag ${verdictCls[s.verdict] ?? ''}">${esc(D.verdict[s.verdict] ?? D.unknown)}</span>${outcome(s)}</span></button></li>`).join('')}</ul>`;
}
// Saved, exported, put in the cart or left: never shown as a purchase
function outcome(s) {
  const done = [s.saved && D.saved, s.listed && D.listed, s.cart && D.cart].filter(Boolean);
  return done.length ? done.map((x) => `<span class="dr-flag on">${esc(x)}</span>`).join('') : `<span class="dr-flag">${esc(D.left)}</span>`;
}
function labDetail(s) {
  if (!s) return `<p class="tw-muted dr-hint">${esc(D.pickLab)}</p>`;
  return `<p class="tw-kicker">${esc(s.name)}</p><p>${outcome(s)} <span class="dr-flag ${verdictCls[s.verdict] ?? ''}">${esc(D.verdict[s.verdict] ?? D.unknown)}</span></p>
    <ul class="dr-parts">${s.products.map((p) => `<li>${thumb(p)}<span><b>${esc(p.name)}</b><span class="tw-muted">#${+p.id} · ${esc(kr(p.price_ore))}</span></span></li>`).join('')}</ul>
    <dl class="tw-dl"><dt>${esc(D.total)}</dt><dd>${esc(kr(s.price_ore))} <span class="tw-muted">${esc(D.totalNote)}</span></dd>
      <dt>${esc(D.conflicts)}</dt><dd>${num(s.conflicts)}</dd><dt>${esc(D.unknownLinks)}</dt><dd>${num(s.unknown)}</dd>
      <dt>${esc(D.timeline)}</dt><dd>${esc(fillIn(D.timelineV, { a: when(s.first), b: when(s.last), n: s.checks }))}</dd></dl>
    ${s.missing.length ? `<h3>${esc(D.missing)}</h3><ul class="dr-miss">${s.missing.map((m) => `<li>${esc(m)}</li>`).join('')}</ul><p class="tw-muted">${esc(D.labNotDefect)}</p>` : ''}
    <div class="tw-act">${s.url ? `<a class="tw-btn" href="${esc(s.url)}" target="_blank" rel="noopener">${esc(D.seeSetup)}</a>` : `<p class="tw-err">${esc(D.noSetup)}</p>`}</div>`;
}

const kinds = {
  sok: { url: 'sok', list: searchList, detail: (k) => searchDetail(state.rows.find((r) => r.q + r.zero === k)) },
  samtaler: { url: 'samtaler', list: chatList, detail: null },
  byggelab: { url: 'byggelab', list: labList, detail: (k) => labDetail(state.rows[+k]) },
};

function render() {
  const K = kinds[state.kind];
  frame(`<div class="dr-col">${K.list()}</div><div class="dr-detail" aria-live="polite">${K.detail ? K.detail(state.pick) : ''}</div>`);
  if (!K.detail) chatDetail(state.rows.find((r) => r.kind + r.id === state.pick));
}
async function load() {
  frame(`<p class="tw-muted">${esc(D.loading)}</p>`);
  try {
    const r = await fetch(`/api/tarn/innsyn/${kinds[state.kind].url}?dager=${state.days}`);
    if (!r.ok) throw new Error();
    state.rows = (await r.json()).rows;
  } catch {
    frame(`<p class="tw-err">${esc(D.failed)}</p>`);
    return;
  }
  render();
}

/** Opens the drill-down for sok, samtaler or byggelab. Closing returns focus to the element that opened it. */
export function openDrill(kind, from = document.activeElement, pick = null) {
  state = { kind, days: 30, rows: [], pick, back: from };
  sheet.hidden = false;
  document.body.classList.add('dr-open');
  if (!reduce) sheet.animate([{ opacity: 0, transform: 'translateX(24px)' }, { opacity: 1, transform: 'none' }], { duration: 240, easing: 'cubic-bezier(.16, 1, .3, 1)' });
  load().then(() => sheet.querySelector('.dr-back')?.focus());
}
function close() {
  sheet.hidden = true;
  sheet.innerHTML = '';
  document.body.classList.remove('dr-open');
  state.back?.focus?.();
}

async function action(b, url, body) {
  b.disabled = true;
  const r = await post(url, body);
  const d = await r.json().catch(() => ({}));
  if (!r.ok) {
    b.disabled = false;
    b.closest('.dr-detail').querySelector('.tw-note').textContent = d.error || D.failed;
    return null;
  }
  return d;
}

sheet.addEventListener('click', async (e) => {
  const b = e.target.closest('button');
  if (!b) return;
  if (b.dataset.close !== undefined) close();
  else if (b.dataset.days) { state.days = +b.dataset.days; state.pick = null; load(); }
  else if (b.dataset.pick !== undefined) { state.pick = b.dataset.pick; render(); if (matchMedia('(max-width: 900px)').matches) sheet.querySelector('.dr-detail')?.scrollIntoView({ block: 'start' }); }
  else if (b.dataset.seen) { if (await action(b, `/api/tarn/hendelse/${+b.dataset.seen}/sett`, { outcome: b.dataset.outcome ?? 'vurdert' })) load(); }
  else if (b.dataset.openTask) { close(); document.dispatchEvent(new CustomEvent('tw:task', { detail: +b.dataset.openTask })); }
  else if (b.dataset.task) {
    const d = await action(b, `/api/tarn/hendelse/${+b.dataset.task}/oppgave`, { title: b.dataset.title, product: null });
    if (d) { await load(); sheet.querySelector('.dr-detail .tw-note').textContent = fillIn(L.g.made, { id: d.id }); }
  } else if (b.dataset.read) { if (await action(b, `/api/tarn/innsyn/samtale/${+b.dataset.read}/lest`, {})) load(); }
  else if (b.dataset.ticket) { close(); document.dispatchEvent(new CustomEvent('tw:ticket', { detail: +b.dataset.ticket })); }
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !sheet.hidden) close(); });
