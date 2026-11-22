// Control Tower insight view (feature 022). Plain SVG charts over /api/tarn/innsikt; every figure is counted from stored rows.
// Forecasts are a linear trend with a 95 % band, withheld below the minimum number of days the server reports.
import { L, $, esc, num, dayFmt, dFmt, getJson, fillIn, post } from 'tower-lib';
import { openDrill } from 'tower-drill';

const I = L.ins;
const pct = (x, d = 0) => (x === null || x === undefined ? null : `${num(x * 100, d)} %`);
const day = (iso) => dayFmt.format(new Date(`${iso}T12:00:00Z`));
const nice = (max) => {
  if (max <= 0) return 1;
  const p = 10 ** Math.floor(Math.log10(max)), f = max / p;
  return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * p;
};
const svg = (w, h, body, label) => `<svg viewBox="0 0 ${w} ${h}" role="img" aria-label="${esc(label)}" preserveAspectRatio="none">${body}</svg>`;
const panel = (cls, title, body, note = '') => `<section class="ins-p ${cls}"><h2>${esc(title)}</h2>${body}${note ? `<p class="ins-note">${note}</p>` : ''}</section>`;

// ---------- Tiles: the week against the week before ----------
function tile(k, [now, prev], rate, spark, part = null) {
  const v = now === null ? null : rate ? pct(now, 1) ?? '0 %' : num(now, now % 1 ? 1 : 0);
  let delta = '';
  if (now !== null && prev !== null && prev !== undefined && (rate || prev > 0)) {
    const d = rate ? (now - prev) * 100 : ((now - prev) / prev) * 100;
    // For the no-result and hand-over rates a rise is bad, so the colour follows what the change means
    const up = d > 0, flat = Math.abs(d) < 0.5, good = ['zeroRate', 'escalateRate'].includes(k) ? !up : up;
    delta = `<span class="ins-d ${flat ? '' : good ? 'up' : 'down'}">${flat ? '±0' : `${up ? '+' : ''}${num(d, rate ? 1 : 0)}${rate ? ' pp' : ' %'}`}</span>`;
  } else if (!rate && now > 0) delta = `<span class="ins-d">${esc(I.prevZero)}</span>`;
  const of = part ? `<small class="ins-of">${esc(fillIn(I.of[k] ?? I.ofAny, { a: num(part[0]), b: num(part[1]) }))}</small>` : '';
  return `<div class="ins-tile"><span>${esc(I.tiles[k])}</span>${v === null ? `<b class="none">${esc(I.noData)}</b>` : `<b>${v}</b>`}${of}${delta}${spark ? sparkline(spark) : ''}</div>`;
}
function sparkline(ys) {
  const w = 120, h = 28, max = Math.max(1, ...ys);
  const pts = ys.map((y, i) => `${((i / (ys.length - 1)) * w).toFixed(1)},${(h - 2 - (y / max) * (h - 4)).toFixed(1)}`).join(' ');
  return `<svg class="ins-spark" viewBox="0 0 ${w} ${h}" aria-hidden="true" preserveAspectRatio="none"><polyline points="${pts}"/></svg>`;
}

