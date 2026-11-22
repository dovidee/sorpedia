// Shared helpers for the Control Tower modules (feature 022). No framework, no build step.
export const cfg = JSON.parse(document.getElementById('tw-cfg').textContent);
export const L = cfg.L;
export const $ = (id) => document.getElementById(id);
export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
export const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
export const locale = cfg.lang === 'en' ? 'en-GB' : 'nb-NO';
export const tFmt = new Intl.DateTimeFormat(locale, { timeZone: 'Europe/Oslo', hour: '2-digit', minute: '2-digit', second: '2-digit' });
export const dFmt = new Intl.DateTimeFormat(locale, { timeZone: 'Europe/Oslo', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
export const dayFmt = new Intl.DateTimeFormat(locale, { timeZone: 'Europe/Oslo', day: '2-digit', month: '2-digit' });
export const num = (n, d = 0) => new Intl.NumberFormat(locale, { maximumFractionDigits: d, minimumFractionDigits: d }).format(n);
export const kr = (ore) => new Intl.NumberFormat('nb-NO', { style: 'currency', currency: 'NOK' }).format(ore / 100);
export const fillIn = (s, v) => s.replace(/\{(\w+)\}/g, (m, k) => v[k] ?? m);
export const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': cfg.csrf }, body: JSON.stringify(body ?? {}) });
// GET as JSON; a failed request throws with the server's message when it sent one
export async function getJson(url) {
  const r = await fetch(url);
  const j = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(j.error || (r.status === 403 ? L.noaccess : L.failed));
  return j;
}
// Side-profile picture per vehicle type (public/assets/tower/vehicles, OpenMoji, CC BY-SA 4.0); unknown types use the lorry
const VEH = ['varebil', 'lastebil', 'trekkvogn', 'tilhenger'];
export const vehSrc = (type) => `/assets/tower/vehicles/${VEH.includes(type) ? type : 'lastebil'}.svg`;
