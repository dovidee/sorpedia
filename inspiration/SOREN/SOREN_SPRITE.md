# Søren — integration handoff

**Finished Søren animations. Integrate only — never redraw, regenerate, recolour, filter, crop, re-encode or alter Søren or these files. No mouth. Eyes exactly as drawn.**

## Why sprite sheets
The export platform can't deliver transparent video (it turns video into MP4 and drops the alpha channel, and it flattens animated PNG/WebP to a single frame). So each animation is one still RGBA PNG that holds 60 frames. **This is the final format. Don't convert it to video or GIF.**

## File names
Downloaded files may have an extra suffix such as `-AbC12xYz` or `_AbC12xYz` before `.png`. Rename them to the canonical names below. Only rename them; change nothing else.

| pose key | canonical filename | sheet | grid | frame | frames | fps | loop |
|---|---|---|---|---|---|---|---|
| default | soren_01_default_sprite.png | 3600×2160 | 10×6 | 360×360 | 60 | 12 | 5 s seamless |
| wave | soren_02_wave_sprite.png | 3600×2160 | 10×6 | 360×360 | 60 | 12 | 5 s seamless |
| thinking | soren_03_thinking_sprite.png | 3600×2160 | 10×6 | 360×360 | 60 | 12 | 5 s seamless |
| cart | soren_04_cart_sprite.png | 3600×2160 | 10×6 | 360×360 | 60 | 12 | 5 s seamless |
| hammer | soren_05_hammer_sprite.png | 3600×2160 | 10×6 | 360×360 | 60 | 12 | 5 s seamless |
| lumber | soren_06_lumber_sprite.png | 3600×2160 | 10×6 | 360×360 | 60 | 12 | 5 s seamless |
| pointing | soren_07_pointing_sprite.png | 3600×2160 | 10×6 | 360×360 | 60 | 12 | 5 s seamless |
| shrug | soren_08_shrug_sprite.png | 3600×2160 | 10×6 | 360×360 | 60 | 12 | 5 s seamless (one slow shrug, then rest) |
| celebrating | soren_09_celebrating_sprite.png | 3600×2160 | 10×6 | 360×360 | 60 | 12 | 5 s seamless |

Frames run left to right, then top to bottom, with no padding between them.

## Folder
`/public/assets/soren/`

