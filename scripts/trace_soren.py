"""
Sporer maskoten (public/assets/mascot.png) til SVG, så Søren ser ut akkurat som tegningen, men kan animeres.

Bildet deles i fargelag (k-means) og i deler som skal kunne bevege seg hver for seg: krone (hvert blad
eller bladklump), løse blader, kropp, øyne, vinkende arm, hengende arm, støvler og skygge. Hvert fargelag
i hver del spores med potrace og lagres som paths med OKLCH-farger i public/assets/soren-art.js.

Krever: pip install pillow numpy opencv-python-headless potracer
Kjør:   python scripts/trace_soren.py
"""
import json, math, os, sys
import numpy as np, cv2, potrace
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'public/assets/mascot.png')
OUT = os.path.join(ROOT, 'public/assets/soren-art.js')
X0, Y0, X1, Y1 = 395, 8, 632, 334     # utsnitt rundt Søren i originalbildet
S = 4                                  # sporer i 4x oppløsning for glatte kanter
K = 12                                 # antall fargelag

img = np.array(Image.open(SRC).convert('RGB'))[Y0:Y1, X0:X1].astype(np.int32)
H, W = img.shape[:2]

# --- Figuren: alt som ikke er bakgrunn sett utenfra (også hvit kropp innenfor omrisset)
near_white = (np.abs(img - 255).sum(-1) < 36).astype(np.uint8)
flood = near_white.copy()
ff = np.zeros((H + 2, W + 2), np.uint8)
cv2.floodFill(flood, ff, (0, 0), 2)
figure = flood != 2
figure = cv2.morphologyEx(figure.astype(np.uint8), cv2.MORPH_CLOSE, np.ones((3, 3), np.uint8)).astype(bool)

# Hvite hull mellom bladene er bakgrunn, ikke kropp: de er omgitt av røde blader, mens kroppen har grått omriss
_r, _g = img[..., 0], img[..., 1]
_red = (_r - _g > 15)  # også rosa kantpiksler: hull mellom blader har rosa kant, kroppen har grå
_white = near_white.astype(bool) & figure & ~_red
n, cc = cv2.connectedComponents(_white.astype(np.uint8))
for i in range(1, n):
    comp = cc == i
    ring = cv2.dilate(comp.astype(np.uint8), np.ones((3, 3), np.uint8)).astype(bool) & ~comp
    grey = (ring & figure & ~_red & ~near_white.astype(bool)).sum()
    red = (ring & _red).sum()
    if grey < 0.3 * max(1, grey + red):
        figure[comp] = False

# --- Fargelag
pix = img[figure].astype(np.float32)
crit = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 60, 0.5)
_, lab, centers = cv2.kmeans(pix, K, None, crit, 6, cv2.KMEANS_PP_CENTERS)
labels = np.full((H, W), -1, np.int32)
labels[figure] = lab.ravel()
centers = centers.astype(int)
lum = [0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2] for c in centers]
reddish = [c[0] - c[1] > 45 for c in centers]

def lin(c):
    c /= 255
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4

def oklch(rgb):
    r, g, b = (lin(float(v)) for v in rgb)
    l = 0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b
    m = 0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b
    s = 0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b
    l, m, s = (math.copysign(abs(v) ** (1 / 3), v) for v in (l, m, s))
    L = 0.2104542553 * l + 0.7936177850 * m - 0.0040720468 * s
    A = 1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s
    B = 0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s
    C = math.hypot(A, B)
    h = math.degrees(math.atan2(B, A)) % 360
    return f'oklch({L * 100:.1f}% {C:.3f} {h:.0f})' if C >= 0.003 else f'oklch({L * 100:.1f}% 0 0)'

colors = [oklch(c) for c in centers]

# --- Deler (koordinater i originalbildet)
def poly(points):
    m = np.zeros((H, W), np.uint8)
    cv2.fillPoly(m, [np.array([(x - X0, y - Y0) for x, y in points], np.int32)], 1)
    return m.astype(bool)

