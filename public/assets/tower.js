// Control Tower (feature 022). Plain Three.js, no framework, no build step.
// Every parcel is a database row from /api/tarn/*. Nothing moves unless a new row arrived.
import * as THREE from 'three';

import { cfg, L, $, esc, reduce, locale, tFmt, dFmt, kr, fillIn, post, vehSrc, getJson, num } from 'tower-lib';
import { showInsight } from 'tower-insight';
import { showDesk } from 'tower-desk';
import { dock, greet } from 'tower-soren';
import { showOverview, hideOverview } from 'tower-overview';
import { createSync, sweep as sweepView } from 'tower-sync';
import { tour } from './tour.js';

const canEdit = ['ansatt', 'admin'].includes(cfg.role);
const POLL_MS = 5000;
const FULL_RELOAD_MS = 300000;
const SPEED = 14; // world units per second along the belt

// ---------- Floor plan (world units, x/z). Products and shipments only (feature 032): no analytics event is a parcel. ----------
// Shipments arrive as pallets on the dock (mottak) and are unpacked in the Varemottak building (inntak). Products run
// along the trunk to the station that matches their state; Datakvalitet branches off Produktkontroll in red.
const POS = {
  inntak: [-17, 0], mottak: [-17, 12], kontroll: [-8, -6], kvalitet: [-8, -12], kunnskap: [-1, 6], kobling: [6, -6], klar: [16, 0],
};
const COL = {
  inntak: 0xf1e6cf, kontroll: 0xe3a84a, kvalitet: 0xe5483b, kunnskap: 0xa9b6d6, kobling: 0x9cc7b4, klar: 0xb9cfa8, mottak: 0xc9a27a,
};
const PRODUCT = 0xf1e6cf; // a product waiting in Varemottak is plain birch
const PALLET = 0xc9a27a; // a shipment is a wrapped pallet
// Conveyor dimensions: belt top height, belt width, half a station pad, how far a lateral runs diagonally off the trunk
const TOP = 1.25, BW = 1.3, PAD = 2.1, DIV = 2.2;
// Lateral from the trunk (z = 0) to a station: leaves along the trunk, bends off at 45 degrees, then runs straight to the pad
const lateral = (k) => {
  const [x, z] = POS[k], s = Math.sign(z);
  return [[x - DIV - 1.6, 0], [x - DIV, 0], [x, s * DIV], [x, z - s * PAD]];
};
// Stations fed by a lateral straight off the trunk
const LAT = ['kontroll', 'kunnskap', 'kobling'];
// [corner points, exception lane, starts on the trunk]
const BELTS = [
  [[[POS.inntak[0] - 0.1, 0], [POS.klar[0] - PAD, 0]]],
  ...LAT.map((k) => [lateral(k), false, true]),
  [[[POS.kontroll[0], POS.kontroll[1] - PAD], [POS.kvalitet[0], POS.kvalitet[1] + PAD]], true],
  [[[POS.mottak[0], POS.mottak[1] - PAD], [POS.mottak[0], 1.9]]],
];
const stationOf = (route) => route[route.length - 1];

// Round every inner corner with a quadratic bend so belts and parcel paths curve instead of kinking
function fillet(pts, r = 1.7, seg = 12) {
  const out = [pts[0]];
  for (let i = 1; i < pts.length - 1; i++) {
    const [ax, az] = pts[i - 1], [bx, bz] = pts[i], [cx, cz] = pts[i + 1];
    const l1 = Math.hypot(bx - ax, bz - az), l2 = Math.hypot(cx - bx, cz - bz);
    if (l1 < 1e-6 || l2 < 1e-6) continue;
    const u = [(bx - ax) / l1, (bz - az) / l1], w = [(cx - bx) / l2, (cz - bz) / l2];
    const turn = Math.acos(Math.max(-1, Math.min(1, u[0] * w[0] + u[1] * w[1])));
    if (turn < 1e-3) { out.push(pts[i]); continue; }
    const t = Math.min(r * Math.tan(turn / 2), l1 / 2, l2 / 2);
    const p = [bx - u[0] * t, bz - u[1] * t], q = [bx + w[0] * t, bz + w[1] * t];
    for (let k = 0; k <= seg; k++) {
      const s = k / seg, a = (1 - s) ** 2, b = 2 * (1 - s) * s, c = s * s;
      out.push([a * p[0] + b * bx + c * q[0], a * p[1] + b * bz + c * q[1]]);
    }
  }
  out.push(pts[pts.length - 1]);
  return out.filter((p, i) => i === 0 || Math.hypot(p[0] - out[i - 1][0], p[1] - out[i - 1][1]) > 1e-4);
}

// ---------- State ----------
const S = { w: 'dag', st: [...cfg.stations], ty: [...cfg.types], tickets: [], tasks: [], last: 0, total: 0, events: [], products: [], ships: [], incoming: [], received: [], picks: [], know: null, totals: {}, backlog: {}, congested: {}, sel: new Set(), items: [], taskView: 'aktive' };
// Every request names the window, the stations and the event types shown
// Bumped on every filter change. A response that started under an older filter is dropped, so it can't overwrite the new view.
let gen = 0;
// The inspector shows the newest pick only. Every pick takes a number; a response for an older number is dropped.
let shown = 0;
const qs = () => `w=${S.w}&st=${S.st.join(',')}&ty=${S.ty.map(encodeURIComponent).join(',')}`;
// Intro playback of rows already loaded (writes nothing)
const R = { active: false, playing: false, i: 0, all: [], timer: 0 };

// ---------- Scene ----------
const host = $('tw-canvas');
let gl = null;
try {
  const r = new THREE.WebGLRenderer({ antialias: true });
  if (!r.getContext()) throw new Error('no context');
  r.shadowMap.enabled = true;
  r.shadowMap.type = THREE.PCFSoftShadowMap;
  gl = r;
} catch {
  gl = null;
}

// Floor palette, mirrored from the tokens in tower.css
// The ground and grid follow the theme on <html> (light unless the account chose dark); the equipment keeps its colours
const DARK = document.documentElement.dataset.theme === 'dark';
const C = {
  ground: DARK ? 0x121714 : 0xe4e6e1, gridA: DARK ? 0x1f2723 : 0xcdd1cb, gridB: DARK ? 0x19201c : 0xd8dbd5,
  deck: 0x505853, deckRed: 0x5e2b25, slat: 0x3a413d, slatRed: 0x45201b,
  frame: 0x666e69, rail: 0xa3aba5, railRed: 0xd2493c, leg: 0x59615c, roller: 0xa9b0ab,
  pad: 0x3a423d, wall: 0x4a524d, roof: 0x5a625d,
};
const scene = new THREE.Scene();
scene.background = new THREE.Color(C.ground);
const camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0.1, 300);
// Orbit camera: turns a full circle around the floor, tilts between a low view and straight down, zooms towards the pointer
const VIEW0 = { theta: Math.PI / 4, phi: Math.atan2(32, Math.hypot(30, 30)) };
const CAM = { ...VIEW0, target: new THREE.Vector3(), dist: 60 };
function placeCam() {
  const { theta, phi, target: t, dist: d } = CAM;
  camera.position.set(t.x + d * Math.cos(phi) * Math.sin(theta), t.y + d * Math.sin(phi), t.z + d * Math.cos(phi) * Math.cos(theta));
  camera.lookAt(t);
  camera.updateMatrixWorld();
}
placeCam();
const hemi = new THREE.HemisphereLight(0xeef0e8, DARK ? 0x18201c : 0x8a908a, DARK ? 0.75 : 0.95);
scene.add(hemi);
const sun = new THREE.DirectionalLight(0xfff6e8, 2.6);
sun.position.set(-14, 34, 10);
sun.castShadow = true;
sun.shadow.mapSize.set(2048, 2048);
Object.assign(sun.shadow.camera, { left: -32, right: 32, top: 32, bottom: -32, near: 1, far: 90 });
sun.shadow.bias = -0.0006;
sun.shadow.normalBias = 0.02;
sun.shadow.radius = 3;
scene.add(sun);

const ground = new THREE.Mesh(new THREE.PlaneGeometry(140, 140), new THREE.MeshStandardMaterial({ color: C.ground, roughness: 1 }));
ground.rotation.x = -Math.PI / 2;
ground.receiveShadow = true;
scene.add(ground);
const grid = new THREE.GridHelper(80, 40, C.gridA, C.gridB);
grid.position.y = 0.005;
scene.add(grid);

// Belt surface: slats across the belt. The texture only scrolls while a parcel is travelling.
function slatTexture(base, line) {
  const c = document.createElement('canvas');
  c.width = 8; c.height = 64;
  const x = c.getContext('2d');
  x.fillStyle = '#' + base.toString(16).padStart(6, '0');
  x.fillRect(0, 0, 8, 64);
  x.fillStyle = '#' + line.toString(16).padStart(6, '0');
  x.fillRect(0, 0, 8, 6);
  const t = new THREE.CanvasTexture(c);
  t.wrapS = t.wrapT = THREE.RepeatWrapping;
  t.colorSpace = THREE.SRGBColorSpace;
  t.anisotropy = 4;
  return t;
}
const SLAT = 0.42; // world units per slat
const slats = [slatTexture(C.deck, C.slat), slatTexture(C.deckRed, C.slatRed)];
const M = {
  deck: new THREE.MeshStandardMaterial({ map: slats[0], roughness: 0.85 }),
  deckRed: new THREE.MeshStandardMaterial({ map: slats[1], roughness: 0.85 }),
  frame: new THREE.MeshStandardMaterial({ color: C.frame, roughness: 0.7, metalness: 0.2 }),
  rail: new THREE.MeshStandardMaterial({ color: C.rail, roughness: 0.45, metalness: 0.5 }),
  railRed: new THREE.MeshStandardMaterial({ color: C.railRed, roughness: 0.5, metalness: 0.2 }),
  leg: new THREE.MeshStandardMaterial({ color: C.leg, roughness: 0.6, metalness: 0.4 }),
  roller: new THREE.MeshStandardMaterial({ color: C.roller, roughness: 0.35, metalness: 0.6 }),
  pad: new THREE.MeshStandardMaterial({ color: C.pad, roughness: 0.9 }),
  wall: new THREE.MeshStandardMaterial({ color: C.wall, roughness: 0.8 }),
  roof: new THREE.MeshStandardMaterial({ color: C.roof, roughness: 0.7 }),
};

// Sample a filleted polyline: points with running distance, unit tangent and left normal
function samples(pts) {
  // Dense enough that cutting a belt at the trunk edge, opening a rail or placing a leg lands within 0.3 units
  const p = [];
  for (const q of fillet(pts)) {
    const l = p.at(-1);
    if (l) {
      const n = Math.ceil(Math.hypot(q[0] - l[0], q[1] - l[1]) / 0.3);
      for (let k = 1; k < n; k++) p.push([l[0] + ((q[0] - l[0]) * k) / n, l[1] + ((q[1] - l[1]) * k) / n]);
    }
    p.push(q);
  }
  const out = [];
  let d = 0;
  for (let i = 0; i < p.length; i++) {
    if (i) d += Math.hypot(p[i][0] - p[i - 1][0], p[i][1] - p[i - 1][1]);
    const a = p[Math.max(0, i - 1)], b = p[Math.min(p.length - 1, i + 1)];
    const l = Math.hypot(b[0] - a[0], b[1] - a[1]) || 1;
    const t = [(b[0] - a[0]) / l, (b[1] - a[1]) / l];
    out.push({ x: p[i][0], z: p[i][1], d, t, n: [-t[1], t[0]] });
  }
  return out;
}

// Sweep a closed 2D profile (lateral offset, height) along samples. Flat-shaded faces, winding matched to the outward normal.
function sweep(sm, profile, { caps = false, uv = false } = {}) {
  const pos = [], nor = [], tex = [];
  const at = (s, [o, y]) => new THREE.Vector3(s.x + s.n[0] * o, y, s.z + s.n[1] * o);
  const cx = profile.reduce((a, p) => a + p[0], 0) / profile.length, cy = profile.reduce((a, p) => a + p[1], 0) / profile.length;
  const quad = (a, b, c, d, n, uvs) => {
    const e1 = new THREE.Vector3().subVectors(b, a), e2 = new THREE.Vector3().subVectors(c, a);
    const flip = e1.cross(e2).dot(n) < 0;
    const tri = flip ? [[a, c, b], [a, d, c]] : [[a, b, c], [a, c, d]];
    const tu = flip ? [[0, 2, 1], [0, 3, 2]] : [[0, 1, 2], [0, 2, 3]];
    tri.forEach((t, k) => t.forEach((v, j) => {
      pos.push(v.x, v.y, v.z); nor.push(n.x, n.y, n.z);
      const q = uvs?.[tu[k][j]] ?? [0, 0];
      tex.push(q[0], q[1]);
    }));
  };
  for (let i = 1; i < sm.length; i++) {
    const s0 = sm[i - 1], s1 = sm[i];
    for (let j = 0; j < profile.length; j++) {
      const p0 = profile[j], p1 = profile[(j + 1) % profile.length];
      const mo = (p0[0] + p1[0]) / 2 - cx, my = (p0[1] + p1[1]) / 2 - cy;
      const eo = p1[0] - p0[0], ey = p1[1] - p0[1];
      let no = ey, ny = -eo;
      if (no * mo + ny * my < 0) { no = -no; ny = -ny; }
      const nm = (s) => new THREE.Vector3(s.n[0] * no, ny, s.n[1] * no).normalize();
      const n = nm(s0).add(nm(s1)).normalize();
      const uvs = uv ? [[0, s0.d / SLAT], [1, s0.d / SLAT], [1, s1.d / SLAT], [0, s1.d / SLAT]] : null;
      quad(at(s0, p0), at(s0, p1), at(s1, p1), at(s1, p0), n, uvs);
    }
  }
  if (caps) for (const [s, sg] of [[sm[0], -1], [sm[sm.length - 1], 1]]) {
    const n = new THREE.Vector3(s.t[0] * sg, 0, s.t[1] * sg), c = at(s, [cx, cy]);
    for (let j = 0; j < profile.length; j++) {
      const a = at(s, profile[j]), b = at(s, profile[(j + 1) % profile.length]);
      quad(c, a, b, c.clone(), n, null);
    }
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
  g.setAttribute('normal', new THREE.Float32BufferAttribute(nor, 3));
  g.setAttribute('uv', new THREE.Float32BufferAttribute(tex, 2));
  return g;
}

function add(geo, mat, cast = true) {
  const m = new THREE.Mesh(geo, mat);
  m.castShadow = cast;
  m.receiveShadow = true;
  scene.add(m);
  return m;
}

// Split samples into runs, dropping the ones a test rejects (rail gaps at diverts, overlap with the trunk)
function runs(sm, keep) {
  const out = [];
  let cur = [];
  for (const s of sm) {
    if (keep(s)) cur.push(s);
    else { if (cur.length > 1) out.push(cur); cur = []; }
  }
  if (cur.length > 1) out.push(cur);
  return out;
}

const H = BW / 2, FRAME = 0.34, RAIL_W = 0.07, RAIL_H = 0.16;
const legs = [], beams = [], rollers = [];
const bounds = [];
// Where each divert leaves the trunk, per side: the rail on that side opens there
const gaps = [];
for (const [pts, , fromTrunk] of BELTS) {
  if (!fromTrunk) continue;
  const sm = samples(pts), side = Math.sign(pts[pts.length - 1][1]);
  const out = sm.find((s) => Math.abs(s.z) >= H);
  if (out) gaps.push({ side, a: out.x - 1.15, b: out.x + 1.15 });
}

for (const [pts, red, fromTrunk] of BELTS) {
  const all = samples(pts);
  // A divert shares the trunk at its start: draw it from where it leaves the trunk edge
  const sm = fromTrunk ? all.slice(Math.max(0, all.findIndex((s) => Math.abs(s.z) >= H - 0.05))) : all;
  const trunk = !fromTrunk && pts.every((p) => p[1] === 0);
  add(sweep(sm, [[-H, TOP - FRAME], [H, TOP - FRAME], [H, TOP - 0.02], [-H, TOP - 0.02]], { caps: true }), M.frame);
  add(sweep(sm, [[-H + 0.04, TOP - 0.03], [H - 0.04, TOP - 0.03], [H - 0.04, TOP], [-H + 0.04, TOP]], { uv: true }), red ? M.deckRed : M.deck, false);
  for (const side of [-1, 1]) {
    const o = side * (H + RAIL_W / 2);
    const keep = (s) => {
      if (fromTrunk && Math.abs(s.z) < H + 0.4) return false;
      if (trunk) return !gaps.some((g) => g.side === side && s.x > g.a && s.x < g.b);
      return true;
    };
    for (const r of runs(sm, keep)) add(sweep(r, [[o - RAIL_W / 2, TOP - 0.1], [o + RAIL_W / 2, TOP - 0.1], [o + RAIL_W / 2, TOP + RAIL_H], [o - RAIL_W / 2, TOP + RAIL_H]], { caps: true }), red ? M.railRed : M.rail);
  }
  // Legs in pairs with a cross beam, evenly along the belt; end rollers at both ends
  const len = sm[sm.length - 1].d - sm[0].d, n = Math.max(1, Math.round(len / 2.6));
  for (let k = 0; k <= n; k++) {
    const target = sm[0].d + (len * k) / n;
    const s = sm.find((q) => q.d >= target - 1e-6) ?? sm[sm.length - 1];
    if (fromTrunk && Math.abs(s.z) < H + 0.9) continue;
    const ang = Math.atan2(s.t[0], s.t[1]);
    for (const side of [-1, 1]) legs.push([s.x + s.n[0] * side * (H - 0.12), s.z + s.n[1] * side * (H - 0.12), ang]);
    beams.push([s.x, s.z, ang]);
  }
  for (const s of fromTrunk ? [sm[sm.length - 1]] : [sm[0], sm[sm.length - 1]]) rollers.push([s.x, s.z, Math.atan2(s.t[0], s.t[1])]);
  for (const s of sm) bounds.push(new THREE.Vector3(s.x, TOP, s.z), new THREE.Vector3(s.x, 0, s.z));
}

const ENDS = BELTS.flatMap(([p]) => [p[0], p[p.length - 1]]);
// Stations: raised decks at belt height on four legs, low walls, the station colour along the rim
const legGeo = new THREE.BoxGeometry(0.14, 1, 0.14);
for (const [k, [x, z]] of Object.entries(POS)) {
  if (k === 'inntak') continue;
  const S2 = PAD * 2;
  const deck = add(new THREE.BoxGeometry(S2, 0.22, S2), M.pad);
  deck.position.set(x, TOP - 0.11, z);
  const rim = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.BoxGeometry(S2, 0.22, S2)), new THREE.LineBasicMaterial({ color: COL[k] }));
  rim.position.copy(deck.position);
  scene.add(rim);
  // Low walls on every edge no belt enters or leaves through
  const wh = 0.38, wt = 0.1;
  for (const [ex, ez] of [[0, -1], [0, 1], [-1, 0], [1, 0]]) {
    const ox = x + ex * PAD, oz = z + ez * PAD;
    if (ENDS.some(([px, pz]) => Math.hypot(px - ox, pz - oz) < 0.6)) continue;
    const m = add(new THREE.BoxGeometry(ex ? wt : S2, wh, ex ? S2 : wt), M.wall);
    m.position.set(x + ex * (PAD - wt / 2), TOP + wh / 2, z + ez * (PAD - wt / 2));
  }
  const stripe = add(new THREE.BoxGeometry(S2 + 0.02, 0.06, S2 + 0.02), new THREE.MeshStandardMaterial({ color: COL[k], roughness: 0.6 }), false);
  stripe.position.set(x, TOP - 0.25, z);
  for (const sx of [-1, 1]) for (const sz of [-1, 1]) legs.push([x + sx * (PAD - 0.25), z + sz * (PAD - 0.25), 0]);
  for (const sx of [-1, 1]) for (const sz of [-1, 1]) bounds.push(new THREE.Vector3(x + sx * PAD, 0, z + sz * PAD), new THREE.Vector3(x + sx * PAD, TOP + 1.2, z + sz * PAD));
}

