// Søren as a vector figure, drawn by hand to the proportions of the original character art (960 px canvas).
// Every leaf in the crown is its own shape with its own gentle motion, arms swing from the shoulder,
// and props (trolley, hard hat and hammer, plank) belong to their pose. All motion is CSS transforms in app.css.
// Usage: <div data-soren="pose"></div> or Soren2D.mount(el, { kind }). Controls: set('idle'|'think'|'talk'), wave(), happy(), sad().
// data-soren values:
//   (empty)  the default pose, on the home page. With data-greet he waves first.
//   head     the face and crown in a circle, in the chat and on the Ask Søren button
//   a pose   default, wave, thinking, cart, hammer, lumber, pointing, shrug or celebrating
//   leaf     one of his leaves as an icon, for links like "Spør Søren om dette produktet"
window.Soren2D = (() => {
  const POSES = ['default', 'wave', 'thinking', 'cart', 'hammer', 'lumber', 'pointing', 'shrug', 'celebrating'];
  const LABELS = { cart: 'Søren med en tom tralle', hammer: 'Søren som snekrer', lumber: 'Søren med en planke' };
  const calm = matchMedia('(prefers-reduced-motion: reduce)');
  const STATE_POSE = { think: 'thinking', talk: 'pointing' };
  const ONCE_MS = { wave: 2500, celebrating: 2700, shrug: 4500 };

  // Leaf shapes, base at 0,0 and tip pointing up, 100 units long
  const OVAL = 'M0,0 C-40,-16 -44,-64 0,-100 C44,-64 40,-16 0,0Z';
  const OVAL_HALF = 'M0,0 C40,-16 44,-64 0,-100Z';
  const MAPLE = 'M0,0 L-4,-22 L-30,-18 L-24,-30 L-46,-46 L-34,-50 L-38,-64 L-20,-58 L-16,-66 L-6,-58 L-10,-84 L-2,-80 L0,-96 '
    + 'L2,-80 L10,-84 L6,-58 L16,-66 L20,-58 L38,-64 L34,-50 L46,-46 L24,-30 L30,-18 L4,-22Z';
  const OAK = (() => {
    const side = [];
    for (let i = 0; i <= 40; i++) {
      const t = i / 40, s = Math.sin(Math.PI * t);
      side.push([-(24 * s ** 0.7 + 10 * Math.max(0, Math.sin(5 * Math.PI * t)) * s), -100 * t]);
    }
    const pts = [...side, ...side.slice(0, -1).reverse().map(([x, y]) => [-x, y])];
    return 'M' + pts.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(' L') + 'Z';
  })();
  const SHADES = ['#D92B2E', '#E23A3B', '#CF2429'];
  const RIB = (y1, y2) => `<path class="rib" d="M0,${y1} L0,${y2}"/>`;
  const shape = (type, i) => {
    const fill = `style="fill:${SHADES[i % 3]}"`;
    if (type === 'm') return `<path class="lv" ${fill} d="${MAPLE}"/><path class="rib" d="M0,-4 L0,-78 M0,-24 L-32,-48 M0,-24 L32,-48"/>`;
    if (type === 'k') return `<path class="lv" ${fill} d="${OAK}"/>${RIB(-4, -88)}`;
    if (type === 's') {
      const leaflet = (y, a, l) => `<g transform="translate(0 ${y}) rotate(${a}) scale(${l / 100})"><path class="lv" ${fill} d="${OVAL}"/><path class="dk" d="${OVAL_HALF}"/></g>`;
      return `<path class="stem" d="M0,0 L0,-70"/>${leaflet(-28, -55, 38)}${leaflet(-28, 55, 38)}${leaflet(-50, -45, 34)}${leaflet(-50, 45, 34)}${leaflet(-68, 0, 36)}`;
    }
    return `<path class="lv" ${fill} d="${OVAL}"/><path class="dk" d="${OVAL_HALF}"/>${RIB(-6, -86)}`;
  };
  const leaf = (type, x, y, a, len, i) => `<g transform="translate(${x.toFixed(1)} ${y.toFixed(1)}) rotate(${a}) scale(${(len / 100).toFixed(2)})">`
    + `<g class="lf" style="--d:-${((i * 0.73) % 3).toFixed(2)}s;--t:${(2.6 + ((i * 0.37) % 1.4)).toFixed(2)}s">${shape(type, i)}</g></g>`;

  // The crown: a dome of separate leaves around the top of the trunk. The outer ring sits behind the trunk, the rest in front.
  const ring = (r, angles, len, types, first) => angles.map((a, j) => {
    const rad = a * Math.PI / 180;
    return leaf(types[j % types.length], 487 + r * Math.sin(rad), 300 - r * 0.9 * Math.cos(rad), a, len, first + j);
  }).join('');
  const span = (from, to, step) => Array.from({ length: Math.round((to - from) / step) + 1 }, (_, i) => from + i * step);
  const CROWN_BACK = ring(190, span(-140, 140, 20), 135, ['o', 'o', 'k', 'o', 'm', 'o', 'o', 's', 'o', 'o', 'm', 'o', 'k', 'o', 'o'], 0)
    + ring(150, span(-120, 120, 40), 125, ['m', 'o', 'k', 'o', 'm', 'o', 'k'], 16);
  const CROWN_FRONT = ring(100, span(-110, 110, 27.5), 120, ['o', 'm', 'o', 'o', 's', 'o', 'o', 'k', 'o'], 32)
    + ring(30, span(-75, 75, 30), 110, ['o', 'k', 's', 'o', 'm', 'o'], 44);

  const TRUNK = 'M410,850 C400,790 388,720 386,650 C384,590 378,520 380,470 C368,450 352,428 338,402 C346,396 352,396 356,400 '
    + 'C366,414 376,428 384,436 C386,400 382,360 376,330 C370,305 356,288 344,276 C356,270 366,272 374,280 C392,298 404,310 418,318 '
    + 'C432,300 440,284 446,270 C454,268 462,270 466,276 C466,292 470,306 480,316 C494,300 506,286 516,272 C524,270 530,272 534,278 '
    + 'C538,294 548,306 562,314 C580,300 596,286 614,272 C624,272 632,276 636,282 C620,300 610,320 606,340 C604,380 606,410 610,436 '
    + 'C622,428 640,414 652,400 C658,398 664,400 666,406 C650,428 630,448 614,470 C616,520 610,590 604,650 C600,720 588,790 580,850 '
    + 'L512,850 C506,830 500,815 492,812 C484,815 478,830 474,850Z';
  const TRUNK_SHADE = 'M606,340 C604,380 606,410 610,436 C616,520 610,590 604,650 C600,720 588,790 580,850 L556,850 C566,780 576,700 580,640 C586,560 586,480 582,400 C580,370 590,350 606,340Z';
  const BARK = 'M455,560 c6,30 4,60 -2,90 M520,600 c-4,24 -2,48 4,70 M430,700 c-4,20 -2,40 2,56 M560,480 c4,20 4,40 0,60 M410,520 c3,16 3,30 0,44';
  // A hanging arm with root fingers and a leaf sprig on the shoulder (left side; the right arm is its mirror image)
  const ARM = `<path class="skin" d="M392,610 C360,630 330,670 322,720 C318,750 314,780 318,800 C322,812 330,812 334,804 C336,814 344,818 350,810 `
    + `C354,816 362,816 364,806 C362,786 358,766 356,746 C360,712 372,688 394,676Z"/><path class="bark" d="M340,700 c-6,16 -8,32 -6,46"/>`
    + `<g transform="translate(352 650) rotate(-40) scale(.6)"><g class="lf" style="--d:-1.1s;--t:3.1s">${shape('s', 1)}</g></g>`;
  const MIRROR = 'matrix(-1 0 0 1 974 0)';
  const BOOT = `<path class="boot" d="M396,822 L478,822 L482,900 C482,920 478,932 470,934 L372,934 C352,934 348,920 352,908 C356,896 372,890 392,888Z"/>`
    + `<path class="rim" d="M395,822 L479,822 L480,842 L396,842Z"/><path class="shine" d="M412,856 C410,872 411,884 414,894 M376,912 C384,906 392,904 400,904"/>`;
  const EYES = `<g class="look"><g class="eyes"><g class="open"><ellipse cx="436" cy="440" rx="21" ry="34"/><ellipse cx="538" cy="440" rx="21" ry="34"/>`
    + `<circle class="hi" cx="444" cy="425" r="8"/><circle class="hi" cx="546" cy="425" r="8"/></g>`
    + `<path class="happy" d="M416,446 q20,-28 40,0 M518,446 q20,-28 40,0"/></g></g>`;
  const TROLLEY = `<g class="cart"><path class="metal" d="M673,700 L715,700 M695,700 L707,915 L875,915 M700,770 L740,770 M703,840 L743,840"/>`
    + `<circle class="tyre" cx="732" cy="935" r="20"/><circle class="tyre" cx="855" cy="935" r="20"/><circle class="hub" cx="732" cy="935" r="6"/><circle class="hub" cx="855" cy="935" r="6"/></g>`;
  const HAT = `<g class="hat"><path d="M300,300 C300,170 390,110 487,110 C584,110 674,170 674,300Z"/><path class="ridge" d="M487,116 L487,296 M420,128 C400,170 392,230 392,296 M554,128 C574,170 582,230 582,296"/>`
    + `<path d="M260,300 L714,300 C714,322 700,330 680,330 L294,330 C274,330 260,322 260,300Z"/></g>`;
  const HAMMER = `<g class="hammer"><path class="handle" d="M645,800 L735,690"/><rect class="head" x="-55" y="-17" width="110" height="34" rx="6" transform="translate(735 690) rotate(39)"/></g>`;
  const STUMP = `<g class="stump"><rect class="wood" x="700" y="790" width="100" height="148" rx="8"/><ellipse class="cut" cx="750" cy="792" rx="50" ry="12"/>`
    + `<path class="grain" d="M720,820 v90 M772,830 v80"/><path class="nail" d="M746,792 v-16 M736,776 h20"/></g>`;
  const PLANK = `<g class="plank" transform="rotate(-10 560 760)"><rect class="wood" x="300" y="722" width="520" height="76" rx="6"/>`
    + `<path class="grain" d="M330,744 C420,738 520,750 640,742 M360,776 C470,770 600,782 790,772"/><rect class="cut" x="806" y="722" width="14" height="76" rx="4"/></g>`;

  const svg = (kind, label) => {
    const head = kind === 'head';
    return `<svg class="soren" viewBox="${head ? '317 250 340 340' : '110 0 820 960'}" ${head ? 'aria-hidden="true"' : `role="img" aria-label="${label}"`} xmlns="http://www.w3.org/2000/svg">`
      + `<ellipse class="shadow" cx="487" cy="940" rx="160" ry="16"/>${STUMP}<g class="all">${TROLLEY}`
      + `<g class="crown">${CROWN_BACK}</g><path class="skin flat" d="${TRUNK}"/><path class="shade" d="${TRUNK_SHADE}"/><path class="outline" d="${TRUNK}"/><path class="bark" d="${BARK}"/>`
      + `${BOOT}<g transform="${MIRROR}">${BOOT}</g><g class="crown">${CROWN_FRONT}</g>${EYES}${HAT}${PLANK}`
      + `<g class="arm arm-l"><g class="arm-in">${ARM}</g></g><g class="arm arm-r"><g class="arm-in">${HAMMER}<g transform="${MIRROR}">${ARM}</g></g></g></g></svg>`;
  };
  const LEAF_ICON = '<img class="soren-leaf" src="/assets/brand/sorpedia-leaf.png" alt="">';

  const mount = (el, opts = {}) => {
    const kind = opts.kind || 'full';
    const home = POSES.includes(kind) ? kind : 'default';
    const still = !!opts.still;
    el.innerHTML = svg(kind, LABELS[home] || 'Søren, maskoten');
    const root = el.firstChild;
    if (still) root.classList.add('still');
    let state = 'idle', timer = 0;

    const show = (p) => { root.dataset.pose = p; };
    const rest = () => show(STATE_POSE[state] || home);
    const set = (s) => { clearTimeout(timer); state = s; rest(); };
    // A pose for a moment, then back to what the state calls for
    const once = (p) => { if (still || calm.matches) return; clearTimeout(timer); show(p); timer = setTimeout(rest, ONCE_MS[p]); };
    const blink = () => { root.classList.add('blink'); setTimeout(() => root.classList.remove('blink'), 140); };
    const life = () => {
      if (!root.isConnected) return;
      if (!still && !calm.matches && !document.hidden) blink();
      setTimeout(life, 2600 + Math.random() * 2600);
    };
    const none = () => {};

    rest();
    if (opts.greet) once('wave');
    setTimeout(life, 1500);
    return { el: root, kind, set, wave: () => once('wave'), happy: () => once('celebrating'), sad: () => once('shrug'),
      gust: none, surprised: none, blink, get state() { return state; } };
  };

  const auto = () => document.querySelectorAll('[data-soren]:not([data-soren-on])').forEach((el) => {
    el.dataset.sorenOn = '1';
    const kind = el.dataset.soren || 'full';
    if (kind === 'leaf') { el.innerHTML = LEAF_ICON; return; }
    el.soren = mount(el, { kind, greet: el.hasAttribute('data-greet'), still: el.hasAttribute('data-still') });
  });
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', auto) : auto();
  return { mount, auto, LEAF_ICON };
})();