yy, xx = np.mgrid[0:H, 0:W]
Y = yy + Y0
X = xx + X0
red_px = figure & ((img[..., 0] - img[..., 1]) > 95)
# Rosa kantpiksler (blad blandet med hvit bakgrunn) er verken blad eller kropp: de blir gjennomsiktige
figure_full = figure.copy()  # med kantpikslene, til utklippet
figure &= ~(((img[..., 0] - img[..., 1]) > 15) & ~red_px)

# Øyne: mørke flekker midt i ansiktet
dark = figure & (img.sum(-1) < 260) & (Y > 130) & (Y < 180)
n, cc, stats, cent = cv2.connectedComponentsWithStats(dark.astype(np.uint8))
eyes_boxes = sorted([stats[i] for i in range(1, n) if stats[i][4] > 40], key=lambda s: s[0])[:2]
eyes = np.zeros((H, W), bool)
eye_info = []
for x, y, w, h, _ in eyes_boxes:
    eyes[max(0, y - 2):y + h + 2, max(0, x - 2):x + w + 2] = True
    eye_info.append({'cx': X0 + x + w / 2, 'cy': Y0 + y + h / 2, 'rx': w / 2, 'ry': h / 2})
eyes &= figure

boots = red_px & (Y >= 286)
shadow = figure & ~red_px & (Y >= 313) & ~boots
arm_l = poly([(416, 178), (468, 186), (484, 214), (482, 247), (446, 250), (414, 236)]) & figure & ~red_px
arm_r = poly([(558, 226), (594, 222), (598, 298), (560, 298), (560, 262)]) & figure & ~red_px & (Y < 313)

# Løse blader: røde klumper under kronen som ikke er støvler
n, cc, stats, cent = cv2.connectedComponentsWithStats(red_px.astype(np.uint8))
loose = np.zeros((H, W), bool)
crown_red = np.zeros((H, W), bool)
for i in range(1, n):
    comp = cc == i
    if (Y[comp] >= 286).all():
        continue
    if cent[i][1] + Y0 > 200:
        loose |= comp
    else:
        crown_red |= comp
crown_red &= ~boots
def with_holes(m):
    filled = m.astype(np.uint8).copy()
    cnts, _ = cv2.findContours(filled, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_NONE)
    cv2.drawContours(filled, cnts, -1, 1, cv2.FILLED)
    return filled.astype(bool)
# Årene i bladene er lyse rosa streker. Der de når kanten av bladet blir de ikke hull, så de lukkes her:
# bare rosa piksler (ikke hvit bakgrunn mellom bladene) tas med. Årene tegnes oppå i sin egen farge.
_pink = (img[..., 0] - img[..., 1]) > 15
def leaf_fill(m):
    """Hvert blad lukkes for seg, så nabobladene ikke vokser sammen. Gir maske og nummer per blad."""
    n, cc = cv2.connectedComponents(m.astype(np.uint8))
    lab = np.zeros((H, W), np.int32)
    for i in range(1, n):
        comp = cc == i
        closed = cv2.morphologyEx(comp.astype(np.uint8), cv2.MORPH_CLOSE, np.ones((5, 5), np.uint8)).astype(bool)
        f = with_holes(comp | (closed & _pink)) & (lab == 0)
        lab[f] = i
    return lab > 0, lab
crown_red, crown_lab = leaf_fill(crown_red)
loose, loose_lab = leaf_fill(loose)
boots = leaf_fill(boots)[0]

# Under øynene: fyll med kroppens farge rundt, så det ikke blir et hull når øynene blunker eller blir glade
ring = cv2.dilate(eyes.astype(np.uint8), np.ones((7, 7), np.uint8)).astype(bool) & ~eyes & figure
ring_labels = labels[ring]
fill_label = int(np.bincount(ring_labels[ring_labels >= 0]).argmax())
labels_eye_filled = labels.copy()
labels_eye_filled[cv2.dilate(eyes.astype(np.uint8), np.ones((9, 9), np.uint8)).astype(bool) & ~red_px] = fill_label  # øynene tegnes som ellipser oppå
for k in range(K):
    if reddish[k]:
        labels_eye_filled[(labels_eye_filled == k) & ~red_px] = fill_label