// Intake: a closed building the trunk leaves through a lit opening
{
  const [x, z] = POS.inntak, w = 3.4, h = 3.2, d = 3.6, cx = x - 1.5;
  const body = add(new THREE.BoxGeometry(w, h, d), M.wall);
  body.position.set(cx, h / 2, z);
  const roof = add(new THREE.BoxGeometry(w + 0.2, 0.2, d + 0.2), M.roof);
  roof.position.set(cx, h + 0.1, z);
  const edge = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.BoxGeometry(w, h, d)), new THREE.LineBasicMaterial({ color: 0x56605a }));
  edge.position.copy(body.position);
  scene.add(edge);
  const hole = add(new THREE.BoxGeometry(0.05, 0.9, BW + 0.3), new THREE.MeshBasicMaterial({ color: 0xe9c27a }), false);
  hole.position.set(cx + w / 2 + 0.01, TOP + 0.35, z);
  for (const dz of [-1.1, 0, 1.1]) {
    const v = add(new THREE.BoxGeometry(0.5, 0.35, 0.5), M.frame);
    v.position.set(cx - 0.6, h + 0.38, z + dz);
  }
  for (const sx of [-1, 1]) for (const sz of [-1, 1]) bounds.push(new THREE.Vector3(cx + sx * w / 2, 0, z + sz * d / 2), new THREE.Vector3(cx + sx * w / 2, h + 0.6, z + sz * d / 2));
}

function instanced(geo, mat, list, place) {
  const m = new THREE.InstancedMesh(geo, mat, list.length);
  const o = new THREE.Object3D();
  list.forEach((v, i) => { place(o, v); o.updateMatrix(); m.setMatrixAt(i, o.matrix); });
  m.castShadow = true;
  m.receiveShadow = true;
  scene.add(m);
}
const legH = TOP - FRAME;
instanced(legGeo, M.leg, legs, (o, [x, z, a]) => { o.position.set(x, legH / 2, z); o.rotation.set(0, a, 0); o.scale.set(1, legH, 1); });
instanced(new THREE.BoxGeometry(0.32, 0.03, 0.32), M.leg, legs, (o, [x, z, a]) => { o.position.set(x, 0.015, z); o.rotation.set(0, a, 0); o.scale.set(1, 1, 1); });
instanced(new THREE.BoxGeometry(BW - 0.1, 0.1, 0.12), M.leg, beams, (o, [x, z, a]) => { o.position.set(x, legH * 0.42, z); o.rotation.set(0, a, 0); });
instanced(new THREE.CylinderGeometry(0.19, 0.19, BW + 0.02, 16), M.roller, rollers, (o, [x, z, a]) => { o.position.set(x, TOP - 0.17, z); o.rotation.set(0, a, Math.PI / 2); });

// Everything built so far is the fixed structure the synchronisation builds up (tower-sync.js); parcels come after
const STRUCTURE = scene.children.filter((o) => o !== ground && o !== grid && !o.isLight);

// One instanced mesh for every parcel: a solid box and a darker edge frame share the same instance matrices.
const MAX = 820;
const SIZE = 0.5;
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
// Parcel: a slightly flattened box with a tape band, so it reads as a carton and not a cube
const boxGeo = new THREE.BoxGeometry(SIZE, SIZE * 0.78, SIZE * 0.86);
const fill = new THREE.InstancedMesh(boxGeo, new THREE.MeshStandardMaterial({ roughness: 0.75 }), MAX);
const frame = new THREE.InstancedMesh(new THREE.BoxGeometry(SIZE * 0.22, SIZE * 0.78 + 0.008, SIZE * 0.86 + 0.008), new THREE.MeshStandardMaterial({ roughness: 0.6 }), MAX);
const solid = new THREE.InstancedMesh(new THREE.BoxGeometry(SIZE * 1.06, SIZE * 0.84, SIZE * 0.92), new THREE.MeshStandardMaterial({ roughness: 0.5, emissive: 0x2a1c05 }), MAX);
const ring = new THREE.InstancedMesh(frameGeometry(SIZE * 1.3, 0.05), new THREE.MeshBasicMaterial({ color: 0xf0b44c }), MAX);
for (const m of [fill, frame, solid, ring]) {
  m.count = 0;
  m.frustumCulled = false;
  m.castShadow = m !== ring;
  scene.add(m);
}

const tmp = new THREE.Object3D();
const color = new THREE.Color();
const PY = TOP + (SIZE * 0.78) / 2; // parcel centre height on a belt or deck

// ---------- Placement ----------
// Rest position: products pile on their station deck in rows of six, then in layers. Products waiting in Varemottak
// queue on the trunk just outside the building; shipment pallets stand in a row on the dock.
function restSlot(station, i) {
  if (station === 'inntak') {
    const per = 7, x = POS.inntak[0] + 1.4 + (i % per) * 0.9, y = PY + Math.floor(i / per) * SIZE * 0.78;
    return new THREE.Vector3(x, y, 0);
  }
  if (station === 'mottak') {
    const [x, z] = POS.mottak;
    return new THREE.Vector3(x - 1.3 + (i % 3) * 1.3, PY + 0.1 + Math.floor(i / 9) * 1.1, z - 1.3 + (Math.floor(i / 3) % 3) * 1.3);
  }
  const [x, z] = POS[station], per = 36, layer = Math.floor(i / per), r = i % per;
  return new THREE.Vector3(x - 1.5 + (r % 6) * 0.6, PY + layer * SIZE * 0.78, z - 1.5 + Math.floor(r / 6) * 0.6);
}
// Way from the trunk to a station along the belts. Datakvalitet is reached through Produktkontroll.
function approach(k) {
  if (k === 'kvalitet') return [...approach('kontroll'), POS.kvalitet];
  if (LAT.includes(k)) return [...fillet(lateral(k)), POS[k]];
  if (k === 'inntak') return [[POS.inntak[0] + 1.4, 0]];
  return [[POS.klar[0] - PAD, 0], POS.klar];
}
function pathPoints(route) {
  return [POS.inntak, ...approach(stationOf(route))].map(([x, z]) => new THREE.Vector3(x, PY, z));
}

// The camera turns around the centre of the floor
const home = new THREE.Box3().setFromPoints(bounds).getCenter(new THREE.Vector3()).setY(TOP / 2);
CAM.target.copy(home);
placeCam();
// Frustum that fits the whole floor at the current angle, with headroom for the station labels
function frameView() {
  const w = host.clientWidth, h = host.clientHeight, a = w / Math.max(h, 1), v = new THREE.Vector3();
  let hw = 0, hh = 0;
  for (const p of bounds) {
    v.copy(p).sub(home).add(CAM.target).applyMatrix4(camera.matrixWorldInverse);
    hw = Math.max(hw, Math.abs(v.x));
    hh = Math.max(hh, Math.abs(v.y));
  }
  hw *= 1.04; hh = hh * 1.04 + 1.2;
  if (hw / hh < a) hw = hh * a; else hh = hw / a;
  camera.left = -hw; camera.right = hw; camera.top = hh; camera.bottom = -hh;
  camera.updateProjectionMatrix();
}

// One object per stored row: a carton per product at the station its state puts it on, a pallet per inbound shipment on
// the dock. A key that is new since the last read travels in from the Varemottak building along the belts.
function rebuild(animateIds = new Set()) {
  const old = new Map(S.items.map((it) => [it.key, it]));
  const items = [];
  const counter = {};
  const travel = (it, station) => {
    const prev = old.get(it.key);
    if (prev?.path) Object.assign(it, { path: prev.path, d: prev.d, len: prev.len });
    else if (animateIds.has(it.key) && !reduce && station !== 'mottak') {
      const path = pathPoints([station]);
      path.push(it.rest.clone()); // glide off the belt onto its place in the pile
      let len = 0;
      for (let i = 1; i < path.length; i++) len += path[i].distanceTo(path[i - 1]);
      Object.assign(it, { path, d: 0, len });
    }
  };
  for (const sh of S.ships) {
    const n = counter.mottak ?? 0;
    counter.mottak = n + 1;
    items.push({ key: 's:' + sh.id, kind: 'ship', ref: sh, station: 'mottak', rest: restSlot('mottak', n), color: PALLET, scale: [2.2, 1.7, 2.2] });
  }
  for (const p of S.products) {
    const n = counter[p.station] ?? 0;
    counter[p.station] = n + 1;
    const it = { key: 'p:' + p.id, kind: 'product', ref: p, station: p.station, rest: restSlot(p.station, n), color: p.station === 'inntak' ? PRODUCT : COL[p.station] };
    travel(it, p.station);
    items.push(it);
  }
  S.items = items.slice(-MAX);
  S.counts = { ...S.totals, mottak: S.ships.length };
  shelves();
  draw();
  labels();
  if (S.items.some((it) => it.path)) loop();
}

// Varekunnskap is the knowledge base, not a pile of products: one column per source of specifications and one for the
// filter values still missing, each as tall as its share. Rebuilt only when the counts change.
const SHELF = { sortre: 0x7fbf8e, ansatt: 0xb39de6, utledet: 0xe2c46b, bruker: 0x8ea9e3, missing: 0x8a3a30 };
let shelfKey = '', shelfGroup = null;
function shelves() {
  const k = S.know;
  if (!gl || !k) return;
  const key = JSON.stringify(k);
  if (key === shelfKey) return;
  shelfKey = key;
  if (shelfGroup) { scene.remove(shelfGroup); shelfGroup.traverse((o) => { o.geometry?.dispose(); o.material?.dispose(); }); }
  shelfGroup = new THREE.Group();
  const cols = ['sortre', 'ansatt', 'utledet', 'bruker', 'missing'], max = Math.max(1, ...cols.map((c) => k[c]));
  const [x, z] = POS.kunnskap;
  cols.forEach((c, i) => {
    const h = 0.12 + 2.4 * Math.sqrt(k[c] / max);
    const m = new THREE.Mesh(new THREE.BoxGeometry(0.55, h, 1.6), new THREE.MeshStandardMaterial({ color: SHELF[c], roughness: 0.7, transparent: c === 'missing', opacity: c === 'missing' ? 0.55 : 1 }));
    m.position.set(x - 1.4 + i * 0.7, TOP + h / 2, z);
    m.castShadow = true; m.receiveShadow = true;
    shelfGroup.add(m);
  });
  scene.add(shelfGroup);
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
  const spin = reduce ? 0 : (performance.now() / 1000) * 0.9;
  S.items.forEach((it, i) => {
    const sel = S.sel.has(it.key);
    if (DRAG?.key === it.key) tmp.position.copy(DRAG.at);
    else tmp.position.copy(posOf(it));
    if (sel) tmp.position.y += 0.3;
    tmp.rotation.set(0, sel ? spin : 0, 0);
    tmp.scale.set(...(it.scale ?? [1, 1, 1]));
    tmp.updateMatrix();
    fill.setMatrixAt(i, tmp.matrix);
    frame.setMatrixAt(i, tmp.matrix);
    color.setHex(it.color);
    fill.setColorAt(i, color);
    frame.setColorAt(i, color.multiplyScalar(0.62)); // tape band, a shade darker than the carton
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
      it.d += SPEED * (it.v ?? 1) * dt;
      if (it.d >= it.len) { delete it.path; delete it.v; }
      else moving = true;
    }
    // The belts run only while something is on them
    if (moving) for (const t of slats) t.offset.y -= (SPEED * dt) / SLAT;
    draw();
    // Keep drawing while parcels travel, a selected carton turns, or a carton is being dragged
    if (moving || (S.sel.size && !reduce) || DRAG) requestAnimationFrame(step);
    else looping = false;
  };
  requestAnimationFrame(step);
}

// ---------- Camera, labels, picking ----------
function resize() {
  if (!gl) return;
  const w = host.clientWidth, h = host.clientHeight;
  if (!w || !h) return; // hidden behind another page; sized again when the floor is shown
  gl.setPixelRatio(Math.min(devicePixelRatio, 2));
  gl.setSize(w, h, false);
  frameView();
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
    if (k === 'inntak') v.set(x - 1.5, 3.9, z - 1.8); else v.set(x, TOP + 0.7, z - PAD);
    v.project(camera);
    const n = S.counts?.[k] ?? 0;
    // What is inside, without clicking: up to three product photos (shipments show their first cargo line)
    const pics = (k === 'mottak' ? S.ships.map((sh) => sh.items[0]).filter(Boolean) : S.products.filter((p) => p.station === k)).slice(0, 3)
      .map((p) => (p.img ? `<img src="/${esc(p.img)}" alt="">` : '<i class="tw-noimg"></i>')).join('');
    const extra = k === 'kunnskap' ? `${S.know?.sortre ?? 0} / ${S.know?.utledet ?? 0} / ${S.know?.bruker ?? 0}` : n;
    html += `<span class="tw-label ${k === 'kvalitet' && n ? 'avvik' : ''}" style="left:${((v.x + 1) / 2) * w}px;top:${((1 - v.y) / 2) * h}px">${L.icons[k]}${esc(L.stations[k])}<b>${esc(extra)}</b>${pics ? `<span class="tw-pics">${pics}</span>` : ''}</span>`;
  }
  box.innerHTML = html;
  // Keep every label inside the floor; the stem stays on its station
  for (const el of box.children) {
    const x = parseFloat(el.style.left), half = el.offsetWidth / 2;
    const dx = Math.min(Math.max(x, half + 4), w - half - 4) - x;
    if (dx) { el.style.left = `${x + dx}px`; el.style.setProperty('--stem', `calc(50% - ${dx}px)`); }
  }
}

const ray = new THREE.Raycaster(), mouse = new THREE.Vector2();
function aim(ev) {
  const r = gl.domElement.getBoundingClientRect();
  mouse.set(((ev.clientX - r.left) / r.width) * 2 - 1, -((ev.clientY - r.top) / r.height) * 2 + 1);
  ray.setFromCamera(mouse, camera);
}
function pick(ev) {
  aim(ev);
  fill.computeBoundingSphere(); // cached bounds go stale when instances move or the count changes
  const hit = ray.intersectObject(fill, false)[0];
  return hit ? S.items[hit.instanceId] : null;
}

// ---------- Camera control and dragging ----------
// Left drag on empty floor turns the view (full circle) and tilts it; right drag pans; the wheel zooms towards the pointer.
// Left drag on an exception parcel (Avvik) carries it to a station that can act on it. Every drop asks first and then
// calls the same endpoint as the matching button in the inspector, so the floor only changes when a row changes.
let DRAG = null; // { key, at, over, targets, held }
let mapFocus = 0; // event whose product search gets focus once the inspector has loaded it
const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
const termOf = (e) => String(e.data?.q ?? '').trim();
const draggable = (it) => it?.kind === 'event' && it.station === 'avvik' && !R.active;
const atBelt = new THREE.Plane(new THREE.Vector3(0, 1, 0), -TOP);
function beltPoint(ev) {
  aim(ev);
  return ray.ray.intersectPlane(atBelt, new THREE.Vector3());
}
function stationAt(p) {
  for (const k of cfg.stations) {
    const [x, z] = POS[k];
    if (Math.abs(p.x - x) <= PAD && Math.abs(p.z - z) <= PAD) return k;
  }
  return 'floor';
}
// Where an exception can go: Kvalitet makes an improvement task, Søk opens product mapping (catalogue staff, needs a
// search term), anywhere else on the floor marks it reviewed. Other stations do not accept it.
function targetsFor(it) {
  const t = { floor: 'sett', kvalitet: 'oppgave' };
  if (canMap && termOf(it.ref)) t.sok = 'kobling';
  return t;
}
function render() { draw(); labels(); }
function zoomBy(f, ev) {
  const before = ev && beltPoint(ev);
  camera.zoom = clamp(camera.zoom * f, 0.7, 8);
  camera.updateProjectionMatrix();
  const after = ev && beltPoint(ev);
  if (before && after) { CAM.target.add(before.sub(after)); placeCam(); }
  render();
}
function resetView() {
  Object.assign(CAM, VIEW0);
  CAM.target.copy(home);
  camera.zoom = 1;
  placeCam();
  frameView();
  render();
}
const dropBox = $('tw-drop'), floorEl = host.parentElement;
function dropSay(html) { dropBox.hidden = !html; dropBox.innerHTML = html ?? ''; }
function endDrag(back = true) {
  const d = DRAG;
  DRAG = null;
  floorEl.classList.remove('dragging', 'drop-floor');
  dropSay(null);
  const it = d && S.items.find((x) => x.key === d.key);
  // Glide back to its place in the pile
  if (back && it && !reduce) {
    const from = d.at.clone(), mid = from.clone().lerp(it.rest, 0.5).setY(Math.max(from.y, it.rest.y) + 1.2);
    Object.assign(it, { path: [from, mid, it.rest.clone()], d: 0, len: from.distanceTo(mid) + mid.distanceTo(it.rest), v: 3 });
  }
  render();
  loop();
}
function dropAsk(d, act) {
  const it = S.items.find((x) => x.key === d.key);
  if (!it) { endDrag(false); return; }
  if (act === 'kobling') { mapFocus = it.ref.id; endDrag(); select(it.key); return; }
  d.held = true;
  const e = it.ref, q = termOf(e), FL = L.fl;
  const msg = act === 'oppgave' ? fillIn(FL.askTask, { q: q || `${evLabel(e.type)} #${e.id}` }) : fillIn(FL.askSeen, { id: e.id });
  dropSay(`<p>${esc(msg)}</p><span class="tw-drop-acts"><button type="button" class="tw-btn tw-go" data-drop="${act}">${esc(FL.confirm)}</button><button type="button" class="tw-btn" data-drop="">${esc(FL.cancel)}</button></span>`);
  dropBox.querySelector('.tw-go').focus();
}
async function dropDo(act, btn) {
  const d = DRAG, it = d && S.items.find((x) => x.key === d.key);
  if (!it) { endDrag(false); return; }
  const e = it.ref, q = termOf(e), FL = L.fl;
  btn.disabled = true;
  const title = q && e.type === 'search_zero' ? fillIn(L.ed.taskDefault, { q }) : `${evLabel(e.type)} #${e.id}${q ? `: «${q}»` : ''}`;
  try {
    const r = await (act === 'oppgave' ? post(`/api/tarn/hendelse/${e.id}/oppgave`, { title, product: null }) : post(`/api/tarn/hendelse/${e.id}/sett`));
    const j = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(j.error || (r.status === 403 ? L.noaccess : L.failed));
    endDrag();
    await load();
    select(it.key);
    dropSay(`<p class="tw-ok">${esc(act === 'oppgave' ? fillIn(FL.taskMade, { id: +j.id }) : FL.seen)}</p>`);
    setTimeout(() => { if (!DRAG) dropSay(null); }, 3500);
  } catch (err) {
    btn.disabled = false;
    dropBox.querySelector('p').insertAdjacentHTML('beforeend', ` <span class="tw-err">${esc(err.message)}</span>`);
  }
}
dropBox.addEventListener('click', (e) => {
  const b = e.target.closest('[data-drop]');
  if (!b) return;
  if (b.dataset.drop) dropDo(b.dataset.drop, b);
  else endDrag();
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && DRAG) endDrag(); });

