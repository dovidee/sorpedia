// Control Tower (feature 022). Plain Three.js, no framework, no build step.
// Every parcel is a database row from /api/tarn/*. Nothing moves unless a new row arrived.
import * as THREE from 'three';

const cfg = JSON.parse(document.getElementById('tw-cfg').textContent);
const L = cfg.L;
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
const locale = cfg.lang === 'en' ? 'en-GB' : 'nb-NO';
const tFmt = new Intl.DateTimeFormat(locale, { timeZone: 'Europe/Oslo', hour: '2-digit', minute: '2-digit', second: '2-digit' });
const dFmt = new Intl.DateTimeFormat(locale, { timeZone: 'Europe/Oslo', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
const kr = (ore) => new Intl.NumberFormat('nb-NO', { style: 'currency', currency: 'NOK' }).format(ore / 100);
const canEdit = ['ansatt', 'admin'].includes(cfg.role);
const POLL_MS = 5000;
const FULL_RELOAD_MS = 300000;
const SPEED = 14; // world units per second along the belt

// ---------- Floor plan (world units, x/z). Stations sit beside the trunk belt, exceptions branch off in red. ----------
const POS = {
  inntak: [-17, 0], sok: [-10, -6], avvik: [-10, -12], guide: [-3, 6], byggelab: [-3, -6], soren: [3, -6], kundeservice: [3, -12],
  kvalitet: [9, 6], handel: [12, -6], ukjent: [18, 0], mottak: [-17, 12],
};
const COL = {
  sok: 0xd8c49c, avvik: 0xe5483b, guide: 0xb9cfa8, byggelab: 0xe0915a, soren: 0x9cc7b4, kundeservice: 0xe3a84a,
  kvalitet: 0xa9b6d6, handel: 0xe8d27a, ukjent: 0x8a918d, mottak: 0xf1e6cf,
};
const PRODUCT = 0xf1e6cf; // product parcels are birch wherever they rest
const BELTS = [
  ['inntak', [18, 0]],
  [[-10, 0], 'sok'], [[-3, 0], 'guide'], [[-3, 0], 'byggelab'], [[3, 0], 'soren'], [[9, 0], 'kvalitet'], [[12, 0], 'handel'],
  ['sok', 'avvik', true], ['soren', 'kundeservice'], ['mottak', [9, 12]], [[9, 12], 'kvalitet'],
];
const pt = (p) => (typeof p === 'string' ? POS[p] : p);
const stationOf = (route) => route[route.length - 1];

// ---------- State ----------
const S = { w: 'dag', st: [...cfg.stations], ty: [...cfg.types], tickets: [], last: 0, total: 0, events: [], products: [], backlog: {}, congested: {}, sel: new Set(), items: [] };
// Every request names the window, the stations and the event types shown
const qs = () => `w=${S.w}&st=${S.st.join(',')}&ty=${S.ty.map(encodeURIComponent).join(',')}`;
// Replay reads rows already loaded and writes nothing
const R = { active: false, playing: false, i: 0, all: [], timer: 0 };

// ---------- Scene ----------
let gl = null;
try {
  const r = new THREE.WebGLRenderer({ antialias: true });
  if (!r.getContext()) throw new Error('no context');
  gl = r;
} catch {
  gl = null;
}

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x141a17);
const camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0.1, 200);
camera.position.set(30, 32, 30);
camera.lookAt(0, 0, 0);
scene.add(new THREE.HemisphereLight(0xf2efe6, 0x1b231f, 1.6));
const sun = new THREE.DirectionalLight(0xffffff, 1.4);
sun.position.set(12, 30, 8);
scene.add(sun);

const grid = new THREE.GridHelper(64, 32, 0x26302b, 0x1f2824);
grid.position.y = -0.2;
scene.add(grid);

const beltMat = new THREE.MeshStandardMaterial({ color: 0x3d5a49, roughness: 0.9 });
const redMat = new THREE.MeshStandardMaterial({ color: 0x6e2a24, roughness: 0.9 });
for (const [a, b, red] of BELTS) {
  const [x1, z1] = pt(a), [x2, z2] = pt(b);
  const len = Math.hypot(x2 - x1, z2 - z1);
  const m = new THREE.Mesh(new THREE.BoxGeometry(len + 1.2, 0.2, 1.2), red ? redMat : beltMat);
  m.position.set((x1 + x2) / 2, 0, (z1 + z2) / 2);
  m.rotation.y = -Math.atan2(z2 - z1, x2 - x1);
  scene.add(m);
  const e = new THREE.LineSegments(new THREE.EdgesGeometry(m.geometry), new THREE.LineBasicMaterial({ color: red ? 0xe5483b : 0x5f806b }));
  e.position.copy(m.position);
  e.rotation.copy(m.rotation);
  scene.add(e);
}
for (const [k, [x, z]] of Object.entries(POS)) {
  if (k === 'inntak') continue;
  const pad = new THREE.Mesh(new THREE.BoxGeometry(4.2, 0.3, 4.2), new THREE.MeshStandardMaterial({ color: 0x232c27, roughness: 1 }));
  pad.position.set(x, 0.05, z);
  scene.add(pad);
  const edge = new THREE.LineSegments(new THREE.EdgesGeometry(pad.geometry), new THREE.LineBasicMaterial({ color: COL[k] }));
  edge.position.copy(pad.position);
  scene.add(edge);
}

// One instanced mesh for every parcel: a transparent fill and a solid frame share the same instance matrices.
const MAX = 820;
const SIZE = 0.48;
function mergeBoxes(boxes) {
  const pos = [], nor = [], idx = [];
  let off = 0;
  for (const g of boxes) {
    pos.push(...g.attributes.position.array);
    nor.push(...g.attributes.normal.array);
    for (const i of g.index.array) idx.push(i + off);
    off += g.attributes.position.count;
  }
  const m = new THREE.BufferGeometry();
  m.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
  m.setAttribute('normal', new THREE.Float32BufferAttribute(nor, 3));
  m.setIndex(idx);
  return m;
}
function frameGeometry(s, t) {
  const h = s / 2, bars = [];
  for (const a of [-h, h]) for (const b of [-h, h]) {
    bars.push(new THREE.BoxGeometry(s, t, t).translate(0, a, b));
    bars.push(new THREE.BoxGeometry(t, s, t).translate(a, 0, b));
    bars.push(new THREE.BoxGeometry(t, t, s).translate(a, b, 0));
  }
  return mergeBoxes(bars);
}
const boxGeo = new THREE.BoxGeometry(SIZE, SIZE, SIZE);
const fill = new THREE.InstancedMesh(boxGeo, new THREE.MeshStandardMaterial({ transparent: true, opacity: 0.42, depthWrite: false, roughness: 0.6 }), MAX);
const frame = new THREE.InstancedMesh(frameGeometry(SIZE, 0.04), new THREE.MeshBasicMaterial(), MAX);
const solid = new THREE.InstancedMesh(boxGeo, new THREE.MeshStandardMaterial({ roughness: 0.5 }), MAX);
const ring = new THREE.InstancedMesh(frameGeometry(SIZE * 1.3, 0.05), new THREE.MeshBasicMaterial({ color: 0xf0b44c }), MAX);
for (const m of [fill, frame, solid, ring]) {
  m.count = 0;
  m.frustumCulled = false;
  scene.add(m);
}

const tmp = new THREE.Object3D();
const color = new THREE.Color();

