// Overview (feature 030): the first page of the Control Tower. The state of the shop at a glance, counted on the server
// (src/Overview.php), so the floor and the map keep their full width. Every card leads to the view with the details.
import { L, $, esc, reduce, locale, tFmt, num, kr, fillIn } from 'tower-lib';
import { openDrill } from 'tower-drill';

const O = L.ov;
const box = $('tw-ov');
const rel = new Intl.RelativeTimeFormat(locale, { numeric: 'auto', style: 'short' });
let timer = 0, last = null, played = false, onGo = () => {}, onGreet = () => {};

// "for 3 t siden" style, and a plain duration for "has waited 3 t"
function ago(at, now) {
  const s = Math.max(0, now - at);
  if (s < 3600) return rel.format(-Math.max(1, Math.round(s / 60)), 'minute');
  if (s < 172800) return rel.format(-Math.round(s / 3600), 'hour');
  return rel.format(-Math.round(s / 86400), 'day');
}
function span(s) {
  const u = s < 3600 ? ['minute', s / 60] : s < 172800 ? ['hour', s / 3600] : ['day', s / 86400];
  return new Intl.NumberFormat(locale, { style: 'unit', unit: u[0], unitDisplay: 'short', maximumFractionDigits: 0 }).format(Math.max(1, u[1]));
}
// A figure the first load counts up to. The final value is always in the markup, so without motion nothing changes.
const count = (v, fmt = 'num') => `<span data-count="${v}" data-fmt="${fmt}">${esc(fmt === 'kr' ? kr(v) : num(v))}</span>`;
const pct = (a, b) => (b ? Math.round((a / b) * 100) : null);
const grade = (p, good, mid) => (p === null ? 'none' : p >= good ? 'good' : p >= mid ? 'mid' : 'low');
const badge = (g, text) => `<b class="ov-badge ${g}">${esc(text)}</b>`;
const bar = (p, g) => `<div class="ov-bar ${g}" role="presentation"><i style="--p:${p ?? 0}%"></i></div>`;
const card = (id, title, body, go, extra = '') => `<article class="ov-card${extra}" data-go="${go}" tabindex="0" aria-labelledby="ov-${id}">
  <h2 id="ov-${id}">${esc(title)}</h2>${body}</article>`;

// Semicircle gauge: the arc length is the share, the colour its grade, and the number and badge say the same in text
function gauge(p, g, label) {
  const r = 52, len = Math.PI * r;
  return `<div class="ov-gauge"><svg viewBox="0 0 128 72" aria-hidden="true">
      <path class="trk" d="M12 66a52 52 0 0 1 104 0"/>
      ${p === null ? '' : `<path class="val ${g}" d="M12 66a52 52 0 0 1 104 0" stroke-dasharray="${(len * p) / 100} ${len}"/>`}</svg>
    <b>${p === null ? esc(O.unknown) : `${count(p)}<small>%</small>`}</b></div>${p === null ? '' : badge(g, label)}`;
}

