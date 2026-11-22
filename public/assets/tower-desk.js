// Control Tower customer service view (feature 022): ticket list, conversation with reply and Søren draft, the
// customer's orders with refunds, and this week's searches without results. Customers are shown by alias only.
import { cfg, L, $, esc, dFmt, tFmt, kr, fillIn, post, getJson } from 'tower-lib';

const C = L.cs;
const D = { tickets: [], searches: [], id: null, t: null, filter: 'apen', stream: null, sel: {}, confirm: null, flash: null };
const canRefund = ['support', 'ansatt', 'admin'].includes(cfg.role);
const canTask = ['ansatt', 'admin'].includes(cfg.role);
const ago = (t) => (t ? dFmt.format(new Date(t * 1000)) : '');

/** Shows the customer service page; with an id, that case is opened (the overview's "Åpne saken"). */
export async function showDesk(openId = null) {
  try {
    const d = await getJson('/api/tarn/kundeservice');
    D.tickets = d.tickets;
    D.searches = d.searches;
  } catch (e) { $('tw-cs').innerHTML = `<p class="tw-err">${esc(e.message)}</p>`; return; }
  if (!$('cs-list')) layout();
  list();
  searches();
  if (openId) open(openId);
  if (D.id === null) {
    const first = D.tickets.find((t) => t.status !== 'lukket') ?? D.tickets[0];
    if (first) open(+first.id); else $('cs-main').innerHTML = `<p class="cs-empty">${esc(C.none)}</p>`;
  }
}

function layout() {
  $('tw-cs').innerHTML = `<div class="cs">
    <aside class="cs-left">
      <div class="cs-tabs" role="tablist">${['apen', 'alle'].map((f) => `<button type="button" role="tab" data-filter="${f}" aria-selected="${f === D.filter}">${esc(C.filters[f])}</button>`).join('')}</div>
      <ol class="cs-list" id="cs-list"></ol>
      <h2>${esc(C.searches)}</h2><p class="cs-hint">${esc(C.searchesHint)}</p><ol class="cs-search" id="cs-search"></ol>
    </aside>
    <section class="cs-main" id="cs-main" aria-live="polite"></section>
    <aside class="cs-right" id="cs-right"></aside></div>`;
  $('tw-cs').addEventListener('click', onClick);
  $('tw-cs').addEventListener('keydown', (e) => {
    if (e.target.id === 'cs-reply' && e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); send(); }
  });
}

function list() {
  const rows = D.tickets.filter((t) => D.filter === 'alle' || t.status !== 'lukket');
  $('cs-list').innerHTML = rows.length ? rows.map((t) => {
    const waiting = t.status !== 'lukket' && t.last_from !== 'support';
    return `<li><button type="button" data-ticket="${+t.id}" aria-current="${+t.id === D.id}" class="${waiting ? 'waiting' : ''}">
      <span class="cs-row1"><b>#${+t.id} ${esc(t.customer ?? C.guest)}</b><span class="cs-st ${esc(t.status)}">${esc(L.tstatus[t.status] ?? t.status)}</span></span>
      <span class="cs-sum">${esc(t.product ?? t.summary ?? '')}</span>
      <span class="cs-meta">${waiting ? `<i class="cs-dot" aria-label="${esc(C.waiting)}"></i>${esc(C.waiting)}, ` : ''}${esc(ago(t.last_at ?? t.at))}</span></button></li>`;
  }).join('') : `<li class="cs-empty">${esc(C.none)}</li>`;
}

function searches() {
  $('cs-search').innerHTML = D.searches.length ? D.searches.map((s) => `<li><span class="cs-q">«${esc(s.q ?? '')}»</span><b>${+s.n}</b>
    ${canTask ? `<button type="button" class="tw-btn" data-task="${+s.event}" data-q="${esc(s.q ?? '')}">${esc(C.task)}</button>` : ''}</li>`).join('')
    : `<li class="cs-empty">${esc(C.noSearches)}</li>`;
}

async function open(id) {
  const keep = D.id === id ? $('cs-reply')?.value ?? '' : '';
  if (D.id !== id) { D.sel = {}; D.confirm = null; D.flash = null; }
  D.id = id;
  list();
  let t;
  try { t = await getJson(`/api/tarn/sak/${id}`); } catch (e) { $('cs-main').innerHTML = `<p class="tw-err">${esc(e.message)}</p>`; return; }
  if (D.id !== id) return;
  D.t = t;
  conversation(t, keep);
  customer(t);
  if (D.stream?.id !== id) {
    D.stream?.es.close();
    const es = new EventSource(`/api/stream?ch=ticket:${id}`);
    es.addEventListener('message', () => { if (D.id === id) open(id); });
    D.stream = { id, es };
  }
}