## Web component (no framework)
```html
<soren-mascot pose="wave"></soren-mascot>
<script>
(() => {
  const BASE = '/public/assets/soren/'.replace('/public', ''); // served path; adjust if your server exposes /public
  const POSES = {
    default: 'soren_01_default_sprite.png', wave: 'soren_02_wave_sprite.png',
    thinking: 'soren_03_thinking_sprite.png', cart: 'soren_04_cart_sprite.png',
    hammer: 'soren_05_hammer_sprite.png', lumber: 'soren_06_lumber_sprite.png',
    pointing: 'soren_07_pointing_sprite.png', shrug: 'soren_08_shrug_sprite.png',
    celebrating: 'soren_09_celebrating_sprite.png'
  };
  const COLS = 10, ROWS = 6, FRAMES = 60, FPS = 12;
  class SorenMascot extends HTMLElement {
    static get observedAttributes() { return ['pose']; }
    connectedCallback() {
      if (!this.el) {
        this.el = document.createElement('div');
        Object.assign(this.el.style, {
          width: '100%', maxWidth: '360px', aspectRatio: '1 / 1',
          backgroundRepeat: 'no-repeat', backgroundSize: '1000% 600%',
          backgroundColor: 'transparent', backgroundPosition: '0% 0%'
        });
        this.style.display = 'block';
        this.appendChild(this.el);
        this.io = new IntersectionObserver(([e]) => { this.visible = e.isIntersecting; this.tick(); });
        this.io.observe(this);
      }
      this.load();
    }
    disconnectedCallback() { this.io && this.io.disconnect(); this.io = null; this.el = null; cancelAnimationFrame(this.raf); }
    attributeChangedCallback() { if (this.el) this.load(); }
    load() {
      cancelAnimationFrame(this.raf); this.ready = false;
      const pose = this.getAttribute('pose') || 'default';
      const file = POSES[pose];
      if (!file) { console.error(`soren-mascot: unknown pose "${pose}"`); this.el.style.backgroundImage = 'none'; return; }
      const url = BASE + file, img = new Image();
      img.onload = () => {
        if (!this.el) return;
        this.el.style.backgroundImage = `url("${url}")`;
        this.frame = 0; this.last = 0; this.ready = true; this.show(0); this.tick();
      };
      img.onerror = () => console.error(`soren-mascot: failed to load ${url}`);
      img.src = url;
    }
    show(f) {
      const c = f % COLS, r = Math.floor(f / COLS);
      this.el.style.backgroundPosition = `${c * 100 / 9}% ${r * 100 / 5}%`;
    }
    tick() {
      cancelAnimationFrame(this.raf);
      if (!this.ready || !this.visible) return;
      if (matchMedia('(prefers-reduced-motion: reduce)').matches) { this.show(0); return; }
      const step = (t) => {
        if (!this.last) this.last = t;
        const n = Math.floor((t - this.last) * FPS / 1000);
        if (n > 0) { this.frame = (this.frame + n) % FRAMES; this.last += n * 1000 / FPS; this.show(this.frame); }
        this.raf = requestAnimationFrame(step);
      };
      this.raf = requestAnimationFrame(step);
    }
  }
  customElements.define('soren-mascot', SorenMascot);
})();
</script>
```
Note: files in `/public/assets/soren/` are usually served at `/assets/soren/`. Set `BASE` to match your server.

## React
```jsx
import { useEffect, useRef, useState } from 'react';
const POSES = { default:'soren_01_default_sprite.png', wave:'soren_02_wave_sprite.png', thinking:'soren_03_thinking_sprite.png', cart:'soren_04_cart_sprite.png', hammer:'soren_05_hammer_sprite.png', lumber:'soren_06_lumber_sprite.png', pointing:'soren_07_pointing_sprite.png', shrug:'soren_08_shrug_sprite.png', celebrating:'soren_09_celebrating_sprite.png' };
export function SorenMascot({ pose = 'default', base = '/assets/soren/' }) {
  const ref = useRef(null); const [url, setUrl] = useState(null);
  useEffect(() => {
    setUrl(null);
    const file = POSES[pose]; if (!file) { console.error(`SorenMascot: unknown pose "${pose}"`); return; }
    const u = base + file, img = new Image();
    img.onload = () => setUrl(u); img.onerror = () => console.error(`SorenMascot: failed to load ${u}`); img.src = u;
  }, [pose, base]);
  useEffect(() => {
    const el = ref.current; if (!url || !el) return;
    const show = f => { el.style.backgroundPosition = `${(f % 10) * 100 / 9}% ${Math.floor(f / 10) * 100 / 5}%`; };
    show(0);
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    let raf, last = 0, frame = 0, vis = false;
    const step = t => { if (!last) last = t; const n = Math.floor((t - last) * 12 / 1000);
      if (n > 0) { frame = (frame + n) % 60; last += n * 1000 / 12; show(frame); } raf = requestAnimationFrame(step); };
    const io = new IntersectionObserver(([e]) => { vis = e.isIntersecting; cancelAnimationFrame(raf); if (vis) { last = 0; raf = requestAnimationFrame(step); } });
    io.observe(el);
    return () => { io.disconnect(); cancelAnimationFrame(raf); };
  }, [url]);
  return <div ref={ref} style={{ width: '100%', maxWidth: 360, aspectRatio: '1 / 1', backgroundImage: url ? `url("${url}")` : 'none', backgroundSize: '1000% 600%', backgroundRepeat: 'no-repeat', backgroundColor: 'transparent' }} />;
}
```