// ---------- Time series with a trend forecast ----------
// days: labels, ys: counts, fc: Stats::forecast result fitted on the last fc.have points, bars: columns instead of a line
function series(days, ys, fc, { bars = false, label }) {
  const W = 640, H = 108, l = 34, r = 10, t = 12, b = 20;
  const ahead = fc.ok ? fc.points.length : 0, n = ys.length + ahead;
  const peak = Math.max(1, ...ys, ...(fc.ok ? fc.points.map((p) => p.hi) : [])), top = Math.ceil(peak / nice(peak / 4)) * nice(peak / 4);
  const x = (i) => l + ((i + 0.5) / n) * (W - l - r), y = (v) => t + (1 - v / top) * (H - t - b), bw = ((W - l - r) / n) * 0.62;
  let g = '';
  const tick = nice(top / 4);
  for (let v = 0; v <= top + 1e-9; v += tick) {
    g += `<line class="ins-grid" x1="${l}" x2="${W - r}" y1="${y(v)}" y2="${y(v)}"/><text class="ins-ax" x="${l - 6}" y="${y(v) + 3}" text-anchor="end">${num(v, v % 1 ? 1 : 0)}</text>`;
  }
  const step = Math.ceil(days.length / 6);
  days.forEach((d, i) => { if (i % step === 0 || i === days.length - 1) g += `<text class="ins-ax" x="${x(i)}" y="${H - 8}" text-anchor="middle">${esc(day(d))}</text>`; });
  if (fc.ok) {
    // Band and dashed forecast after the last day; the fitted trend under the observed range
    const s = ys.length - fc.have, f = fc.fit;
    const band = fc.points.map((p, i) => `${x(ys.length + i)},${y(p.hi)}`).concat(fc.points.map((p, i) => `${x(ys.length + i)},${y(p.lo)}`).reverse()).join(' ');
    g += `<rect class="ins-fzone" x="${x(ys.length) - (W - l - r) / n / 2}" y="${t}" width="${(ahead / n) * (W - l - r)}" height="${H - t - b}"/>`;
    g += `<polygon class="ins-band" points="${band}"/>`;
    g += `<line class="ins-trend" x1="${x(s)}" y1="${y(Math.max(0, f.a))}" x2="${x(ys.length - 1)}" y2="${y(Math.max(0, f.a + f.b * (fc.have - 1)))}"/>`;
    g += `<polyline class="ins-fc" points="${[[ys.length - 1, Math.max(0, f.a + f.b * (fc.have - 1))], ...fc.points.map((p, i) => [ys.length + i, p.y])].map(([i, v]) => `${x(i)},${y(v)}`).join(' ')}"/>`;
  }
  if (bars) ys.forEach((v, i) => { if (v > 0) g += `<rect class="ins-bar" x="${x(i) - bw / 2}" y="${y(v)}" width="${bw}" height="${y(0) - y(v)}" rx="1.5"/>`; });
  else {
    g += `<polygon class="ins-area" points="${x(0)},${y(0)} ${ys.map((v, i) => `${x(i)},${y(v)}`).join(' ')} ${x(ys.length - 1)},${y(0)}"/>`;
    g += `<polyline class="ins-line" points="${ys.map((v, i) => `${x(i)},${y(v)}`).join(' ')}"/>`;
    g += `<circle class="ins-dot" cx="${x(ys.length - 1)}" cy="${y(ys[ys.length - 1])}" r="4"/>`;
  }
  // Hover columns: one per day, forecast days included
  const cw = (W - l - r) / n;
  ys.forEach((v, i) => { g += `<rect class="ins-hit" x="${x(i) - cw / 2}" y="${t}" width="${cw}" height="${H - t - b}" data-tip="${esc(`${day(days[i])}: ${num(v)}`)}"/>`; });
  if (fc.ok) fc.points.forEach((p, i) => { g += `<rect class="ins-hit" x="${x(ys.length + i) - cw / 2}" y="${t}" width="${cw}" height="${H - t - b}" data-tip="${esc(`${I.forecast} +${i + 1}: ${num(p.y, 1)} (${num(p.lo, 1)} ${I.to} ${num(p.hi, 1)})`)}"/>`; });
  // The last value is written at the end of the line, and the week is summed above the chart against the week before
  const lastV = ys[ys.length - 1] ?? 0;
  g += `<text class="ins-end" x="${x(ys.length - 1)}" y="${y(lastV) - 9}" text-anchor="middle">${num(lastV)}</text>`;
  const sum = (a) => a.reduce((t, v) => t + v, 0), a7 = sum(ys.slice(-7)), b7 = sum(ys.slice(-14, -7));
  const head = `<p class="ins-sum"><b>${num(a7)}</b> <span class="ins-d ${a7 > b7 ? 'up' : a7 < b7 ? 'down' : ''}">${b7 ? `${a7 >= b7 ? '+' : ''}${num(((a7 - b7) / b7) * 100)} %` : ''}</span>
    <span>${esc(fillIn(I.vs, { b: num(b7) }))}</span></p>`;
  const legend = `<p class="ins-leg"><i class="k-act${bars ? ' bar' : ''}"></i>${esc(I.actual)}${fc.ok ? `<i class="k-trend"></i>${esc(I.trend)}<i class="k-fc"></i>${esc(I.forecast)}<i class="k-band"></i>${esc(I.band)}` : ''}</p>`;
  return `<div class="ins-sumrow">${head}${legend}</div><div class="ins-plot">${svg(W, H, g, label)}</div>`;
}
const fcNote = (fc) => (fc.ok
  ? fillIn(fc.mae === null ? I.fitOnly : I.fitLine, { r2: num(fc.fit.r2, 2), mae: fc.mae === null ? '' : num(fc.mae, 2) })
  : fillIn(I.needData, { need: fc.need, have: fc.have }));