function pointers(el) {
  let down = null;
  el.addEventListener('contextmenu', (e) => e.preventDefault());
  el.addEventListener('pointerdown', (e) => {
    if (DRAG?.held) return;
    const it = e.button === 0 ? pick(e) : null;
    down = { x: e.clientX, y: e.clientY, theta: CAM.theta, phi: CAM.phi, it, pan: e.button === 2 || e.shiftKey && !it, last: beltPoint(e), mode: null };
    el.setPointerCapture(e.pointerId);
  });
  el.addEventListener('pointermove', (e) => {
    if (!down) {
      if (!DRAG) el.style.cursor = draggable(pick(e)) ? 'grab' : '';
      return;
    }
    const dx = e.clientX - down.x, dy = e.clientY - down.y;
    if (!down.mode) {
      if (Math.hypot(dx, dy) < 5) return;
      down.mode = down.pan ? 'pan' : draggable(down.it) ? 'drag' : 'orbit';
      if (down.mode === 'drag') {
        DRAG = { key: down.it.key, at: down.it.rest.clone(), over: null, targets: targetsFor(down.it), held: false };
        floorEl.classList.add('dragging');
        el.style.cursor = 'grabbing';
        dropSay(`<p>${esc(fillIn(L.fl.hint, { kvalitet: L.stations.kvalitet, sok: L.stations.sok }))}</p>`);
        loop();
      }
    }
    if (down.mode === 'orbit') {
      CAM.theta = down.theta - dx * 0.008;
      CAM.phi = clamp(down.phi + dy * 0.006, 0.15, 1.5);
      placeCam();
      render();
    } else if (down.mode === 'pan') {
      const p = beltPoint(e);
      if (p && down.last) { CAM.target.add(down.last.clone().sub(p)); placeCam(); render(); }
    } else if (down.mode === 'drag') {
      const p = beltPoint(e);
      if (!p) return;
      DRAG.at.set(p.x, PY + 0.9, p.z);
      const over = stationAt(p);
      if (over !== DRAG.over) {
        DRAG.over = over;
        floorEl.classList.toggle('drop-floor', over === 'floor');
        labels();
      }
    }
  });
  const up = (e, cancel) => {
    if (!down) return;
    const { mode, it } = down;
    down = null;
    if (mode === 'drag') {
      el.style.cursor = '';
      const act = !cancel && DRAG.targets[DRAG.over];
      if (act) dropAsk(DRAG, act); else endDrag();
    } else if (!mode && !cancel) select(it?.key ?? null, e.shiftKey);
  };
  el.addEventListener('pointerup', (e) => up(e, false));
  el.addEventListener('pointercancel', (e) => up(e, true));
  el.addEventListener('dblclick', resetView);
  el.addEventListener('wheel', (e) => { e.preventDefault(); zoomBy(e.deltaY > 0 ? 1 / 1.15 : 1.15, e); }, { passive: false });
}
// Legend: what each station holds, one line each
$('tw-legend').innerHTML = `<h2>${esc(L.legend)}</h2><dl>${['inntak', ...cfg.stations].map((k) => `<dt>${L.icons[k]}${esc(L.stations[k])}</dt><dd>${esc(L.what[k])}</dd>`).join('')}</dl>`;
$('tw-legend-btn').addEventListener('click', (e) => {
  const open = $('tw-legend').hidden;
  $('tw-legend').hidden = !open;
  e.currentTarget.setAttribute('aria-expanded', String(open));
});
document.querySelector('.tw-tools')?.addEventListener('click', (e) => {
  const b = e.target.closest('[data-cam]');
  if (!b) return;
  if (b.dataset.cam === 'reset') resetView();
  else zoomBy(b.dataset.cam === 'in' ? 1.25 : 1 / 1.25);
});

// ---------- Selection and inspector ----------
// The inspector floats over the floor and never replaces the Varemottak and Forbedringsoppgaver panel beside it.
const G = L.g;
const when = (at) => (at ? dFmt.format(new Date(at * 1000)) : '');
const thumb = (img, cls = 'tw-thumb') => (img ? `<img class="${cls}" src="/${esc(img)}" alt="" loading="lazy">` : `<span class="${cls} tw-noimg" aria-hidden="true"></span>`);
const chip = (st) => `<span class="tw-chip s-${esc(st)}">${esc(G.status[st] ?? st)}</span>`;
function select(key, add = false) {
  if (!key) S.sel.clear();
  else if (add) S.sel.has(key) ? S.sel.delete(key) : S.sel.add(key);
  else { S.sel.clear(); S.sel.add(key); }
  draw();
  loop();
  inspect();
}
function inspOpen(html, keep = {}) {
  const box = $('tw-inspect');
  box.hidden = false;
  box.innerHTML = `<button type="button" class="tw-x" data-insp-close aria-label="${esc(G.close)}">×</button>${html}`;
  box.dataset.product = keep.product ?? '';
  box.dataset.ticket = keep.ticket ?? '';
  box.dataset.task = keep.task ?? '';
}
function inspClose() {
  shown++;
  const box = $('tw-inspect');
  box.hidden = true;
  box.innerHTML = '';
  box.dataset.product = box.dataset.ticket = box.dataset.task = '';
  if (S.sel.size) { S.sel.clear(); draw(); }
}
function inspect() {
  shown++; // a synchronous pick also outdates any product, shipment or ticket still loading
  const sel = S.items.filter((it) => S.sel.has(it.key));
  if (!sel.length) { if (T.view === 'gulv') inspClose(); return; }
  if (sel.length === 1 && sel[0].kind === 'product') { product(sel[0].ref.id); return; }
  if (sel.length === 1 && sel[0].kind === 'ship') { shipFloor(sel[0].ref.id); return; }
  inspOpen(multiView(sel));
  combined();
}
// A pallet on the dock: the same shipment panel as on the map, without leaving the floor
async function shipFloor(id) {
  const my = ++shown;
  inspOpen(`<p class="tw-muted">${esc(L.loading ?? '…')}</p>`);
  try {
    const r = await fetch(`/api/tarn/sending/${id}`);
    if (!r.ok) throw new Error(r.status === 403 ? L.noaccess : L.failed);
    const d = await r.json();
    if (my !== shown) return;
    inspOpen(`<p class="tw-kicker">${esc(G.ship)}</p>${shipView(d)}<p><button type="button" class="tw-btn" data-ship="${+id}">${esc(G.onMap)}</button></p>`);
  } catch (e) {
    if (my === shown) inspOpen(`<p class="tw-err">${esc(e.message)}</p>`);
  }
}

