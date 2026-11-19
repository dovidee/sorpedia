# Søren mascot — handoff for Claude

**These are finished Søren mascot animations. Integrate only — do not redraw, regenerate or alter Søren.**

## Standing rules
- Søren has **no mouth**. Eyes stay exactly as drawn.
- **No** CSS filters, recolouring, tinting, drop-shadows, borders, cropping or added features. Display as-is, 1:1, `object-fit: contain`.
- Backgrounds are transparent; place him on any page colour. A soft built-in ground shadow is part of the asset.
- Every clip is a **5.04 s seamless loop** (24 fps, silent). `shrug` = one slow shrug, then rest, then loops.

## Files → `/public/assets/soren/`
| Pose | Files | Size px | Duration |
|---|---|---|---|
| `default` | `soren_01_default.webm` (2.9 MB) · `soren_01_default.webp` (6.7 MB) · `soren_01_default_poster.png` | 960×960 | 5.04 s, 24 fps |
| `wave` | `soren_02_wave.webm` (3.4 MB) · `soren_02_wave.webp` (6.8 MB) · `soren_02_wave_poster.png` | 960×960 | 5.04 s, 24 fps |
| `thinking` | `soren_03_thinking.webm` (2.1 MB) · `soren_03_thinking.webp` (6.5 MB) · `soren_03_thinking_poster.png` | 960×960 | 5.04 s, 24 fps |
| `cart` | `soren_04_cart.webm` (2.2 MB) · `soren_04_cart.webp` (5.5 MB) · `soren_04_cart_poster.png` | 720×720 | 5.04 s, 24 fps |
| `hammer` | `soren_05_hammer.webm` (1.1 MB) · `soren_05_hammer.webp` (3.9 MB) · `soren_05_hammer_poster.png` | 720×720 | 5.04 s, 24 fps |
| `lumber` | `soren_06_lumber.webm` (2.1 MB) · `soren_06_lumber.webp` (5.3 MB) · `soren_06_lumber_poster.png` | 720×720 | 5.04 s, 24 fps |
| `pointing` | `soren_07_pointing.webm` (1.3 MB) · `soren_07_pointing.webp` (7.1 MB) · `soren_07_pointing_poster.png` | 960×960 | 5.04 s, 24 fps |
| `shrug` | `soren_08_shrug.webm` (2.9 MB) · `soren_08_shrug.webp` (6.9 MB) · `soren_08_shrug_poster.png` | 960×960 | 5.04 s, 24 fps |
| `celebrating` | `soren_09_celebrating.webm` (4.0 MB) · `soren_09_celebrating.webp` (5.9 MB) · `soren_09_celebrating_poster.png` | 960×960 | 5.04 s, 24 fps |

- `.webm` = VP9 with alpha (Chrome, Edge, Firefox).
- `.webp` = animated WebP with alpha, infinite loop (Safari / iOS fallback).
- `_poster.png` = first frame, RGBA (reduced-motion + poster).

## Web component (framework-free, paste once)
```html
<script>
(() => {
  const BASE = '/assets/soren/';
  const POSES = { default:'01_default', wave:'02_wave', thinking:'03_thinking', cart:'04_cart',
    hammer:'05_hammer', lumber:'06_lumber', pointing:'07_pointing', shrug:'08_shrug', celebrating:'09_celebrating' };
  // Safari/iOS can't render VP9 alpha even when canPlayType says yes → use WebP there.
  const ua = navigator.userAgent;
  const isSafari = /Safari\//.test(ua) && !/Chrome|Chromium|CriOS|Edg|FxiOS|Android/.test(ua) || /iPad|iPhone|iPod/.test(ua);
  const vp9Alpha = !isSafari && !!document.createElement('video').canPlayType('video/webm; codecs="vp9"');
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');

  class SorenMascot extends HTMLElement {
    static observedAttributes = ['pose', 'label'];
    connectedCallback() { this.render(); reduce.addEventListener?.('change', this._r = () => this.render()); }
    disconnectedCallback() { reduce.removeEventListener?.('change', this._r); }
    attributeChangedCallback() { if (this.isConnected) this.render(); }
    render() {
      const f = BASE + 'soren_' + (POSES[this.getAttribute('pose')] || POSES.default);
      const alt = this.getAttribute('label') || 'Søren, the Sør-Tre mascot';
      const css = 'display:block;width:100%;height:100%;object-fit:contain;background:transparent';
      const root = this.shadowRoot || this.attachShadow({ mode: 'open' });
      const media = reduce.matches
        ? `<img src="${f}_poster.png" alt="${alt}" style="${css}">`
        : vp9Alpha
          ? `<video autoplay muted loop playsinline preload="auto" poster="${f}_poster.png" aria-label="${alt}" style="${css}"><source src="${f}.webm" type='video/webm; codecs="vp9"'></video>`
          : `<img src="${f}.webp" alt="${alt}" style="${css}">`;
      root.innerHTML = `<style>:host{display:inline-block;width:240px;max-width:100%;aspect-ratio:1/1;background:transparent}</style>${media}`;
    }
  }
  customElements.get('soren-mascot') || customElements.define('soren-mascot', SorenMascot);
})();
</script>

<soren-mascot pose="wave"></soren-mascot>
<soren-mascot pose="pointing" style="width:clamp(120px,25vw,320px)"></soren-mascot>
```
Poses: `default wave thinking cart hammer lumber pointing shrug celebrating`.

## React variant
```tsx
import { useEffect, useState } from 'react';

const POSES = { default:'01_default', wave:'02_wave', thinking:'03_thinking', cart:'04_cart', hammer:'05_hammer',
  lumber:'06_lumber', pointing:'07_pointing', shrug:'08_shrug', celebrating:'09_celebrating' } as const;
export type SorenPose = keyof typeof POSES;

export function Soren({ pose = 'default', size = 240, label = 'Søren, the Sør-Tre mascot' }:
  { pose?: SorenPose; size?: number | string; label?: string }) {
  const [mode, setMode] = useState<'webm' | 'webp' | 'still'>('still'); // SSR-safe default
  useEffect(() => {
    const ua = navigator.userAgent;
    const safari = (/Safari\//.test(ua) && !/Chrome|Chromium|CriOS|Edg|FxiOS|Android/.test(ua)) || /iPad|iPhone|iPod/.test(ua);
    const mq = matchMedia('(prefers-reduced-motion: reduce)');
    const pick = () => setMode(mq.matches ? 'still' : safari ? 'webp' : 'webm');
    pick(); mq.addEventListener('change', pick); return () => mq.removeEventListener('change', pick);
  }, []);
  const f = `/assets/soren/soren_${POSES[pose]}`;
  const style = { width: size, maxWidth: '100%', aspectRatio: '1 / 1', objectFit: 'contain' as const, background: 'transparent', display: 'block' };
  if (mode === 'webm')
    return <video key={f} autoPlay muted loop playsInline poster={`${f}_poster.png`} aria-label={label} style={style}>
      <source src={`${f}.webm`} type='video/webm; codecs="vp9"' /></video>;
  return <img src={mode === 'webp' ? `${f}.webp` : `${f}_poster.png`} alt={label} style={style} />;
}
```

## Notes
- Display size: native 960/720 px is plenty for retina up to ~480/360 CSS px. WebP files are larger (≈4–7 MB) — they're only loaded on Safari; consider `loading="lazy"` for below-the-fold use.
- Don't add `controls`, filters or `mix-blend-mode`. Keep `muted` + `playsinline` or autoplay will be blocked on mobile.
