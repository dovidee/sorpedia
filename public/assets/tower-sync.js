// Floor synchronisation (feature 031): the warehouse floor builds itself before it goes live, like connecting to a
// digital twin. A perspective grid lights up, the structure rises from the ground as a glowing wireframe, turns solid,
// gets its materials, light and shadows, and then goes live with the real parcels from the database.
//
// Only the fixed structure is staged. Parcels, counts and labels are the application's own data and appear when the
// real request has answered; nothing is invented to fill the wait. Live updates never restart the sequence.
//
// full(ready, onLive): the whole sequence, on the first visit to the floor after the page loads.
// short(): a quick resync sweep when the floor is opened again.

import { esc } from 'tower-lib';

const BLUE = 0x4cb4ff, DEEP = 0x05090f;
const ease = {
  out: (x) => 1 - (1 - x) ** 3,
  inOut: (x) => (x < 0.5 ? 4 * x ** 3 : 1 - (-2 * x + 2) ** 3 / 2),
  expo: (x) => (x >= 1 ? 1 : 1 - 2 ** (-10 * x)),
};
const span = (t, a, b) => Math.max(0, Math.min(1, (t - a) / (b - a)));

/**
 * o: { THREE, gl, scene, camera, structure, parcels, hemi, sun, grid, slats, labels, floor, panel, L, reduce,
 *      cam: { CAM, VIEW0, placeCam, frameView }, render }
 */