function conversation(t, keep) {
  const who = (r) => (r === 'user' ? C.customer : 'Søren');
  const chat = t.chat.map((m) => `<li class="cs-b ${m.role === 'user' ? 'them' : 'bot'}"><b>${esc(who(m.role))}</b><p>${esc(m.content)}</p></li>`).join('');
  const thread = t.messages.map((m) => `<li class="cs-b ${m.sender === 'kunde' ? 'them' : 'me'}"><b>${esc(m.sender === 'kunde' ? C.customer : C.staff)}</b>
    <time>${esc(tFmt.format(new Date(m.at * 1000)))}</time><p>${esc(m.body)}</p></li>`).join('');
  const open = t.status !== 'lukket';
  $('cs-main').innerHTML = `<header class="cs-head"><h2>#${+t.id} ${esc(t.customer ?? C.guest)}</h2><span class="cs-st ${esc(t.status)}">${esc(L.tstatus[t.status] ?? t.status)}</span>
      <time>${esc(ago(t.at))}</time>${open ? `<button type="button" class="tw-btn" data-close>${esc(C.close)}</button>` : ''}</header>
    <ol class="cs-thread">
      ${chat ? `<li class="cs-sep">${esc(C.withSoren)}</li>${chat}<li class="cs-sep">${esc(C.handover)}</li>` : ''}
      ${thread || `<li class="cs-sep">${esc(C.noReplies)}</li>`}
    </ol>
    ${open ? `<div class="cs-compose">
      <textarea id="cs-reply" rows="3" aria-label="${esc(C.reply)}" placeholder="${esc(C.placeholder)}">${esc(keep)}</textarea>
      <div class="cs-acts"><button type="button" class="tw-btn" data-draft>${esc(C.draft)}</button><span class="cs-note" id="cs-note" aria-live="polite"></span>
        <button type="button" class="tw-btn go" data-send>${esc(C.send)}</button></div></div>` : `<p class="cs-closed">${esc(C.closed)}</p>`}`;
  const th = $('cs-main').querySelector('.cs-thread');
  th.scrollTop = th.scrollHeight;
}

function customer(t) {
  const prod = t.product_id ? `<p class="cs-prod"><span>${esc(C.product)}</span><a href="/produkt/${encodeURIComponent(t.slug ?? '')}" target="_blank" rel="noopener">${esc(t.product ?? t.product_id)}</a></p>` : '';
  const orders = t.orders.length ? t.orders.map(order).join('') : `<p class="cs-hint">${esc(t.customer ? C.noOrders : C.guestOrders)}</p>`;
  const flash = D.flash ? `<p class="${D.flash.ok ? 'cs-ok' : 'tw-err'}" role="${D.flash.ok ? 'status' : 'alert'}">${esc(D.flash.text)}</p>` : '';
  $('cs-right').innerHTML = `${flash}<h2>${esc(C.customerTitle)}</h2><p class="cs-who">${esc(t.customer ?? C.guest)}</p>${prod}<h2>${esc(C.orders)}</h2>${orders}`;
}

function order(o) {
  const left = (i) => +i.qty - +i.refunded;
  const sel = D.sel[o.id] ?? {};
  const chosen = o.items.reduce((s, i) => s + (sel[i.product_id] ?? 0) * +i.price_ore, 0);
  const all = o.items.reduce((s, i) => s + left(i) * +i.price_ore, 0);
  const refundable = o.status === 'betalt' && all > 0;
  const confirm = D.confirm?.order === +o.id ? D.confirm : null;
  const lines = o.items.map((i) => {
    const n = sel[i.product_id] ?? 0, max = left(i);
    return `<li class="${max ? '' : 'done'}">${i.img ? `<img src="/${esc(i.img)}" alt="" loading="lazy">` : '<span class="cs-noimg"></span>'}
      <span class="cs-line"><b>${esc(i.name)}</b><small>${+i.qty} × ${kr(+i.price_ore)}${+i.refunded ? `, ${esc(fillIn(C.refunded, { n: i.refunded }))}` : ''}</small></span>
      ${refundable && max && canRefund ? `<span class="cs-step"><button type="button" data-step="-1" data-o="${+o.id}" data-p="${+i.product_id}" aria-label="${esc(C.less)}"${n ? '' : ' disabled'}>−</button>
        <output>${n}</output><button type="button" data-step="1" data-o="${+o.id}" data-p="${+i.product_id}" aria-label="${esc(C.more)}"${n < max ? '' : ' disabled'}>+</button></span>` : ''}</li>`;
  }).join('');
  const acts = !canRefund || !refundable ? '' : confirm
    ? `<div class="cs-confirm" role="alert"><p>${esc(fillIn(C.confirm, { sum: kr(confirm.sum), id: o.id }))}</p>
        <button type="button" class="tw-btn" data-cancel>${esc(C.cancel)}</button><button type="button" class="tw-btn danger" data-do>${esc(C.confirmBtn)}</button></div>`
    : `<div class="cs-ref"><button type="button" class="tw-btn" data-refund="sel" data-o="${+o.id}"${chosen ? '' : ' disabled'}>${esc(fillIn(C.refundSel, { sum: kr(chosen) }))}</button>
        <button type="button" class="tw-btn" data-refund="all" data-o="${+o.id}">${esc(fillIn(C.refundAll, { sum: kr(all) }))}</button></div>`;
  return `<article class="cs-order"><header><b>${esc(fillIn(C.order, { id: o.id }))}</b>${+o.demo ? '<span class="tw-demo">DEMO</span>' : ''}
      <span class="cs-st ${esc(o.status)}">${esc(C.ostatus[o.status] ?? o.status)}</span></header>
    <p class="cs-hint">${esc(ago(o.at))}, ${esc(o.payment)}, ${kr(+o.total_ore)}</p><ul class="cs-lines">${lines}</ul>${acts}</article>`;
}