// ---------- Placement ----------
// Rest position: parcels pile on their station pad in rows of six, then in layers. Products queue along the goods belt.
function restSlot(station, i) {
  if (station === 'mottak') {
    const per = 28, x = -16 + (i % per) * 0.9, y = 0.38 + Math.floor(i / per) * 0.5;
    return new THREE.Vector3(x, y, 12);
  }
  const [x, z] = POS[station], per = 36, layer = Math.floor(i / per), r = i % per;
  return new THREE.Vector3(x - 1.5 + (r % 6) * 0.6, 0.45 + layer * 0.5, z - 1.5 + Math.floor(r / 6) * 0.6);
}
function pathPoints(route) {
  const first = POS[route[0]];
  const pts = [POS.inntak, [first[0], 0], first, ...route.slice(1).map((s) => POS[s])];
  return pts.map(([x, z]) => new THREE.Vector3(x, 0.38, z));
}

function rebuild(animateIds = new Set()) {
  const old = new Map(S.items.map((it) => [it.key, it]));
  const items = [];
  const counter = {};
  for (const e of S.events) {
    const station = stationOf(e.route);
    const n = counter[station] ?? 0;
    counter[station] = n + 1;
    const key = 'e:' + e.id;
    const prev = old.get(key);
    const it = { key, kind: 'event', ref: e, station, rest: restSlot(station, n), color: COL[station] ?? COL.ukjent };
    if (prev?.path) Object.assign(it, { path: prev.path, d: prev.d, len: prev.len });
    else if (animateIds.has(e.id) && !reduce) {
      const path = pathPoints(e.route);
      let len = 0;
      for (let i = 1; i < path.length; i++) len += path[i].distanceTo(path[i - 1]);
      Object.assign(it, { path, d: 0, len });
    }
    items.push(it);
  }
  for (const p of S.products) {
    const n = counter[p.station] ?? 0;
    counter[p.station] = n + 1;
    items.push({ key: 'p:' + p.id, kind: 'product', ref: p, station: p.station, rest: restSlot(p.station, n), color: PRODUCT });
  }
  S.items = items.slice(-MAX);
  S.counts = counter;
  draw();
  labels();
  if (S.items.some((it) => it.path)) loop();
}

function posOf(it) {
  if (!it.path) return it.rest;
  // Walk the belt path, then drop onto the pile
  let d = it.d;
  for (let i = 1; i < it.path.length; i++) {
    const seg = it.path[i].distanceTo(it.path[i - 1]);
    if (d <= seg) return it.path[i - 1].clone().lerp(it.path[i], d / seg);
    d -= seg;
  }
  return it.rest;
}

function draw() {
  if (!gl) return;
  let s = 0;
  S.items.forEach((it, i) => {
    tmp.position.copy(posOf(it));
    tmp.updateMatrix();
    fill.setMatrixAt(i, tmp.matrix);
    frame.setMatrixAt(i, tmp.matrix);
    color.setHex(it.color);
    fill.setColorAt(i, color);
    frame.setColorAt(i, color);
    if (S.sel.has(it.key)) {
      solid.setMatrixAt(s, tmp.matrix);
      solid.setColorAt(s, color);
      ring.setMatrixAt(s, tmp.matrix);
      s++;
    }
  });
  fill.count = frame.count = S.items.length;
  solid.count = ring.count = s;
  for (const m of [fill, frame, solid, ring]) {
    m.instanceMatrix.needsUpdate = true;
    if (m.instanceColor) m.instanceColor.needsUpdate = true;
  }
  gl.render(scene, camera);
}

let looping = false, lastT = 0;
function loop() {
  if (looping || !gl) return;
  looping = true;
  lastT = performance.now();
  const step = (now) => {
    const dt = Math.min(0.05, (now - lastT) / 1000);
    lastT = now;
    let moving = false;
    for (const it of S.items) {
      if (!it.path) continue;
      it.d += SPEED * dt;
      if (it.d >= it.len) delete it.path;
      else moving = true;
    }
    draw();
    if (moving) requestAnimationFrame(step);
    else looping = false;
  };
  requestAnimationFrame(step);
}

// ---------- Camera, labels, picking ----------
const host = $('tw-canvas');
function resize() {
  if (!gl) return;
  const w = host.clientWidth, h = host.clientHeight;
  gl.setPixelRatio(Math.min(devicePixelRatio, 2));
  gl.setSize(w, h, false);
  const span = 24, a = w / Math.max(h, 1);
  const half = a >= 1.6 ? span * 0.62 : (span * 1.0) / a;
  camera.left = -half * a; camera.right = half * a; camera.top = half; camera.bottom = -half;
  camera.updateProjectionMatrix();
  draw();
  labels();
}

function labels() {
  const box = $('tw-labels');
  if (!gl) { box.innerHTML = ''; return; }
  const w = host.clientWidth, h = host.clientHeight, v = new THREE.Vector3();
  let html = '';
  for (const k of ['inntak', ...cfg.stations]) {
    const [x, z] = POS[k];
    v.set(x, 1.6, z - 2.1).project(camera);
    const n = k === 'inntak' ? S.total : (S.counts?.[k] ?? 0);
    const queue = S.backlog[k];
    const hot = S.congested[k] === true;
    const extra = queue !== undefined ? `${n} · ${L.queue} ${queue}` : n;
    html += `<span class="tw-label ${k === 'avvik' ? 'avvik' : ''} ${hot ? 'hot' : ''}" style="left:${((v.x + 1) / 2) * w}px;top:${((1 - v.y) / 2) * h}px">${esc(L.stations[k])}<b>${esc(extra)}</b></span>`;
  }
  box.innerHTML = html;
}

const ray = new THREE.Raycaster(), mouse = new THREE.Vector2();
let down = null;
function pick(ev) {
  const r = gl.domElement.getBoundingClientRect();
  mouse.set(((ev.clientX - r.left) / r.width) * 2 - 1, -((ev.clientY - r.top) / r.height) * 2 + 1);
  ray.setFromCamera(mouse, camera);
  fill.computeBoundingSphere(); // cached bounds go stale when instances move or the count changes
  const hit = ray.intersectObject(fill, false)[0];
  return hit ? S.items[hit.instanceId] : null;
}

// ---------- Selection and inspector ----------
function select(key, add = false) {
  if (!key) S.sel.clear();
  else if (add) S.sel.has(key) ? S.sel.delete(key) : S.sel.add(key);
  else { S.sel.clear(); S.sel.add(key); }
  draw();
  logList();
  inspect();
}

const evLabel = (type) => type.replace(/_/g, ' ');
function inspect() {
  const box = $('tw-inspect');
  const sel = S.items.filter((it) => S.sel.has(it.key));
  if (!sel.length) { box.innerHTML = emptyInspector(); box.dataset.product = ''; box.dataset.ticket = ''; return; }
  if (sel.length === 1 && sel[0].kind === 'product') { product(sel[0].ref.id); return; }
  if (sel.length === 1) { box.innerHTML = eventView(sel[0].ref); return; }
  box.innerHTML = multiView(sel);
  box.dataset.product = '';
  combined();
}