function cards(d) {
  const G = O.grade;
  // Product data
  const dp = pct(d.data.filled, d.data.slots), dg = grade(dp, 75, 50);
  const P = {};
  P.data = card('data', O.data.title, `${gauge(dp, dg, G[dg] ?? '')}${bar(dp, dg)}
    <p class="ov-sub">${esc(fillIn(O.data.sub, { n: num(d.data.filled), m: num(d.data.slots) }))}<br>${esc(fillIn(O.data.more, { u: num(d.data.unverified), r: num(d.data.reports) }))}</p>`, 'innsikt');
  // Deliveries on time
  const v = d.delivery, vp = pct(v.onTime, v.arrived), vg = grade(vp, 90, 70);
  P.del = card('del', O.delivery.title, `${gauge(vp, vg, G[vg] ?? '')}${bar(vp, vg)}
    <p class="ov-sub">${esc(v.arrived ? fillIn(O.delivery.sub, { n: v.onTime, m: v.arrived }) : O.delivery.none)}${v.demo ? ` <span class="ov-demo">${esc(O.demo)}</span>` : ''}<br>
    ${esc(fillIn(O.delivery.road, { n: v.open, l: v.late }))}</p>`, 'transport');
  // Customer cases waiting
  const c = d.cases;
  P.cases = card('cases', O.cases.title, `<p class="ov-big ${c.waiting ? 'amber' : 'ok'}">${count(c.waiting)}</p>
    <p class="ov-lab">${esc(c.waiting ? O.cases.waiting : O.cases.none)}</p>
    <p class="ov-badges">${c.handling ? badge('mid', fillIn(O.cases.handling, { n: c.handling })) : ''}</p>
    ${c.oldest ? `<p class="ov-sub">${esc(fillIn(O.cases.oldest, { t: span(d.now - c.oldest.at) }))}</p>` : ''}`, 'kundeservice');
  // Orders and, for the admin role, money
  const o = d.orders, money = 'ore' in o;
  const row = (k, val, cls = '') => `<li><span>${esc(k)}</span><b class="${cls}">${esc(val)}</b></li>`;
  P.ord = card('ord', money ? O.orders.title : O.orders.titleN, `<p class="ov-big">${money ? count(o.ore, 'kr') : count(o.n)}</p>
    <p class="ov-lab">${esc(O.orders.label)}</p><ul class="ov-rows">
    ${money ? row(O.orders.n, num(o.n)) + row(O.orders.avg, o.n ? kr(o.ore / o.n) : O.unknown) + row(O.orders.refunds, kr(o.refundOre), o.refundOre ? 'red' : '')
      : row(O.orders.refundN, num(o.refunds), o.refunds ? 'red' : '')}</ul>
    ${o.demo ? `<p class="ov-sub"><span class="ov-demo">${esc(fillIn(O.orders.demo, { n: o.demo, m: o.n }))}</span></p>` : ''}`, 'innsikt');
  // Searches with a hit
  const s = d.search, sp = pct(s.n - s.zero, s.n), sg = grade(sp, 90, 75);
  P.srch = card('srch', O.search.title, `<div class="ov-pair"><p class="ov-mid">${sp === null ? esc(O.unknown) : `${count(sp)}<small>%</small>`}</p>
    <p class="ov-side"><b class="${s.zero ? 'amber' : ''}">${num(s.zero)}</b>${esc(O.search.zero)}</p></div>${bar(sp, sg)}
    <p class="ov-sub">${esc(fillIn(O.search.of, { n: num(s.n) }))}</p>`, 'drill:sok');
  // Søren answered without a person
  const q = d.soren, qp = pct(q.n - q.escalated, q.n), qg = grade(qp, 85, 70);
  P.sor = card('sor', O.soren.title, `<div class="ov-pair"><p class="ov-mid">${qp === null ? esc(O.unknown) : `${count(qp)}<small>%</small>`}</p>
    <p class="ov-side"><b class="${q.escalated ? 'amber' : ''}">${num(q.escalated)}</b>${esc(O.soren.esc)}</p></div>${bar(qp, qg)}
    <p class="ov-sub">${esc(fillIn(O.soren.of, { n: num(q.n) }))}</p>`, 'drill:samtaler');
  // The one thing to do first: the oldest waiting case, else a late shipment, else the most common search without a hit
  const T = O.top;
  let top;
  if (c.oldest) {
    top = { g: 'low', tag: T.urgent, title: c.oldest.summary || fillIn(T.case, { id: c.oldest.id }), sub: fillIn(T.case, { id: c.oldest.id }),
      due: fillIn(T.waited, { t: span(d.now - c.oldest.at) }), go: 'kundeservice', act: T.openCase, ticket: c.oldest.id };
  } else if (v.late) {
    top = { g: 'low', tag: T.urgent, title: fillIn(T.late, { n: v.late }), sub: T.lateSub, go: 'transport', act: T.openMap };
  } else if (s.top) {
    top = { g: 'mid', tag: T.watch, title: fillIn(T.miss, { q: s.top.q }), sub: fillIn(T.missSub, { n: s.top.n }), go: 'innsikt', act: T.openIns };
  }
  P.top = card('top', T.title, top ? `${badge(top.g, top.tag)}<p class="ov-what">${esc(top.title)}</p><p class="ov-lab">${esc(top.sub)}</p>
    ${top.due ? `<p class="ov-due">${esc(top.due)}</p>` : ''}<button type="button" class="ov-act"${top.ticket ? ` data-ticket="${top.ticket}"` : ''}>${esc(top.act)}</button>`
    : `${badge('good', G.good)}<p class="ov-what">${esc(T.calm)}</p>`, top?.go ?? 'gulv');
  // Today's route: shipments, ferries and closures
  const R = O.route, F = R.ferry, fg = { normal: 'good', scheduled: 'none', delayed: 'mid', disrupted: 'mid', cancelled: 'low', unknown: 'none' };
  P.route = card('route', R.title, `<div class="ov-pair"><p class="ov-mid">${count(v.open)}</p><p class="ov-side">${esc(R.ships)}</p></div>
    <ul class="ov-rows">${d.ferries.map((f) => `<li><span>${esc(f.name)}</span>${badge(fg[f.status] ?? 'none', F[f.status] ?? F.unknown)}</li>`).join('')}
    <li><span>${esc(R.closed)}</span><b class="${d.roads.closures ? 'amber' : ''}">${num(d.roads.closures)}</b></li>
    <li><span>${esc(R.warn)}</span><b class="${d.roads.warnings ? 'amber' : ''}">${num(d.roads.warnings)}</b></li></ul>
    ${d.roads.demo ? `<p class="ov-sub"><span class="ov-demo">${esc(O.demo)}</span></p>` : ''}`, 'transport');
  // Exceptions and actions first, then operations, then the week's figures (CONTROLTOWER-REDESIGN.md 4.2)
  return ['top', 'cases', 'route', 'del', 'ord', 'srch', 'sor', 'data'].map((k) => P[k]).join('');
}