// ---------- Funnel: share of the first step, and conversion from the step before ----------
function funnel(steps) {
  const first = Math.max(1, steps[0].n);
  return `<ol class="ins-fun">${steps.map((s, i) => {
    const prev = i ? steps[i - 1].n : null;
    const conv = prev ? fillIn(I.ofPrev, { p: pct(s.n / prev, 1) }) : '';
    return `<li data-tip="${esc(`${I.steps[s.k]}: ${num(s.n)}${conv ? `, ${conv}` : ''}`)}"><span>${esc(I.steps[s.k])}</span>
      <div class="ins-track"><i style="width:${Math.max(1.5, (s.n / first) * 100)}%"></i></div><b>${num(s.n)}</b><small>${esc(conv)}</small></li>`;
  }).join('')}</ol>`;
}

// ---------- Histogram with mean, median and the middle half ----------
function histogram(v) {
  if (!v.n) return '';
  const W = 640, H = 110, l = 6, r = 6, t = 8, b = 20, lo = v.bins[0].from, hi = v.bins[v.bins.length - 1].to;
  const top = Math.max(...v.bins.map((x) => x.n)), x = (k) => l + ((k - lo) / (hi - lo || 1)) * (W - l - r), y = (n) => t + (1 - n / top) * (H - t - b);
  let g = `<rect class="ins-iqr" x="${x(v.q1)}" y="${t}" width="${x(v.q3) - x(v.q1)}" height="${H - t - b}"/>`;
  v.bins.forEach((bn) => {
    g += `<rect class="ins-bar" x="${x(bn.from) + 1}" y="${y(bn.n)}" width="${Math.max(1, x(bn.to) - x(bn.from) - 2)}" height="${y(0) - y(bn.n)}" rx="1.5"
      data-tip="${esc(`${num(bn.from)} ${I.to} ${num(bn.to)} kr: ${bn.n}`)}"/>`;
  });
  g += `<line class="ins-mean" x1="${x(v.mean)}" x2="${x(v.mean)}" y1="${t}" y2="${H - b}"/><line class="ins-median" x1="${x(v.median)}" x2="${x(v.median)}" y1="${t}" y2="${H - b}"/>`;
  g += `<text class="ins-ax" x="${l}" y="${H - 6}">${num(lo)} kr</text><text class="ins-ax" x="${W - r}" y="${H - 6}" text-anchor="end">${num(hi)} kr</text>`;
  return `<p class="ins-leg"><i class="k-mean"></i>${esc(I.mean)} ${num(v.mean)} kr<i class="k-median"></i>${esc(I.median)} ${num(v.median)} kr<i class="k-iqr"></i>${esc(I.iqr)}</p>
    <div class="ins-plot">${svg(W, H, g, I.values)}</div>
    <dl class="ins-dl"><dt>${esc(I.sd)}</dt><dd>${num(v.sd)} kr</dd><dt>${esc(I.iqr)}</dt><dd>${num(v.q1)} ${esc(I.to)} ${num(v.q3)} kr</dd><dt>n</dt><dd>${v.n}</dd></dl>`;
}