// Nothing selected: open tickets (the customer service list), then the product parcels per station
function emptyInspector() {
  const TK = L.tk;
  let html = `<p class="tw-muted">${esc(L.pick)}</p>`;
  if (S.st.includes('kundeservice')) {
    html += `<h3>${esc(TK.title)} ${S.tickets.length}</h3>`;
    html += S.tickets.length ? `<table class="tw-t">${S.tickets.map((t) => `<tr><td><button type="button" data-ticket="${+t.id}">#${+t.id} ${esc(t.product ?? TK.noProduct)}</button>
      <br><span class="tw-muted">${esc(t.customer ?? TK.guest)}, ${esc(dFmt.format(new Date(t.at * 1000)))}</span></td>
      <td><span class="tw-tag">${esc(L.tstatus[t.status] ?? t.status)}</span></td></tr>`).join('')}</table>` : `<p class="tw-muted">${esc(TK.none)}</p>`;
  }
  for (const st of ['kvalitet', 'kundeservice', 'mottak']) {
    const list = S.products.filter((p) => p.station === st);
    if (!list.length) continue;
    html += `<h3>${esc(L.stations[st])}: ${esc(L.product)} ${list.length}</h3><table class="tw-t">${list.map((p) =>
      `<tr><td><button type="button" data-key="p:${p.id}">${esc(p.name)}</button>${p.inbound ? `<br><span class="tw-muted">${esc(L.tr.inbound)} ${esc(p.inbound)}</span>` : ''}</td><td class="n">${+p.revisions + +p.reports + +p.tickets}</td></tr>`).join('')}</table>`;
  }
  return html;
}

function eventView(e) {
  const rows = Object.entries(e.data).map(([k, v]) => {
    const val = Array.isArray(v) ? v.join(', ') : typeof v === 'boolean' ? (v ? '1' : '0') : v;
    if (k === 'product' && v) return `<dt>${esc(k)}</dt><dd><button type="button" class="tw-btn" data-product="${+v}">${esc(L.product)} ${+v}</button></dd>`;
    return `<dt>${esc(k)}</dt><dd>${esc(val)}</dd>`;
  }).join('');
  const st = e.route.map((s) => `<span class="tw-tag ${s === 'avvik' ? 'avvik' : ''}">${esc(L.stations[s])}</span>`).join(' ');
  return `<h2>${esc(L.event)} #${e.id}</h2><p>${st}</p><dl class="tw-dl"><dt>${esc(L.type)}</dt><dd>${esc(e.type)}</dd>
    <dt>${esc(L.time)}</dt><dd><time datetime="${new Date(e.at * 1000).toISOString()}">${esc(dFmt.format(new Date(e.at * 1000)))}</time></dd>${rows}</dl>`;
}

function multiView(sel) {
  const ev = sel.filter((it) => it.kind === 'event').map((it) => it.ref);
  const pr = sel.filter((it) => it.kind === 'product').map((it) => it.ref);
  const by = (f) => Object.entries(ev.reduce((a, e) => ((a[f(e)] = (a[f(e)] ?? 0) + 1), a), {})).sort((a, b) => b[1] - a[1]);
  const t = (rows, lab = (x) => x) => `<table class="tw-t">${rows.map(([k, n]) => `<tr><th>${esc(lab(k))}</th><td class="n">${n}</td></tr>`).join('')}</table>`;
  let html = `<h2>${sel.length} ${esc(L.selected)}</h2>`;
  if (ev.length) {
    const ats = ev.map((e) => e.at);
    html += `<dl class="tw-dl"><dt>${esc(L.time)}</dt><dd>${esc(tFmt.format(new Date(Math.min(...ats) * 1000)))} / ${esc(tFmt.format(new Date(Math.max(...ats) * 1000)))}</dd></dl>`;
    html += `<h3>${esc(L.station)}</h3>${t(by((e) => stationOf(e.route)), (k) => L.stations[k])}<h3>${esc(L.type)}</h3>${t(by((e) => e.type), evLabel)}`;
  }
  if (pr.length) {
    html += `<h3>${esc(L.product)} ${pr.length}</h3>${t([[L.revisions, pr.reduce((a, p) => a + +p.revisions, 0)], [L.reports, pr.reduce((a, p) => a + +p.reports, 0)], [L.tr.tickets, pr.reduce((a, p) => a + +p.tickets, 0)]])}
      <h3>${esc(L.combined)}</h3><div id="tw-combined" data-ids="${pr.map((p) => +p.id).join(',')}"></div>`;
  }
  return html;
}
// Combined missing keys for the selected products, read after the selection is drawn
async function combined() {
  const box = $('tw-combined');
  if (!box) return;
  const ids = box.dataset.ids;
  try {
    const d = await (await fetch(`/api/tarn/mangler?ids=${ids}`)).json();
    if ($('tw-combined') !== box) return;
    box.innerHTML = d.missing.length ? `<table class="tw-t">${d.missing.map((m) => `<tr><th>${esc(m.label)}</th><td class="n">${+m.n}</td></tr>`).join('')}</table>` : `<p class="tw-muted">0</p>`;
  } catch {
    box.innerHTML = `<p class="tw-err">${esc(L.failed)}</p>`;
  }
}

