// Søren som animert SVG. Formene (målt fra den originale tegningen) ligger i soren-art.js, laget av scripts/draw_soren.py.
// Formene ligger i soren-art.js. Her settes delene sammen og animeres: kronen svaier, bladene blafrer,
// den vinkende armen vinker, øynene blunker og viser følelser.
// Bruk: <div data-soren="..."></div> eller Soren2D.mount(el, { kind }). Kontroller: set('idle'|'think'|'talk'), wave(), gust(), happy(), sad(), surprised().
// Varianter:
//   full    hele Søren, bare på forsiden
//   head    utsnitt av ansiktet i en sirkel, i chatten og på Spør Søren-knappen
//   basket  hele Søren, står stille med en tom handlekurv (tom handlekurv)
//   leaf    ett av bladene hans som ikon, til lenker som «Spør Søren om dette produktet»
window.Soren2D = (() => {
  const NS = 'http://www.w3.org/2000/svg';
  const A = window.SorenArt;
  const calm = matchMedia('(prefers-reduced-motion: reduce)');
  const [L, R] = A.eyes; // venstre og høyre øye: cx, cy, rx, ry
  const BASKET_C = { body: 'oklch(56.6% .205 26)', rim: 'oklch(47% .178 26)', hole: 'oklch(40% .15 26)' };

  // Hvert blad (eller klump av blader som henger sammen i tegningen) i sin egen gruppe, så det kan blafre.
  // Små blader kan blåse av; de store klumpene blafrer bare litt.
  const leafGroup = (l, i, loose) => {
    const small = l.px < 520;
    return `<g transform="translate(${l.cx} ${l.cy}) rotate(0) scale(1)"${small && !loose ? ` data-leaf="${i}"` : ''}>`
      + `<g class="sl${small ? '' : ' big'}" style="--d:${((i * 0.37) % 2).toFixed(2)}s"><g transform="translate(${-l.cx} ${-l.cy})">${l.svg}</g></g></g>`;
  };
  const CROWN = `<g class="s-crown">${A.leaves.map((l, i) => leafGroup(l, i)).join('')}</g>`;
  const LOOSE = `<g class="s-loose">${A.loose.map((l, i) => leafGroup(l, i, true)).join('')}</g>`;

  // Øynene fra tegningen, pluss glade buer og triste lokk som legges over ved behov
  const EYES = `<g class="s-eyes"><g class="s-eyes-open">${A.eyesSvg}
      <path class="s-lids" d="M${L.cx - L.rx - 3},${L.cy - L.ry * 0.25} L${L.cx + L.rx + 2},${L.cy - L.ry - 3} M${R.cx + R.rx + 3},${R.cy - R.ry * 0.25} L${R.cx - R.rx - 2},${R.cy - R.ry - 3}" stroke="${A.skin}" stroke-width="9" stroke-linecap="round"/></g>
      <path class="s-eyes-happy" d="M${L.cx - L.rx},${L.cy + 2} q${L.rx},-${L.ry} ${L.rx * 2},0 M${R.cx - R.rx},${R.cy + 2} q${R.rx},-${R.ry} ${R.rx * 2},0" stroke="${A.eyeColor}" stroke-width="4.5" fill="none" stroke-linecap="round"/>
    </g>`;
  const BUBBLE = `<g class="s-bubble">
      <circle cx="604" cy="40" r="3.5"/><circle cx="614" cy="27" r="5"/>
      <ellipse cx="638" cy="2" rx="25" ry="16"/>
      <circle class="s-dot" cx="628" cy="2" r="3"/><circle class="s-dot" cx="638" cy="2" r="3"/><circle class="s-dot" cx="648" cy="2" r="3"/>
    </g>`;
  // Tom handlekurv som henger i den høyre hånden
  const BASKET = `<g class="s-basket">
      <path d="M572,302 Q589,276 606,302" stroke="${BASKET_C.rim}" stroke-width="3.2" fill="none" stroke-linecap="round"/>
      <path d="M565,303 h48 l-5,24 q-1,3 -4,3 h-30 q-3,0 -4,-3 Z" fill="${BASKET_C.body}"/>
      <g fill="${BASKET_C.hole}">${[0, 1, 2, 3, 4].map((i) => `<rect x="${574 + i * 7.2}" y="309" width="3.4" height="14" rx="1.7"/>`).join('')}</g>
      <rect x="562" y="300" width="54" height="5.5" rx="2.75" fill="${BASKET_C.rim}"/>
    </g>`;

  const svg = (kind) => {
    if (kind === 'head') {
      return `<svg class="soren soren-head" viewBox="447 76 136 136" aria-hidden="true" xmlns="${NS}"><g class="s-all">${A.body}${CROWN}${EYES}</g></svg>`;
    }
    const basket = kind === 'basket';
    const [x, y, w, h] = A.box;
    const box = basket ? `${x} ${y} ${w} ${h + 6}` : `${x} ${y - 24} ${w + 36} ${h + 24}`;
    return `<svg class="soren soren-${kind}" viewBox="${box}" role="img" aria-label="${basket ? 'Søren med en tom handlekurv' : 'Søren, maskoten'}" xmlns="${NS}">
      ${A.shadow}<g class="s-all">${A.boots}${A.body}${CROWN}${LOOSE}${basket ? BASKET : ''}<g class="s-arm s-arm-r">${A.armR}</g><g class="s-arm s-arm-l">${A.armL}</g>${EYES}</g>
      ${basket ? '' : BUBBLE}<g class="s-fly"></g></svg>`;
  };
  const LEAF_ICON = (() => {
    const l = A.leafIcon, [x, y, w, h] = l.bbox;
    return `<svg class="soren-leaf" viewBox="${x - 1} ${y - 1} ${w + 2} ${h + 2}" aria-hidden="true" xmlns="${NS}">${l.svg}</svg>`;
  })();

  const rnd = (a, b) => a + Math.random() * (b - a);

  const mount = (el, opts = {}) => {
    const kind = opts.kind || 'full';
    el.innerHTML = svg(kind);
    const root = el.querySelector('svg');
    const fly = root.querySelector('.s-fly');
    const still = kind === 'basket' || opts.still;
    if (still) root.classList.add('still');
    let state = 'idle', timers = [];
    const later = (fn, ms) => { const t = setTimeout(fn, ms); timers.push(t); return t; };

    const set = (s) => { state = s; root.dataset.state = s; if (s === 'think') gust(2); };
    const pulse = (cls, ms) => { if (still) return; root.classList.add(cls); later(() => root.classList.remove(cls), ms); };
    const wave = () => { if (!calm.matches && kind === 'full') pulse('waving', 2000); };
    // Følelser vises i øynene: glade buer, triste lokk, store øyne ved overraskelse
    const happy = () => { pulse('glad', 1600); if (!calm.matches && kind === 'full') pulse('hop', 900); };
    const sad = () => pulse('sad', 2600);
    const surprised = () => pulse('wide', 700);
    const blink = () => pulse('blink', 170);

    // Vindkast: noen små blader løsner og flyr av gårde, og gror ut igjen. Hodet i chatten rasler bare med bladene.
    const gust = (n = rnd(2, 4) | 0) => {
      if (calm.matches || still) return;
      root.classList.add('rustle'); later(() => root.classList.remove('rustle'), 1200);
      if (!fly) return;
      const pool = [...root.querySelectorAll('.s-crown [data-leaf]')].filter((g) => !g.classList.contains('gone'));
      for (let k = 0; k < n && pool.length; k++) {
        const g = pool.splice((Math.random() * pool.length) | 0, 1)[0];
        const m = /translate\(([-\d.]+) ([-\d.]+)\)/.exec(g.getAttribute('transform'));
        if (!m) continue;
        const wrap = document.createElementNS(NS, 'g');
        wrap.setAttribute('transform', `translate(${m[1]} ${m[2]})`);
        wrap.innerHTML = `<g class="flying">${g.querySelector('.sl').innerHTML}</g>`;
        fly.append(wrap);
        g.classList.add('gone');
        const dx = rnd(70, 170), dy = rnd(-30, 70), spin = rnd(260, 620) * (Math.random() < 0.5 ? -1 : 1);
        const a = wrap.firstChild.animate([
          { transform: 'translate(0,0) rotate(0deg)', opacity: 1 },
          { transform: `translate(${dx * 0.35}px,${dy * 0.2 - 18}px) rotate(${spin * 0.3}deg)`, opacity: 1, offset: 0.3 },
          { transform: `translate(${dx}px,${dy}px) rotate(${spin}deg)`, opacity: 0 },
        ], { duration: rnd(1500, 2300), easing: 'cubic-bezier(.3,.1,.5,1)', delay: k * 90 });
        a.onfinish = () => wrap.remove();
        later(() => g.classList.remove('gone'), rnd(2600, 4200));
      }
    };

    // Liv i ro: blunker. Hele Søren vinker også av og til og mister et blad nå og da.
    const loop = () => {
      if (!root.isConnected) return timers.forEach(clearTimeout);
      if (!calm.matches && !document.hidden) {
        blink();
        const r = Math.random();
        if (kind === 'full' && state === 'idle' && r < 0.18) gust();
        else if (kind === 'full' && state === 'idle' && r < 0.26 && opts.lively !== false) wave();
      }
      later(loop, rnd(2600, 5200));
    };
    set('idle');
    if (!still && !calm.matches) later(loop, rnd(800, 2000));
    if (opts.greet) later(wave, 300);
    return { el: root, kind, set, wave, gust, happy, sad, surprised, blink, get state() { return state; } };
  };

  const auto = () => document.querySelectorAll('[data-soren]:not([data-soren-on])').forEach((el) => {
    el.dataset.sorenOn = '1';
    const kind = el.dataset.soren || 'full';
    if (kind === 'leaf') { el.innerHTML = LEAF_ICON; return; }
    const c = mount(el, { kind, greet: el.hasAttribute('data-greet'), lively: !el.hasAttribute('data-quiet'), still: el.hasAttribute('data-still') });
    el.soren = c;
    if (kind === 'full') {
      el.addEventListener('mouseenter', () => c.wave());
      el.addEventListener('click', () => { if (!el.closest('a,button')) c.gust(); });
    }
  });
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', auto) : auto();
  return { mount, auto, LEAF_ICON };
})();