body = figure & ~boots & ~shadow & ~crown_red & ~loose
body = with_holes(body)  # ingen hull inne i kroppen (bakgrunnen skinte gjennom over øynene)
# Kroppen går litt inn under bladene, så det ikke blir sprekker mellom kropp og krone
body |= cv2.dilate(body.astype(np.uint8), np.ones((7, 7), np.uint8)).astype(bool) & (crown_red | loose)
# Armene overlapper kroppen litt ved skulderen, så det ikke blir sprekker når de beveger seg
body_wo_arms = body & ~(arm_l & (X < 470)) & ~(arm_r & (X > 563))

# --- Sporing
def trace(mask, dilate=1):
    if not mask.any():
        return ''
    m = cv2.resize(mask.astype(np.uint8) * 255, (W * S, H * S), interpolation=cv2.INTER_NEAREST)
    m = cv2.GaussianBlur(m, (0, 0), S * 0.55)
    m = m > 127
    if dilate:
        m = cv2.dilate(m.astype(np.uint8), np.ones((dilate * 2 + 1, dilate * 2 + 1), np.uint8)).astype(bool)
    plist = potrace.Bitmap(~m).trace(turdsize=6 * S, alphamax=1.0, opticurve=True, opttolerance=0.25)
    f = lambda p: f'{p.x / S + X0:.1f},{p.y / S + Y0:.1f}'
    out = []
    for curve in plist:
        d = 'M' + f(curve.start_point)
        for seg in curve.segments:
            d += ('L' + f(seg.c) + 'L' + f(seg.end_point)) if seg.is_corner else ('C' + f(seg.c1) + ' ' + f(seg.c2) + ' ' + f(seg.end_point))
        out.append(d + 'Z')
    return ''.join(out)

def layers(mask, base=True, only_red=False, lab=None):
    """Hvit grunnflate for hele delen, så hvert fargelag fra lyst til mørkt oppå."""
    parts = []
    if only_red:
        # Blader: én rød grunnflate (uten lyse kantpiksler), så de mørkere røde nyansene oppå
        reds = [k for k in range(K) if reddish[k]]
        basek = max(reds, key=lambda k: int(np.sum((labels == k) & mask)))
        parts.append(f'<path d="{trace(mask, 0)}" fill="{colors[basek]}"/>')
        for k in sorted(reds, key=lambda k: -lum[k]):
            if k != basek and lum[k] < lum[basek]:
                d = trace(mask & red_px & (labels == k), 0)
                if d:
                    parts.append(f'<path d="{d}" fill="{colors[k]}" fill-rule="evenodd"/>')
        # Lyse årer i bladene
        inner = cv2.erode(mask.astype(np.uint8), np.ones((5, 5), np.uint8)).astype(bool)
        vein = inner & ~red_px
        if vein.sum() >= 4:
            parts.append(f'<path d="{trace(vein, 0)}" fill="{oklch(img[vein].mean(0))}"/>')
        return ''.join(parts)
    if base:
        parts.append(f'<path d="{trace(mask, 1)}" fill="{colors[int(np.argmax(lum))]}"/>')
    for k in sorted(range(K), key=lambda k: -lum[k]):
        if base and k == int(np.argmax(lum)):
            continue
        d = trace(mask & ((labels if lab is None else lab) == k), 1)
        if d:
            parts.append(f'<path d="{d}" fill="{colors[k]}" fill-rule="evenodd"/>')
    return ''.join(parts)