async function product(id) {
  const box = $('tw-inspect');
  let p;
  try {
    const r = await fetch(`/api/tarn/produkt/${id}`, { headers: { Accept: 'application/json' } });
    if (!r.ok) throw new Error(r.status === 403 ? L.noaccess : String(r.status));
    p = await r.json();
  } catch (e) {
    box.innerHTML = `<p class="tw-err">${esc(e.message)}</p>`;
    return;
  }
  const specs = p.specs.map((s) => `<tr><th>${esc(s.label)}</th><td>${s.value === null ? `<span class="tw-muted">${esc(L.unknown)}</span>`
    : esc(s.value + (s.unit ? ' ' + s.unit : ''))}${s.source ? `<span class="tw-src ${esc(s.source)}">${esc(L.sources[s.source] ?? s.source)}</span>` : ''}
    ${s.version ? `<span class="tw-muted"> ${esc(L.versions[s.version] ?? s.version)}</span>` : ''}</td></tr>`).join('');
  const label = Object.fromEntries([...p.specs, ...p.missing].map((s) => [s.key, s.label]));
  const revs = p.revisions.map((r) => `<div class="tw-item">${r.diff.map((d) => `<p>${esc(label[d.key] ?? d.key)}:<s class="tw-muted">${esc(d.from ?? L.unknown)}</s> <b>${esc(d.to)}</b></p>`).join('')}
    <p class="tw-muted">${esc(r.author ?? '')} ${esc(dFmt.format(new Date(r.at * 1000)))}</p>${r.comment ? `<blockquote>${esc(r.comment)}</blockquote>` : ''}
    ${canEdit ? `<div class="tw-act"><button type="button" class="tw-btn go" data-post="/ansatt/revisjon/${+r.id}" data-action="flett">${esc(L.merge)}</button>
    <button type="button" class="tw-btn" data-post="/ansatt/revisjon/${+r.id}" data-action="avvis">${esc(L.reject)}</button></div>` : ''}</div>`).join('');
  const reps = p.reports.map((r) => `<div class="tw-item"><p>${esc(r.label)} <span class="tw-muted">${+r.n}</span></p><blockquote>${esc(r.reasons)}</blockquote>
    ${canEdit ? `<div class="tw-act"><button type="button" class="tw-btn go" data-post="/ansatt/rapport/${+r.id}" data-action="fjern">${esc(L.remove)}</button>
    <button type="button" class="tw-btn" data-post="/ansatt/rapport/${+r.id}" data-action="lukk">${esc(L.correct)}</button>
    <button type="button" class="tw-btn" data-assess="${+r.id}">${esc(L.assess)}</button></div><p class="tw-note" aria-live="polite"></p>` : ''}</div>`).join('');
  const tix = p.tickets.map((t) => `<tr><th><button type="button" data-ticket="${+t.id}">#${+t.id}</button></th><td>${esc(t.summary ?? '')}
    <br><span class="tw-tag">${esc(L.tstatus[t.status] ?? t.status)}</span> <span class="tw-muted">${esc(dFmt.format(new Date(t.at * 1000)))}, ${+t.messages} ${esc(L.messages)}</span></td></tr>`).join('');
  const inb = p.inbound.map((s) => `<tr><th><button type="button" data-ship="${+s.id}">${esc(s.ref)}</button></th>
    <td>${esc(s.origin_city)} ${L.arrow} ${esc(s.dest_city)}, ${+s.qty}${+s.is_demo ? `<span class="tw-demo">${esc(L.demo)}</span>` : ''}</td></tr>`).join('');
  const stock = p.stock.map((w) => `<tr><th>${esc(w.name)}</th><td class="n">${w.in_store} / ${w.online}</td></tr>`).join('');
  box.innerHTML = `<h2>${esc(p.name)}</h2><p><a href="/produkt/${encodeURIComponent(p.slug)}?versjon=apen" target="_blank" rel="noopener">${esc(L.open)}</a></p>
    <dl class="tw-dl"><dt>${esc(L.id)}</dt><dd>${p.id}</dd><dt>${esc(L.price)}</dt><dd>${esc(kr(p.price_ore))}</dd></dl>
    ${p.revisions.length ? `<h3>${esc(L.revisions)} ${p.revisions.length}</h3>${revs}` : ''}
    ${p.reports.length ? `<h3>${esc(L.reports)} ${p.reports.length}</h3>${reps}` : ''}
    ${tix ? `<h3>${esc(L.tr.tickets)} ${p.tickets.length}</h3><table class="tw-t">${tix}</table>` : ''}
    ${inb ? `<h3>${esc(L.tr.inbound)}</h3><table class="tw-t">${inb}</table>` : ''}
    ${p.missing.length ? `<h3>${esc(L.missing)} ${p.missing.length}</h3><ul class="tw-miss">${p.missing.map((m) => `<li>${esc(m.label)}</li>`).join('')}</ul>` : ''}
    <h3>${esc(L.specs)}</h3><table class="tw-t">${specs}</table>
    ${stock ? `<h3>${esc(L.stock)}<span class="tw-demo">${esc(L.demo)}</span></h3><table class="tw-t">${stock}</table>` : ''}`;
  box.dataset.product = String(p.id);
  box.dataset.ticket = '';
}

async function act(btn) {
  btn.disabled = true;
  const body = new FormData();
  body.append('csrf', cfg.csrf);
  body.append('action', btn.dataset.action);
  try {
    const r = await fetch(btn.dataset.post, { method: 'POST', body, redirect: 'manual' });
    if (!(r.ok || r.type === 'opaqueredirect')) throw new Error(r.status === 403 ? L.noaccess : L.failed);
  } catch (e) {
    btn.disabled = false;
    btn.insertAdjacentHTML('afterend', `<span class="tw-err"> ${esc(e.message)}</span>`);
    return;
  }
  const box = $('tw-inspect'), pid = box.dataset.product, tid = box.dataset.ticket;
  await load();
  if (tid) { box.dataset.ticket = ''; inspect(); } // a closed ticket leaves the list
  else if (pid) product(+pid);
}

$('tw-inspect').addEventListener('click', (e) => {
  const b = e.target.closest('button');
  if (!b) return;
  if (b.dataset.post) act(b);
  else if (b.dataset.product) { S.sel.clear(); draw(); product(+b.dataset.product); }
  else if (b.dataset.key) select(b.dataset.key);
  else if (b.dataset.ship) { if (T.view !== 'transport') setView('transport'); shipSelect(+b.dataset.ship); }
  else if (b.dataset.reply) reply(b);
  else if (b.dataset.draft) draft(b);
  else if (b.dataset.ticket) ticket(+b.dataset.ticket);
  else if (b.dataset.assess) assess(b);
  else if (b.dataset.back !== undefined) { $('tw-inspect').dataset.ticket = ''; inspect(); }
});

// ---------- Log, KPI, throughput, fallback table ----------
function logList() {
  const ev = S.events.slice(-40).reverse();
  $('tw-log').innerHTML = ev.map((e) => {
    const st = stationOf(e.route), key = 'e:' + e.id;
    const what = e.data.q ?? (e.data.product ? `${L.product} ${e.data.product}` : '');
    return `<li><button type="button" data-key="${key}" aria-pressed="${S.sel.has(key)}"><time>${esc(tFmt.format(new Date(e.at * 1000)))}</time>
      <span class="${st === 'avvik' ? 'avvik' : ''}">${esc(L.stations[st])}</span><span>${esc(evLabel(e.type))} ${esc(what)}</span></button></li>`;
  }).join('');
}
$('tw-log').addEventListener('click', (e) => {
  const b = e.target.closest('button[data-key]');
  if (b) select(b.dataset.key, e.shiftKey);
});

// Station counts as text: the visible floor without WebGL, and a screen reader list beside the canvas with it
function fallbackTable() {
  const rows = cfg.stations.filter((k) => S.st.includes(k)).map((k) => `<tr><th>${esc(L.stations[k])}</th><td class="n">${S.counts?.[k] ?? 0}</td></tr>`).join('');
  const table = `<table class="tw-t"><caption>${esc(L.stations.inntak)} ${S.total}</caption>${rows}</table>`;
  if (gl) { $('tw-counts').innerHTML = table; return; }
  $('tw-table').innerHTML = `<p>${esc(L.nowebgl)}</p>${table}`;
}

async function kpis() {
  let k;
  try { k = await (await fetch(`/api/tarn/kpi?${qs()}`)).json(); } catch { return; }
  const K = L.kpi;
  const row = (l, n) => `<tr><th>${esc(l)}</th><td class="n">${esc(n)}</td></tr>`;
  let html = `<section><h2>${esc(K.title)}</h2><table class="tw-t">${Object.entries(k.kpi).map(([key, n]) => row(K[key] ?? key, n)).join('')}</table></section>`;
  if (k.zeroTerms.length) html += `<section><h2>${esc(K.zeroTerms)}</h2><table class="tw-t">${k.zeroTerms.map((z) => row(z.q, z.n)).join('')}</table></section>`;
  html += `<p class="tw-muted">${esc(K.state)}</p><section><h2>${esc(K.coverage)}</h2><table class="tw-t">${k.coverage.map((c) => row(`${L.versions[c.version] ?? c.version} / ${L.sources[c.source] ?? c.source}`, c.n)).join('')}</table></section>`;
  html += `<section><h2>${esc(K.rel)}</h2><table class="tw-t">${k.rel.map((r) => row(`${r.level} / ${r.kind}`, r.n)).join('')}</table></section>`;
  html += `<section><h2>${esc(K.contrib)}</h2><table class="tw-t">${Object.entries(k.contrib).map(([s, n]) => row(s, n)).join('')}</table></section>`;
  if (k.via) {
    html += `<section><h2>${esc(K.via)}<span class="tw-demo">${esc(L.demo)}</span></h2><table class="tw-t">${k.via.map((v) => row(v.via, `${v.n} / ${kr(+v.ore)}`)).join('')}</table>
      <p class="tw-muted">${esc(k.viaDemo)} ${esc(K.viaDemo)}</p></section>`;
  }
  if (k.quality) {
    const Q = L.q, q = k.quality;
    html += `<section><h2>${esc(Q.title)}</h2><table class="tw-t">
      ${row(Q.missingProducts, `${q.missingProducts} ${Q.of} ${q.products}`)}${row(Q.missingValues, q.missingValues)}${row(Q.unverified, q.unverified)}
      ${row(Q.reports, q.reports)}${row(Q.noPrice, q.noPrice)}${row(Q.unknownEvents, q.unknownEvents)}
      <tr><th>${esc(Q.stalePositions)}<span class="tw-demo">${esc(L.demo)}</span></th><td class="n">${+q.stalePositions}</td></tr></table></section>`;
  }
  if (k.users) html += `<section><h2>${esc(K.users)}</h2><table class="tw-t">${k.users.map((u) => row(u.role, `${u.n} / ${u.verified}`)).join('')}</table></section>`;
  $('tw-kpi').innerHTML = html;
}

