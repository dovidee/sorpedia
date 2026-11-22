// Guided tour for the harder pages (Byggelab, Control Tower). Non-blocking: a soft spotlight and a small card beside
// the element being explained. Clicks pass through to the page, nothing is locked, focus is never trapped.
// Shown once per page on the first visit (browser storage, a convenience only), and again from the page's own button
// or with ?omvisning in the address.
//
// A step: { sel, title, text, before?, done? }. `sel` may be missing (the card is then centred). `before` runs when the
// step opens (for example switching a view). `done` is polled; when it returns true the tour moves on by itself, so the
// customer learns by doing the step instead of reading about it. "Neste" always works too.

const KEY = (name) => `sorpedia-tour-${name}`;
const store = {
  get(k) { try { return localStorage.getItem(k); } catch { return null; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch { /* private window */ } },
};
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const fill = (s, v) => String(s).replace(/\{(\w+)\}/g, (_, k) => (v[k] ?? ''));
// Read when the module loads: a page may rewrite its address (Byggelab keeps the setup there) before the tour starts
const FORCED = new URLSearchParams(location.search).has('omvisning');
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
const visible = (el) => { if (!el) return false; const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; };
// The first visible match, since a page can carry the same control twice (desktop and phone header)
const find = (sel) => (sel ? [...document.querySelectorAll(sel)].find(visible) ?? null : null);

/**
 * Sets up the tour for one page. Returns { start } for the page's own "Omvisning" button.
 * L: { of: '{i} av {n}', next, back, close, finish, doIt }.
 */
export function tour(name, steps, L) {
  let at = -1, timer = 0, spot = null, card = null;

  function place() {
    const s = steps[at];
    const el = find(s.sel);
    const pad = 6, gap = 12, vw = innerWidth, vh = innerHeight;
    const cw = Math.min(340, vw - 32);
    card.style.width = `${cw}px`;
    const ch = card.offsetHeight;
    if (!visible(el)) {
      spot.hidden = true;
      card.style.left = `${(vw - cw) / 2}px`;
      card.style.top = `${Math.max(16, (vh - ch) / 2)}px`;
      return;
    }
    const r = el.getBoundingClientRect();
    spot.hidden = false;
    // The spotlight never covers more than the window, so a large panel still leaves the card room
    const x = Math.max(4, r.left - pad), y = Math.max(4, r.top - pad);
    spot.style.transform = `translate(${x}px, ${y}px)`;
    spot.style.width = `${Math.min(vw - 8, r.right + pad) - x}px`;
    spot.style.height = `${Math.min(vh - 8, r.bottom + pad) - y}px`;
    // Card: below, above, right, left of the element, else bottom right corner
    const fits = [
      [r.left, r.bottom + gap, r.bottom + gap + ch < vh],
      [r.left, r.top - gap - ch, r.top - gap - ch > 0],
      [r.right + gap, r.top, r.right + gap + cw < vw],
      [r.left - gap - cw, r.top, r.left - gap - cw > 0],
    ].find((f) => f[2]) ?? [vw - cw - 16, vh - ch - 16];
    card.style.left = `${Math.min(vw - cw - 16, Math.max(16, fits[0]))}px`;
    card.style.top = `${Math.min(vh - ch - 16, Math.max(16, fits[1]))}px`;
  }

  function draw() {
    const s = steps[at];
    const waiting = typeof s.done === 'function';
    card.innerHTML = `<p class="tour-n">${esc(fill(L.of, { i: at + 1, n: steps.length }))}</p>
      <h2 id="tour-h">${esc(s.title)}</h2><p id="tour-t">${esc(s.text)}</p>
      ${waiting ? `<p class="tour-do">${esc(L.doIt)}</p>` : ''}
      <div class="tour-acts">
        ${at > 0 ? `<button type="button" data-tour="back">${esc(L.back)}</button>` : '<span></span>'}
        <button type="button" class="tour-go" data-tour="next">${esc(at === steps.length - 1 ? L.finish : L.next)}</button>
      </div>
      <button type="button" class="tour-x" data-tour="close" aria-label="${esc(L.close)}">×</button>`;
    place();
  }

  function go(i) {
    if (i < 0) return;
    if (i >= steps.length) return stop();
    at = i;
    try { steps[at].before?.(); } catch { /* a step that cannot prepare still explains */ }
    // Let a view switch or a panel open settle before measuring
    setTimeout(() => { if (at === i) { draw(); if (!reduce) find(steps[at].sel)?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' }); } }, 120);
  }

  function tick() {
    if (at < 0) return;
    const s = steps[at];
    let done = false;
    try { done = typeof s.done === 'function' && s.done(); } catch { done = false; }
    if (done) { go(at + 1); return; }
    place();
  }

  function stop() {
    at = -1;
    clearInterval(timer);
    spot?.remove(); card?.remove();
    spot = card = null;
    store.set(KEY(name), 'done');
    removeEventListener('resize', tick);
    removeEventListener('keydown', onKey);
  }

  function onKey(e) { if (e.key === 'Escape' && at >= 0) stop(); }

  function start() {
    if (at >= 0) return;
    spot = document.createElement('div');
    spot.className = 'tour-spot';
    spot.setAttribute('aria-hidden', 'true');
    card = document.createElement('section');
    card.className = 'tour-card';
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-modal', 'false');
    card.setAttribute('aria-labelledby', 'tour-h');
    card.setAttribute('aria-describedby', 'tour-t');
    card.addEventListener('click', (e) => {
      const b = e.target.closest('[data-tour]');
      if (!b) return;
      if (b.dataset.tour === 'next') go(at + 1);
      else if (b.dataset.tour === 'back') go(at - 1);
      else stop();
    });
    document.body.append(spot, card);
    addEventListener('resize', tick);
    addEventListener('keydown', onKey);
    timer = setInterval(tick, 300);
    go(0);
  }

  if (FORCED || !store.get(KEY(name))) setTimeout(start, 600);
  return { start };
}