def leaf_groups(mask, min_px=8, lab=None):
    """Hver bladklump for seg, så de kan blafre og blåse av."""
    n, cc, stats, cent = cv2.connectedComponentsWithStats(mask.astype(np.uint8))
    if lab is not None:  # bladene slik de var før årene ble lukket
        n = int(lab.max()) + 1
        cc = lab
        stats = np.zeros((n, 5), int)
        cent = np.zeros((n, 2))
        for i in range(1, n):
            ys, xs = np.nonzero(lab == i)
            if len(xs):
                stats[i] = [xs.min(), ys.min(), xs.max() - xs.min() + 1, ys.max() - ys.min() + 1, len(xs)]
                cent[i] = [xs.mean(), ys.mean()]
    out = []
    for i in range(1, n):
        if stats[i][4] < min_px:
            continue
        comp = cc == i
        cx, cy = cent[i][0] + X0, cent[i][1] + Y0
        x, y, w, h = (int(v) for v in stats[i][:4])
        out.append({'cx': round(cx, 1), 'cy': round(cy, 1), 'px': int(stats[i][4]), 'bbox': [x + X0, y + Y0, w, h], 'svg': layers(comp, only_red=True)})
        print(f'  blad {len(out)} ({stats[i][4]} px)', file=sys.stderr)
    return out

print('kropp', file=sys.stderr)
art = {
    'box': [X0, Y0, W, H],
    'eyes': eye_info,
    'body': layers(body_wo_arms, lab=labels_eye_filled),
    'armL': layers(arm_l, lab=labels_eye_filled),
    'armR': layers(arm_r, lab=labels_eye_filled),
    'boots': layers(boots, only_red=True),
    'shadow': layers(shadow, base=False),
    'eyesSvg': layers(eyes & dark | (eyes & (img.sum(-1) > 600) & ~near_white.astype(bool)), base=False),
    'loose': leaf_groups(loose, lab=loose_lab),
    'leaves': leaf_groups(crown_red, lab=crown_lab),
    'skin': colors[int(np.argmax(lum))],
    'edge': colors[sorted(range(K), key=lambda k: -lum[k])[2]],
}
# Øyne: mørkt og glans for seg
eye_dark = eyes & (img.sum(-1) < 330)
eye_glint = eyes & (img.sum(-1) > 690) & ~eye_dark
# Øynene er ovaler i tegningen: tilpass en ellipse til hver mørke pupill, og en sirkel til glansen
eye_parts = []
n, cc, stats, cent = cv2.connectedComponentsWithStats(eye_dark.astype(np.uint8))
for i in sorted(range(1, n), key=lambda i: -stats[i][4])[:2]:
    comp = (cc == i)
    filled = with_holes(comp)  # glansen er et hull i pupillen
    cnts, _ = cv2.findContours(filled.astype(np.uint8), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_NONE)
    (ex, ey), (ew, eh), ang = cv2.fitEllipse(max(cnts, key=cv2.contourArea))
    # Pilleform som i tegningen: rett side, runde ender. Bredde og høyde fra pupillen selv.
    ys, xs = np.nonzero(filled)
    pw, ph = xs.max() - xs.min() + 1, ys.max() - ys.min() + 1
    eye_parts.append(f'<rect x="{xs.min() + X0:.1f}" y="{ys.min() + Y0:.1f}" width="{pw:.1f}" height="{ph:.1f}" rx="{pw / 2:.1f}" fill="{oklch(img[comp].mean(0))}"/>')
    oval = np.zeros((H, W), np.uint8)
    cv2.ellipse(oval, ((ex, ey), (ew, eh), ang), 1, -1)
    oval = cv2.erode(oval, np.ones((5, 5), np.uint8))  # ikke kanten av ovalen
    glint = oval.astype(bool) & ~comp & (img.sum(-1) > 450)  # lyse piksler inne i ovalen
    if glint.sum() >= 2:
        gy, gx = np.nonzero(glint)
        eye_parts.append(f'<circle cx="{gx.mean() + X0 + .5:.1f}" cy="{gy.mean() + Y0 + .5:.1f}" r="{max(1.2, math.sqrt(glint.sum() / math.pi)):.1f}" fill="oklch(100% 0 0)"/>')