export function createSync(o) {
  const { THREE, gl, scene } = o;
  let built = false, prepared = false, raf = 0, userMoved = false;
  const H = 4.8; // tallest structure (intake roof and vents) with margin
  const wireP = new THREE.Plane(new THREE.Vector3(0, -1, 0), 0);
  const solidP = new THREE.Plane(new THREE.Vector3(0, -1, 0), 0);
  const mats = new Map(); // structure material -> what it had before
  const wires = new THREE.Group();
  let holo, ring, wireLine, wireFill;
  const base = {};

  function prepare() {
    if (prepared) return;
    prepared = true;
    gl.localClippingEnabled = true;
    wireLine = new THREE.LineBasicMaterial({ color: BLUE, transparent: true, opacity: 0.9, blending: THREE.AdditiveBlending, depthWrite: false, clippingPlanes: [wireP] });
    wireFill = new THREE.MeshBasicMaterial({ color: BLUE, wireframe: true, transparent: true, opacity: 0.5, blending: THREE.AdditiveBlending, depthWrite: false, clippingPlanes: [wireP] });
    // The wireframe twin of every structural part: edges for single meshes, a wireframe of the same instances for legs and rollers
    for (const s of o.structure) {
      s.updateMatrixWorld(true);
      if (s.isInstancedMesh) {
        const w = new THREE.InstancedMesh(s.geometry, wireFill, s.count);
        w.instanceMatrix = s.instanceMatrix;
        w.frustumCulled = false;
        wires.add(w);
      } else if (s.isMesh) {
        const w = new THREE.LineSegments(new THREE.EdgesGeometry(s.geometry, 24), wireLine);
        w.matrixAutoUpdate = false;
        w.matrix.copy(s.matrixWorld);
        wires.add(w);
      }
      const list = Array.isArray(s.material) ? s.material : [s.material];
      for (const m of list) if (!mats.has(m)) mats.set(m, { emissive: m.emissive?.getHex(), ei: m.emissiveIntensity });
    }
    wires.visible = false;
    scene.add(wires);
    // The glowing perspective grid and the ring that sweeps out over the empty floor
    holo = new THREE.GridHelper(120, 60, BLUE, 0x1b5a92);
    for (const m of [holo.material].flat()) Object.assign(m, { transparent: true, opacity: 0, blending: THREE.AdditiveBlending, depthWrite: false });
    holo.position.y = 0.02;
    holo.visible = false;
    scene.add(holo);
    ring = new THREE.Mesh(new THREE.RingGeometry(0.985, 1, 160), new THREE.MeshBasicMaterial({ color: BLUE, transparent: true, opacity: 0, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide }));
    ring.rotation.x = -Math.PI / 2;
    ring.position.copy(o.cam.CAM.target).setY(0.03);
    ring.visible = false;
    scene.add(ring);
    Object.assign(base, { bg: scene.background.clone(), hemi: o.hemi.intensity, sun: o.sun.intensity });
    o.floor.addEventListener('pointerdown', () => { userMoved = true; });
    o.floor.addEventListener('wheel', () => { userMoved = true; }, { passive: true });
  }

  const setClip = (on) => {
    for (const m of mats.keys()) { m.clippingPlanes = on ? [solidP] : null; m.needsUpdate = true; }
  };
  const glow = (k) => {
    for (const [m, was] of mats) {
      if (!m.emissive) continue;
      m.emissive.setHex(k > 0 ? 0x0d3a66 : was.emissive);
      m.emissiveIntensity = k > 0 ? k : was.ei;
    }
  };
  const parcels = (on) => { for (const p of o.parcels) p.visible = on; };

  // ---------- Status panel: real steps, the data step finishes only when the request has answered ----------
  const P = o.L;
  function panel(show) {
    o.panel.hidden = !show;
    if (!show) return;
    o.panel.innerHTML = `<p class="sync-h">${esc(P.title)}</p><div class="sync-bar"><i></i></div><b class="sync-pct">0 %</b>
      <ol>${P.steps.map((s, i) => `<li data-s="${i}">${esc(s)}</li>`).join('')}</ol>`;
  }
  function panelAt(pct, done) {
    o.panel.querySelector('.sync-bar i').style.transform = `scaleX(${pct / 100})`;
    o.panel.querySelector('.sync-pct').textContent = `${Math.round(pct)} %`;
    o.panel.querySelectorAll('li').forEach((li, i) => { li.className = done[i] === true ? 'ok' : done[i] === 'now' ? 'now' : ''; });
  }

  function stop() { cancelAnimationFrame(raf); raf = 0; }

  /** The whole sequence. ready: the floor's first data request; onLive: called once the floor is live. */
  function full(ready, onLive) {
    if (!gl || o.reduce) { Promise.resolve(ready).then(onLive); built = true; return; }
    prepare();
    stop();
    let dataAt = null;
    Promise.resolve(ready).then(() => { dataAt = performance.now(); });
    const { CAM, VIEW0, placeCam, frameView } = o.cam;
    const cam1 = { theta: CAM.theta, phi: CAM.phi };
    const cam0 = { theta: cam1.theta - 0.6, phi: Math.min(cam1.phi + 0.18, 1.3) };
    userMoved = false;
    // Start: an empty, dark digital space
    setClip(true);
    wireP.constant = -0.1; solidP.constant = -0.1;
    wires.visible = holo.visible = ring.visible = true;
    wireLine.opacity = 0.9; wireFill.opacity = 0.5;
    o.grid.visible = false;
    parcels(false);
    glow(1);
    o.sun.castShadow = false;
    o.floor.classList.add('syncing');
    panel(true);
    const t0 = performance.now();
    let live = false;
    const frame = (now) => {
      const t = now - t0;
      // 1. Grid and scan ring (0 to 1.4 s)
      holo.material.opacity = 0.55 * ease.out(span(t, 0, 900)) * (1 - ease.inOut(span(t, 3600, 5200)));
      const r = ease.expo(span(t, 100, 1500));
      ring.scale.setScalar(1 + r * 60);
      ring.material.opacity = 0.9 * (1 - r);
      // 2. The wireframe rises from the ground (0.5 to 2.9 s), 3. the solid structure follows (1.5 to 3.7 s)
      wireP.constant = -0.1 + (H + 0.2) * ease.inOut(span(t, 500, 2900));
      solidP.constant = -0.1 + (H + 0.2) * ease.inOut(span(t, 1500, 3700));
      // 4. Materials, light and shadow take over from the blue glow (3.3 to 5 s)
      const m = ease.inOut(span(t, 3300, 5000));
      glow(1 - m);
      o.hemi.intensity = 0.1 + (base.hemi - 0.1) * m;
      o.sun.intensity = base.sun * m;
      scene.background.setHex(DEEP).lerp(base.bg, m);
      if (t > 3300 && !o.sun.castShadow) o.sun.castShadow = true;
      if (t > 4200) o.grid.visible = true;
      wireLine.opacity = 0.9 * (1 - ease.inOut(span(t, 3700, 5200)));
      wireFill.opacity = 0.5 * (1 - ease.inOut(span(t, 3500, 4800)));
      // Camera settles from a wider angle into the working view, unless staff have already taken it
      if (!userMoved && o.floor.clientWidth && o.floor.clientHeight) { // hidden floor: keep the camera as it is
        const c = ease.out(span(t, 0, 4600));
        CAM.theta = cam0.theta + (cam1.theta - cam0.theta) * c;
        CAM.phi = cam0.phi + (cam1.phi - cam0.phi) * c;
        placeCam();
        frameView();
      }
      // 5. Live: when both the build and the data are done the parcels arrive and the belts run
      const ok = dataAt !== null;
      if (t > 5000 && ok && !live) {
        live = true;
        parcels(true);
        o.floor.classList.remove('syncing');
        onLive();
      }
      const belt = live ? 1 - span(now - (dataAt > t0 + 5000 ? dataAt : t0 + 5000), 0, 1600) : 0;
      if (belt > 0) for (const s of o.slats) s.offset.y -= 0.35 * belt;
      const pct = Math.min(t / 5000, ok ? 1 : 0.92) * 100;
      panelAt(pct, [t > 900 || 'now', t > 3700 || (t > 900 ? 'now' : false), t > 5000 || (t > 3700 ? 'now' : false),
        ok || (t > 1000 ? 'now' : false), live || (t > 5000 && ok ? 'now' : false)]);
      o.render();
      if (live && t > 5000 + 1700) { finish(); return; }
      raf = requestAnimationFrame(frame);
    };
    raf = requestAnimationFrame(frame);
  }

  function finish() {
    stop();
    setClip(false);
    glow(0);
    wires.visible = holo.visible = ring.visible = false;
    Object.assign(o.hemi, { intensity: base.hemi });
    Object.assign(o.sun, { intensity: base.sun, castShadow: true });
    scene.background.copy(base.bg);
    o.grid.visible = true;
    parcels(true);
    o.floor.classList.remove('syncing');
    o.panel.classList.add('done');
    setTimeout(() => { o.panel.hidden = true; o.panel.classList.remove('done'); }, 700);
    built = true;
    o.render();
  }

  /** Coming back to the floor: an edge scan runs up the structure once and fades (900 ms). */
  function short() {
    if (!built || !gl || o.reduce) return;
    prepare();
    stop();
    wires.visible = true;
    const t0 = performance.now();
    const frame = (now) => {
      const t = now - t0;
      wireP.constant = -0.1 + (H + 0.2) * ease.out(span(t, 0, 600));
      wireLine.opacity = 0.9 * (1 - ease.inOut(span(t, 250, 900)));
      wireFill.opacity = 0.45 * (1 - ease.inOut(span(t, 150, 700)));
      o.render();
      if (t >= 900) { wires.visible = false; o.render(); raf = 0; return; }
      raf = requestAnimationFrame(frame);
    };
    raf = requestAnimationFrame(frame);
  }

  return { full, short, get built() { return built; } };
}

/**
 * The other views get a short scan line across the page and a soft rise of the content, in the direction that fits:
 * down a page, across the map.
 */
export function sweep(el, across = false, reduce = false) {
  if (reduce || !el) return;
  const line = document.getElementById('tw-sweep');
  if (line) {
    line.className = across ? 'across' : 'down';
    void line.offsetWidth; // restart the animation
    line.classList.add('run');
  }
  el.animate([{ opacity: 0, transform: across ? 'translateX(-8px)' : 'translateY(8px)' }, { opacity: 1, transform: 'none' }],
    { duration: 420, easing: 'cubic-bezier(.16, 1, .3, 1)' });
}