async function throughput() {
  let t;
  try { t = await (await fetch(`/api/tarn/throughput?${qs()}`)).json(); } catch { return; }
  const pts = t.points;
  const box = $('tw-chart');
  const W = box.clientWidth || 400, H = box.clientHeight || 140;
  if (!pts.length) { box.innerHTML = ''; return; }
  const max = Math.max(1, ...pts.map((p) => p[1]));
  const x = (i) => (i / Math.max(1, pts.length - 1)) * (W - 40) + 32;
  const y = (n) => H - 16 - (n / max) * (H - 28);
  let d = `M${x(0)},${y(pts[0][1])}`;
  for (let i = 1; i < pts.length; i++) d += `H${x(i)}V${y(pts[i][1])}`;
  const f = t.bucket >= 86400 ? new Intl.DateTimeFormat(locale, { timeZone: 'Europe/Oslo', day: '2-digit', month: '2-digit' }) : new Intl.DateTimeFormat(locale, { timeZone: 'Europe/Oslo', hour: '2-digit', minute: '2-digit' });
  box.innerHTML = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(L.throughput)}: ${pts.reduce((a, p) => a + p[1], 0)}">
    <line class="grid" x1="32" x2="${W - 8}" y1="${y(0)}" y2="${y(0)}"/><line class="grid" x1="32" x2="${W - 8}" y1="${y(max)}" y2="${y(max)}"/>
    <text x="0" y="${y(max) + 4}">${max}</text><text x="0" y="${y(0) + 4}">0</text>
    <path class="step" d="${d}"/>
    <text x="32" y="${H}">${esc(f.format(new Date(pts[0][0] * 1000)))}</text>
    <text x="${W - 8}" y="${H}" text-anchor="end">${esc(f.format(new Date(pts[pts.length - 1][0] * 1000)))}</text></svg>`;
}

// ---------- Data ----------
function status(ok) {
  const el = $('tw-live');
  el.className = 'tw-live ' + (ok ? 'on' : 'err');
  el.querySelector('span').textContent = ok ? `${L.updated} ${tFmt.format(new Date())}` : L.failed;
}
function header() {
  if (R.active) { $('tw-count').textContent = `${L.replay} ${R.i} / ${R.all.length}`; return; }
  $('tw-count').textContent = S.total > S.events.length ? `${L.sampled} ${S.events.length} / ${S.total}` : String(S.total);
  const idle = !S.events.length && !S.products.length;
  $('tw-idle').hidden = !idle || T.view === 'transport';
  $('tw-idle').textContent = L.empty;
}
function apply(d) {
  S.products = d.products;
  S.tickets = d.tickets;
  S.backlog = d.backlog;
  S.congested = d.congested;
  S.last = Math.max(S.last, d.last);
}

async function load() {
  try {
    const r = await fetch(`/api/tarn/events?${qs()}`);
    if (!r.ok) throw new Error(String(r.status));
    const d = await r.json();
    S.last = 0;
    apply(d);
    S.events = d.rows;
    S.total = d.total;
    status(true);
  } catch {
    status(false);
    return;
  }
  rebuild();
  header();
  logList();
  fallbackTable();
  const box = $('tw-inspect');
  if (!S.sel.size && !box.dataset.product && !box.dataset.ticket && T.view === 'gulv') inspect();
}

async function poll() {
  if (document.hidden || R.active) return;
  try {
    const r = await fetch(`/api/tarn/events?${qs()}&after=${S.last}`);
    if (!r.ok) throw new Error(String(r.status));
    const d = await r.json();
    apply(d);
    if (d.rows.length) {
      S.events = S.events.concat(d.rows).slice(-400);
      S.total += d.rows.length;
      logList();
    }
    rebuild(new Set(d.rows.map((e) => e.id)));
    header();
    fallbackTable();
    status(true);
  } catch {
    status(false);
  }
}

// ---------- Transport (DELIVERY.md 5.9). Markers sit at the latest stored position. Nothing is interpolated or animated. ----------
const TR = L.tr;
const T = { view: 'gulv', rows: [], land: null, path: '', sel: null, detail: null, vb: null };
const svg = $('tw-svg');
const KM = 60; // svg units per degree of latitude
const cosL = Math.cos((63 * Math.PI) / 180);
const prj = (lat, lng) => [(lng - 4) * cosL * KM, (72 - lat) * KM];

async function landLoad() {
  if (T.land) return;
  T.land = await (await fetch('/assets/tower/norway.json')).json();
  T.path = T.land.polygons.map((r) => 'M' + r.map(([lng, lat]) => prj(lat, lng).map((n) => n.toFixed(1)).join(',')).join('L') + 'Z').join('');
}
function setVB([x, y, w, h]) {
  const W = svg.clientWidth || 600, H = svg.clientHeight || 400, a = W / H;
  if (w / h > a) { const nh = w / a; y -= (nh - h) / 2; h = nh; } else { const nw = h * a; x -= (nw - w) / 2; w = nw; }
  T.vb = [x, y, w, h];
  svg.setAttribute('viewBox', T.vb.join(' '));
}
function fit(pts, pad = 0.15) {
  if (!pts.length) return;
  const xs = pts.map((p) => p[0]), ys = pts.map((p) => p[1]);
  const x0 = Math.min(...xs), y0 = Math.min(...ys), w = Math.max(Math.max(...xs) - x0, 40), h = Math.max(Math.max(...ys) - y0, 40);
  setVB([x0 - w * pad, y0 - h * pad, w * (1 + 2 * pad), h * (1 + 2 * pad)]);
}
const rowPts = (r) => [...r.route.map(([a, b]) => prj(a, b)), ...(r.lat !== null ? [prj(+r.lat, +r.lng)] : [])];
const FIT = {
  no: () => fit(T.land.polygons.flat().map(([lng, lat]) => prj(lat, lng)), 0.02),
  all: () => (T.rows.length ? fit(T.rows.flatMap(rowPts)) : FIT.no()),
  route: () => { const r = T.rows.find((x) => x.id === T.sel); if (r) fit(rowPts(r)); },
};

function mapDraw() {
  const s = T.vb ? T.vb[2] / (svg.clientWidth || 600) : 1; // svg units per screen pixel: markers keep their size when zooming
  let h = `<path class="tw-land" d="${T.path}"/>`;
  for (const r of T.rows) {
    if (r.route.length) h += `<polyline class="tw-route ${r.id === T.sel ? 'on' : ''}" points="${r.route.map(([a, b]) => prj(a, b).join(',')).join(' ')}"/>`;
  }
  if (T.detail && T.detail.id === T.sel && T.detail.positions.length > 1) {
    h += `<polyline class="tw-trace" points="${T.detail.positions.map((p) => prj(+p.lat, +p.lng).join(',')).join(' ')}"/>`;
  }
  for (const r of T.rows) {
    if (r.lat === null) continue;
    const [x, y] = prj(+r.lat, +r.lng), m = 7 * s;
    const head = r.heading !== null ? `<path class="hd" d="M0,${-m * 2} L${m * 0.7},${-m * 1.1} L${-m * 0.7},${-m * 1.1}Z" transform="rotate(${+r.heading})"/>` : '';
    h += `<g class="tw-vh ${esc(r.status)} ${r.stale ? 'stale' : ''} ${r.id === T.sel ? 'on' : ''}" data-ship="${r.id}" transform="translate(${x},${y})" tabindex="0" role="button"
      aria-label="${esc(r.fleet_no ?? r.ref)}, ${esc(TR.status[r.status] ?? r.status)}${r.stale ? ', ' + esc(TR.stale) : ''}">${head}<rect x="${-m}" y="${-m}" width="${2 * m}" height="${2 * m}"/>
      <text x="${m * 1.6}" y="${m * 0.5}" style="font-size:${11 * s}px;stroke-width:${3 * s}px">${esc(r.fleet_no ?? r.ref)}</text></g>`;
  }
  svg.innerHTML = h;
}

let pan = null;
svg.addEventListener('pointerdown', (e) => {
  if (e.target.closest('.tw-vh') || !T.vb) return;
  pan = [e.clientX, e.clientY, ...T.vb];
  svg.setPointerCapture(e.pointerId);
  svg.classList.add('drag');
});
svg.addEventListener('pointermove', (e) => {
  if (!pan) return;
  const s = pan[4] / svg.clientWidth;
  T.vb = [pan[2] - (e.clientX - pan[0]) * s, pan[3] - (e.clientY - pan[1]) * s, pan[4], pan[5]];
  svg.setAttribute('viewBox', T.vb.join(' '));
});
svg.addEventListener('pointerup', () => { pan = null; svg.classList.remove('drag'); });
svg.addEventListener('wheel', (e) => {
  if (!T.vb) return;
  e.preventDefault();
  const r = svg.getBoundingClientRect(), f = e.deltaY > 0 ? 1.2 : 1 / 1.2, [x, y, w, h] = T.vb;
  const px = x + ((e.clientX - r.left) / r.width) * w, py = y + ((e.clientY - r.top) / r.height) * h;
  T.vb = [px - (px - x) * f, py - (py - y) * f, w * f, h * f];
  svg.setAttribute('viewBox', T.vb.join(' '));
  mapDraw();
}, { passive: false });
svg.addEventListener('click', (e) => { const g = e.target.closest('.tw-vh'); if (g) shipSelect(+g.dataset.ship); });
svg.addEventListener('keydown', (e) => {
  const g = e.target.closest('.tw-vh');
  if (g && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); shipSelect(+g.dataset.ship); }
});
document.querySelectorAll('[data-fit]').forEach((b) => b.addEventListener('click', () => { if (T.land) { FIT[b.dataset.fit](); mapDraw(); } }));

async function transport() {
  try {
    const r = await fetch('/api/tarn/transport');
    if (!r.ok) throw new Error(String(r.status));
    T.rows = (await r.json()).rows;
    await landLoad();
    status(true);
  } catch {
    status(false);
    return;
  }
  if (!T.vb) FIT.all();
  document.querySelector('.tw-demo-big').hidden = !T.rows.some((r) => +r.is_demo);
  mapDraw();
  if (T.sel === null) $('tw-inspect').innerHTML = shipList();
}

function shipList() {
  if (!T.rows.length) return `<p class="tw-muted">${esc(TR.none)}</p>`;
  return `<h3>${esc(TR.shipments)}</h3><table class="tw-t">${T.rows.map((r) => `<tr><td><button type="button" data-ship="${r.id}">${esc(r.origin_city)} ${L.arrow} ${esc(r.dest_city)}</button>
    <br><span class="tw-muted">${esc(r.ref)} ${esc(r.fleet_no ?? '')}${r.stale ? ' · ' + esc(TR.stale) : ''}</span></td>
    <td><span class="tw-tag ${r.status === 'forsinket' ? 'avvik' : ''}">${esc(TR.status[r.status] ?? r.status)}</span>${+r.is_demo ? `<span class="tw-demo">${esc(L.demo)}</span>` : ''}</td></tr>`).join('')}</table>`;
}

async function shipSelect(id) {
  T.sel = id;
  $('tw-inspect').dataset.product = '';
  try {
    const r = await fetch(`/api/tarn/sending/${id}`);
    if (!r.ok) throw new Error(r.status === 403 ? L.noaccess : String(r.status));
    T.detail = await r.json();
  } catch (e) {
    $('tw-inspect').innerHTML = `<p class="tw-err">${esc(e.message)}</p>`;
    return;
  }
  FIT.route();
  mapDraw();
  $('tw-inspect').innerHTML = shipView(T.detail);
}

function shipView(d) {
  const tf = (s) => (s ? esc(dFmt.format(new Date(s * 1000))) : `<span class="tw-muted">${esc(L.unknown)}</span>`);
  const kg = (n) => (n === null || n === undefined ? `<span class="tw-muted">${esc(L.unknown)}</span>` : `${new Intl.NumberFormat(locale).format(+n)} kg`);
  const last = d.positions[d.positions.length - 1];
  // Age, staleness, variance, progress and remaining payload are derived on the server (ShipmentRules)
  const age = d.age_min, stale = d.stale, variance = d.variance, pct = d.progress, estimated = d.estimated;
  const v = d.vehicle, dr = d.driver, w = d.weight;
  const strip = pct === null ? '' : `<div class="tw-prog" role="img" aria-label="${esc(TR.progress)} ${Math.round(pct * 100)} %"><span class="ln"></span>
    <span class="done" style="width:${pct * 100}%"></span><span class="end" style="left:0"></span><span class="end" style="right:0"></span>
    <span class="box" style="left:${Math.min(88, Math.max(12, pct * 100))}%">${esc(v?.fleet_no ?? d.ref)}</span></div>
    <p class="tw-muted">${esc(TR.progress)} ${Math.round(pct * 100)} %${estimated ? ', ' + esc(TR.estimate) : ''}</p>`;
  const fresh = !last ? `<span class="tw-muted">${esc(TR.noPos)}</span>` : stale ? `<span class="tw-err">${esc(TR.stale)}, ${age} ${TR.min}</span>` : `${esc(TR.fresh)}, ${age} ${TR.min}`;
  const items = d.items.map((i) => `<tr><th><button type="button" data-product="${+i.product_id}">${esc(i.name)}</button></th><td class="n">${+i.qty}</td>
    <td class="n">${i.weight_kg === null ? `<span class="tw-muted">${esc(L.unknown)}</span>` : `${kg(i.weight_kg)} ${esc(i.weight_basis ?? '')}`}</td></tr>`).join('');
  const dist = w.items !== null && w.items > 0 ? `<div class="tw-bar-w" role="img" aria-label="${esc(TR.weight)}">${d.items.map((i) => `<i style="width:${(+i.weight_kg / w.items) * 100}%" title="${esc(i.name)}"></i>`).join('')}</div>` : '';
  const remaining = w.remaining;
  const hist = d.positions.slice().reverse().map((p) => `<tr><th>${esc(tFmt.format(new Date(p.observed_at * 1000)))}</th><td>${(+p.lat).toFixed(3)}, ${(+p.lng).toFixed(3)} <span class="tw-muted">${esc(p.source)}</span></td></tr>`).join('');
  const dl = (pairs) => `<dl class="tw-dl">${pairs.filter(Boolean).map(([k, val]) => `<dt>${esc(k)}</dt><dd>${val}</dd>`).join('')}</dl>`;
  return `${+d.is_demo ? `<p><span class="tw-demo-big">${esc(TR.demo)}</span></p>` : ''}
    <div class="tw-hdr">${esc(d.origin_city)} ${L.arrow} ${esc(d.dest_city)}</div>
    <p class="tw-muted">${esc(d.ref)}${d.origin_point ? ', ' + esc(d.origin_point) : ''}${d.dest_point ? ', ' + esc(d.dest_point) : ''}</p>
    ${strip}
    <p><span class="tw-tag ${d.status === 'forsinket' ? 'avvik' : ''}">${esc(TR.status[d.status] ?? d.status)}</span> ${fresh}</p>
    <h3>${esc(TR.timeline)} (${esc(TR.tz)})</h3>
    ${dl([[TR.plannedDep, tf(d.planned_departure)], [TR.actualDep, tf(d.actual_departure)], [TR.lastPos, last ? tf(last.observed_at) : tf(null)],
      [TR.eta, d.eta ? `${tf(d.eta)}<br><span class="tw-muted">${esc(TR.source)}: ${esc(d.eta_source ?? L.unknown)}, ${esc(TR.calculated)} ${tf(d.eta_calculated_at)}</span>` : tf(null)],
      [TR.plannedArr, tf(d.planned_arrival)], [TR.actualArr, tf(d.actual_arrival)],
      variance !== null && [TR.variance, `${variance > 0 ? '+' : ''}${Math.round(variance / 60)} ${TR.min}`],
      d.delay_reason && [TR.delay, esc(d.delay_reason)]])}
    <h3>${esc(TR.vehicle)}</h3>
    ${v ? dl([[TR.vehicle, `${esc(TR.types[v.type] ?? v.type)} ${esc(v.fleet_no)}`], [L.type, esc([v.make, v.model, v.model_year].filter(Boolean).join(' '))],
      'registration' in v && [TR.reg, esc(v.registration ?? L.unknown)], [TR.payload, kg(v.payload_kg)], [TR.capacity, kg(v.capacity_kg)]]) : `<p class="tw-muted">${esc(L.unknown)}</p>`}
    <h3>${esc(TR.driver)}</h3>
    ${dr ? dl([[TR.driver, `${esc(dr.alias)} <span class="tw-muted">${esc(dr.driver_ref)}</span>`],
      'phone' in dr && [TR.phone, dr.phone ? `<a href="tel:${esc(dr.phone)}">${esc(dr.phone)}</a>` : `<span class="tw-muted">${esc(L.unknown)}</span>`]]) : `<p class="tw-muted">${esc(L.unknown)}</p>`}
    <h3>${esc(TR.cargo)}</h3>
    ${dl([[TR.container, d.container_ref ? `${esc(d.container_ref)} ${esc(d.container_type ?? '')}` : `<span class="tw-muted">${esc(L.unknown)}</span>`]])}
    <table class="tw-t"><tr><th></th><th>${esc(TR.qty)}</th><th>${esc(TR.weight)}</th></tr>${items}</table>
    ${dist}
    ${dl([[TR.declared, kg(w.declared)], [TR.measured, kg(w.measured)], [TR.itemsSum, kg(w.items)], [TR.payload, kg(w.payload)],
      [TR.remaining, remaining === null ? kg(null) : `${kg(remaining)} <span class="tw-muted">${esc(TR.calculated)}</span>`]])}
    ${w.mismatch ? `<p class="tw-err">${esc(TR.mismatch)}</p>` : ''}
    <h3>${esc(TR.history)}</h3><table class="tw-t">${hist || `<tr><td class="tw-muted">${esc(TR.noPos)}</td></tr>`}</table>`;
}

function setView(v) {
  T.view = v;
  const map = v === 'transport';
  document.querySelector(`input[name="view"][value="${v}"]`).checked = true;
  $('tw-map').hidden = !map;
  host.hidden = map;
  $('tw-labels').hidden = map;
  $('tw-table').hidden = map || !!gl;
  if (map) { $('tw-idle').hidden = true; T.sel = null; T.detail = null; transport(); }
  else { header(); inspect(); }
}
document.querySelectorAll('input[name="view"]').forEach((r) => r.addEventListener('change', () => setView(r.value)));
setInterval(() => {
  if (T.view !== 'transport' || document.hidden) return;
  transport();
  if (T.sel !== null) shipSelect(T.sel);
}, 30000);

// ---------- Ticket (the customer service view, DELIVERY 5.10) ----------
let ticketStream = null;
async function ticket(id) {
  const box = $('tw-inspect'), TK = L.tk;
  let t;
  try {
    const r = await fetch(`/api/tarn/sak/${id}`);
    if (!r.ok) throw new Error(r.status === 403 ? L.noaccess : String(r.status));
    t = await r.json();
  } catch (e) {
    box.innerHTML = `<p class="tw-err">${esc(e.message)}</p>`;
    return;
  }
  S.sel.clear();
  draw();
  const keep = box.dataset.ticket === String(t.id) ? box.querySelector('.tw-reply')?.value ?? '' : '';
  const chat = t.chat.map((m) => `<p><b>${esc(m.role === 'user' ? TK.customer : 'Søren')}:</b> ${esc(m.content)}</p>`).join('');
  const thread = t.messages.map((m) => `<p class="tw-msg ${m.sender === 'kunde' ? '' : 'me'}"><b>${esc(m.sender === 'kunde' ? TK.customer : TK.staff)}</b>
    <time class="tw-muted">${esc(tFmt.format(new Date(m.at * 1000)))}</time><br>${esc(m.body)}</p>`).join('');
  const open = t.status !== 'lukket';
  box.innerHTML = `<p><button type="button" class="tw-btn" data-back>${esc(L.tk.title)}</button></p>
    <h2>#${+t.id} <span class="tw-tag">${esc(L.tstatus[t.status] ?? t.status)}</span></h2>
    <dl class="tw-dl"><dt>${esc(TK.customer)}</dt><dd>${esc(t.customer ?? TK.guest)}</dd><dt>${esc(L.time)}</dt><dd>${esc(dFmt.format(new Date(t.at * 1000)))}</dd>
    <dt>${esc(L.product)}</dt><dd>${t.product_id ? `<button type="button" class="tw-btn" data-product="${+t.product_id}">${esc(t.product ?? t.product_id)}</button>` : `<span class="tw-muted">${esc(TK.noProduct)}</span>`}</dd></dl>
    <p>${esc(t.summary ?? '')}</p>
    ${chat ? `<details class="tw-chat"><summary>${esc(TK.chat)}</summary>${chat}</details>` : ''}
    <h3>${esc(TK.thread)} ${t.messages.length}</h3>${thread}
    ${open ? `<div class="tw-item"><textarea class="tw-reply" rows="3" aria-label="${esc(L.answer)}">${esc(keep)}</textarea>
    <div class="tw-act"><button type="button" class="tw-btn go" data-reply="${+t.id}">${esc(L.send)}</button>
    <button type="button" class="tw-btn" data-draft="${+t.id}">${esc(L.draft)}</button>
    <button type="button" class="tw-btn" data-post="/support/${+t.id}/lukk" data-action="lukk">${esc(L.close)}</button></div>
    <p class="tw-note" aria-live="polite"></p></div>` : ''}`;
  box.dataset.ticket = String(t.id);
  box.dataset.product = '';
  // New messages on this ticket arrive on the existing SSE channel
  if (ticketStream?.id !== t.id) {
    ticketStream?.es.close();
    const es = new EventSource(`/api/stream?ch=ticket:${+t.id}`);
    es.addEventListener('message', () => { if (box.dataset.ticket === String(t.id)) ticket(t.id); });
    ticketStream = { id: t.id, es };
  }
}

// Søren's view on an error report. Shown to the staff member only; the decision stays with the existing buttons.
async function assess(btn) {
  const note = btn.closest('.tw-item').querySelector('.tw-note');
  btn.disabled = true;
  note.className = 'tw-note';
  note.textContent = L.drafting;
  try {
    const r = await fetch(`/api/tarn/rapport/${+btn.dataset.assess}/utkast`, { method: 'POST', headers: { 'X-CSRF': cfg.csrf } });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error ?? L.failed);
    note.innerHTML = `<b>${esc(L.assess)}</b><br>${esc(d.draft)}<br><span class="tw-muted">${esc(L.assessNote)}</span>`;
  } catch (e) {
    note.className = 'tw-note tw-err';
    note.textContent = e.message;
  }
  btn.disabled = false;
}

// Søren writes a draft into the reply box. Nothing is sent until staff press Send.
async function draft(btn) {
  const item = btn.closest('.tw-item'), ta = item.querySelector('textarea'), note = item.querySelector('.tw-note');
  btn.disabled = true;
  note.className = 'tw-note';
  note.textContent = L.drafting;
  try {
    const r = await fetch(`/api/tarn/sak/${+btn.dataset.draft}/utkast`, { method: 'POST', headers: { 'X-CSRF': cfg.csrf } });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error ?? L.failed);
    ta.value = d.draft;
    ta.rows = 6;
    ta.focus();
    note.textContent = L.drafted;
  } catch (e) {
    note.className = 'tw-note tw-err';
    note.textContent = e.message;
  }
  btn.disabled = false;
}

// Ticket reply from the inspector: the same endpoint customer service uses
async function reply(btn) {
  const item = btn.closest('.tw-item'), ta = item.querySelector('textarea'), note = item.querySelector('.tw-note'), body = ta.value.trim();
  if (!body) { ta.focus(); return; }
  btn.disabled = true;
  note.className = 'tw-note';
  try {
    const r = await fetch(`/api/support/${+btn.dataset.reply}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': cfg.csrf }, body: JSON.stringify({ body }) });
    if (!r.ok) throw new Error(r.status === 403 ? L.noaccess : L.failed);
    ta.value = '';
    note.textContent = L.sent;
    if ($('tw-inspect').dataset.ticket === String(+btn.dataset.reply)) ticket(+btn.dataset.reply);
  } catch (e) {
    note.className = 'tw-note tw-err';
    note.textContent = e.message;
  }
  btn.disabled = false;
}