function multiView(sel) {
  const pr = sel.filter((it) => it.kind === 'product').map((it) => it.ref);
  const by = Object.entries(pr.reduce((a, p) => ((a[p.station] = (a[p.station] ?? 0) + 1), a), {}));
  let html = `<h2>${sel.length} ${esc(L.selected)}</h2><ul class="tw-mini">${pr.slice(0, 12).map((p) => `<li><button type="button" data-key="p:${+p.id}">${thumb(p.img)}<span>${esc(p.name)}</span></button></li>`).join('')}</ul>`;
  if (pr.length) {
    html += `<h3>${esc(L.station)}</h3><table class="tw-t">${by.map(([k, n]) => `<tr><th>${esc(L.stations[k])}</th><td class="n">${n}</td></tr>`).join('')}</table>
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

// ---------- Improvement tasks: one place, explicit states ----------
// The next steps a task can take from each state. Finished tasks can be archived or opened again.
const NEXT = {
  apen: ['under_behandling', 'fullfort', 'avvist'],
  under_behandling: ['klar_for_kontroll', 'fullfort', 'avvist'],
  klar_for_kontroll: ['fullfort', 'under_behandling', 'avvist'],
  fullfort: ['arkivert', 'apen'], avvist: ['arkivert', 'apen'], arkivert: [],
};
const ACTIVE = ['apen', 'under_behandling', 'klar_for_kontroll'];
function taskCard(t) {
  const first = NEXT[t.status]?.[0];
  return `<li class="tw-task-card${$('tw-inspect').dataset.task === String(t.id) ? ' on' : ''}">
    <button type="button" class="tw-task-open" data-task="${+t.id}">${thumb(t.img)}
      <span class="tw-task-body"><b>${esc(t.title)}</b><span class="tw-muted">${t.product ? esc(t.product) : esc(G.noProduct)}</span>
      <span class="tw-task-meta">${chip(t.status)}<span>${esc(G.sources[t.source] ?? t.source)}</span><time>${esc(when(t.at))}</time></span></span></button>
    ${canEdit && first ? `<button type="button" class="tw-btn tw-task-go" data-move="${+t.id}" data-to="${first}">${esc(G.move[first])}</button>` : ''}</li>`;
}
function taskView(t) {
  const moves = canEdit ? (NEXT[t.status] ?? []).map((st, i) => `<button type="button" class="tw-btn${i === 0 ? ' go' : ''}" data-move="${+t.id}" data-to="${st}">${esc(G.move[st])}</button>`).join('') : '';
  return `<p class="tw-kicker">${esc(G.task)} #${+t.id}</p>
    <div class="tw-phead">${thumb(t.img, 'tw-pimg-s')}<div><h2>${esc(t.title)}</h2><p>${chip(t.status)} <span class="tw-muted">${esc(G.sources[t.source] ?? t.source)}</span></p></div></div>
    <dl class="tw-dl"><dt>${esc(G.created)}</dt><dd>${esc(when(t.at))}${t.who ? `, ${esc(t.who)}` : ''}</dd>
    ${t.product_id ? `<dt>${esc(G.product)}</dt><dd><button type="button" class="tw-link" data-product="${+t.product_id}">${esc(t.product)}</button> <span class="tw-muted">#${+t.product_id}</span></dd>` : ''}
    ${t.spec_label || t.spec_key ? `<dt>${esc(G.taskKey)}</dt><dd>${esc(t.spec_label ?? t.spec_key)}</dd>` : ''}
    ${t.origin ? `<dt>${esc(G.origin)}</dt><dd>${esc(t.origin)}</dd>` : ''}</dl>
    ${t.note ? `<blockquote>${esc(t.note)}</blockquote>` : ''}
    ${t.outcome ? `<p class="tw-outcome"><b>${esc(G.outcome)}:</b> ${esc(t.outcome)}</p>` : `<p class="tw-muted">${esc(G.hint[t.status] ?? '')}</p>`}
    ${moves ? `<div class="tw-act">${moves}</div>` : ''}<p class="tw-note" aria-live="polite"></p>`;
}
function openTask(id) {
  const t = S.tasks.find((x) => x.id === id);
  if (!t) return;
  shown++;
  inspOpen(taskView(t), { task: String(id) });
  ops();
}
async function moveTask(btn) {
  btn.disabled = true;
  const id = +btn.dataset.move;
  try {
    const r = await post(`/api/tarn/oppgave/${id}/status`, { status: btn.dataset.to });
    if (!r.ok) throw new Error((await r.json().catch(() => ({}))).error || L.failed);
  } catch (e) {
    btn.disabled = false;
    btn.insertAdjacentHTML('afterend', `<span class="tw-err"> ${esc(e.message)}</span>`);
    return;
  }
  const box = $('tw-inspect'), pid = box.dataset.product, tid = box.dataset.task;
  await load();
  if (tid) openTask(+tid);
  else if (pid) product(+pid, true);
}

// ---------- Side panel: Varemottak and Forbedringsoppgaver, always beside the floor ----------
function ops() {
  const box = $('tw-ops');
  if (!box) return;
  const inc = S.incoming, ships = S.ships;
  const act = S.tasks.filter((t) => ACTIVE.includes(t.status)), done = S.tasks.filter((t) => !ACTIVE.includes(t.status));
  const list = S.taskView === 'aktive' ? act : done;
  const counts = ACTIVE.map((st) => `<span>${chip(st)} ${act.filter((t) => t.status === st).length}</span>`).join('');
  box.querySelector('[data-part="inn"]').innerHTML = `<summary><h2>${esc(G.incoming)}</h2><b class="tw-n">${inc.length}</b></summary>
    ${ships.length ? `<ul class="tw-ships">${ships.map((sh) => `<li><button type="button" data-key="s:${+sh.id}">${L.icons.mottak}<span><b>${esc(sh.ref)}</b> ${esc(sh.from)} ${L.arrow} ${esc(sh.to)}
      <span class="tw-muted">${esc(G.eta)} ${esc(when(sh.eta))}</span></span>${sh.demo ? `<span class="tw-demo">${esc(L.demo)}</span>` : ''}</button></li>`).join('')}</ul>` : ''}
    ${inc.length ? `<ul class="tw-inc">${inc.map((p) => `<li><button type="button" data-key="p:${+p.id}" data-product="${+p.id}">${thumb(p.img)}
      <span><b>${esc(p.name)}</b><span class="tw-muted">#${+p.id} · ${esc(G.src[p.source])} ${esc(p.ref)}${p.qty ? ` · ${+p.qty} ${esc(L.tr.qty)}` : ''}</span>
      <span class="tw-task-meta"><span class="tw-chip s-${p.source === 'bidrag' ? 'klar_for_kontroll' : 'apen'}">${esc(p.source === 'bidrag' ? G.awaiting : (L.tr.status[p.status] ?? p.status))}</span><time>${esc(when(p.at))}</time>${p.demo ? `<span class="tw-demo">${esc(L.demo)}</span>` : ''}</span></span></button></li>`).join('')}</ul>`
      : `<p class="tw-muted tw-pad">${esc(G.noIncoming)}</p>`}
    ${S.received.length ? `<h3 class="tw-sub">${esc(G.arrived)}</h3><ul class="tw-recv">${S.received.map((sh) => `<li><p><b>${esc(sh.ref)}</b> ${esc(sh.vehicle ?? '')} ${esc(G.fromCity)} ${esc(sh.from)}
      <span class="tw-muted">${esc(G.arrivedAt)} ${esc(when(sh.arrived))}</span>${sh.demo ? `<span class="tw-demo">${esc(L.demo)}</span>` : ''}</p>
      <ul class="tw-picks">${sh.items.map((i) => `<li><span>${thumb(i.img)}<span><b>${esc(i.name)}</b><small>${+i.qty} ${esc(L.tr.sim.units)}</small></span></span></li>`).join('')}</ul>
      <button type="button" class="tw-btn tw-go" data-checkin="${+sh.id}">${esc(G.checkIn)}</button></li>`).join('')}</ul>` : ''}`;
  // The order list: lines marked in Innsikt, waiting for a vehicle in Transport, on the way, or received
  const picks = S.picks ?? [], waiting = picks.filter((p) => p.status === 'valgt').length;
  box.querySelector('[data-part="best"]').innerHTML = `<summary><h2>${esc(G.orderList)}</h2><b class="tw-n" title="${esc(G.pickStatus.valgt)}">${picks.length}</b></summary>
    ${picks.length ? `<ul class="tw-picks tw-pad">${picks.map((p) => `<li><span>${thumb(p.img)}<span><b>${esc(p.name)}</b>
      <small><span class="tw-chip s-${p.status === 'valgt' ? 'apen' : p.status === 'bestilt' ? 'under_behandling' : 'mottatt'}">${esc(G.pickStatus[p.status])}</span>
      ${+p.qty} ${esc(L.tr.sim.units)}${p.ref ? ` · ${esc(p.ref)}` : ''}${p.from ? ` · ${esc(G.fromCity)} ${esc(p.from)}` : ''}</small></span>
      ${p.status === 'valgt' ? `<button type="button" class="tw-btn tw-unpick" data-unpick="${+p.id}" aria-label="${esc(G.unpick)}: ${esc(p.name)}">${esc(G.unpick)}</button>` : ''}</span></li>`).join('')}</ul>
      ${waiting ? `<button type="button" class="tw-btn tw-go tw-pad-btn" data-to-transport>${esc(G.toTransport)}</button>` : ''}`
      : `<p class="tw-muted tw-pad">${esc(G.noPicks)}</p>`}`;
  box.querySelector('[data-part="opp"]').innerHTML = `<summary><h2>${esc(G.tasks)}</h2><b class="tw-n">${act.length}</b></summary>
    <div class="tw-task-tabs" role="group"><button type="button" class="tw-btn" data-tasks="aktive" aria-pressed="${S.taskView === 'aktive'}">${esc(G.active)} ${act.length}</button>
      <button type="button" class="tw-btn" data-tasks="ferdige" aria-pressed="${S.taskView !== 'aktive'}">${esc(G.done)} ${done.length}</button></div>
    ${S.taskView === 'aktive' && act.length ? `<p class="tw-task-sum">${counts}</p>` : ''}
    ${list.length ? `<ul class="tw-tasks">${list.map(taskCard).join('')}</ul>` : `<p class="tw-muted tw-pad">${esc(S.taskView === 'aktive' ? G.none : G.noneDone)}</p>`}`;
}
$('tw-ops')?.addEventListener('click', async (e) => {
  const b = e.target.closest('button');
  if (!b) return;
  if (b.dataset.tasks) { S.taskView = b.dataset.tasks; ops(); }
  else if (b.dataset.checkin || b.dataset.unpick) {
    b.disabled = true;
    const r = await post(b.dataset.checkin ? `/api/tarn/sending/${+b.dataset.checkin}/mottatt` : `/api/tarn/bestilling/${+b.dataset.unpick}/fjern`).catch(() => null);
    if (!r?.ok) { b.disabled = false; b.textContent = L.failed; return; }
    await load();
  } else if ('toTransport' in b.dataset) setView('transport');
  else if (b.dataset.move) moveTask(b);
  else if (b.dataset.task) openTask(+b.dataset.task);
  else if (b.dataset.key && S.items.some((it) => it.key === b.dataset.key)) select(b.dataset.key);
  else if (b.dataset.product) { S.sel.clear(); draw(); product(+b.dataset.product); }
});

// ---------- Product inspector ----------
const sourceOf = { kvalitet: 'datakvalitet', kobling: 'kompatibilitet' };
async function product(id, quiet = false) {
  const my = ++shown;
  let p;
  try {
    const r = await fetch(`/api/tarn/produkt/${id}`, { headers: { Accept: 'application/json' } });
    if (!r.ok) throw new Error(r.status === 403 ? L.noaccess : String(r.status));
    p = await r.json();
    if (my !== shown) return;
  } catch (e) {
    if (my === shown && !quiet) inspOpen(`<p class="tw-err">${esc(e.message)}</p>`);
    return;
  }
  const keepNote = quiet ? $('tw-inspect').querySelector('.tw-result')?.outerHTML ?? '' : '';
  const onFloor = S.products.find((x) => x.id === id);
  const label = Object.fromEntries([...p.specs, ...p.missing].map((s) => [s.key, s.label]));
  const current = Object.fromEntries(p.specs.map((s) => [s.key, s]));
  const specs = p.specs.filter((s) => s.value !== null).map((s) => `<tr><th>${esc(s.label)}</th><td>${esc(s.value + (s.unit ? ' ' + s.unit : ''))}${s.source ? `<span class="tw-src ${esc(s.source)}">${esc(L.sources[s.source] ?? s.source)}</span>` : ''}
    ${s.version ? `<span class="tw-muted"> ${esc(L.versions[s.version] ?? s.version)}</span>` : ''}</td></tr>`).join('');
  const revs = p.revisions.map((r) => `<div class="tw-item" data-rev="${+r.id}">${r.diff.map((d) => `<p class="tw-diff"><span>${esc(label[d.key] ?? d.key)}</span>
      <s class="tw-muted">${esc(d.from ?? G.empty)}</s><b>${esc(d.to)}</b></p>`).join('')}
    <p class="tw-muted">${esc(r.author ?? '')} ${esc(when(r.at))}</p>${r.comment ? `<blockquote>${esc(r.comment)}</blockquote>` : ''}
    ${canEdit ? `<div class="tw-act"><button type="button" class="tw-btn go" data-ask="flett" data-rev="${+r.id}">${esc(L.merge)}</button>
    <button type="button" class="tw-btn" data-ask="avvis" data-rev="${+r.id}">${esc(L.reject)}</button></div><div class="tw-confirm" hidden></div>` : ''}</div>`).join('');
  const reps = p.reports.map((r) => `<div class="tw-item"><p><b>${esc(r.label)}</b> <span class="tw-muted">${esc(fillIn(G.reportsN, { n: +r.n }))}</span></p>
    <p class="tw-muted">${esc(G.current)}: ${esc(current[r.key]?.value ?? G.empty)}</p><blockquote>${esc(r.reasons)}</blockquote>
    ${canEdit ? `<div class="tw-act"><button type="button" class="tw-btn go" data-post="/ansatt/rapport/${+r.id}" data-action="fjern">${esc(L.remove)}</button>
    <button type="button" class="tw-btn" data-post="/ansatt/rapport/${+r.id}" data-action="lukk">${esc(L.correct)}</button>
    <button type="button" class="tw-btn" data-assess="${+r.id}">${esc(L.assess)}</button></div><p class="tw-note" aria-live="polite"></p>` : ''}</div>`).join('');
  const rel = (p.relations ?? []).map((r) => `<li class="${r.kind === 'passer_ikke' ? 'bad' : ''}"><button type="button" class="tw-link" data-product="${+r.id}">${thumb(r.img)}<span><b>${esc(r.name)}</b>
    <span class="tw-muted">${esc(r.kind === 'passer_ikke' ? G.nofit : G.fits)} · ${esc(L.ins?.levels?.[r.level] ?? r.level)}</span><span class="tw-muted">${esc(r.reason)}</span></span></button></li>`).join('');
  const inb = p.inbound.map((s) => `<tr><th><button type="button" data-ship="${+s.id}">${esc(s.ref)}</button></th>
    <td>${esc(s.origin_city)} ${L.arrow} ${esc(s.dest_city)}, ${+s.qty}${+s.is_demo ? `<span class="tw-demo">${esc(L.demo)}</span>` : ''}</td></tr>`).join('');
  const stock = p.stock.map((w) => `<tr><th>${esc(w.name)}</th><td class="n">${w.in_store} / ${w.online}</td></tr>`).join('');
  const tasks = S.tasks.filter((t) => t.product_id === id);
  const keys = [...p.missing.map((m) => [m.key, m.label]), ...p.specs.map((s) => [s.key, s.label])];
  const src = sourceOf[onFloor?.station] ?? 'produkt';
  const form = canEdit ? `<details class="tw-new"${tasks.length ? '' : ' open'}><summary>${esc(G.newTask)}</summary><form class="tw-task" data-product="${id}" data-source="${src}">
    <label for="tw-nt-t">${esc(G.taskTitle)}</label><input id="tw-nt-t" name="title" required maxlength="200" value="${esc(p.reports[0] ? fillIn(G.fixDefault, { key: p.reports[0].label }) : p.missing[0] ? fillIn(G.fillDefault, { key: p.missing[0].label }) : '')}">
    <label for="tw-nt-k">${esc(G.taskKey)}</label><select id="tw-nt-k" name="key"><option value="">${esc(G.wholeProduct)}</option>${keys.map(([k, l]) => `<option value="${esc(k)}"${(p.reports[0]?.key ?? p.missing[0]?.key) === k ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select>
    <label for="tw-nt-n">${esc(G.taskNote)}</label><textarea id="tw-nt-n" name="note" maxlength="500" rows="2"></textarea>
    <button class="tw-btn go">${esc(G.taskMake)}</button><p class="tw-note" aria-live="polite"></p></form></details>` : '';
  inspOpen(`${keepNote}<div class="tw-phead">${p.img ? `<img class="tw-pimg" src="/${esc(p.img)}" alt="${esc(p.name)}">` : thumb(null, 'tw-pimg')}</div>
    <h2>${esc(p.name)}</h2>
    <p>${onFloor ? `<span class="tw-chip st-${esc(onFloor.station)}">${esc(L.stations[onFloor.station])}</span> <span class="tw-muted">${esc(G.why[onFloor.station])}</span>` : ''}</p>
    <dl class="tw-dl"><dt>${esc(L.id)}</dt><dd>${p.id}</dd><dt>${esc(L.price)}</dt><dd>${esc(kr(p.price_ore))}</dd>
      <dt>${esc(G.verif)}</dt><dd>${esc(fillIn(G.verifN, { v: p.specs.filter((s) => s.version === 'verifisert').length, n: p.specs.filter((s) => s.value !== null).length, m: p.missing.length }))}</dd></dl>
    <p class="tw-links"><a href="/produkt/${encodeURIComponent(p.slug)}?versjon=apen" target="_blank" rel="noopener">${esc(L.open)}</a></p>
    ${p.revisions.length ? `<h3>${esc(L.revisions)} ${p.revisions.length}</h3>${revs}` : ''}
    ${p.reports.length ? `<h3>${esc(L.reports)} ${p.reports.length}</h3>${reps}` : ''}
    <h3>${esc(G.tasks)} ${tasks.length}</h3>${tasks.length ? `<ul class="tw-tasks">${tasks.map(taskCard).join('')}</ul>` : ''}${form}
    ${rel ? `<h3>${esc(G.relations)}</h3><ul class="tw-rel">${rel}</ul>` : ''}
    ${inb ? `<h3>${esc(L.tr.inbound)}</h3><table class="tw-t">${inb}</table>` : ''}
    ${p.missing.length ? `<h3>${esc(L.missing)} ${p.missing.length}</h3><ul class="tw-miss">${p.missing.map((m) => `<li>${esc(m.label)}</li>`).join('')}</ul>` : ''}
    <h3>${esc(L.specs)}</h3><table class="tw-t">${specs}</table>
    ${stock ? `<h3>${esc(L.stock)}<span class="tw-demo">${esc(L.demo)}</span></h3><table class="tw-t">${stock}</table>` : ''}`, { product: String(p.id) });
  $('tw-inspect').dataset.rev = JSON.stringify(Object.fromEntries(p.revisions.map((r) => [r.id, r.diff.map((d) => ({ ...d, label: label[d.key] ?? d.key, now: current[d.key]?.value ?? null }))])));
}

// Flett inn and Avvis ask first, with the product, the value now and the proposed value, and what will happen
function askRevision(btn) {
  const item = btn.closest('.tw-item'), box = item.querySelector('.tw-confirm'), id = +btn.dataset.rev, merge = btn.dataset.ask === 'flett';
  const diff = JSON.parse($('tw-inspect').dataset.rev || '{}')[id] ?? [];
  const settles = S.tasks.filter((t) => ACTIVE.includes(t.status) && (t.revision_id === id || diff.some((d) => d.key === t.spec_key)) && t.product_id === +$('tw-inspect').dataset.product);
  box.hidden = false;
  box.innerHTML = `<p><b>${esc(merge ? G.mergeAsk : G.rejectAsk)}</b></p>
    <table class="tw-t"><tr><th></th><th>${esc(G.current)}</th><th>${esc(G.proposed)}</th></tr>${diff.map((d) => `<tr><th>${esc(d.label)}</th><td>${esc(d.now ?? G.empty)}</td><td><b>${esc(d.to)}</b></td></tr>`).join('')}</table>
    <p class="tw-muted">${esc(merge ? G.mergeWhat : G.rejectWhat)} ${esc(settles.length ? fillIn(merge ? G.willComplete : G.willReject, { n: settles.length }) : G.noLinked)}</p>
    <div class="tw-act"><button type="button" class="tw-btn go" data-post="/ansatt/revisjon/${id}" data-action="${merge ? 'flett' : 'avvis'}" data-json="1">${esc(G.confirm)}</button>
    <button type="button" class="tw-btn" data-cancel>${esc(G.cancel)}</button></div>`;
  box.querySelector('[data-post]').focus();
}

async function act(btn) {
  btn.disabled = true;
  const body = new FormData();
  body.append('csrf', cfg.csrf);
  body.append('action', btn.dataset.action);
  let out = null;
  try {
    const r = await fetch(btn.dataset.post, { method: 'POST', body, redirect: 'manual', headers: btn.dataset.json ? { Accept: 'application/json' } : {} });
    if (btn.dataset.json) {
      out = await r.json().catch(() => null);
      if (!r.ok || !out?.ok) throw new Error(out?.error || (r.status === 403 ? L.noaccess : L.failed));
    } else if (!(r.ok || r.type === 'opaqueredirect')) throw new Error(r.status === 403 ? L.noaccess : L.failed);
  } catch (e) {
    btn.disabled = false;
    btn.insertAdjacentHTML('afterend', `<span class="tw-err"> ${esc(e.message)}</span>`);
    return;
  }
  const box = $('tw-inspect'), pid = box.dataset.product, tid = box.dataset.ticket;
  const names = Object.fromEntries(Object.values(JSON.parse(box.dataset.rev || '{}')).flat().map((d) => [d.key, d.label]));
  await load();
  if (tid) { box.dataset.ticket = ''; inspect(); } // a closed ticket leaves the list
  else if (pid) {
    await product(+pid);
    if (out) result(out, names);
  }
}
// What a merge or a rejection did, shown at the top of the product until the next action
function result(out, names = {}) {
  const merged = out.status === 'flettet';
  const t = out.tasks;
  const html = `<div class="tw-result ${merged ? 'ok' : ''}" role="status"><p><b>${esc(merged ? G.mergedOk : G.rejectedOk)}</b>: ${out.diff.map((d) => `${esc(names[d.key] ?? d.key)} ${esc(d.to)}`).join(', ')}</p>
    ${t.settled.length ? `<p>${esc(G.settled)}: ${t.settled.map((x) => `#${+x.id} ${esc(x.title)} (${esc(G.status[x.status])})`).join(', ')}</p>` : `<p class="tw-muted">${esc(G.noTasks)}</p>`}
    ${t.open.length ? `<p>${esc(G.stillOpen)}: ${t.open.map((x) => `#${+x.id} ${esc(x.title)} (${esc(G.status[x.status])})`).join(', ')}</p>` : ''}</div>`;
  $('tw-inspect').querySelector('.tw-x').insertAdjacentHTML('afterend', html);
}

$('tw-inspect').addEventListener('submit', async (e) => {
  const f = e.target.closest('form.tw-task');
  if (!f) return;
  e.preventDefault();
  const b = f.querySelector('button'), note = f.querySelector('.tw-note');
  b.disabled = true;
  const pid = +f.dataset.product, key = f.key.value;
  try {
    const r = await post('/api/tarn/oppgave', { title: f.title.value, product: pid, key, note: f.note.value, source: f.dataset.source, origin: `produkt:${pid}:${key || 'alt'}` });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error || L.failed);
    await load();
    await product(pid);
    S.taskView = 'aktive';
    ops();
    $('tw-inspect').querySelector('.tw-x').insertAdjacentHTML('afterend', `<div class="tw-result ok" role="status"><p>${esc(fillIn(d.dup ? G.dup : G.made, { id: d.id }))}</p></div>`);
    document.querySelector(`#tw-ops [data-task="${+d.id}"]`)?.closest('li')?.classList.add('flash');
  } catch (err) {
    b.disabled = false;
    note.textContent = err.message;
  }
});

$('tw-inspect').addEventListener('click', (e) => {
  const b = e.target.closest('button');
  if (!b) return;
  if (b.dataset.inspClose !== undefined) inspClose();
  else if (b.dataset.ask) askRevision(b);
  else if (b.dataset.cancel !== undefined) { const c = b.closest('.tw-confirm'); c.hidden = true; c.innerHTML = ''; }
  else if (b.dataset.post) act(b);
  else if (b.dataset.move) moveTask(b);
  else if (b.dataset.task) openTask(+b.dataset.task);
  else if (b.dataset.product) { const k = 'p:' + b.dataset.product; if (T.view === 'gulv' && S.items.some((it) => it.key === k)) select(k); else { S.sel.clear(); draw(); product(+b.dataset.product); } }
  else if (b.dataset.key) select(b.dataset.key);
  else if (b.dataset.ship) { if (T.view !== 'transport') setView('transport'); shipSelect(+b.dataset.ship); }
  else if (b.dataset.reply) reply(b);
  else if (b.dataset.draft) draft(b);
  else if (b.dataset.ticket) ticket(+b.dataset.ticket);
  else if (b.dataset.assess) assess(b);
  else if (b.dataset.back !== undefined) { $('tw-inspect').dataset.ticket = ''; inspect(); }
});
// A linked task opened from Innsikt: tasks live in Gulv, Forbedringsoppgaver (CONTROLTOWER-TRIAGE.md 2)
document.addEventListener('tw:task', (e) => { setView('gulv'); openTask(e.detail); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && T.view === 'gulv' && !$('tw-inspect').hidden && $('tw-inspect').contains(document.activeElement)) inspClose(); });

// ---------- Log, KPI, throughput, fallback table ----------
// Station counts as text: the visible floor without WebGL, and a screen reader list beside the canvas with it
function fallbackTable() {
  const rows = ['inntak', ...cfg.stations].map((k) => `<tr><th>${esc(L.stations[k])}</th><td class="n">${S.counts?.[k] ?? 0}</td></tr>`).join('');
  const table = `<table class="tw-t"><caption>${esc(L.g.floor)}</caption>${rows}</table>`;
  if (gl) { $('tw-counts').innerHTML = table; return; }
  $('tw-table').innerHTML = `<p>${esc(L.nowebgl)}</p>${table}`;
}

// Stock flow: units in from arrived shipments above the line, units sold below, net change as a line, and the units
// still on the way as a dashed column after today. Counted on the server from stored rows (Insight::stockFlow).
// Drawn at the measured width; a chart fetched while its view is hidden waits for the observer to see a real size.
let flowData = null, flowObs = null;
async function throughput() {
  try { flowData = await (await fetch('/api/tarn/lagerflyt')).json(); } catch { return; }
  const box = $('tw-chart');
  if (!flowObs) {
    flowObs = new ResizeObserver(() => drawFlow());
    flowObs.observe(box);
  }
  drawFlow();
}
function drawFlow() {
  const t = flowData, box = $('tw-chart'), F = L.flow;
  if (!t || !box.clientWidth) return;
  const W = box.clientWidth, H = box.clientHeight || 140;
  const n = t.days.length + 1, l = 34, r = 8, top = 8, bot = 18;
  const lo = Math.max(1, ...t.out), hi = Math.max(1, lo, ...t.in); // scaled to measured days; the on-the-way column is capped and labelled
  const mid = top + ((H - top - bot) * hi) / (hi + lo), per = (H - top - bot) / (hi + lo);
  const x = (i) => l + ((i + 0.5) / n) * (W - l - r), bw = Math.max(3, ((W - l - r) / n) * 0.55);
  const df = new Intl.DateTimeFormat(locale, { timeZone: 'Europe/Oslo', day: '2-digit', month: '2-digit' });
  const day = (iso) => df.format(new Date(`${iso}T12:00:00Z`));
  let g = `<line class="grid" x1="${l}" x2="${W - r}" y1="${mid}" y2="${mid}"/>
    <text x="${l - 6}" y="${top + 8}" text-anchor="end">+${hi}</text><text x="${l - 6}" y="${mid + 4}" text-anchor="end">0</text><text x="${l - 6}" y="${H - bot}" text-anchor="end">−${lo}</text>`;
  t.days.forEach((d, i) => {
    const tip = `${day(d)}: ${F.in} ${t.in[i]}, ${F.out} ${t.out[i]}, ${F.net} ${t.net[i] > 0 ? '+' : ''}${t.net[i]}`;
    if (t.in[i]) g += `<rect class="in" x="${x(i) - bw / 2}" y="${mid - t.in[i] * per}" width="${bw}" height="${t.in[i] * per}"/>`;
    if (t.out[i]) g += `<rect class="out" x="${x(i) - bw / 2}" y="${mid}" width="${bw}" height="${t.out[i] * per}"/>`;
    g += `<rect class="hit" x="${x(i) - (W - l - r) / n / 2}" y="${top}" width="${(W - l - r) / n}" height="${H - top - bot}"><title>${esc(tip)}</title></rect>`;
  });
  const k = t.days.length;
  if (t.coming) {
    const h = Math.min(t.coming, hi) * per;
    g += `<rect class="coming${t.coming > hi ? ' capped' : ''}" x="${x(k) - bw / 2}" y="${mid - h}" width="${bw}" height="${h}"><title>${esc(`${F.coming}: ${t.coming}`)}</title></rect>
      <text class="v" x="${x(k)}" y="${mid - h - 4}" text-anchor="middle">${t.coming}</text>`;
  }
  g += `<polyline class="net" points="${t.net.map((v, i) => `${x(i)},${mid - v * per}`).join(' ')}"/>`;
  g += `<text x="${x(0)}" y="${H - 2}" text-anchor="middle">${esc(day(t.days[0]))}</text><text x="${x(k - 1)}" y="${H - 2}" text-anchor="middle">${esc(F.today)}</text>`;
  if (t.coming) g += `<text x="${x(k)}" y="${H - 2}" text-anchor="middle">${esc(F.next)}</text>`;
  const sum = (a) => a.reduce((s, v) => s + v, 0);
  $('tw-flow-leg').innerHTML = `<span><i class="in"></i>${esc(F.in)} ${sum(t.in)}</span><span><i class="out"></i>${esc(F.out)} ${sum(t.out)}</span>
    <span><i class="net"></i>${esc(F.net)}</span><span><i class="coming"></i>${esc(F.coming)} ${t.coming}</span>${t.demo ? `<span class="tw-demo">${esc(L.demo)}</span>` : ''}`;
  box.innerHTML = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(`${L.throughput}: ${F.in} ${sum(t.in)}, ${F.out} ${sum(t.out)}, ${F.coming} ${t.coming}`)}">${g}</svg>`;
}

// ---------- Data ----------
function status(ok) {
  const el = $('tw-live');
  el.className = 'tw-live ' + (ok ? 'on' : 'err');
  el.querySelector('span').textContent = ok ? `${L.updated} ${tFmt.format(new Date())}` : L.failed;
}
function header() {
  $('tw-count').textContent = '';
  const idle = !S.products.length && !S.ships.length;
  $('tw-idle').hidden = !idle || T.view !== 'gulv';
  $('tw-idle').textContent = L.empty;
}
function apply(d) {
  S.products = d.products;
  S.ships = d.shipments;
  S.tasks = d.tasks;
  S.incoming = d.incoming;
  S.received = d.received ?? [];
  S.picks = d.picks ?? [];
  S.know = d.knowledge;
  S.totals = d.totals;
  S.tickets = d.tickets ?? S.tickets;
  // Open cases on the Kundeservice tab
  const open = S.tickets.filter((t) => t.status !== 'lukket').length, b = document.querySelector('[data-badge="kundeservice"]');
  b.hidden = !open;
  b.textContent = open;
}

// The whole floor comes from one read (/api/tarn/gulv). Keys that were not there on the last read travel in.
let known = null;
async function load() {
  const g = gen;
  let d;
  try {
    const r = await fetch('/api/tarn/gulv');
    if (!r.ok) throw new Error(String(r.status));
    d = await r.json();
    if (g !== gen) return;
  } catch {
    status(false);
    return;
  }
  apply(d);
  status(true);
  const keys = [...S.ships.map((x) => 's:' + x.id), ...S.products.map((p) => 'p:' + p.id + ':' + p.station)];
  const fresh = known ? new Set(S.products.filter((p) => !known.has('p:' + p.id + ':' + p.station)).map((p) => 'p:' + p.id)) : new Set();
  known = new Set(keys);
  rebuild(fresh);
  header();
  fallbackTable();
  ops(); // the inspector is only redrawn by the staff member's own actions, so a poll never wipes a form or a confirm step
}
async function poll() {
  if (document.hidden || T.view !== 'gulv') return;
  await load();
}

// ---------- Transport (DELIVERY.md 5.9). Markers sit at the latest stored position. Nothing is interpolated or animated. ----------
const TR = L.tr;
const T = { view: 'gulv', rows: [], land: null, path: '', sel: null, detail: null, vb: null };
const svg = $('tw-svg');
const KM = 60; // svg units per degree of latitude
const cosL = Math.cos((63 * Math.PI) / 180);
const prj = (lat, lng) => [(lng - 4) * cosL * KM, (72 - lat) * KM];

