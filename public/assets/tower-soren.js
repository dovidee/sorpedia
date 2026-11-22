// Ask Søren (feature 022): staff ask about the store in plain words. The server counts the facts first and Søren only
// words them; the panel shows the facts with every answer. Nothing is changed or sent from here.
import { L, $, esc, post } from 'tower-lib';

const S = L.soren;
const panel = $('tw-soren'), btn = $('tw-soren-btn');
const log = [];
let busy = false;

panel.innerHTML = `<header class="sr-head"><h2>${esc(S.title)}</h2><button type="button" class="tw-btn" data-close aria-label="${esc(S.close)}">×</button></header>
  <p class="sr-note">${esc(S.note)}</p>
  <ol class="sr-log" id="sr-log" aria-live="polite"></ol>
  <div class="sr-chips">${S.suggest.map((q) => `<button type="button" class="tw-btn" data-q="${esc(q)}">${esc(q)}</button>`).join('')}</div>
  <form class="sr-form" id="sr-form"><label class="tw-sr" for="sr-q">${esc(S.ask)}</label>
    <textarea id="sr-q" rows="2" maxlength="500" placeholder="${esc(S.placeholder)}"></textarea>
    <button type="submit" class="tw-btn go">${esc(S.send)}</button></form>`;

function toggle(open = panel.hidden, focus = true) {
  panel.hidden = !open;
  btn.setAttribute('aria-expanded', String(open));
  document.body.classList.toggle('sr-open', open);
  if (!focus) return;
  if (open) $('sr-q').focus(); else btn.focus();
}

/**
 * Søren is a drawer opened on purpose (CONTROLTOWER-REDESIGN.md 4.2): no view docks him, so closing him gives the
 * page its full width back. Kept as an export so callers need no change; it only closes a docked layout left over.
 */
export function dock() { document.body.classList.remove('sr-dock', 'sr-docked-before'); }
/** Søren's opening line, shown only while nobody has asked anything. */
export function greet(text) {
  if (log.some((m) => m.q)) return;
  log.length = 0;
  log.push({ text, greet: true });
  render();
}
function render() {
  $('sr-log').innerHTML = log.map((m) => m.q
    ? `<li class="sr-q">${esc(m.q)}</li>`
    : `<li class="sr-a${m.err ? ' err' : ''}"><span class="sr-who">Søren</span>${m.pending ? `<span class="sr-think">${esc(S.thinking)}</span>` : esc(m.text)}
      ${m.facts ? `<details><summary>${esc(S.facts)}</summary><pre>${esc(m.facts)}</pre></details>` : ''}</li>`).join('');
  const el = $('sr-log');
  el.scrollTop = el.scrollHeight;
}
async function ask(q) {
  q = q.trim();
  if (!q || busy) return;
  busy = true;
  panel.setAttribute('aria-busy', 'true');
  log.push({ q }, { pending: true });
  render();
  const a = log[log.length - 1];
  try {
    const r = await post('/api/tarn/soren', { q });
    const d = await r.json().catch(() => ({}));
    Object.assign(a, { pending: false, text: r.ok ? d.answer : (d.error ?? L.failed), facts: d.facts ?? '', err: !r.ok });
  } catch {
    Object.assign(a, { pending: false, text: L.failed, err: true });
  }
  busy = false;
  panel.removeAttribute('aria-busy');
  render();
}

btn.addEventListener('click', () => toggle());
panel.addEventListener('click', (e) => {
  const b = e.target.closest('button');
  if (!b) return;
  if ('close' in b.dataset) toggle(false);
  if (b.dataset.q) ask(b.dataset.q);
});
$('sr-form').addEventListener('submit', (e) => { e.preventDefault(); const t = $('sr-q'); ask(t.value); t.value = ''; });
$('sr-q').addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('sr-form').requestSubmit(); } });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) toggle(false); });