// ---------- Donut: share of stored values per source ----------
function donut(rows) {
  const by = {};
  rows.forEach((r) => { by[r.source] = (by[r.source] || 0) + +r.n; });
  const order = ['sortre', 'ansatt', 'utledet', 'bruker'].filter((k) => by[k]);
  const total = order.reduce((s, k) => s + by[k], 0) || 1, R = 70, w = 22, C = 2 * Math.PI * R;
  let off = 0, arcs = '';
  order.forEach((k) => {
    const len = (by[k] / total) * C;
    arcs += `<circle r="${R}" cx="90" cy="90" class="ins-arc" style="stroke:var(--src-${k})" stroke-width="${w}" stroke-dasharray="${Math.max(0, len - 2)} ${C}" stroke-dashoffset="${-off}"
      data-tip="${esc(`${L.sources[k] ?? k}: ${num(by[k])} (${pct(by[k] / total, 1)})`)}" transform="rotate(-90 90 90)"/>`;
    off += len;
  });
  const center = `<text x="90" y="88" text-anchor="middle" class="ins-big">${num(total)}</text><text x="90" y="106" text-anchor="middle" class="ins-ax">${esc(I.valuesWord)}</text>`;
  return `<div class="ins-donut">${svg(180, 180, `<circle r="${R}" cx="90" cy="90" class="ins-ring" stroke-width="${w}"/>${arcs}${center}`, I.coverage)}
    <ul class="ins-key">${order.map((k) => `<li><i style="background:var(--src-${k})"></i>${esc(L.sources[k] ?? k)}<b>${pct(by[k] / total, 1)}</b><small>${num(by[k])}</small></li>`).join('')}</ul></div>`;
}

// ---------- Segmented bar: relations per level ----------
function segmented(rows) {
  const col = { dokumentert: 'var(--src-sortre)', regelutledet: 'var(--src-utledet)', antatt: 'var(--faint)' };
  const total = rows.reduce((s, r) => s + +r.n, 0) || 1;
  const order = ['dokumentert', 'regelutledet', 'antatt'].map((k) => rows.find((r) => r.level === k)).filter(Boolean);
  return `<div class="ins-seg">${order.map((r) => `<i style="flex:${+r.n};background:${col[r.level]}" data-tip="${esc(`${I.levels[r.level] ?? r.level}: ${num(+r.n)} (${pct(+r.n / total, 1)})`)}"></i>`).join('')}</div>
    <ul class="ins-key">${order.map((r) => `<li><i style="background:${col[r.level]}"></i>${esc(I.levels[r.level] ?? r.level)}<b>${pct(+r.n / total, 1)}</b><small>${num(+r.n)}</small></li>`).join('')}</ul>`;
}

// ---------- Meters and ranked bars ----------
// Share of the whole: products for product counts, all stored values for unchecked values
function meters(q, values) {
  return `<ul class="ins-meters">${Object.keys(I.q).map((k) => {
    const of = k === 'unverified' ? values : q.products;
    const share = of ? q[k] / of : 0;
    return `<li data-tip="${esc(`${I.q[k]}: ${num(q[k])}`)}"><span>${esc(I.q[k])}</span><b>${num(q[k])}</b>
      <div class="ins-track ${share > 0.5 ? 'bad' : share > 0.1 ? 'mid' : 'good'}"><i style="width:${Math.max(share ? 1.5 : 0, share * 100)}%"></i></div></li>`;
  }).join('')}</ul>`;
}
function ranked(rows, val, label, fmt = num, cls = '') {
  if (!rows.length) return '';
  const max = Math.max(1, ...rows.map(val));
  return `<ol class="ins-rank ${cls}">${rows.map((r) => `<li data-tip="${esc(`${label(r)}: ${fmt(val(r))}`)}"><span>${esc(label(r))}</span>
    <div class="ins-track"><i style="width:${Math.max(1.5, (val(r) / max) * 100)}%"></i></div><b>${fmt(val(r))}</b></li>`).join('')}</ol>`;
}