art['eyesSvg'] = ''.join(eye_parts)
art['eyeColor'] = oklch(img[eye_dark].mean(0))
# Ett blad til ikonet: det øverste bladet
# ---------- Utklipp: delene er bitene av selve tegningen, ikke sporede flater ----------
# Hver piksel får fargen fra tegningen. Kantpiksler (blandet med hvit bakgrunn) får nøyaktig den
# gjennomsiktigheten de har mot hvitt, regnet ut fra fargen innenfor kanten. Da blir det ingen hvit kant i mørk modus.
import base64, io
F = img.astype(np.float32)
core = cv2.erode(figure_full.astype(np.uint8), np.ones((3, 3), np.uint8)).astype(bool)
zone = cv2.dilate(figure_full.astype(np.uint8), np.ones((3, 3), np.uint8)).astype(bool)
edge = zone & ~core
inner_col = cv2.inpaint(img.astype(np.uint8), edge.astype(np.uint8), 4, cv2.INPAINT_TELEA).astype(np.float32)
d_px = 255 - F
d_in = 255 - inner_col
den = (d_in ** 2).sum(-1)
a_edge = np.where(den > 300, (d_px * d_in).sum(-1) / np.maximum(den, 1), 1.0).clip(0, 1)
alpha = np.where(core, 1.0, np.where(zone, a_edge, 0.0))
fg = np.where(edge[..., None] & (den[..., None] > 300), inner_col, F)
# Gjennomsiktigheten kommer fra verktøyene (Segment Anything + BiRefNet), se scripts/cutout_soren.py.
# Kantpiksler har hvit bakgrunn blandet inn. Den fjernes med vanlig formel, siden bakgrunnen er kjent hvit:
# farge = (piksel - (1 - alfa) * hvit) / alfa
tool_a = np.array(Image.open(os.path.join(ROOT, 'public/assets/mascot-alpha.png')))[Y0:Y1, X0:X1].astype(np.float32) / 255
alpha = tool_a
fg = np.where(tool_a[..., None] > 0.05, (F - (1 - tool_a[..., None]) * 255) / np.maximum(tool_a[..., None], 0.05), F).clip(0, 255)
# Svake kantpiksler gir støy i formelen (deler på liten alfa): de får fargen til nærmeste hele piksel (OpenCV inpaint)
_faint = (tool_a < 0.6).astype(np.uint8)
_solid = cv2.inpaint(fg.astype(np.uint8), _faint, 3, cv2.INPAINT_TELEA).astype(np.float32)
fg = np.where(_faint[..., None] > 0, _solid, fg)
zone = tool_a > 0.02
# Skyggen: lys grå på hvitt blir svart med lav dekning, så den ser ut som skygge også på mørk bakgrunn
sh = shadow | (cv2.dilate(shadow.astype(np.uint8), np.ones((5, 5), np.uint8)).astype(bool) & zone & (Y >= 313) & ~boots)
alpha = np.where(sh, ((255 - F.min(-1)) / 255).clip(0, 1), alpha)
fg[sh] = 0

# Hver piksel i figuren hører til én del. Kantpiksler får delen til nærmeste del-piksel.
part = np.zeros((H, W), np.int32)
masks = {1: shadow, 2: boots, 3: body_wo_arms, 4: arm_l & ~boots, 5: arm_r & ~boots}
for k, m in masks.items():
    part[m & (part == 0)] = k
LEAF0, LOOSE0 = 100, 1000
part[(crown_lab > 0) & (part != 2)] = crown_lab[(crown_lab > 0) & (part != 2)] + LEAF0
part[(loose_lab > 0) & (part != 2)] = loose_lab[(loose_lab > 0) & (part != 2)] + LOOSE0
for _ in range(4):
    grown = cv2.dilate(part.astype(np.float32), np.ones((3, 3), np.uint8)).astype(np.int32)
    part = np.where((part == 0) & zone, grown, part)
part[sh & (part == 0)] = 1

skin_rgb = np.median(F[body & core & (F.min(-1) > 235)], axis=0)
eye_hole = cv2.dilate(eyes.astype(np.uint8), np.ones((11, 11), np.uint8)).astype(bool) & ~red_px  # med kantpikslene rundt øyet