// ---------- Start ----------
if (gl) {
  host.appendChild(gl.domElement);
  gl.domElement.setAttribute('role', 'img');
  gl.domElement.setAttribute('aria-label', L.pick);
  gl.domElement.addEventListener('pointerdown', (e) => { down = [e.clientX, e.clientY]; });
  gl.domElement.addEventListener('pointerup', (e) => {
    if (!down || Math.hypot(e.clientX - down[0], e.clientY - down[1]) > 5) return;
    const it = pick(e);
    select(it?.key ?? null, e.shiftKey);
  });
  new ResizeObserver(resize).observe(host);
} else {
  $('tw-table').hidden = false;
}

document.querySelectorAll('input[name="w"]').forEach((r) => r.addEventListener('change', () => {
  S.w = r.value;
  replayEnd(false);
  S.sel.clear();
  load();
  throughput();
  kpis();
}));
$('tw-st').addEventListener('change', () => {
  S.st = [...document.querySelectorAll('input[name="st"]:checked')].map((i) => i.value);
  replayEnd(false);
  S.sel.clear();
  load();
  throughput();
  kpis();
});

$('tw-ty').addEventListener('change', () => {
  S.ty = [...document.querySelectorAll('input[name="ty"]:checked')].map((i) => i.value);
  $('tw-ty-n').textContent = `${S.ty.length} / ${cfg.types.length}`;
  replayEnd(false);
  S.sel.clear();
  load();
  throughput();
  kpis();
});