// ---------- Product opportunities: needs the catalogue does not meet, for staff to review ----------
// A visual feed: the product photo and name first, the issue and the counts second. No photo gives a marked placeholder.
const pimg = (p) => (p?.img ? `<img src="/${esc(p.img)}" alt="" loading="lazy">` : `<span class="ins-noimg" title="${esc(I.oppNoImg)}"></span>`);
function opportunities(rows) {
  if (!rows.length) return `<p class="ins-note">${esc(I.oppNone)}</p>`;
  return `<ol class="ins-opp ins-more">${rows.map((o, i) => {
    const main = o.products[0] ?? null;
    return `<li class="ins-oc${i >= 3 ? ' more' : ''}"><div class="ins-oc-img">${pimg(main)}${o.products.slice(1).map((p) => `<span class="ins-oc-more">${pimg(p)}</span>`).join('')}</div>
    <div class="ins-oc-body"><span class="ins-kind ${esc(o.kind)}">${esc(I.oppKind[o.kind])}</span>
      <b class="ins-oc-name">${main ? esc(main.name) : esc(I.oppNoProduct)}</b>
      <p class="ins-oc-issue">«${esc(o.text)}»</p>
      <span class="ins-meta">${esc(fillIn(I.oppCount, { s: num(o.sessions), n: num(o.n) }))}, ${esc(dFmt.format(new Date(o.last * 1000)).slice(0, 10))}</span>
      <span class="ins-do">${main ? `<a class="tw-btn" href="/produkt/${encodeURIComponent(main.slug)}" target="_blank" rel="noopener">${esc(I.oppView)}</a>` : ''}
      ${o.tasks.length ? o.tasks.map((t) => `<button type="button" class="tw-btn" data-open-task="${+t.id}">${esc(fillIn(I.taskState, { id: t.id, s: L.g.status[t.status] ?? t.status }))}</button>`).join('')
        : `<span class="tw-tag st-${esc(o.review)}">${esc(I.states[o.review] ?? o.review)}</span>
        ${o.review !== 'vurdert' ? `<button type="button" class="tw-btn" data-opp-seen="${o.event}" data-outcome="vurdert">${esc(I.oppDoSeen)}</button>` : ''}
        ${o.review !== 'ignorert' ? `<button type="button" class="tw-btn" data-opp-seen="${o.event}" data-outcome="ignorert">${esc(I.ignore)}</button>` : ''}
        ${o.review === 'vurdert' ? `<button type="button" class="tw-btn" data-opp-task="${o.event}" data-product="${o.product ?? ''}" data-title="${esc(fillIn(I.oppTaskTitle, { kind: I.oppKind[o.kind], text: o.text }))}">${esc(I.oppDoTask)}</button>` : ''}`}</span></div></li>`;
  }).join('')}</ol>${rows.length > 3 ? `<button type="button" class="tw-btn ins-all" data-more>${esc(fillIn(I.showAll, { n: rows.length - 3 }))}</button>` : ''}`;
}
function labInsight(l) {
  if (!l.configs) return `<p class="ins-note">${esc(I.labNone)}</p>`;
  const v = Object.entries(l.verdicts).map(([k, n]) => `${esc(I.labVerdict[k] ?? k)} ${num(n)}`).join(', ');
  return `<dl class="ins-kv"><div><dt>${esc(I.labConfigs)}</dt><dd>${num(l.configs)}</dd></div><div><dt>${esc(I.labAbandoned)}</dt><dd>${num(l.abandoned)}</dd></div></dl>
    <p class="ins-note">${v}</p><p><button type="button" class="tw-btn go" data-drill="byggelab">${esc(I.labOpen)}</button></p>
    <h3>${esc(I.labTop)}</h3>${ranked(l.products.slice(0, 5), (r) => r.n, (r) => r.name)}
    ${l.pairs.length ? `<h3>${esc(I.labPairs)}</h3>${ranked(l.pairs.slice(0, 5), (r) => r.n, (r) => `${r.a} + ${r.b}`)}` : ''}`;
}
function guideDrop(g) {
  return `<dl class="ins-kv"><div><dt>${esc(I.gdStarted)}</dt><dd>${num(g.started)}</dd></div><div><dt>${esc(I.gdDone)}</dt><dd>${num(g.done)}</dd></div></dl>
    <h3>${esc(I.gdDrop)}</h3>${ranked(g.at.slice(0, 5), (r) => r.n, (r) => r.q) || `<p class="ins-note">${esc(I.gdNone)}</p>`}`;
}
// Review and task use the same event actions as the floor inspector (EventDesk); then the view reloads
function insTab(k, focus = false) {
  document.querySelectorAll('#tw-ins [data-ins-tab]').forEach((b) => { const on = b.dataset.insTab === k; b.setAttribute('aria-selected', String(on)); b.tabIndex = on ? 0 : -1; if (on && focus) b.focus(); });
  document.querySelectorAll('#tw-ins .ins-pane').forEach((p) => { p.hidden = p.id !== `ins-pane-${k}`; });
  try { localStorage.setItem('tw-ins-tab', k); } catch { /* storage blocked */ }
}
// Arrow keys move between the tabs, as in any tab list
document.addEventListener('keydown', (e) => {
  const b = e.target.closest?.('#tw-ins [data-ins-tab]');
  if (!b || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
  const all = [...document.querySelectorAll('#tw-ins [data-ins-tab]')], i = all.indexOf(b);
  const j = e.key === 'Home' ? 0 : e.key === 'End' ? all.length - 1 : (i + (e.key === 'ArrowRight' ? 1 : -1) + all.length) % all.length;
  e.preventDefault();
  insTab(all[j].dataset.insTab, true);
});
document.addEventListener('click', async (e) => {
  const tb = e.target.closest('#tw-ins [data-ins-tab]');
  if (tb) { insTab(tb.dataset.insTab); return; }
  const all = e.target.closest('#tw-ins [data-more]');
  if (all) {
    // Show the rest and move focus to the first item revealed, so the keyboard user keeps their place
    const list = all.previousElementSibling;
    list.classList.add('open');
    const first = list.querySelector('.more');
    const target = first?.querySelector('button, a, input') ?? first;
    if (target && !target.matches('button, a, input')) target.tabIndex = -1;
    all.remove();
    target?.focus();
    return;
  }
  const dr = e.target.closest('#tw-ins [data-drill]');
  if (dr) { openDrill(dr.dataset.drill, dr); return; }
  const ot = e.target.closest('#tw-ins [data-open-task]');
  if (ot) { document.dispatchEvent(new CustomEvent('tw:task', { detail: +ot.dataset.openTask })); return; }
  // Missing on the shelf: the line goes on the order list in Gulv, and Transport picks the vehicles
  const add = e.target.closest('#tw-ins [data-want]');
  if (add) {
    add.disabled = true;
    const r = await post('/api/tarn/bestilling', { product: +add.dataset.want, qty: +(add.closest('li').querySelector('input')?.value || 1) });
    add.textContent = r.ok ? I.added : L.failed;
    if (!r.ok) add.disabled = false;
    return;
  }
  const b = e.target.closest('[data-opp-seen], [data-opp-task]');
  if (!b) return;
  b.disabled = true;
  const r = b.dataset.oppSeen ? await post(`/api/tarn/hendelse/${+b.dataset.oppSeen}/sett`, { outcome: b.dataset.outcome ?? 'vurdert' })
    : await post(`/api/tarn/hendelse/${+b.dataset.oppTask}/oppgave`, { title: b.dataset.title, product: b.dataset.product || null });
  if (!r.ok) { b.disabled = false; b.insertAdjacentHTML('afterend', ` <span class="tw-err">${esc(r.status === 403 ? L.noaccess : L.failed)}</span>`); return; }
  showInsight();
});

// ---------- Missing on the shelf of the receiving store: the best sellers first, with stock elsewhere ----------
function missing(m) {
  if (!m) return `<p class="ins-note">${esc(I.missFail)}</p>`;
  if (!m.rows.length) return `<p class="ins-note">${esc(I.missNone)}</p>`;
  const n = Math.min(12, m.rows.length);
  return `<ul class="ins-miss ins-more">${m.rows.slice(0, 12).map((r, i) => `<li${i >= 5 ? ' class="more"' : ''}>${pimg(r)}<span><b>${esc(r.name)}</b>
      <small>${esc(fillIn(I.missMeta, { sold: num(r.sold), n: num(r.elsewhere), s: num(r.stores) }))}</small></span>
      ${r.listed ? `<span class="tw-tag on">${esc(I.listed[r.listed] ?? r.listed)}</span>`
        : r.elsewhere ? `<label class="ins-qty"><span class="tw-sr">${esc(I.qty)}</span><input type="number" min="1" max="99" value="1" inputmode="numeric"></label>
          <button type="button" class="tw-btn" data-want="${+r.id}">${esc(I.addToOrder)}</button>` : `<span class="tw-muted">${esc(I.nowhere)}</span>`}</li>`).join('')}</ul>
    ${n > 5 ? `<button type="button" class="tw-btn ins-all" data-more>${esc(fillIn(I.showAll, { n: n - 5 }))}</button>` : ''}`;
}

// ---------- Page ----------
let busy = false;
export async function showInsight() {
  const box = $('tw-ins');
  if (busy) return;
  busy = true;
  if (!box.innerHTML) box.innerHTML = `<p class="ins-load">${esc(I.loading)}</p>`;
  let d;
  try { d = await getJson('/api/tarn/innsikt'); } catch (e) { box.innerHTML = `<p class="tw-err">${esc(e.message)}</p>`; busy = false; return; }
  busy = false;
  const miss = await getJson('/api/tarn/bestilling/mangler').catch(() => null);
  const T = d.tiles, P = d.parts ?? {}, last14 = (a) => a.slice(-14);
  // Level 1: a few outcomes, each rate with its numerator and denominator; the rest of the week's counts sit in a quieter row
  const main = ['searches', 'zeroRate', 'chat', 'escalateRate', 'purchases', 'accessoryRate'].map((k) => tile(k, T[k], k.endsWith('Rate'),
    k === 'searches' ? last14(d.series.search) : k === 'chat' ? last14(d.series.chat) : null, P[k] ?? null)).join('');
  const minor = ['guides', 'cart', 'toCartMin'].map((k) => tile(k, T[k], false, null)).join('');
  const covered = d.cover.filter((c) => c.days !== null);
  const bk = d.backup ? fillIn(I.backup, { t: dFmt.format(new Date(d.backup.at * 1000)), mb: num(d.backup.bytes / 1048576, 1), n: d.backup.count }) : I.noBackup;
  const open = d.opportunities.filter((o) => o.review === 'ikke').length;
  // One tab at a time, so each part fits the screen; the chosen tab is remembered in this browser
  const tabs = [['att', I.lvAttention, ''], ['trend', I.lvTrends, ''], ['opp', I.oppTab, open || ''], ['use', I.lvUsage, ''], ['data', I.lvData, '']];
  let cur = 'att';
  try { cur = localStorage.getItem('tw-ins-tab') || 'att'; } catch { /* storage blocked */ }
  if (!tabs.some(([k]) => k === cur)) cur = 'att';
  const pane = (k, body) => `<div class="ins-pane" role="tabpanel" tabindex="0" id="ins-pane-${k}" aria-labelledby="ins-tab-${k}" ${k === cur ? '' : 'hidden'}>${body}</div>`;
  box.innerHTML = `<header class="ins-head"><h1>${esc(I.title)}</h1><p>${esc(I.week)}</p><p class="ins-backup">${esc(bk)}</p></header>
    <div class="ins-tiles">${main}${minor.replaceAll('class="ins-tile"', 'class="ins-tile minor"')}</div>
    <div class="tw-seg ins-tabs" role="tablist" aria-label="${esc(I.title)}">${tabs.map(([k, t, n]) => `<button type="button" class="tw-btn" role="tab" id="ins-tab-${k}"
      aria-controls="ins-pane-${k}" aria-selected="${k === cur}" tabindex="${k === cur ? 0 : -1}" data-ins-tab="${k}">${esc(t)}${n !== '' ? ` <b class="tw-n">${n}</b>` : ''}</button>`).join('')}</div>
    ${pane('att', `<div class="ins-grid">
      ${panel('w7', I.missTitle, missing(miss), miss ? esc(fillIn(I.missNote, { n: num(miss.total), home: miss.home })) + ' <span class="tw-demo">DEMO</span>' : '')}
      <div class="ins-stack w5">
      ${panel('', I.zero, `${ranked(d.zeroTerms.slice(0, 5), (r) => +r.n, (r) => r.q ?? '') || `<p class="ins-note">${esc(I.zeroNone)}</p>`}
        <p><button type="button" class="tw-btn" data-drill="sok">${esc(I.zeroOpen)}</button></p>`, esc(I.zeroTriage))}
      ${panel('', I.cover, ranked(covered.slice(0, 5), (r) => r.days, (r) => r.name, (v) => (v === 0 ? I.empty : fillIn(I.days, { n: num(v, v < 10 ? 1 : 0) })), 'inv'),
        `${esc(I.coverNote)}${covered.some((c) => +c.demo) ? ` <span class="tw-demo">DEMO</span>` : ''}`)}
      </div></div>`)}
    ${pane('trend', `<div class="ins-grid">
      ${panel('w6', I.searchTrend, series(d.days, d.series.search, d.searchForecast, { label: I.searchTrend }), esc(fcNote(d.searchForecast)))}
      ${panel('w6', I.orders, series(d.orderDays, d.orders, d.orderForecast, { bars: true, label: I.orders }),
        `${esc(fcNote(d.orderForecast))}${d.orderForecast.ok ? ` ${esc(fillIn(I.slope, { b: `${d.orderForecast.fit.b >= 0 ? '+' : ''}${num(d.orderForecast.fit.b * 7, 2)}` }))}.` : ''}
        ${d.ordersDemo ? ` <span class="tw-demo">DEMO</span> ${esc(fillIn(I.demo, { n: d.ordersDemo }))}` : ''}`)}
      ${panel('w6', I.funnel, funnel(d.funnel))}
      ${panel('w6 hist', I.values, histogram(d.orderValues), `${esc(I.meanMedian)}${d.ordersDemo ? ` <span class="tw-demo">DEMO</span> ${esc(fillIn(I.demo, { n: d.ordersDemo }))}` : ''}`)}
    </div>`)}
    ${pane('opp', `<div class="ins-grid">${panel('w12', I.opp, opportunities(d.opportunities), esc(I.oppNote))}</div>`)}
    ${pane('use', `<div class="ins-grid">${panel('w6', I.lab, labInsight(d.lab)) + panel('w6', I.gd, guideDrop(d.guideDrop))}</div>`)}
    ${pane('data', `<div class="ins-grid">${panel('w4', I.coverage, donut(d.coverage)) + panel('w4', I.rel, segmented(d.rel)) + panel('w4', I.quality, meters(d.quality, d.coverage.reduce((s, r) => s + +r.n, 0)))}</div>`)}
    <div class="ins-tip" id="ins-tip" role="tooltip" hidden></div>`;
}

// One tooltip for every chart: follows the pointer over any [data-tip]
document.addEventListener('pointermove', (e) => {
  const tip = $('ins-tip');
  if (!tip) return;
  const el = e.target.closest?.('#tw-ins [data-tip]');
  if (!el) { tip.hidden = true; return; }
  tip.textContent = el.dataset.tip;
  tip.hidden = false;
  const r = tip.getBoundingClientRect();
  tip.style.left = `${Math.min(innerWidth - r.width - 8, e.clientX + 14)}px`;
  tip.style.top = `${Math.max(8, e.clientY - r.height - 12)}px`;
});
