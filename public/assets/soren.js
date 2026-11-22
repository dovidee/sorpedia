// Søren, the drawn animation. Each pose is a sprite sheet in /assets/soren/ (60 frames in a 10 x 6 grid, 12 fps,
// prepared by scripts/soren_sprites.py): the whole figure on pages, and a face sheet per pose in the chat, so his
// expressions come from the drawing itself. The sheets are transparent and sit on any page colour.
// Usage: <div data-soren="pose"></div> or Soren2D.mount(el, { kind }). Controls: set('idle'|'think'|'talk'), wave(), happy(), sad().
// data-soren values:
//   (empty)  the default pose
//   head     the face, in the chat and on the Ask Søren button; changes expression with set() and the one-off poses
//   a pose   default, wave, thinking, cart, hammer, lumber, pointing, shrug or celebrating
//   leaf     one of his leaves as an icon, for links like "Spør Søren om dette produktet"
// A frame runs only while the figure is on screen and the tab is visible; with reduced motion, or data-still, the
// first frame stands. The sheets are never changed here: the motion on top is CSS on the box. Two stacked layers
// crossfade from one frame to the next, the box breathes with a soft squash and stretch, and it springs on a new
// expression, on hover and on a click (app.css, .soren-sprite).
window.Soren2D = (() => {
  const FILES = { default: '01_default', wave: '02_wave', thinking: '03_thinking', cart: '04_cart', hammer: '05_hammer',
    lumber: '06_lumber', pointing: '07_pointing', shrug: '08_shrug', celebrating: '09_celebrating' };
  const LABELS = { cart: 'Søren med en tom tralle', hammer: 'Søren som snekrer', lumber: 'Søren med en planke' };
  const STATE_POSE = { idle: 'default', think: 'thinking', talk: 'pointing' };
  const ONCE_MS = { wave: 2500, celebrating: 2700, shrug: 4500 };
  const FPS = 12, FRAMES = 60;
  const calm = matchMedia('(prefers-reduced-motion: reduce)');
  const LEAF_ICON = '<img class="soren-leaf" src="/assets/brand/sorpedia-leaf.png" alt="">';
  const url = (pose, head) => head ? `/assets/soren/soren_head_${pose}.png` : `/assets/soren/soren_${FILES[pose]}_sprite.png`;

  const mount = (el, opts = {}) => {
    const head = opts.kind === 'head';
    const home = FILES[opts.kind] ? opts.kind : 'default';
    const still = !!opts.still;
    const box = document.createElement('div');
    box.className = head ? 'soren-sprite soren-face' : 'soren-sprite';
    if (head) box.setAttribute('aria-hidden', 'true');
    else { box.setAttribute('role', 'img'); box.setAttribute('aria-label', LABELS[home] || 'Søren, maskoten'); }
    const layers = [document.createElement('i'), document.createElement('i')];
    box.append(...layers);
    el.replaceChildren(box);

    let frame = 0, last = 0, seen = false, raf = 0, pose = '', state = 'idle', timer = 0, top = 0;
    const at = () => `${(frame % 10) * 100 / 9}% ${Math.floor(frame / 10) * 20}%`;
    // The next frame goes on the hidden layer, which then shows over the old one while the old one fades out
    const draw = () => {
      top ^= 1;
      layers[top].style.backgroundPosition = at();
      layers[top].classList.add('on');
      layers[top ^ 1].classList.remove('on');
    };
    const boing = () => {
      if (still || calm.matches) return;
      box.classList.remove('boing');
      void box.offsetWidth;
      box.classList.add('boing');
    };
    box.addEventListener('animationend', (e) => { if (e.animationName === 's-boing') box.classList.remove('boing'); });
    box.addEventListener('pointerenter', boing);
    box.addEventListener('pointerdown', boing);
    const show = (p) => {
      if (p === pose) return;
      const first = !pose;
      pose = p; frame = 0;
      layers.forEach((l) => { l.style.backgroundImage = `url(${url(p, head)})`; l.style.backgroundPosition = at(); });
      layers[top].classList.add('on');
      if (!first) boing();
    };
    const tick = (t) => {
      raf = 0;
      if (!seen || still || calm.matches || document.hidden || !box.isConnected) return;
      if (t - last >= 1000 / FPS) { last = t; frame = (frame + 1) % FRAMES; draw(); }
      raf = requestAnimationFrame(tick);
    };
    const go = () => { if (!raf) raf = requestAnimationFrame(tick); };
    new IntersectionObserver(([e]) => { seen = e.isIntersecting; go(); }).observe(box);
    document.addEventListener('visibilitychange', go);

    // On a page the pose is fixed; the chat face follows the state and plays one-off poses for a moment
    const rest = () => show(head ? STATE_POSE[state] || 'default' : home);
    const set = (s) => { clearTimeout(timer); state = s; rest(); };
    const once = (p) => { if (still || calm.matches) return; clearTimeout(timer); show(p); timer = setTimeout(rest, ONCE_MS[p]); };
    rest();
    if (opts.greet) once('wave');
    const none = () => {};
    return { el: box, kind: opts.kind || 'default', set, wave: () => once('wave'), happy: () => once('celebrating'),
      sad: () => once('shrug'), gust: none, surprised: none, blink: none, get state() { return state; } };
  };

  const auto = () => document.querySelectorAll('[data-soren]:not([data-soren-on])').forEach((el) => {
    el.dataset.sorenOn = '1';
    const kind = el.dataset.soren || 'default';
    if (kind === 'leaf') { el.innerHTML = LEAF_ICON; return; }
    el.soren = mount(el, { kind, greet: el.hasAttribute('data-greet'), still: el.hasAttribute('data-still') });
  });
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', auto) : auto();
  return { mount, auto, LEAF_ICON };
})();