// Ticket and revision changes come from the existing SSE stream (channels support and staff); the panel reloads its lists
let sseTimer = 0;
const staffStream = new EventSource('/api/stream?ch=support,staff');
staffStream.addEventListener('message', () => {
  clearTimeout(sseTimer);
  sseTimer = setTimeout(async () => {
    if (R.active || document.hidden) return;
    await load();
    kpis();
    const pid = $('tw-inspect').dataset.product;
    if (pid && T.view === 'gulv') product(+pid);
  }, 400);
});

// ---------- Replay: the loaded rows in time order, gaps compressed by the chosen speed ----------
const playBtn = $('tw-play');
function replayUi() {
  playBtn.setAttribute('aria-pressed', String(R.playing));
  playBtn.textContent = R.playing ? L.pause : L.play;
  const el = $('tw-live');
  if (R.active) { el.className = 'tw-live replay'; el.querySelector('span').textContent = L.replay; }
  header();
}
function replayStep() {
  if (!R.playing) return;
  if (R.i >= R.all.length) { replayEnd(true); return; }
  const e = R.all[R.i++];
  S.events.push(e);
  rebuild(new Set([e.id]));
  logList();
  header();
  const next = R.all[R.i];
  const gap = next ? Math.min(2000, Math.max(40, ((next.at - e.at) * 1000) / +$('tw-speed').value)) : 600;
  R.timer = setTimeout(replayStep, gap);
}
function replayEnd(restore) {
  if (!R.active) return;
  clearTimeout(R.timer);
  if (restore) S.events = R.all;
  Object.assign(R, { active: false, playing: false, i: 0, all: [] });
  replayUi();
  if (restore) { rebuild(); logList(); header(); status(true); }
}
playBtn.addEventListener('click', () => {
  if (!R.active) {
    Object.assign(R, { active: true, playing: true, i: 0, all: S.events.slice() });
    S.events = [];
    S.sel.clear();
    rebuild();
    inspect();
    replayUi();
    replayStep();
  } else if (R.playing) {
    R.playing = false;
    clearTimeout(R.timer);
    replayUi();
  } else {
    R.playing = true;
    replayUi();
    replayStep();
  }
});
document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

await load();
resize();
kpis();
throughput();
setInterval(poll, POLL_MS);
setInterval(() => { if (!R.active) { load(); throughput(); } kpis(); }, FULL_RELOAD_MS);
