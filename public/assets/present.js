// Demo Hub (feature 033): section keys, the feature filter, the architecture diagram and the theme switch.
// Everything works without this script: the sections are links, the details open by themselves.
const $$ = (s, el = document) => [...el.querySelectorAll(s)];
const secs = $$('.ph-sec');
const navLinks = $$('.ph-nav ol a');

// The section in view is marked in the navigation
const seen = new IntersectionObserver((entries) => {
  for (const e of entries) {
    if (!e.isIntersecting) continue;
    navLinks.forEach((a) => a.setAttribute('aria-current', String(a.hash === `#${e.target.id}`)));
  }
}, { rootMargin: '-45% 0px -50% 0px' });
secs.forEach((s) => seen.observe(s));

const current = () => Math.max(0, secs.findIndex((s) => s.getBoundingClientRect().bottom > innerHeight * 0.4));
const go = (i) => secs[Math.max(0, Math.min(secs.length - 1, i))]?.scrollIntoView({ block: 'start' });

// Keys for the presenter: 1 to 8 jump, arrows and Page keys step, F toggles full screen. Typing in a field is left alone.
document.addEventListener('keydown', (e) => {
  if (e.altKey || e.ctrlKey || e.metaKey || e.target.closest('input, textarea, select, [contenteditable]')) return;
  const n = Number(e.key);
  if (n >= 1 && n <= secs.length) { e.preventDefault(); go(n - 1); }
  else if (['ArrowDown', 'PageDown', 'ArrowRight'].includes(e.key) && !e.target.closest('summary, button')) { e.preventDefault(); go(current() + 1); }
  else if (['ArrowUp', 'PageUp', 'ArrowLeft'].includes(e.key) && !e.target.closest('summary, button')) { e.preventDefault(); go(current() - 1); }
  else if (e.key === 'f' || e.key === 'F') {
    e.preventDefault();
    if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen?.();
  }
});

// Feature filter: by status
const chips = $$('.ph-chip');
chips.forEach((c) => c.addEventListener('click', () => {
  chips.forEach((x) => x.setAttribute('aria-pressed', String(x === c)));
  const f = c.dataset.f;
  $$('.ph-area li').forEach((li) => {
    li.hidden = !(f === 'alle' || li.dataset.st === f);
  });
  $$('.ph-area').forEach((a) => { a.hidden = !$$('li', a).some((li) => !li.hidden); });
}));

// Architecture: a line or a list row shows what flows between two parts and where it is in the code
const why = document.getElementById('ph-why');
const rows = $$('.ph-links button');
function pick(i) {
  const b = rows[i];
  if (!b) return;
  $$('.ph-edge').forEach((g) => g.classList.toggle('on', g.dataset.link === String(i)));
  rows.forEach((r) => r.setAttribute('aria-pressed', String(r === b)));
  const ends = [b.dataset.from, b.dataset.to];
  $$('.ph-node').forEach((n) => n.classList.toggle('on', ends.includes(n.querySelector('.t').textContent)));
  why.innerHTML = '';
  const route = document.createElement('p');
  route.className = 'route';
  route.textContent = `${b.dataset.from} til ${b.dataset.to}`;
  const h = document.createElement('h3');
  h.textContent = b.dataset.label;
  const p = document.createElement('p');
  p.textContent = b.dataset.how;
  why.append(route, h, p);
}
rows.forEach((b) => b.addEventListener('click', () => pick(+b.dataset.link)));
$$('.ph-edge').forEach((g) => {
  g.addEventListener('click', () => pick(+g.dataset.link));
  g.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(+g.dataset.link); } });
});

// Light by default for a projector; dark on request, remembered in this browser only
const themeBtn = document.getElementById('ph-theme');
function theme(dark) {
  document.documentElement.dataset.theme = dark ? 'dark' : 'light';
  themeBtn.setAttribute('aria-pressed', String(dark));
  themeBtn.textContent = dark ? 'Lys visning' : 'Mørk visning';
}
try { theme(localStorage.getItem('sorpedia-hub-theme') === 'dark'); } catch { theme(false); }
themeBtn.addEventListener('click', () => {
  const dark = document.documentElement.dataset.theme !== 'dark';
  theme(dark);
  try { localStorage.setItem('sorpedia-hub-theme', dark ? 'dark' : 'light'); } catch { /* private window: the choice lasts for this page */ }
});