async function onClick(e) {
  const b = e.target.closest('button');
  if (!b) return;
  if (b.dataset.filter) {
    D.filter = b.dataset.filter;
    document.querySelectorAll('.cs-tabs button').forEach((x) => x.setAttribute('aria-selected', String(x === b)));
    list();
  } else if (b.dataset.ticket) open(+b.dataset.ticket);
  else if ('send' in b.dataset) send();
  else if ('draft' in b.dataset) draft(b);
  else if ('close' in b.dataset) {
    b.disabled = true;
    await fetch(`/support/${D.id}/lukk`, { method: 'POST', headers: { 'X-CSRF': cfg.csrf } });
    await showDesk();
    open(D.id);
  } else if (b.dataset.step) {
    const o = +b.dataset.o, p = +b.dataset.p;
    D.sel[o] ??= {};
    D.sel[o][p] = Math.max(0, (D.sel[o][p] ?? 0) + +b.dataset.step);
    customer(D.t);
  } else if (b.dataset.refund) {
    D.flash = null;
    const o = D.t.orders.find((x) => +x.id === +b.dataset.o);
    const all = b.dataset.refund === 'all';
    const lines = all ? null : Object.fromEntries(Object.entries(D.sel[o.id] ?? {}).filter(([, n]) => n > 0));
    const sum = o.items.reduce((s, i) => s + (all ? +i.qty - +i.refunded : (lines[i.product_id] ?? 0)) * +i.price_ore, 0);
    D.confirm = { order: +o.id, lines, sum };
    customer(D.t);
    $('cs-right').querySelector('[data-cancel]')?.focus();
  } else if ('cancel' in b.dataset) { D.confirm = null; customer(D.t); }
  else if ('do' in b.dataset) refund(b);
  else if (b.dataset.task) task(b);
}

async function send() {
  const ta = $('cs-reply'), note = $('cs-note'), body = ta.value.trim();
  if (!body) { ta.focus(); return; }
  note.className = 'cs-note';
  const r = await post(`/api/support/${D.id}`, { body });
  if (!r.ok) { note.className = 'cs-note tw-err'; note.textContent = L.failed; return; }
  ta.value = '';
  await showDesk();
  open(D.id);
}

// Søren writes a draft into the box; nothing is sent until staff press Send
async function draft(b) {
  const ta = $('cs-reply'), note = $('cs-note');
  b.disabled = true;
  note.className = 'cs-note';
  note.textContent = L.drafting;
  try {
    const r = await post(`/api/tarn/sak/${D.id}/utkast`);
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error ?? L.failed);
    ta.value = d.draft;
    ta.rows = 6;
    ta.focus();
    note.textContent = L.drafted;
  } catch (err) { note.className = 'cs-note tw-err'; note.textContent = err.message; }
  b.disabled = false;
}

async function refund(b) {
  const c = D.confirm;
  b.disabled = true;
  const r = await post(`/api/tarn/sak/${D.id}/refusjon`, c.lines ? { order: c.order, lines: c.lines } : { order: c.order });
  const d = await r.json().catch(() => ({}));
  D.confirm = null;
  if (!r.ok) { D.flash = { ok: false, text: d.error ?? L.failed }; customer(D.t); return; }
  D.sel = {};
  D.flash = { ok: true, text: fillIn(C.refundDone, { sum: kr(d.total_ore) }) };
  await showDesk();
  await open(D.id);
}

async function task(b) {
  b.disabled = true;
  const r = await post(`/api/tarn/hendelse/${+b.dataset.task}/oppgave`, { title: fillIn(C.taskTitle, { q: b.dataset.q }), product: null });
  const d = await r.json().catch(() => ({}));
  b.replaceWith(Object.assign(document.createElement('span'), { className: r.ok ? 'cs-ok' : 'tw-err', textContent: r.ok ? fillIn(C.taskMade, { id: d.id }) : (d.error ?? L.failed) }));
}