function activity(d) {
  const A = O.activity;
  const line = (e) => {
    let t, s;
    if (e.kind === 'order') { t = A.order; s = fillIn(A.orderSub, { n: e.a ?? 0, w: e.b ?? '' }) + (e.ore !== null ? `, ${kr(e.ore)}` : ''); }
    else if (e.kind === 'case') { t = A.case; s = e.a ?? ''; }
    else if (e.kind === 'edit') { t = A.edit; s = e.a ?? ''; }
    else if (e.kind === 'arrived') { t = A.arrived; s = fillIn(A.arrivedSub, { ref: e.a, w: e.b ?? '' }); }
    else { t = A.audit[e.a] ?? e.a; s = e.b ?? ''; }
    return `<li class="${e.kind}${e.a === 'backup_fail' ? ' bad' : ''}"><i aria-hidden="true">${O.icons[e.kind] ?? ''}</i>
      <div><b>${esc(t)}</b><span>${esc(s)}${e.demo ? ` <em class="ov-demo">${esc(O.demo)}</em>` : ''}</span></div>
      <time datetime="${new Date(e.at * 1000).toISOString()}">${esc(ago(e.at, d.now))}</time></li>`;
  };
  return `<section class="ov-card ov-wide" aria-labelledby="ov-act"><h2 id="ov-act">${esc(A.title)}</h2>
    ${d.activity.length ? `<ol class="ov-feed">${d.activity.map(line).join('')}</ol>` : `<p class="ov-sub">${esc(A.none)}</p>`}</section>`;
}

function warehouses(d) {
  const W = O.wh, total = d.warehouses.reduce((a, w) => a + w.units, 0);
  const rows = d.warehouses.map((w) => {
    const g = grade(w.cover, 70, 45);
    return `<tr><th scope="row">${esc(w.name)}</th><td>${num(w.skus)}</td><td>${num(w.units)}</td><td>${num(w.orders)}</td>
      <td>${w.road ? `<b class="amber">${num(w.road)}</b>` : '0'}</td>
      <td class="ov-cov" title="${esc(W.grade[g] ?? '')}">${bar(w.cover, g)}<span>${w.cover ?? O.unknown} %</span></td></tr>`;
  }).join('');
  return `<section class="ov-card ov-wide" aria-labelledby="ov-wh"><h2 id="ov-wh">${esc(W.title)} <span class="ov-demo">${esc(W.demo)}</span></h2>
    <div class="ov-scroll"><table class="ov-table"><thead><tr><th scope="col">${esc(W.name)}</th><th scope="col">${esc(W.skus)}</th><th scope="col">${esc(W.units)}</th>
      <th scope="col">${esc(W.orders)}</th><th scope="col">${esc(W.road)}</th><th scope="col">${esc(W.cover)}</th></tr></thead><tbody>${rows}</tbody></table></div>
    <p class="ov-total"><span>${esc(W.total)}</span><b>${num(total)}</b></p></section>`;
}

// Status line under the header: only what needs attention, each with its state in text and a dot
function ticker(d) {
  const K = O.tick, items = [];
  const add = (g, s) => items.push(`<li class="${g}"><i aria-hidden="true"></i>${esc(s)}</li>`);
  add('good', fillIn(K.live, { t: tFmt.format(new Date(d.now * 1000)) }));
  if (d.cases.waiting) add('mid', fillIn(K.waiting, { n: d.cases.waiting }));
  if (d.delivery.late) add('low', fillIn(K.late, { n: d.delivery.late }));
  if (d.delivery.stale) add('mid', fillIn(K.stale, { n: d.delivery.stale }));
  d.ferries.filter((f) => !['normal', 'scheduled'].includes(f.status)).forEach((f) => add(f.status === 'cancelled' ? 'low' : 'mid', fillIn(K.ferry, { name: f.name, s: O.route.ferry[f.status] ?? O.route.ferry.unknown })));
  if (d.roads.closures) add('mid', fillIn(K.closed, { n: d.roads.closures }));
  if (d.search.zero) add('mid', fillIn(K.zero, { n: d.search.zero }));
  if (d.backup) add(d.now - d.backup.at > 26 * 3600 ? 'low' : 'good', fillIn(K.backup, { t: span(d.now - d.backup.at) }));
  else add('low', K.noBackup);
  if (items.length === 1) add('good', K.calm);
  return `<ul class="ov-tick" aria-label="${esc(O.title)}">${items.join('')}</ul>`;
}