def png(rgba, x0, y0, w, h):
    im = Image.fromarray(rgba.round().astype(np.uint8), 'RGBA')  # originaloppløsning: nettleseren skalerer jevnt, uten skjøter
    b = io.BytesIO()
    im.save(b, 'PNG', optimize=True)
    return (f'<image href="data:image/png;base64,{base64.b64encode(b.getvalue()).decode()}" x="{x0 + X0}" y="{y0 + Y0}" '
            f'width="{w}" height="{h}" preserveAspectRatio="none"/>')

def cut(sel, under=None, inpaint=None, keep=None):
    """Utklipp av delen sel. under: piksler som fylles med kroppsfarge (kroppen under bladene).
    keep: piksler under delen som beholder fargen fra tegningen (kroppen under armene, så det ikke blir skjøt)."""
    m = sel | (under if under is not None else False) | (keep if keep is not None else False)
    if not m.any():
        return ''
    ys, xs = np.nonzero(m)
    x0, x1, y0, y1 = xs.min(), xs.max() + 1, ys.min(), ys.max() + 1
    col = fg.copy()
    a = np.where(sel, alpha, 0.0)
    if under is not None:
        u = under & ~sel
        col[u] = skin_rgb
        a = np.where(u, 1.0, a)
    if keep is not None:
        m = m | keep
        a = np.where(keep & ~sel, alpha, a)
    if inpaint is not None:
        fill = cv2.inpaint(col.clip(0, 255).astype(np.uint8), inpaint.astype(np.uint8), 5, cv2.INPAINT_TELEA).astype(np.float32)
        col = np.where(inpaint[..., None], fill, col)
        a = np.where(inpaint & m, 1.0, a)
    rgba = np.dstack([col.clip(0, 255), a * 255])[y0:y1, x0:x1]
    return png(rgba, x0, y0, x1 - x0, y1 - y0)

def cut_groups(lab, base):
    out = []
    for i in range(1, int(lab.max()) + 1):
        sel = part == base + i
        if sel.sum() < 8:
            continue
        ys, xs = np.nonzero(sel)
        out.append({'cx': round(float(xs.mean()) + X0, 1), 'cy': round(float(ys.mean()) + Y0, 1), 'px': int((lab == i).sum()),
                    'bbox': [int(xs.min()) + X0, int(ys.min()) + Y0, int(xs.max() - xs.min() + 1), int(ys.max() - ys.min() + 1)],
                    'svg': cut(sel)})
    return out

body_sel = part == 3
near_body = cv2.dilate(body_sel.astype(np.uint8), np.ones((9, 9), np.uint8)).astype(bool)
under_body = near_body & (part > LEAF0) & body
keep_body = near_body & ((part == 4) | (part == 5))
art['body'] = cut(body_sel, under=under_body, inpaint=eye_hole & body_sel, keep=keep_body)  # øynene tegnes oppå, så hullet fylles med kropp
# Armene overlapper kroppen med to piksler, så skjøten ikke synes
grow = lambda k: (part == k) | (cv2.dilate((part == k).astype(np.uint8), np.ones((5, 5), np.uint8)).astype(bool) & (part == 3))
art['armL'] = cut(grow(4))
art['armR'] = cut(grow(5))
art['boots'] = cut(part == 2)
art['shadow'] = cut(part == 1)
art['leaves'] = cut_groups(crown_lab, LEAF0)
art['loose'] = cut_groups(loose_lab, LOOSE0)
art['skin'] = 'oklch(99.4% 0 0)' if skin_rgb.min() > 250 else oklch(skin_rgb)

top = min(art['leaves'], key=lambda l: l['cy'])
art['leafIcon'] = top

with open(OUT, 'w') as fh:
    fh.write('// Generert av scripts/trace_soren.py fra public/assets/mascot.png. Ikke rediger for hånd.\n')
    fh.write('window.SorenArt = ' + json.dumps(art, ensure_ascii=False) + ';\n')
print(f'skrev {OUT}: {os.path.getsize(OUT) // 1024} kB, {len(art["leaves"])} bladklumper, {len(art["loose"])} løse blader', file=sys.stderr)