const kmBetween = (a, b, c, e) => { const R = 6371, r = Math.PI / 180, x = (e - b) * r * Math.cos(((a + c) / 2) * r), y = (c - a) * r; return Math.hypot(x, y) * R; };
const compass = (deg) => TR.dirs[Math.round(deg / 45) % 8];
const icon = { locate: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>' };

// ---------- My position: the browser's location, shown on the map only; it is never sent to the server ----------
const ME = { watch: null, at: null };
function meLayer(s) {
  if (!ME.at) return '';
  const [x, y] = prj(ME.at.lat, ME.at.lng), acc = Math.min(40, Math.max(6, (ME.at.acc / 1000) * KM / s));
  return `<g class="tw-me" transform="translate(${x},${y})"><circle class="acc" r="${acc * s}"/><circle r="${6 * s}"/>
    <text x="${10 * s}" y="${4 * s}" style="font-size:${11 * s}px;stroke-width:${3 * s}px">${esc(TR.me)}</text></g>`;
}
function meToggle(b) {
  if (ME.watch !== null) {
    navigator.geolocation.clearWatch(ME.watch);
    Object.assign(ME, { watch: null, at: null });
    b.setAttribute('aria-pressed', 'false');
    mapDraw();
    return;
  }
  if (!navigator.geolocation) { demoSay(TR.meNone, true); return; }
  b.setAttribute('aria-pressed', 'true');
  ME.watch = navigator.geolocation.watchPosition((p) => {
    const first = !ME.at;
    ME.at = { lat: p.coords.latitude, lng: p.coords.longitude, acc: p.coords.accuracy };
    if (first) { const [x, y] = prj(ME.at.lat, ME.at.lng); fit([[x - 60, y - 60], [x + 60, y + 60]], 0.2); }
    mapDraw();
  }, () => { demoSay(TR.meDenied, true); b.setAttribute('aria-pressed', 'false'); ME.watch = null; }, { enableHighAccuracy: true, maximumAge: 10000 });
}
$('tw-me')?.addEventListener('click', (e) => meToggle(e.currentTarget));


async function landLoad() {
  if (T.land) return;
  T.land = await (await fetch('/assets/tower/norway.json')).json();
  // Natural Earth 1:10m cut to the region (norway.json): land, neighbours, county borders, lakes, main roads, towns
  const ring = (r, close = true) => 'M' + r.map(([lng, lat]) => prj(lat, lng).map((n) => n.toFixed(1)).join(',')).join('L') + (close ? 'Z' : '');
  const m = T.land;
  T.path = `<path class="tw-sea-land nb" d="${m.neighbours.map((r) => ring(r)).join('')}"/>
    <path class="tw-land" d="${m.land.map((r) => ring(r)).join('')}"/>
    <path class="tw-lake" d="${m.lakes.map((r) => ring(r)).join('')}"/>
    <path class="tw-county" d="${m.counties.flatMap((c) => c.rings.map((r) => ring(r))).join('')}"/>
    <path class="tw-road r2" d="${m.roads.filter((r) => r.t === 2).map((r) => ring(r.p, false)).join('')}"/>
    <path class="tw-road r1" d="${m.roads.filter((r) => r.t === 1).map((r) => ring(r.p, false)).join('')}"/>`;
}
function placeLayer(s) {
  return T.land.places.map((p) => {
    const [x, y] = prj(p.lat, p.lng);
    const fs = (p.r === 1 ? 12 : 10.5) * s, ok = labelAt(x + 4 * s, y - fs * 0.6, textW(p.n, fs), fs * 1.3);
    return `<g class="tw-place r${p.r}" transform="translate(${x},${y})"><circle r="${2.2 * s}"/>${ok ? `<text x="${5 * s}" y="${4 * s}" style="font-size:${fs}px;stroke-width:${3 * s}px">${esc(p.n)}</text>` : ''}</g>`;
  }).join('');
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
  no: () => fit(T.land.land.flat().filter(([lng, lat]) => lat > 57.8 && lng < 31.5 && lat < 71.3).map(([lng, lat]) => prj(lat, lng)), 0.02),
  all: () => (T.rows.length ? fit(T.rows.flatMap(rowPts)) : FIT.no()),
  route: () => { const r = T.rows.find((x) => x.id === T.sel); if (D.s && (!r || r.id === D.id)) fit(D.s.stops.map((p) => prj(p.lat, p.lng)), 0.12); else if (r) fit(rowPts(r)); },
};

// ---------- Weather and route conditions (Conditions.php, filled by scripts/conditions.php) ----------
const CD = () => TR.cond;
const skyOf = (c) => { const s = String(c.symbol ?? ''); if (s.includes('thunder') || +c.wind_ms >= 17) return 'storm'; if (s.includes('snow')) return 'sno';
  if (s.includes('rain') || s.includes('sleet')) return 'regn'; if (s.startsWith('clearsky') || s.startsWith('fair')) return s.endsWith('_night') ? 'natt' : 'sol'; return 'sky'; };
const iconAt = (svgText, x, y, size) => svgText.replace('<svg ', `<svg x="${x - size / 2}" y="${y - size / 2}" width="${size}" height="${size}" `);
// Obstacles closer than 26 screen pixels share one marker: the most serious one is drawn, with a count.
// Grouping runs on every draw, so groups split apart when the map is zoomed in.
const COND_RANK = { closure: 0, ferry: 1, warning: 2, traffic: 3, rail: 4, roadwork: 5 };
function condLayer(s) {
  if (!T.cond) return '';
  let h = '';
  const groups = [];
  for (const c of T.cond.items) {
    const [x, y] = prj(+c.lat, +c.lng);
    if (c.kind === 'weather') {
      const k = skyOf(c);
      LBL.push([x - 13 * s, y - 13 * s, 26 * s, 26 * s]);
      const temp = c.temp_c === null ? '' : `${Math.round(+c.temp_c)}°`;
      const fits = temp && labelAt(x + 16 * s, y - 6 * s, textW(temp, 11 * s), 13 * s);
      h += `<g class="tw-wxpt ${k}"><circle cx="${x}" cy="${y}" r="${13 * s}"/>${iconAt(CD().icons[k], x, y, 16 * s)}
        ${fits ? `<text x="${x + 16 * s}" y="${y + 4 * s}" style="font-size:${11 * s}px;stroke-width:${3 * s}px">${temp}</text>` : ''}</g>`;
      continue;
    }
    const g = groups.find((o) => Math.hypot(o.x - x, o.y - y) < 26 * s);
    if (g) g.items.push(c); else groups.push({ x, y, items: [c] });
  }
  const worse = (a, b) => (a.severity === 'stop') - (b.severity === 'stop') || (COND_RANK[b.kind] ?? 9) - (COND_RANK[a.kind] ?? 9);
  for (const { x, y, items } of groups) {
    const lead = items.reduce((a, b) => (worse(b, a) > 0 ? b : a));
    const lines = items.slice(0, 8).map((c) => `${CD().kinds[c.kind]}: ${c.title}${+c.is_demo ? ` (${CD().demo})` : ''}`);
    if (items.length > 8) lines.push(`+${items.length - 8}`);
    LBL.push([x - 11 * s, y - 11 * s, 22 * s, 22 * s]);
    h += `<g class="tw-cond ${esc(lead.kind)} ${esc(lead.severity)}" data-cond="${+lead.id}"><title>${esc(lines.join('\n'))}</title>
      <rect x="${x - 11 * s}" y="${y - 11 * s}" width="${22 * s}" height="${22 * s}" rx="${3 * s}"/>${iconAt(CD().icons[lead.kind], x, y, 14 * s)}`;
    if (items.length > 1) {
      h += `<g class="n"><circle cx="${x + 11 * s}" cy="${y - 11 * s}" r="${8 * s}"/>
        <text x="${x + 11 * s}" y="${y - 11 * s}" dy=".35em" style="font-size:${10 * s}px">${items.length > 99 ? '99+' : items.length}</text></g>`;
    }
    h += '</g>';
  }
  return h;
}
// The whole map takes the mood of the worst weather on the routes: bright in sun, grey in rain, dark with flashes in a storm
function weatherMood() {
  const map = $('tw-map'), badge = $('tw-wx-badge');
  if (D.s && !D.s.done && D.s.now.sky) {
    const n = D.s.now;
    map.className = `tw-map wx-${n.sky}`;
    badge.hidden = false;
    badge.innerHTML = `${CD().icons[n.sky] ?? ''}<b>${esc(TR.sim.sky[n.sky] ?? '')}</b> ${n.wind !== null && n.wind !== undefined ? `${CD().icons.wind}${n.wind} m/s` : ''}<small>${esc(n.wxsrc ?? TR.sim.demo)}</small>`;
    return;
  }
  const mood = T.cond?.mood ?? null, k = mood === 'sol' && T.cond.night ? 'natt' : mood;
  map.className = `tw-map${k ? ` wx-${k}` : ''}`;
  if (!k) { badge.hidden = true; return; }
  const w = T.cond.items.filter((c) => c.kind === 'weather');
  const demo = w.some((c) => +c.is_demo);
  const temps = w.map((c) => +c.temp_c).filter((t) => !Number.isNaN(t));
  const wind = Math.max(...w.map((c) => +c.wind_ms || 0));
  badge.hidden = false;
  badge.innerHTML = `${CD().icons[k]}<b>${esc(CD().sky[k])}</b> ${temps.length ? esc(fillIn(CD().range, { a: Math.round(Math.min(...temps)), b: Math.round(Math.max(...temps)) })) : ''}
    ${wind ? `${CD().icons.wind}${Math.round(wind)} m/s` : ''}<small>${esc(demo ? CD().demo : CD().credit)}</small>`;
}
function condList(id) {
  const on = T.cond?.routes?.[id];
  if (!T.cond || !on) return '';
  const items = T.cond.items.filter((c) => on.ids.includes(+c.id) && c.kind !== 'weather');
  const sky = T.cond.items.filter((c) => on.ids.includes(+c.id) && c.kind === 'weather');
  const worst = sky.length ? sky.map(skyOf).sort((a, b) => ['sol', 'natt', 'sky', 'regn', 'sno', 'storm'].indexOf(b) - ['sol', 'natt', 'sky', 'regn', 'sno', 'storm'].indexOf(a))[0] : null;
  return `<h3>${esc(CD().title)}</h3>
    ${worst ? `<p class="tw-cline">${CD().icons[worst]}<span>${esc(CD().now)}: <b>${esc(CD().sky[worst])}</b>${sky.some((c) => +c.is_demo) ? ` <span class="tw-demo">${esc(L.demo)}</span>` : ''}</span></p>` : ''}
    ${items.length ? `<ul class="tw-clist">${items.map((c) => `<li class="${esc(c.severity)}">${CD().icons[c.kind]}<span><b>${esc(c.title)}</b> <span class="tw-tag ${c.severity === 'stop' ? 'avvik' : ''}">${esc(CD().sev[c.severity])}</span>
      ${c.detail ? `<br>${esc(c.detail)}` : ''}<br><small>${esc(CD().source)}: ${esc(c.source)}${+c.is_demo ? ` <span class="tw-demo">${esc(L.demo)}</span>` : ''}</small></span></li>`).join('')}</ul>`
      : `<p class="tw-muted">${esc(CD().none)}</p>`}
    ${on.delay_min ? `<p class="tw-cdelay">${esc(fillIn(CD().delay, { n: on.delay_min }))}</p>` : ''}`;
}

// Ferries (src/Ferries.php): the crossing line coloured by status, a status marker in the middle, and each vessel
// where Entur last placed it. Old positions are drawn faded. Nothing here is simulated.
const FS = () => CD().ferry;
const fStatus = (f) => fillIn(FS().status[f.status], { n: f.delay_min ?? '' });
const hhmm = (ts) => tFmt.format(new Date(ts * 1000)).slice(0, 5);
const OLD_MIN = 15; // a vessel position older than this is drawn faded and called old
const ageMin = (ts) => Math.max(0, Math.round((Date.now() / 1000 - ts) / 60));
function ferryLayer(s) {
  let h = '';
  for (const f of T.ferries ?? []) {
    const [a, b] = f.quays.map((q) => prj(q.lat, q.lng));
    const [mx, my] = [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
    LBL.push([mx - 11 * s, my - 11 * s, 22 * s, 22 * s]);
    h += `<g class="tw-ferry ${esc(f.status)}"><title>${esc(`${f.name} (${f.road}): ${fStatus(f)}`)}</title>
      <line x1="${a[0]}" y1="${a[1]}" x2="${b[0]}" y2="${b[1]}"/>${[a, b].map(([x, y]) => `<rect class="q" x="${x - 3 * s}" y="${y - 3 * s}" width="${6 * s}" height="${6 * s}"/>`).join('')}
      <circle cx="${mx}" cy="${my}" r="${11 * s}"/>${iconAt(CD().icons.ferry, mx, my, 13 * s)}</g>`;
    for (const v of f.vessels) {
      const [x, y] = prj(v.lat, v.lng), old = ageMin(v.at) > OLD_MIN;
      h += `<g class="tw-vessel ${old ? 'old' : ''}" transform="translate(${x},${y})"><title>${esc(`${f.name}${v.mmsi ? `, MMSI ${v.mmsi}` : ''}: ${fillIn(FS().pos, { n: ageMin(v.at) })}`)}</title>
        <path d="M0,${-7 * s} L${4 * s},${5 * s} L0,${3 * s} L${-4 * s},${5 * s}Z" transform="rotate(${+(v.bearing ?? 0)})"/></g>`;
    }
  }
  return h;
}
function ferryList(id) {
  const mine = (T.ferries ?? []).filter((f) => f.ships.includes(id));
  if (!mine.length) return '';
  return `<h3>${esc(FS().title)}</h3><ul class="tw-clist">${mine.map((f) => {
    const deps = f.departures.filter((d) => (d.expected ?? d.aimed) > Date.now() / 1000 - 300).slice(0, 4);
    const sev = f.status === 'cancelled' ? 'stop' : ['disrupted', 'delayed'].includes(f.status) ? 'slow' : '';
    return `<li class="${sev}">${CD().icons.ferry}<span><b>${esc(f.name)}</b> <span class="tw-tag ${sev === 'stop' ? 'avvik' : ''}">${esc(fStatus(f))}</span>
      ${f.messages.map((m) => `<br>${esc(FS().msg)}: ${esc(m)}`).join('')}
      ${deps.length ? `<br>${esc(FS().next)}: ${deps.map((d) => `${esc(hhmm(d.expected ?? d.aimed))}${d.expected && d.expected - d.aimed >= 60 ? ` (${esc(hhmm(d.aimed))})` : ''} ${esc(FS().from)} ${esc(d.from)} <span class="tw-fd ${d.cancelled ? 'x' : d.live ? 'live' : ''}">${esc(d.cancelled ? FS().cancelled : d.live ? FS().live : FS().planned)}</span>`).join(', ')}` : ''}
      <br>${f.vessels.length ? `${esc(FS().vessels)}: ${f.vessels.length}, ${esc((() => { const n = Math.min(...f.vessels.map((v) => ageMin(v.at))); return n > OLD_MIN ? FS().old : fillIn(FS().age, { n }); })())}` : esc(FS().none)}
      <br><small>${esc(CD().source)}: ${esc(f.source ?? FS().credit)}${f.checked ? `. ${esc(fillIn(FS().checked, { t: hhmm(f.checked) }))}` : ''}</small></span></li>`;
  }).join('')}</ul>`;
}

function mapDraw() {
  const s = T.vb ? T.vb[2] / (svg.clientWidth || 600) : 1; // svg units per screen pixel: markers keep their size when zooming
  // The land is parsed once; only the layer on top of it is rebuilt on a draw
  if (!svg.querySelector('#tw-land') && T.path) svg.innerHTML = `<g id="tw-land">${T.path}</g><g id="tw-dyn"></g><g id="tw-cars"></g><g id="tw-top"></g>`;
  // Labels are placed in priority order: the selected trip, then stores and vehicles, then the towns underneath
  LBL.length = 0;
  overlayRects();
  const top = demoLayer(s), veh = vehLayer(s), marks = ferryLayer(s) + condLayer(s);
  let h = '';
  // Demo vehicles are drawn by vehLayer from the simulation; the shipment rows here are the other vehicles
  const fleet = new Set(SIM.vehicles.map((v) => v.fleet_no));
  const rows = T.rows.filter((r) => !fleet.has(r.fleet_no));
  for (const r of rows) {
    if (r.route.length) h += `<polyline class="tw-route ${r.id === T.sel ? 'on' : ''}" points="${r.route.map(([a, b]) => prj(a, b).join(',')).join(' ')}"/>`;
  }
  if (T.detail && T.detail.id === T.sel && T.detail.positions.length > 1) {
    h += `<polyline class="tw-trace" points="${T.detail.positions.map((p) => prj(+p.lat, +p.lng).join(',')).join(' ')}"/>`;
  }
  const placed = []; // vehicles on the same stretch of road get their labels stacked instead of printed over each other
  for (const r of rows) {
    if (r.lat === null) continue;
    const [x, y] = prj(+r.lat, +r.lng), m = 7 * s;
    const below = placed.filter(([px, py]) => Math.abs(px - x) < 160 * s && Math.abs(py - y) < 16 * s).length;
    placed.push([x, y + below * 16 * s]);
    LBL.push([x - m * 2.4, y - m * 1.4, m * 4.8, m * 2.8]);
    const rowText = `${r.fleet_no ?? r.ref} ${r.origin_city} → ${r.dest_city}`;
    const rowFits = labelAt(x + m * 2.6, y - 8 * s + below * 16 * s, textW(rowText, 11 * s) + 4 * s, 16 * s);
    const head = r.heading !== null ? `<path class="hd" d="M0,${-m * 2} L${m * 0.7},${-m * 1.1} L${-m * 0.7},${-m * 1.1}Z" transform="rotate(${+r.heading})"/>` : '';
    const boat = '';
    h += `<g class="tw-vh ${esc(r.status)} ${boat} ${r.stale ? 'stale' : ''} ${r.id === T.sel ? 'on' : ''}" data-ship="${r.id}" transform="translate(${x},${y})" tabindex="0" role="button"
      aria-label="${esc(TR.types[r.vehicle_type] ?? r.vehicle_type ?? '')} ${esc(r.fleet_no ?? r.ref)}, ${esc(TR.status[r.status] ?? r.status)}${r.stale ? ', ' + esc(TR.stale) : ''}">${head}<rect x="${-m * 2.4}" y="${-m * 1.4}" width="${m * 4.8}" height="${m * 2.8}" rx="${m * 0.5}"/>
      <image href="${vehSrc(r.vehicle_type)}" x="${-m * 2.2}" y="${-m * 1.6}" width="${m * 4.4}" height="${m * 3}" preserveAspectRatio="xMidYMid meet"/>
      ${rowFits || r.id === T.sel ? `<text x="${m * 2.9}" y="${m * 0.5 + below * 16 * s}" style="font-size:${11 * s}px;stroke-width:${3 * s}px">${esc(r.fleet_no ?? r.ref)}<tspan class="od" dx="${4 * s}">${esc(r.origin_city)} → ${esc(r.dest_city)}</tspan></text>` : ''}</g>`;
  }
  const sel = T.rows.find((r) => r.id === T.sel);
  if (sel && sel.route.length > 1) {
    const end = (p, label, cls) => { const [x, y] = prj(p[0], p[1]), w = textW(label, 11 * s); return `<g class="tw-end ${cls}" transform="translate(${x},${y})"><rect x="${-5 * s}" y="${-5 * s}" width="${10 * s}" height="${10 * s}"/>
      ${labelAt(x - w / 2, y - 20 * s, w, 14 * s) ? `<text y="${-9 * s}" text-anchor="middle" style="font-size:${11 * s}px;stroke-width:${3 * s}px">${esc(label)}</text>` : ''}</g>`; };
    h += end(sel.route[0], `${TR.from} ${sel.origin_city}`, 'from') + end(sel.route[sel.route.length - 1], `${TR.to} ${sel.dest_city}`, 'to');
  }
  // Town names last, in the space the markers and labels above left free, and drawn underneath them
  h = placeLayer(s) + h + marks + crossings(s) + veh + meLayer(s);
  const dyn = svg.querySelector('#tw-dyn');
  if (dyn) { dyn.innerHTML = h; svg.querySelector('#tw-top').innerHTML = top; patchCars(); } else svg.innerHTML = h + top;
  weatherMood();
}

// ---------- Simulation and ordering (feature 043: DemoDelivery.php, DemoSim.php, Supply.php) ----------
// One server clock drives every demo vehicle. This page reads the state once a second and sends play, pause, speed,
// scenario, replay, a demo trip and orders. D.s is the trip of the selected vehicle, so the route fit, the weather
// mood and the ferry marker follow it. The camera is the presenter's: nothing here moves it unless Følg kjøretøy is on.
const D = { id: null, s: null, follow: false };
const SIM = { clock: null, trips: [], vehicles: [], veh: null, pick: false, seen: new Map(), timer: null, n: 0, ov: null, batch: null, drop: new Set(), chosen: new Set(), busy: false };
const SM = TR.sim;
const simBox = $('tw-sim'), followBtn = $('tw-follow'), demoMsg = $('tw-demo-msg');
const simFmt = new Intl.DateTimeFormat(locale, { timeZone: 'Europe/Oslo', weekday: 'short', hour: '2-digit', minute: '2-digit' });
const hm = (t) => simFmt.format(new Date(t * 1000));
function demoSay(text, err = false) { demoMsg.hidden = !text; demoMsg.textContent = text ?? ''; demoMsg.classList.toggle('err', err); }
const noteText = (n) => fillIn(SM.note[n.key] ?? n.key, n.vars);
const srcLine = (src) => (src ? `<small class="tw-from">${esc(SM.source)}: ${esc(src)}</small>` : '');
const tripOf = (vid) => SIM.trips.find((t) => t.vehicle === vid && !t.done) ?? SIM.trips.find((t) => t.vehicle === vid) ?? null;
const vehById = (id) => SIM.vehicles.find((v) => v.id === id);
const warnKeys = ['forsinket', 'storm', 'stormvarsel', 'innstilt', 'innstilt_entur', 'stengt', 'farevarsel', 'veiarbeid', 'veiarbeid_n', 'ferjemelding'];

// Labels never print over each other: each draw places them in priority order (the selected trip, stores, vehicles,
// towns) and a label whose box would overlap one already placed is left out. Its marker keeps a tooltip.
const LBL = [];
// Text width from the real font: the mono place labels by default, Archivo for the arrival box
const MEASURE = document.createElement('canvas').getContext('2d');
const FONTS = {};
const textW = (str, px, font = 'mono', weight = 400) => {
  FONTS[font] ??= getComputedStyle(document.body).getPropertyValue(font === 'mono' ? '--mono' : '--font').trim() || 'monospace';
  MEASURE.font = `${weight} 100px ${FONTS[font]}`;
  return (MEASURE.measureText(String(str)).width / 100) * px;
};
// The map's HTML overlays (weather badge, clock panel, messages) as rectangles in svg units, so no label goes under them
function overlayRects() {
  const ctm = svg.getScreenCTM?.();
  if (!ctm) return;
  const inv = ctm.inverse();
  for (const el of document.querySelectorAll('#tw-wx-badge, #tw-sim, #tw-demo-msg, #tw-toasts > *')) {
    if (el.hidden || !el.getClientRects().length) continue;
    const r = el.getBoundingClientRect();
    const a = new DOMPoint(r.left, r.top).matrixTransform(inv), b = new DOMPoint(r.right, r.bottom).matrixTransform(inv);
    LBL.push([a.x, a.y, b.x - a.x, b.y - a.y]);
  }
}
function labelAt(x, y, w, h) {
  if (LBL.some(([a, b, c, d]) => x < a + c && x + w > a && y < b + d && y + h > b)) return false;
  LBL.push([x, y, w, h]);
  return true;
}
// Every demo vehicle on the map: on a trip at its simulated position, otherwise at its base. In a batch the vehicles
// that take part are marked with how many lines they fetch, and the stores holding lines no vehicle reaches get a red triangle.
function vehLayer(s) {
  let h = '';
  const m = 7 * s, sel = vehById(SIM.veh);
  if (sel && !sel.busy) {
    const [x, y] = prj(sel.lat, sel.lng), r = (SIM.ov?.radius ?? 60) / 111 * KM;
    h += `<circle class="tw-reach" cx="${x}" cy="${y}" r="${r}"/>`;
  }
  const near = new Set((SIM.ov?.vehicles.find((v) => v.id === SIM.veh)?.near ?? []).map((n) => n.id));
  const missing = new Set((SIM.batch ? SIM.ov?.assign.uncovered ?? [] : []).flatMap((u) => u.stock.map((x) => x.id)));
  // Every Sør-Tre store is on the map with the leaf, red for the real store and yellow for the simulated ones: stores in the selected vehicle's reach are ringed, and in a batch the
  // stores holding lines no vehicle reaches get a red triangle
  for (const st of SIM.ov?.stores ?? []) {
    const cls = missing.has(st.id) ? 'miss' : near.has(st.id) ? 'near' : '';
    const [x, y] = prj(st.lat, st.lng), z = 18 * s;
    LBL.push([x - z * 0.72, y - z * 0.72, z * 1.44, z * 1.44]);
    h += `<g class="tw-store tw-hq ${cls} ${st.home ? 'home' : ''}" transform="translate(${x},${y})"><circle r="${z * 0.72}"/>
      <image href="/assets/brand/sorpedia-leaf-96${st.real ? '' : '-demo'}.png" x="${-z / 2}" y="${-z / 2}" width="${z}" height="${z}"/>
      ${cls === 'miss' ? `<path d="M${8 * s},${-14 * s} L${13.5 * s},${-4 * s} L${2.5 * s},${-4 * s}Z"/>` : ''}
      ${labelAt(x - textW(st.name, 10.5 * s) / 2, y + z - 6 * s, textW(st.name, 10.5 * s), 13 * s) ? `<text y="${z + 4 * s}" text-anchor="middle" style="font-size:${10.5 * s}px;stroke-width:${3 * s}px">${esc(st.name)}</text>` : ''}
      <title>Sør-Tre ${esc(st.name)}${st.real ? '' : ` (${esc(SM.simHq)})`}${cls ? `: ${esc(cls === 'miss' ? SM.missHere : SM.inReach)}` : ''}</title></g>`;
  }
  // Vehicles closer than 30 screen pixels are fanned out round their common point, so each one can be seen and clicked
  const at = SIM.vehicles.map((v) => { const t = v.busy ? tripOf(v.id) : null; return prj(t ? t.now.lat : v.lat, t ? t.now.lng : v.lng); });
  const groups = [];
  at.forEach(([x, y], i) => { const g = groups.find((o) => Math.hypot(o.x - x, o.y - y) < 30 * s); if (g) g.m.push(i); else groups.push({ x, y, m: [i] }); });
  for (const g of groups) {
    if (g.m.length < 2) continue;
    const r = (14 + 4 * g.m.length) * s;
    g.m.forEach((i, k) => { const a = (k / g.m.length) * 2 * Math.PI - Math.PI / 2; at[i] = [g.x + r * Math.cos(a), g.y + r * Math.sin(a)]; });
  }
  // The selected vehicle is labelled first; the others get their fleet number where it fits
  const order = SIM.vehicles.map((v, i) => i).sort((a, b) => (SIM.vehicles[b].id === SIM.veh) - (SIM.vehicles[a].id === SIM.veh));
  CARS.length = 0;
  // Every vehicle marker is taken before any label, so no label prints over a vehicle
  at.forEach(([x, y]) => LBL.push([x - m * 2.4, y - m * 1.4, m * 4.8, m * 2.8]));
  for (const i of order) {
    const v = SIM.vehicles[i], t = v.busy ? tripOf(v.id) : null;
    const [x, y] = at[i];
    const lines = SIM.batch ? (SIM.ov?.assign.vehicles[v.id] ?? []) : [];
    const kept = lines.filter((l) => !SIM.drop.has(l.pick));
    const short = kept.some((l) => l.qty < l.want);
    const st = t ? (t.done ? 'levert' : t.delay ? 'forsinket' : 'underveis') : 'ledig';
    const on = v.id === SIM.veh, kind = t && !t.done ? t.now.kind : null;
    const label = on && t && !t.done ? `${v.fleet_no} → ${t.now.to ?? ''}` : v.fleet_no;
    const eta = on && t ? etaBox(t, x, y, s) : '';
    const fs = 11 * s, show = labelAt(x + m * 2.6, y - fs * 0.7, textW(label, fs) + 4 * s, fs * 1.4);
    const tip = `${v.fleet_no}${t && !t.done ? `: ${t.now.from} → ${t.now.to}, ${SM.etaOk} ${hm(t.eta)}` : `, ${SM.free}`}`;
    // On a ferry the vehicle rides a hull with a wake; waiting at the quay it gets a slow ring
    const boat = kind === 'ferje' ? `<path class="hull" d="M${-m * 3.4},${m * 0.9} L${m * 3.4},${m * 0.9} L${m * 2.6},${m * 2.3} L${-m * 2.6},${m * 2.3}Z"/>
      <path class="wake" d="M${-m * 3.6},${m * 1.8} q${-m * 1.2},${m * 0.5} ${-m * 2.6},0 M${-m * 3.6},${m * 1.4} q${-m * 1.5},${-m * 0.6} ${-m * 3},0"/>` : '';
    const wait = kind === 'venter' ? `<circle class="wait" r="${m * 3}"/>` : '';
    CARS.push({ id: v.id, x, y, label: `${TR.types[v.type] ?? v.type} ${v.fleet_no}, ${t ? TR.status[st] ?? st : SM.free}`,
      cls: `tw-vh tw-car ${st} ${kind === 'ferje' ? 'onferry' : ''} ${on ? 'on' : ''} ${lines.length ? (kept.length ? 'pick' : 'dropped') : ''}`,
      inner: `<title>${esc(tip)}</title>${wait}${boat}
      ${t && !t.done && t.now.heading !== undefined ? `<path class="hd" d="M0,${-m * 2} L${m * 0.7},${-m * 1.1} L${-m * 0.7},${-m * 1.1}Z" transform="rotate(${+t.now.heading})"/>` : ''}
      <rect x="${-m * 2.4}" y="${-m * 1.4}" width="${m * 4.8}" height="${m * 2.8}" rx="${m * 0.5}"/>
      <image href="${vehSrc(v.type)}" x="${-m * 2.2}" y="${-m * 1.6}" width="${m * 4.4}" height="${m * 3}" preserveAspectRatio="xMidYMid meet"/>
      ${show ? `<text x="${m * 2.9}" y="${m * 0.5}" style="font-size:${fs}px;stroke-width:${3 * s}px">${esc(label)}</text>` : ''}${eta}
      ${lines.length ? `<g class="tw-count ${short ? 'short' : ''}" transform="translate(${-m * 2.4},${-m * 2.6})"><rect width="${m * 4}" height="${m * 1.9}" rx="${m * 0.9}"/>
        <text x="${m * 2}" y="${m * 1.35}" text-anchor="middle" style="font-size:${10 * s}px">${kept.length}/${lines.length}${short ? ' !' : ''}</text></g>` : ''}` });
  }
  return h;
}

// The arrival box of the selected trip, inside the vehicle's own node so it moves with it. Sized from the text in the
// box's own font; above the vehicle, or below it when something already sits above.
function etaBox(t, x, y, s) {
  const m = 7 * s, line = t.done ? TR.demoDone : `${SM.etaOk} ${hm(t.eta)}`, fs = 12 * s;
  const bw = textW(line, fs, 'font', 700) + 16 * s, bh = fs * 1.9, gap = m * 1.4 + 4 * s;
  const up = labelAt(x - bw / 2, y - gap - bh, bw, bh);
  if (!up) LBL.push([x - bw / 2, y + gap, bw, bh]);
  const top = up ? -gap - bh : gap;
  return `<g class="tw-eta ${t.delay ? 'late' : ''}"><rect x="${-bw / 2}" y="${top}" width="${bw}" height="${bh}" rx="${m * 0.4}"/>
    <text y="${top + bh / 2}" dy=".35em" text-anchor="middle" style="font-size:${fs}px">${esc(line)}</text></g>`;
}

// Vehicles are kept as nodes between draws and moved with a CSS transform, so they glide from one simulation tick to
// the next instead of jumping (no movement under reduced motion, see tower.css)
const CARS = [];
const NS = 'http://www.w3.org/2000/svg';
// Only a draw that follows a simulation tick may glide; zoom, fit, replay and the return to base jump
let glide = false;
function patchCars() {
  const g = svg.querySelector('#tw-cars');
  if (!g) return;
  const s = T.vb ? T.vb[2] / (svg.clientWidth || 600) : 1;
  const keep = new Set();
  for (const c of CARS) {
    let el = g.querySelector(`[data-veh="${c.id}"]`);
    const at = `translate(${c.x.toFixed(2)}px, ${c.y.toFixed(2)}px)`;
    if (!el) {
      el = document.createElementNS(NS, 'g');
      el.dataset.veh = c.id;
      el.setAttribute('tabindex', '0');
      el.setAttribute('role', 'button');
      el.style.transform = at;
      g.append(el);
    }
    el.setAttribute('class', c.cls);
    el.setAttribute('aria-label', c.label);
    // The inner nodes are kept between ticks, so the wait ring and the wake keep running
    if (el._inner !== c.inner) { el.innerHTML = c.inner; el._inner = c.inner; }
    const far = el._x === undefined || Math.hypot(c.x - el._x, c.y - el._y) / s > 80;
    if (!glide || far) {
      el.style.transition = 'none';
      el.style.transform = at;
      el.getBoundingClientRect(); // apply the jump before the transition comes back
      el.style.transition = '';
    } else el.style.transform = at;
    el._x = c.x;
    el._y = c.y;
    keep.add(String(c.id));
  }
  glide = false;
  for (const el of [...g.children]) if (!keep.has(el.dataset.veh)) el.remove();
  // The selected vehicle draws on top of the others
  const sel = g.querySelector('.tw-car.on');
  if (sel && sel !== g.lastElementChild) {
    const had = document.activeElement === sel;
    g.append(sel);
    if (had) sel.focus({ preventScroll: true }); // moving a node blurs it
  }
}

// Ferry crossings in use: every vehicle on a ferry right now gets its crossing drawn with a moving wake
function crossings(s) {
  let h = '';
  for (const t of SIM.trips) {
    if (t.done || t.now.kind !== 'ferje') continue;
    for (const f of t.ferries) {
      const [a, b] = [prj(...f.a), prj(...f.b)];
      h += `<line class="tw-crossing" x1="${a[0]}" y1="${a[1]}" x2="${b[0]}" y2="${b[1]}" style="stroke-width:${3 * s}px"/>`;
    }
  }
  return h;
}

// The selected vehicle's trip on the map: ferry crossings dashed over the water, the stops, the destination and the ETA
function demoLayer(s) {
  const t = D.s;
  if (!t) return '';
  const m = 7 * s;
  let h = '';
  for (const f of t.ferries) {
    const [a, b] = [prj(...f.a), prj(...f.b)];
    h += `<g class="tw-simferry"><line x1="${a[0]}" y1="${a[1]}" x2="${b[0]}" y2="${b[1]}"/><title>${esc(SM.ferry)}</title></g>`;
  }
  h += `<polyline class="tw-route on" points="${t.stops.map((p) => prj(p.lat, p.lng).join(',')).join(' ')}"/>`;
  for (const p of t.stops.filter((x) => x.kind === 'hent' || x.kind === 'start')) {
    const [px, py] = prj(p.lat, p.lng);
    const w = textW(p.name, 10 * s);
    LBL.push([px - 3 * s, py - 3 * s, 6 * s, 6 * s]);
    const fits = labelAt(px - 5 * s - w, py - 6 * s, w, 13 * s);
    h += `<g class="tw-stop ${p.kind}" transform="translate(${px},${py})"><circle r="${3 * s}"/>${fits ? `<text x="${-5 * s}" y="${4 * s}" text-anchor="end" style="font-size:${10 * s}px;stroke-width:${3 * s}px">${esc(p.name)}</text>` : ''}</g>`;
  }
  // A closed road the trip drives round: the closed stretch in red with the closure mark, its reason in the tooltip
  for (const c of t.detour ?? []) {
    const [a, b] = [prj(...c.a), prj(...c.b)], [mx, my] = [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
    h += `<g class="tw-closed"><title>${esc(`${(c.road ? fillIn(SM.detour, { road: c.road }) : SM.detourAny)}: ${c.text}. ${SM.source}: ${c.src}`)}</title>
      <line x1="${a[0]}" y1="${a[1]}" x2="${b[0]}" y2="${b[1]}"/><rect x="${mx - 10 * s}" y="${my - 10 * s}" width="${20 * s}" height="${20 * s}" rx="${3 * s}"/>${iconAt(CD().icons.closure, mx, my, 13 * s)}</g>`;
  }
  const end = t.stops[t.stops.length - 1], [x, y] = prj(end.lat, end.lng);
  const ew = textW(end.name, 11 * s);
  h += `<g class="tw-you" transform="translate(${x},${y})"><circle r="${m * 2.2}" class="halo"/><circle r="${m * 0.9}"/>
    ${labelAt(x - ew / 2, y + m * 2.4, ew, 14 * s) ? `<text y="${m * 3.6}" text-anchor="middle" style="font-size:${11 * s}px;stroke-width:${3 * s}px">${esc(end.name)}</text>` : ''}</g>`;
  return h;
}

// The clock strip: simulated time, play and pause, speed, the conditions scenario, and the order list waiting in Gulv
function simPanel() {
  const c = SIM.clock;
  simBox.hidden = !c;
  if (!c) return;
  const waiting = SIM.ov?.picks.length ?? 0;
  simBox.innerHTML = `<div class="tw-sim-row"><span class="tw-sim-clock"><small>${esc(SM.clock)} <span class="tw-demo">${esc(SM.demo)}</span></small><b>${esc(hm(c.sim))}</b></span>
      <button type="button" class="tw-btn" data-sim="${c.paused ? 'play' : 'pause'}" aria-label="${esc(c.paused ? SM.play : SM.pause)}">${esc(c.paused ? SM.play : SM.pause)}</button>
      <span class="tw-sim-rates" role="group" aria-label="${esc(SM.speed)}">${c.rates.map((r) => `<button type="button" class="tw-btn" data-rate="${r}" aria-pressed="${r === c.rate}">x${r}</button>`).join('')}</span></div>
    <div class="tw-sim-row"><span class="tw-seg" role="group" aria-label="${esc(SM.scenario)}">${['ekte', 'storm'].map((k) => `<button type="button" class="tw-btn" data-scen="${k}" aria-pressed="${c.scenario === k}">${esc(SM.scen[k])}</button>`).join('')}</span>
      ${waiting ? `<button type="button" class="tw-btn tw-go" data-batch>${esc(fillIn(SM.batch, { n: waiting }))}</button>` : ''}</div>
    <p class="tw-sim-src">${esc(c.scenario === 'storm' ? SM.srcStorm : SM.srcReal)}</p>`;
}

// A notification shows once as a toast when the clock passes it, then stays in the vehicle's history
function toast(n, t) {
  const box = $('tw-toasts');
  const el = document.createElement('p');
  el.className = `tw-toast ${warnKeys.includes(n.key) ? 'warn' : n.key === 'levert' ? 'ok' : ''}`;
  el.innerHTML = `<time>${esc(hm(t.t0 + n.at))}</time><span><b>${esc(t.fleet_no ?? t.ref)}</b> ${esc(noteText(n))}${srcLine(n.src)}</span>`;
  box.prepend(el);
  while (box.children.length > 2) box.lastChild.remove();
  setTimeout(() => el.remove(), 7000);
}
function centerOn(lat, lng) {
  if (!T.vb) return;
  const [x, y] = prj(lat, lng), [, , w, h] = T.vb;
  T.vb = [x - w / 2, y - h / 2, w, h];
  svg.setAttribute('viewBox', T.vb.join(' '));
}
function setFollow(on) {
  D.follow = on;
  followBtn.setAttribute('aria-pressed', String(on));
  if (on && D.s) { centerOn(D.s.now.lat, D.s.now.lng); mapDraw(); }
}

// The vehicle panel in the inspector: its trip with where, weather, ETA, delay reasons and notes, each with its source;
// a free vehicle lists the waiting lines a store in its reach has, to order with this vehicle only, and a demo trip
function tripView(t) {
  const n = t.now;
  const reasons = t.delay ? `<ul class="tw-why">${t.delay.reasons.map((r) => `<li>${esc(fillIn(SM.why[r.type] ?? r.type, r))}${srcLine(r.src)}</li>`).join('')}</ul>` : '';
  return `<section class="tw-trip"><h3>${esc(t.ref)}${t.scenario === 'storm' ? ` <span class="tw-tag avvik">${esc(SM.scen.storm)}</span>` : ''}</h3>
    <div class="tw-sim-bar" role="progressbar" aria-label="${esc(TR.progress)}" aria-valuenow="${Math.round(t.progress * 100)}" aria-valuemin="0" aria-valuemax="100"><i style="transform:scaleX(${t.progress})"></i></div>
    <p class="tw-sim-where"><b>${esc(t.done ? TR.demoDone : fillIn(SM.phase[n.kind] ?? SM.phase.vei, { from: n.from, to: n.to, road: n.road ?? '' }))}</b></p>
    ${n.sky ? `<p class="tw-sim-wx wx-${esc(n.sky)}">${CD().icons[n.sky] ?? ''}<span>${esc(SM.sky[n.sky] ?? n.sky)}${n.wind !== null && n.wind !== undefined ? `, ${esc(fillIn(SM.wind, { n: n.wind }))}` : ''}${n.temp !== null && n.temp !== undefined ? `, ${num(n.temp)}°` : ''}${srcLine(n.wxsrc)}</span></p>` : ''}
    ${(t.detour ?? []).map((c) => `<p class="tw-sim-detour">${CD().icons.closure}<span>${esc((c.road ? fillIn(SM.detour, { road: c.road }) : SM.detourAny))}: ${esc(c.text)}${srcLine(c.src)}</span></p>`).join('')}
    <p class="tw-sim-eta">${t.delay ? `<span><small>${esc(SM.planned)}</small><s>${esc(hm(t.planned))}</s></span><span class="late"><small>${esc(SM.eta)}</small><b>${esc(hm(t.eta))}</b></span>
      <span class="tw-tag avvik">${esc(fillIn(SM.delay, { n: t.delay.min }))}</span>` : `<span><small>${esc(t.done ? TR.demoDone : SM.etaOk)}</small><b>${esc(hm(t.done ? t.t0 + t.total : t.eta))}</b></span>`}</p>${reasons}
    <div class="tw-trip-act"><button type="button" class="tw-btn" data-sim="${SIM.clock?.paused ? 'play' : 'pause'}">${esc(SIM.clock?.paused ? SM.play : SM.pause)}</button>
      <button type="button" class="tw-btn" data-replay="${t.id}">${esc(SM.replay)}</button>
      <button type="button" class="tw-btn" data-fit="route">${esc(TR.sim.route)}</button></div>
    <h4>${esc(SM.notes)} <b>${t.notes.length}</b></h4>
    <ol class="tw-notes" reversed>${t.notes.slice().reverse().map((x, i) => `<li class="${warnKeys.includes(x.key) ? 'warn' : ''}"><button type="button" data-note="${t.notes.length - 1 - i}"><time>${esc(hm(t.t0 + x.at))}</time><span>${esc(noteText(x))}${srcLine(x.src)}</span></button></li>`).join('') || `<li class="tw-muted">${esc(SM.noNotes)}</li>`}</ol></section>`;
}
function freeView(v) {
  const ov = SIM.ov, opts = ov ? ov.picks.map((p) => [p, (ov.assign.options[p.id] ?? []).find((o) => o.vehicle === v.id)]) : [];
  const can = opts.filter(([, o]) => o), cannot = opts.filter(([, o]) => !o);
  const near = ov?.vehicles.find((x) => x.id === v.id)?.near ?? [];
  const n = [...SIM.chosen].filter((id) => can.some(([p]) => p.id === id)).length;
  return `<section class="tw-trip"><p class="tw-muted">${esc(fillIn(SM.reach, { km: ov?.radius ?? 60, n: near.length }))}${near.length ? `: ${near.map((s) => `${esc(s.name)} (${num(s.km)} km)`).join(', ')}` : ''}</p>
    <h4>${esc(SM.canFetch)} <b>${can.length}</b></h4>
    ${can.length ? `<ul class="tw-picks">${can.map(([p, o]) => `<li><label><input type="checkbox" data-choose="${p.id}" ${SIM.chosen.has(p.id) ? 'checked' : ''}>
      ${p.img ? `<img src="/${esc(p.img)}" alt="" loading="lazy">` : '<i class="tw-noimg"></i>'}<span><b>${esc(p.name)}</b>
      <small>${esc(fillIn(SM.from, { store: o.store_name, have: o.have, take: Math.min(p.qty, o.have), want: p.qty }))}${o.have < p.qty ? ` <span class="tw-tri" aria-hidden="true">▲</span><span class="tw-sr">${esc(SM.short)}</span>` : ''}</small></span></label></li>`).join('')}</ul>
      <button type="button" class="tw-btn tw-go" data-order-one="${v.id}" ${n ? '' : 'disabled'}>${esc(fillIn(SM.orderOne, { n, v: v.fleet_no }))}</button>`
      : `<p class="tw-muted">${esc(ov?.picks.length ? SM.noneInReach : SM.noPicks)}</p>`}
    ${cannot.length ? `<details><summary>${esc(fillIn(SM.outOfReach, { n: cannot.length }))}</summary><ul class="tw-picks off">${cannot.map(([p]) => `<li><span><b>${esc(p.name)}</b>
      <small>${p.stock.length ? esc(fillIn(SM.onlyAt, { list: p.stock.map((x) => `${x.name} (${x.qty})`).join(', ') })) : esc(SM.nowhere)}</small></span></li>`).join('')}</ul></details>` : ''}
    <h4>${esc(SM.tourTitle)}</h4><p class="tw-muted">${esc(SM.tourText)}</p>
    <button type="button" class="tw-btn" data-tour="${v.id}" aria-pressed="${SIM.pick}">${esc(SIM.pick ? SM.tourCancel : SM.tourGo)}</button>
    ${v.at !== v.base ? `<button type="button" class="tw-btn" data-home="${v.id}">${esc(fillIn(SM.backBase, { city: v.base }))}</button>` : ''}</section>`;
}
function vehView() {
  const v = vehById(SIM.veh);
  if (!v) return shipList();
  const t = v.busy ? tripOf(v.id) : null;
  return `<button type="button" class="tw-back-link" data-veh-close>${esc(L.dr.back)}</button>
    <h3 class="tw-veh-h"><img src="${vehSrc(v.type)}" alt="">${esc(v.fleet_no)}<small>${esc(TR.types[v.type] ?? v.type)}, ${esc([v.make, v.model].filter(Boolean).join(' '))}</small></h3>
    <p class="tw-muted">${esc(fillIn(SM.base, { city: v.base }))} · ${esc(t ? (t.done ? TR.demoDone : TR.status[t.delay ? 'forsinket' : 'underveis']) : SM.free)} <span class="tw-demo">${esc(L.demo)}</span></p>
    ${t ? tripView(t) : freeView(v)}`;
}

// The batch: every waiting line goes to the free vehicle with the nearest store that has it. Unticking a line or a
// vehicle leaves it out of this order; it stays on the list in Gulv.
function batchView() {
  const a = SIM.ov.assign, used = Object.entries(a.vehicles);
  const kept = used.flatMap(([, ls]) => ls).filter((l) => !SIM.drop.has(l.pick));
  const nv = used.filter(([, ls]) => ls.some((l) => !SIM.drop.has(l.pick))).length;
  return `<button type="button" class="tw-back-link" data-batch-close>${esc(L.dr.back)}</button><h3>${esc(SM.batchTitle)}</h3>
    <p class="tw-muted">${esc(fillIn(SM.batchLead, { n: SIM.ov.picks.length, km: SIM.ov.radius }))}</p>
    ${used.map(([vid, ls]) => { const v = vehById(+vid), on = ls.filter((l) => !SIM.drop.has(l.pick)).length; return `<section class="tw-bveh">
      <label class="tw-bveh-h"><input type="checkbox" data-drop-veh="${vid}" ${on ? 'checked' : ''}><b>${esc(v?.fleet_no ?? vid)}</b><span class="tw-muted">${esc(v?.base ?? '')}</span><span class="tw-n">${on}/${ls.length}</span></label>
      <ul class="tw-picks">${ls.map((l) => `<li><label><input type="checkbox" data-drop="${l.pick}" ${SIM.drop.has(l.pick) ? '' : 'checked'}>
        ${l.img ? `<img src="/${esc(l.img)}" alt="" loading="lazy">` : '<i class="tw-noimg"></i>'}<span><b>${esc(l.name)}</b>
        <small>${esc(fillIn(SM.from, { store: l.store_name, have: l.qty, take: l.qty, want: l.want }))}${l.qty < l.want ? ` <span class="tw-tri" aria-hidden="true">▲</span><span class="tw-sr">${esc(SM.short)}</span>` : ''}</small></span></label></li>`).join('')}</ul></section>`; }).join('')}
    ${a.uncovered.length ? `<section class="tw-bveh miss"><h4><span class="tw-tri" aria-hidden="true">▲</span> ${esc(fillIn(SM.uncovered, { n: a.uncovered.length }))}</h4>
      <ul class="tw-picks off">${a.uncovered.map((u) => `<li><span><b>${esc(u.name)}</b><small>${u.stock.length ? esc(fillIn(SM.onlyAt, { list: u.stock.map((x) => `${x.name} (${x.qty})`).join(', ') })) : esc(SM.nowhere)}</small></span></li>`).join('')}</ul></section>` : ''}
    ${used.length ? `<button type="button" class="tw-btn tw-go" data-order-batch ${kept.length ? '' : 'disabled'}>${esc(fillIn(SM.orderBatch, { n: kept.length, v: nv }))}</button>` : `<p class="tw-muted">${esc(SM.noneInReach)}</p>`}`;
}
function inspector() {
  if (T.view !== 'transport' || T.sel !== null) return;
  const box = $('tw-inspect'), q = document.activeElement?.id === 'tw-ship-q';
  if (q) return; // never rebuild under the presenter's typing
  const open = box.querySelector('details')?.open;
  box.innerHTML = SIM.batch ? batchView() : SIM.veh !== null ? vehView() : shipList();
  if (open) box.querySelector('details')?.setAttribute('open', '');
}

function simApply(r) {
  SIM.clock = r.clock;
  SIM.trips = r.trips;
  for (const t of r.trips) {
    const seen = SIM.seen.get(t.id);
    if (seen !== undefined) for (const n of t.notes.slice(seen)) toast(n, t);
    SIM.seen.set(t.id, t.notes.length);
    // The shipment row moves on the position the tick just wrote
    const row = T.rows.find((x) => x.id === t.id);
    if (row) Object.assign(row, { lat: t.now.lat, lng: t.now.lng, heading: t.now.heading, status: t.done ? 'levert' : t.delay ? 'forsinket' : 'underveis', eta: t.done ? null : t.eta, stale: false, progress: t.progress });
  }
  const v = vehById(SIM.veh);
  D.s = v?.busy ? tripOf(v.id) : null;
  D.id = D.s?.id ?? null;
  if (D.follow && D.s) centerOn(D.s.now.lat, D.s.now.lng);
  followBtn.hidden = !D.s;
  simPanel();
  inspector();
  glide = true;
  scheduleDraw();
}
async function simTick() {
  if (T.view !== 'transport' || document.hidden || SIM.busy) return;
  const r = await post('/api/tarn/sim/tick').catch(() => null);
  if (!r?.ok) return;
  const s = await r.json();
  const arrived = s.trips.some((t) => t.done && !SIM.trips.find((x) => x.id === t.id)?.done);
  simApply(s);
  // The vehicle list and the order list are read again when a trip ends and every fifteenth tick
  if (arrived || ++SIM.n % 15 === 0) { await Promise.all([transport(), orderLoad()]); if (arrived) throughput(); }
}
async function simControl(body) {
  const r = await post('/api/tarn/sim/styr', body);
  if (r.ok) simApply(await r.json());
}
async function orderLoad() {
  try { SIM.ov = await getJson('/api/tarn/bestilling'); } catch { SIM.ov = null; }
  simPanel();
  inspector();
}
function vehSelect(id) {
  SIM.veh = SIM.veh === id ? null : id;
  SIM.pick = false;
  SIM.batch = null;
  SIM.chosen.clear();
  svg.classList.remove('pick');
  T.sel = null;
  T.detail = null;
  demoSay('');
  const v = vehById(SIM.veh);
  D.s = v?.busy ? tripOf(v.id) : null;
  D.id = D.s?.id ?? null;
  followBtn.hidden = !D.s;
  inspector();
  mapDraw();
}
async function order(plan) {
  SIM.busy = true;
  const r = await post('/api/tarn/bestill', { plan }).catch(() => null);
  SIM.busy = false;
  const j = await r?.json().catch(() => ({})) ?? {};
  if (!r?.ok) { demoSay(j.error || SM.orderFail, true); return; }
  demoSay(fillIn(SM.ordered, { n: j.made.length }) + (j.skipped.length ? ` ${fillIn(SM.skipped, { n: j.skipped.length })}` : ''));
  SIM.batch = null;
  SIM.drop.clear();
  SIM.chosen.clear();
  await Promise.all([transport(), orderLoad()]);
  await simTick();
  inspector();
}
function pickMode(on) {
  SIM.pick = on;
  svg.classList.toggle('pick', on);
  if (on) { demoSay(SM.tourPick); demoMsg.insertAdjacentHTML('beforeend', ` <button type="button" class="tw-btn" data-pick-cancel>${esc(SM.tourCancel)}</button>`); } else demoSay('');
  inspector();
}
demoMsg.addEventListener('click', (e) => { if (e.target.closest('[data-pick-cancel]')) pickMode(false); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && SIM.pick) pickMode(false); });
svg.addEventListener('contextmenu', (e) => { if (SIM.pick) { e.preventDefault(); pickMode(false); } });
async function tourTo(lat, lng) {
  SIM.pick = false;
  svg.classList.remove('pick');
  const r = await post('/api/tarn/sim/tur', { vehicle: SIM.veh, lat, lng }).catch(() => null);
  if (!r?.ok) { demoSay((await r?.json().catch(() => ({})))?.error || TR.demoFail, true); return; }
  demoSay(SM.tourStarted);
  await transport();
  if (SIM.clock?.paused) await simControl({ what: 'play' });
  await simTick();
  if (D.s) fit(D.s.stops.map((p) => prj(p.lat, p.lng)), 0.12);
  mapDraw();
}

simBox.addEventListener('click', (e) => {
  const b = e.target.closest('button');
  if (!b) { if (e.target.closest('.tw-sim-clock')) simBox.classList.toggle('open'); return; }
  if (b.dataset.sim) simControl({ what: b.dataset.sim });
  else if (b.dataset.rate) simControl({ what: 'rate', rate: +b.dataset.rate });
  else if (b.dataset.scen) simControl({ what: 'scenario', scenario: b.dataset.scen });
  else if ('batch' in b.dataset) {
    SIM.batch = true; SIM.veh = null; SIM.drop.clear(); T.sel = null; D.s = null;
    // Once, on opening: every vehicle that takes part and every store holding a line nobody reaches in view
    const used = Object.keys(SIM.ov.assign.vehicles).map((id) => vehById(+id)).filter(Boolean).map((v) => prj(v.lat, v.lng));
    const miss = new Set(SIM.ov.assign.uncovered.flatMap((u) => u.stock.map((x) => x.id)));
    const pts = [...used, ...SIM.ov.stores.filter((x) => miss.has(x.id)).map((x) => prj(x.lat, x.lng))];
    if (pts.length) fit(pts, 0.25);
    inspector(); mapDraw();
  }
});
$('tw-inspect').addEventListener('click', async (e) => {
  if (T.view !== 'transport') return;
  const b = e.target.closest('button');
  if (!b) return;
  if ('vehClose' in b.dataset) vehSelect(SIM.veh);
  else if ('batchClose' in b.dataset) { SIM.batch = null; SIM.drop.clear(); inspector(); mapDraw(); }
  else if (b.dataset.sim) simControl({ what: b.dataset.sim });
  else if (b.dataset.replay) {
    const r = await post(`/api/tarn/sim/${+b.dataset.replay}/igjen`);
    if (!r.ok) return;
    SIM.seen.delete(+b.dataset.replay);
    $('tw-toasts').innerHTML = '';
    if (SIM.clock?.paused) await simControl({ what: 'play' });
    simApply(await (await post('/api/tarn/sim/tick')).json());
    await transport();
  } else if (b.dataset.note && D.s) {
    // A notification leads to the place it happened, without changing the zoom
    const n = D.s.notes[+b.dataset.note];
    setFollow(false);
    centerOn(n.lat, n.lng);
    mapDraw();
  } else if (b.dataset.tour) pickMode(!SIM.pick);
  else if (b.dataset.home) { const v = vehById(+b.dataset.home); if (v) tourTo(v.base_lat, v.base_lng); } else if (b.dataset.orderOne) order({ [b.dataset.orderOne]: [...SIM.chosen] });
  else if ('orderBatch' in b.dataset) {
    const plan = {};
    for (const [vid, ls] of Object.entries(SIM.ov.assign.vehicles)) {
      const ids = ls.map((l) => l.pick).filter((id) => !SIM.drop.has(id));
      if (ids.length) plan[vid] = ids;
    }
    order(plan);
  } else if (b.dataset.fit === 'route') { setFollow(false); FIT.route(); mapDraw(); }
});
$('tw-inspect').addEventListener('change', (e) => {
  const c = e.target;
  if (T.view !== 'transport' || c.type !== 'checkbox') return;
  if (c.dataset.choose) { c.checked ? SIM.chosen.add(+c.dataset.choose) : SIM.chosen.delete(+c.dataset.choose); }
  else if (c.dataset.drop) { c.checked ? SIM.drop.delete(+c.dataset.drop) : SIM.drop.add(+c.dataset.drop); }
  else if (c.dataset.dropVeh) { for (const l of SIM.ov.assign.vehicles[c.dataset.dropVeh] ?? []) c.checked ? SIM.drop.delete(l.pick) : SIM.drop.add(l.pick); }
  inspector();
  mapDraw();
});
followBtn.addEventListener('click', () => setFollow(!D.follow));
function simStart() {
  clearInterval(SIM.timer);
  SIM.timer = setInterval(simTick, 1000);
  orderLoad();
  simTick();
}

// Drawing is batched to one frame, so a burst of wheel steps or ticks costs one redraw
let drawQueued = false, zoomRest = 0;
function scheduleDraw() {
  if (drawQueued) return;
  drawQueued = true;
  requestAnimationFrame(() => { drawQueued = false; if (T.view === 'transport') mapDraw(); });
}

let pan = null, ptr = null;
svg.addEventListener('pointerdown', (e) => {
  ptr = [e.clientX, e.clientY];
  if (e.target.closest('.tw-vh') || !T.vb) return;
  pan = [e.clientX, e.clientY, ...T.vb];
  if (D.follow) setFollow(false); // the presenter took the camera
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
  // Following keeps the van in the middle while zooming; otherwise the zoom goes towards the pointer
  const [cx, cy] = D.follow && D.s ? prj(D.s.now.lat, D.s.now.lng) : [px, py];
  T.vb = D.follow && D.s ? [cx - (w * f) / 2, cy - (h * f) / 2, w * f, h * f] : [px - (px - x) * f, py - (py - y) * f, w * f, h * f];
  svg.setAttribute('viewBox', T.vb.join(' '));
  // Markers are rebuilt once the wheel rests, not on every step, so zooming stays smooth with many vehicles
  clearTimeout(zoomRest);
  zoomRest = setTimeout(scheduleDraw, 120);
}, { passive: false });
svg.addEventListener('click', (e) => {
  const g = e.target.closest('.tw-vh');
  if (g) { g.dataset.veh ? vehSelect(+g.dataset.veh) : shipSelect(+g.dataset.ship); return; }
  // In pick mode a click (not a drag) on the map sets the destination of the demo trip
  if (!SIM.pick || !T.vb || (ptr && Math.hypot(e.clientX - ptr[0], e.clientY - ptr[1]) > 5)) return;
  const b = svg.getBoundingClientRect(), [x0, y0, w, h] = T.vb;
  const x = x0 + ((e.clientX - b.left) / b.width) * w, y = y0 + ((e.clientY - b.top) / b.height) * h;
  tourTo(72 - y / KM, x / (cosL * KM) + 4);
});
svg.addEventListener('keydown', (e) => {
  const g = e.target.closest('.tw-vh');
  if (g && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); g.dataset.veh ? vehSelect(+g.dataset.veh) : shipSelect(+g.dataset.ship); }
});
document.querySelectorAll('[data-fit]').forEach((b) => b.addEventListener('click', () => { if (T.land) { setFollow(false); FIT[b.dataset.fit](); mapDraw(); } }));

async function transport() {
  try {
    const r = await fetch('/api/tarn/transport');
    if (!r.ok) throw new Error(String(r.status));
    const data = await r.json();
    T.rows = data.rows;
    SIM.vehicles = data.vehicles ?? [];
    T.cond = data.conditions ?? null;
    T.ferries = data.ferries ?? [];
    await landLoad();
    status(true);
  } catch {
    status(false);
    return;
  }
  if (!T.vb) FIT.all();
  document.querySelector('.tw-demo-big').hidden = !T.rows.some((r) => +r.is_demo);
  mapDraw();
  if (T.sel === null) inspector();
}

// Search filters the list by reference, fleet number or city; the map keeps every marker
let shipQ = '';
function shipList() {
  if (!T.rows.length) return `<p class="tw-muted">${esc(TR.none)}</p>`;
  return `<h3>${esc(TR.shipments)} ${T.rows.length}</h3><input type="search" id="tw-ship-q" class="tw-ship-q" value="${esc(shipQ)}" placeholder="${esc(TR.find)}" aria-label="${esc(TR.find)}" autocomplete="off">
    <div id="tw-ship-rows">${shipRows()}</div>`;
}
function shipRows() {
  const q = shipQ.trim().toLowerCase();
  const rows = T.rows.filter((r) => !q || [r.ref, r.fleet_no, r.origin_city, r.dest_city].some((v) => String(v ?? '').toLowerCase().includes(q)));
  if (!rows.length) return `<p class="tw-muted">${esc(TR.noHit)}</p>`;
  // One card per shipment, as on a tracking list: route and status, the vehicle riding its progress line, then the ETA
  return rows.map((r) => {
    const pct = r.progress === null ? null : Math.round(r.progress * 100);
    return `<article class="tw-card ${r.id === T.sel ? 'on' : ''}">
      <header><button type="button" data-ship="${r.id}">${esc(r.origin_city)} ${L.arrow} ${esc(r.dest_city)}</button>
        <span class="tw-tag ${r.status === 'forsinket' ? 'avvik' : ''}">${esc(TR.status[r.status] ?? r.status)}</span></header>
      <p class="tw-muted">${esc(TR.types[r.vehicle_type] ?? r.vehicle_type ?? '')} ${esc(r.fleet_no ?? '')} · ${esc(r.ref)}${+r.is_demo ? ` <span class="tw-demo">${esc(L.demo)}</span>` : ''}</p>
      <div class="tw-track" role="img" aria-label="${pct === null ? esc(TR.noPos) : `${esc(TR.progressEst)} ${pct} %`}">
        <span class="ln"></span>${pct === null ? '' : `<span class="dn" style="width:${pct}%"></span>`}
        <span class="tw-vpic" style="left:${pct ?? 0}%"><img src="${vehSrc(r.vehicle_type)}" alt="" width="40" height="26"></span></div>
      <p class="tw-card-ft"><span>${pct === null ? esc(TR.noPos) : `${pct} %`}${r.stale ? ` · <span class="tw-err">${esc(TR.stale)}</span>` : ''}</span>
        ${r.eta ? `<span>${esc(TR.eta)} ${esc(tFmt.format(new Date(r.eta * 1000)))}</span>` : r.actual_arrival ? `<span>${esc(TR.actualArr)} ${esc(tFmt.format(new Date(r.actual_arrival * 1000)))}</span>` : ''}</p></article>`;
  }).join('');
}
$('tw-inspect').addEventListener('input', (e) => {
  if (e.target.id !== 'tw-ship-q') return;
  shipQ = e.target.value;
  $('tw-ship-rows').innerHTML = shipRows();
});

async function shipSelect(id) {
  T.sel = id;
  $('tw-inspect').dataset.product = '';
  const my = ++shown;
  try {
    const r = await fetch(`/api/tarn/sending/${id}`);
    if (!r.ok) throw new Error(r.status === 403 ? L.noaccess : String(r.status));
    const d = await r.json();
    if (my !== shown) return;
    T.detail = d;
  } catch (e) {
    if (my !== shown) return;
    $('tw-inspect').innerHTML = `<p class="tw-err">${esc(e.message)}</p>`;
    return;
  }
  mapDraw(); // the camera stays where the presenter left it; Rute or Følg kjøretøy move it on request
  $('tw-inspect').innerHTML = shipView(T.detail);
}

function shipView(d) {
  // A field without a value is left out, so the panel only lists what is known
  const tf = (s) => (s ? esc(dFmt.format(new Date(s * 1000))) : null);
  const kg = (n) => (n === null || n === undefined ? null : `${new Intl.NumberFormat(locale).format(+n)} kg`);
  const last = d.positions[d.positions.length - 1];
  // Age, staleness, variance, progress and remaining payload are derived on the server (ShipmentRules)
  const age = d.age_min, stale = d.stale, variance = d.variance, pct = d.progress, estimated = d.estimated;
  const v = d.vehicle, dr = d.driver, w = d.weight;
  const strip = pct === null ? '' : `<div class="tw-prog" role="img" aria-label="${esc(TR.progress)} ${Math.round(pct * 100)} %"><span class="ln"></span>
    <span class="done" style="width:${pct * 100}%"></span><span class="end" style="left:0"></span><span class="end" style="right:0"></span>
    <span class="box" style="left:${Math.min(88, Math.max(12, pct * 100))}%">${esc(v?.fleet_no ?? d.ref)}</span></div>
    <p class="tw-muted">${esc(TR.progress)} ${Math.round(pct * 100)} %${estimated ? ', ' + esc(TR.estimate) : ''}</p>`;
  // Speed and direction from the last two stored positions; nothing is shown when there is only one
  const prev = d.positions[d.positions.length - 2];
  const kmh = last && prev && last.observed_at > prev.observed_at ? Math.round(kmBetween(+prev.lat, +prev.lng, +last.lat, +last.lng) / ((last.observed_at - prev.observed_at) / 3600)) : null;
  const gps = last ? [last.source.startsWith('demo') ? `GPS ${TR.demoTag}` : 'GPS', kmh !== null && kmh < 200 ? `${kmh} km/t` : null,
    last.heading !== null ? `${compass(+last.heading)} ${+last.heading}°` : null].filter(Boolean).join(', ') : '';
  const fresh = !last ? `<span class="tw-muted">${esc(TR.noPos)}</span>` : stale ? `<span class="tw-err">${esc(TR.stale)}, ${age} ${TR.min}</span>` : `${esc(TR.fresh)}, ${age} ${TR.min}`;
  // Cargo as a photo grid: every item in the load with picture, quantity and weight; a click opens the product
  const items = d.items.map((i) => `<li><button type="button" data-product="${+i.product_id}">
    ${i.img ? `<img src="/${esc(i.img)}" alt="" loading="lazy">` : '<span class="tw-noimg"></span>'}
    <b>${esc(i.name)}</b><span><output>${+i.qty}</output> ${esc(TR.qty)}${i.weight_kg === null ? '' : `, ${kg(i.weight_kg)}`}</span></button></li>`).join('');
  const dist = w.items !== null && w.items > 0 ? `<div class="tw-bar-w" role="img" aria-label="${esc(TR.weight)}">${d.items.map((i) => `<i style="width:${(+i.weight_kg / w.items) * 100}%" title="${esc(i.name)}"></i>`).join('')}</div>` : '';
  const remaining = w.remaining;
  const hist = d.positions.slice().reverse().map((p) => `<tr><th>${esc(tFmt.format(new Date(p.observed_at * 1000)))}</th><td>${(+p.lat).toFixed(3)}, ${(+p.lng).toFixed(3)} <span class="tw-muted">${esc(p.source)}</span></td></tr>`).join('');
  const dl = (pairs) => `<dl class="tw-dl">${pairs.filter((p) => p && p[1] !== null && p[1] !== undefined && p[1] !== '').map(([k, val]) => `<dt>${esc(k)}</dt><dd>${val}</dd>`).join('')}</dl>`;
  return `${+d.is_demo ? `<p><span class="tw-demo-big">${esc(TR.demo)}</span></p>` : ''}
    <div class="tw-hdr">${esc(d.origin_city)} ${L.arrow} ${esc(d.dest_city)}</div>
    <p class="tw-muted">${esc(d.ref)}${d.origin_point ? ', ' + esc(d.origin_point) : ''}${d.dest_point ? ', ' + esc(d.dest_point) : ''}</p>
    ${v ? `<figure class="tw-op"><span class="tw-vpic big"><img src="${vehSrc(v.type)}" alt="${esc(TR.types[v.type] ?? v.type)}"></span>
      <figcaption><b>${esc(v.fleet_no)}</b> ${esc(TR.types[v.type] ?? v.type)}<br><span class="tw-muted">${esc([v.make, v.model].filter(Boolean).join(' '))}</span></figcaption></figure>
      <div class="tw-op-cells">${d.container_type ? `<div><span>${esc(TR.container)}</span><b>${esc(d.container_type)}</b></div>` : ''}
      ${remaining === null || remaining === undefined || v.payload_kg === null ? '' : `<div><span>${esc(TR.available)}</span><b>${kg(remaining)} / ${kg(v.payload_kg)}</b></div>`}</div>` : ''}
    ${strip}
    <p><span class="tw-tag ${d.status === 'forsinket' ? 'avvik' : ''}">${esc(TR.status[d.status] ?? d.status)}</span> ${fresh}</p>
    ${gps ? `<p class="tw-gps">${icon.locate}${esc(gps)}</p>` : ''}
    ${d.actual_arrival ? '' : ferryList(+d.id) + condList(+d.id)}
    <h3>${esc(TR.timeline)} (${esc(TR.tz)})</h3>
    ${dl([[TR.plannedDep, tf(d.planned_departure)], [TR.actualDep, tf(d.actual_departure)], [TR.lastPos, last ? tf(last.observed_at) : null],
      d.eta && [TR.eta, `${tf(d.eta)}<br><span class="tw-muted">${[d.eta_source && `${esc(TR.source)}: ${esc(d.eta_source)}`, d.eta_calculated_at && `${esc(TR.calculated)} ${tf(d.eta_calculated_at)}`].filter(Boolean).join(', ')}</span>`],
      [TR.plannedArr, tf(d.planned_arrival)], [TR.actualArr, tf(d.actual_arrival)],
      variance !== null && [TR.variance, `${variance > 0 ? '+' : ''}${Math.round(variance / 60)} ${TR.min}`],
      d.delay_reason && [TR.delay, esc(d.delay_reason)]])}
    ${v ? `<h3>${esc(TR.vehicle)}</h3>` : ''}
    ${v ? dl([[TR.vehicle, `${esc(TR.types[v.type] ?? v.type)} ${esc(v.fleet_no)}`], [L.type, esc([v.make, v.model, v.model_year].filter(Boolean).join(' '))],
      v.registration && [TR.reg, esc(v.registration)], [TR.payload, kg(v.payload_kg)], [TR.capacity, kg(v.capacity_kg)]]) : ''}
    ${dr ? `<h3>${esc(TR.driver)}</h3>${dl([[TR.driver, `${esc(dr.alias)} <span class="tw-muted">${esc(dr.driver_ref)}</span>`],
      dr.phone && [TR.phone, `<a href="tel:${esc(dr.phone)}">${esc(dr.phone)}</a>`]])}` : ''}
    <h3>${esc(TR.cargo)}</h3>
    ${dl([[TR.container, d.container_ref ? `${esc(d.container_ref)} ${esc(d.container_type ?? '')}` : null]])}
    <ul class="tw-cargo">${items}</ul>
    ${dist}
    ${dl([[TR.declared, kg(w.declared)], [TR.measured, kg(w.measured)], [TR.itemsSum, kg(w.items)], [TR.payload, kg(w.payload)],
      [TR.remaining, remaining === null ? null : `${kg(remaining)} <span class="tw-muted">${esc(TR.calculated)}</span>`]])}
    ${w.mismatch ? `<p class="tw-err">${esc(TR.mismatch)}</p>` : ''}
    <h3>${esc(TR.history)}</h3><table class="tw-t">${hist || `<tr><td class="tw-muted">${esc(TR.noPos)}</td></tr>`}</table>`;
}

let viewed = false; // the first setView is the page opening, which has its own entrance
// A drill-down asks for a support case: Kundeservice opens with it
document.addEventListener('tw:ticket', (e) => setView('kundeservice', e.detail));
function setView(v, ticketId = null) {
  const from = T.view, again = viewed && from !== v;
  viewed = true;
  T.view = v;
  const map = v === 'transport', page = v === 'kundeservice' || v === 'innsikt' || v === 'oversikt';
  document.querySelector(`input[name="view"][value="${v}"]`).checked = true;
  document.querySelector('.tw-grid').hidden = page;
  $('tw-ov').hidden = v !== 'oversikt';
  $('tw-cs').hidden = v !== 'kundeservice';
  $('tw-ins').hidden = v !== 'innsikt';
  dock(v === 'oversikt');
  if (v === 'oversikt') { showOverview((to, id) => setView(to, id), greet); if (again) sweepView($('tw-ov'), false, reduce); return; }
  hideOverview();
  if (v === 'kundeservice') { showDesk(ticketId); if (again) sweepView($('tw-cs'), false, reduce); return; }
  if (v === 'innsikt') { showInsight(); if (again) sweepView($('tw-ins'), false, reduce); return; }
  if (map && again) sweepView($('tw-map'), true, reduce);
  $('tw-map').hidden = !map;
  host.hidden = map;
  $('tw-labels').hidden = map;
  $('tw-table').hidden = map || !!gl;
  // Transport docks the inspector as the shipment list on the right; Gulv keeps its operations panel there
  document.querySelector('.tw-grid').classList.toggle('v-map', map);
  $('tw-ops').hidden = map;
  if (map) { $('tw-idle').hidden = true; $('tw-inspect').hidden = false; T.sel = null; T.detail = null; transport().then(simStart); }
  else {
    resize(); header(); inspect(); // the floor was hidden while another page was open
    // First visit after the page loads: the full synchronisation; later visits: a short resync scan
    if (!sync.built && !syncing) { syncing = true; sync.full(firstLoad, () => intro()); } else if (again) sync.short();
  }
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
  const box = $('tw-inspect'), TK = L.tk, my = ++shown;
  let t;
  try {
    const r = await fetch(`/api/tarn/sak/${id}`);
    if (!r.ok) throw new Error(r.status === 403 ? L.noaccess : String(r.status));
    t = await r.json();
    if (my !== shown) return;
  } catch (e) {
    if (my !== shown) return;
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
  pointers(gl.domElement);
  new ResizeObserver(resize).observe(host);
} else {
  $('tw-table').hidden = false;
}

// Ticket and revision changes come from the existing SSE stream (channels support and staff); the panel reloads its lists
let sseTimer = 0;
const staffStream = new EventSource('/api/stream?ch=support,staff');
staffStream.addEventListener('message', () => {
  clearTimeout(sseTimer);
  sseTimer = setTimeout(async () => {
    if (R.active || document.hidden) return;
    await load();
    const pid = $('tw-inspect').dataset.product;
    if (pid && T.view === 'gulv') product(+pid);
  }, 400);
});

// The floor is live from the first read: products and shipments rest where their state puts them, nothing is replayed
function intro() {}
document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

// The floor builds itself the first time it is shown (tower-sync.js); parcels and counts come from this first request
const sync = createSync({ THREE, gl, scene, camera, structure: STRUCTURE, parcels: [fill, frame, solid, ring], hemi, sun, grid, slats,
  floor: floorEl, panel: $('tw-sync'), L: L.sync, reduce, cam: { CAM, VIEW0, placeCam, frameView }, render });
let syncing = false;
const firstLoad = load();
setView('oversikt'); // the overview is the first page and needs nothing from the floor; the floor loads behind it
await firstLoad;
if (T.view === 'gulv') resize();
throughput();
setInterval(poll, POLL_MS);
setInterval(() => { throughput(); }, FULL_RELOAD_MS);

// ---------- Guided tour of the whole panel (public/assets/tour.js) ----------
// Each step switches to its view first, so the tour can be run straight through. The buyer edit step opens the first
// product with an open contribution from a verified buyer.
const view = (v) => () => { const r = document.querySelector(`input[name=view][value=${v}]`); if (r && !r.checked) r.click(); };
const TS = L.tour.steps;
const step = (i, sel, before) => ({ ...TS[i], sel, before });
const towerTour = tour('kontrolltarn', [
  step(0, '.tw-nav', view('oversikt')),
  step(3, '#tw-ov .ov-grid', view('oversikt')),
  step(1, '.tw-floor', view('gulv')),
  step(2, '#tw-inspect', () => {
    view('gulv')();
    const p = S.incoming.find((x) => x.source === 'bidrag');
    if (p) { if (S.items.some((it) => it.key === `p:${p.id}`)) select(`p:${p.id}`); else product(p.id); }
  }),
  step(4, '#tw-ops', view('gulv')),
  step(5, '#tw-sim', view('transport')),
  step(6, '#tw-svg', view('transport')),
  step(7, '#tw-svg', view('transport')),
  step(8, '#tw-inspect', view('transport')),
  step(9, '#cs-list', view('kundeservice')),
  step(10, '.cs-thread', view('kundeservice')),
  step(11, '.cs-compose', view('kundeservice')),
  step(12, '#cs-right', view('kundeservice')),
  step(13, '[data-close]', view('kundeservice')),
  step(14, '#cs-search', view('kundeservice')),
  step(15, '#tw-ins', view('innsikt')),
  step(16, '#tw-soren-btn', view('oversikt')),
], L.tour.ui);
$('tw-tour-btn').addEventListener('click', () => towerTour.start());