// Søren's first line on the page, built from the same counted figures (no model involved)
function greeting(d) {
  const G = O.greet, hr = +new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Oslo', hour: 'numeric', hourCycle: 'h23' }).format(new Date(d.now * 1000));
  const parts = [hr < 10 ? G.morning : hr < 18 ? G.day : G.evening];
  const pl = (f, n) => fillIn(n === 1 ? f.one : f.other, { n });
  if (d.cases.waiting) parts.push(pl(G.cases, d.cases.waiting));
  if (d.delivery.late) parts.push(pl(G.late, d.delivery.late));
  if (!d.cases.waiting && !d.delivery.late) parts.push(G.calm);
  parts.push(G.ask);
  return parts.join(' ');
}

async function load() {
  let d;
  try {
    const r = await fetch('/api/tarn/oversikt');
    if (!r.ok) throw new Error(String(r.status));
    d = await r.json();
  } catch {
    if (!last) box.innerHTML = `<p class="ov-fail" role="status">${esc(O.failed)}</p>`;
    return;
  }
  last = d;
  box.innerHTML = `${ticker(d)}<header class="ov-head"><h1>${esc(O.title)}</h1><p>${esc(O.week)}. ${esc(fillIn(O.updated, { t: tFmt.format(new Date(d.now * 1000)) }))}</p></header>
    <div class="ov-grid">${cards(d)}${activity(d)}${warehouses(d)}</div>`;
  onGreet(greeting(d));
  if (!played) { played = true; if (!reduce) intro(); }
}

// First load only: the cards rise in one after another, each gauge sweeps up to its value, the figures count up and
// the bars fill, all ending on the real value. Refreshes after that change the numbers in place. Skipped entirely
// with reduced motion. Only transform, opacity and the gauge stroke are animated.
const EXPO = 'cubic-bezier(.16, 1, .3, 1)';
function intro() {
  const t0 = performance.now(), runs = [];
  box.querySelectorAll('.ov-card').forEach((c, i) => {
    const delay = Math.min(i, 9) * 55;
    c.animate([{ opacity: 0, transform: 'translateY(12px)' }, { opacity: 1, transform: 'none' }], { duration: 620, delay, easing: EXPO, fill: 'backwards' });
    c.querySelectorAll('.ov-gauge .val').forEach((path) => {
      const [v, len] = path.getAttribute('stroke-dasharray').split(' ').map(Number);
      path.animate([{ strokeDasharray: `0 ${len}` }, { strokeDasharray: `${v} ${len}` }], { duration: 1300, delay: delay + 120, easing: EXPO, fill: 'backwards' });
    });
    c.querySelectorAll('.ov-bar i').forEach((bar) => {
      bar.animate([{ transform: 'scaleX(0)' }, { transform: getComputedStyle(bar).transform }], { duration: 1100, delay: delay + 200, easing: EXPO, fill: 'backwards' });
    });
    c.querySelectorAll('[data-count]').forEach((el) => runs.push({ el, to: +el.dataset.count, kr: el.dataset.fmt === 'kr', delay: delay + 120 }));
  });
  // Count up with the same expo curve as the gauges, so number and arc arrive together
  const ease = (x) => (x >= 1 ? 1 : 1 - 2 ** (-10 * x));
  const dur = 1300;
  function frame(now) {
    let busy = false;
    for (const r of runs) {
      const x = Math.max(0, Math.min(1, (now - t0 - r.delay) / dur));
      const v = r.to * ease(x);
      r.el.textContent = r.kr ? kr(Math.round(v)) : num(Math.round(v));
      if (x < 1) busy = true;
    }
    if (busy) requestAnimationFrame(frame);
  }
  runs.forEach((r) => { r.el.textContent = r.kr ? kr(0) : num(0); });
  requestAnimationFrame(frame);
}

// A card leads to its view, or, for searches and Søren, to the records behind the number
function go(c, ticket) {
  const v = c.dataset.go;
  if (v.startsWith('drill:')) openDrill(v.slice(6), c);
  else onGo(v, ticket);
}
box.addEventListener('click', (e) => {
  const act = e.target.closest('.ov-act');
  const c = e.target.closest('[data-go]');
  if (!c) return;
  go(c, act?.dataset.ticket ? +act.dataset.ticket : null);
});
box.addEventListener('keydown', (e) => {
  const c = e.target.closest('.ov-card[data-go]');
  if (c && e.target === c && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); go(c, null); }
});

/** Shows the page and refreshes it every minute while it is open. go(view, ticket) switches view; greet(text) gives Søren his first line. */
export function showOverview(go, greet) {
  onGo = go; onGreet = greet;
  load();
  clearInterval(timer);
  timer = setInterval(() => { if (!box.hidden && !document.hidden) load(); }, 60000);
}
export function hideOverview() { clearInterval(timer); }
