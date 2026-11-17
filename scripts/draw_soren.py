"""
Tegner Søren som ny, ren vektorfigur til public/assets/soren-art.js.

Ingen piksler fra bildet havner i figuren. Tegningen (public/assets/mascot.png) og utklippsmasken
(public/assets/mascot-alpha.png) brukes bare til å måle formene, så omriss, proporsjoner og plassering
er de samme: tykk stamme, greiner, korte bein, eik- og lønneblader. Hver del tegnes som én glatt flate
med én farge: hvit stamme med myk skyggekant (ikke omriss) og barkstreker, røde blader med lys midtnerve, røde støvler, pilleøyne og en myk skygge.

Delene holdes hver for seg, så soren.js kan animere dem (krone, løse blader, armer, øyne).
Krever: pip install pillow numpy opencv-python-headless
Kjør:   python scripts/draw_soren.py
"""
import json, math, os
import numpy as np, cv2
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, 'public/assets/soren-art.js')
X0, Y0, X1, Y1 = 395, 8, 632, 334
W, H = X1 - X0, Y1 - Y0
S = 4  # målingene gjøres i 4x for glatte omriss

SKIN, SHADE, BARK = 'oklch(98.5% 0.003 250)', 'oklch(86% 0.012 250)', 'oklch(86% 0.01 250)'
RED, RED_DARK, VEIN = 'oklch(60% 0.21 25)', 'oklch(50% 0.19 25)', 'oklch(86% 0.07 20)'
EYE = 'oklch(23% 0.04 293)'

img = np.array(Image.open(os.path.join(ROOT, 'public/assets/mascot.png')).convert('RGB'))[Y0:Y1, X0:X1].astype(np.int32)
alpha = np.array(Image.open(os.path.join(ROOT, 'public/assets/mascot-alpha.png')))[Y0:Y1, X0:X1] > 127
yy, xx = np.mgrid[0:H, 0:W]
Y, X = yy + Y0, xx + X0
red = alpha & ((img[..., 0] - img[..., 1]) > 60)
k = lambda n: cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (n, n))


def fill_holes(m):
    f = m.astype(np.uint8).copy()
    cnts, _ = cv2.findContours(f, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_NONE)
    cv2.drawContours(f, cnts, -1, 1, cv2.FILLED)
    return f.astype(bool)


def poly(points):
    m = np.zeros((H, W), np.uint8)
    cv2.fillPoly(m, [np.array([(x - X0, y - Y0) for x, y in points], np.int32)], 1)
    return m.astype(bool)


def path(mask, eps=0.6, min_area=6):
    """Glatt omriss av en maske som SVG-sti (Catmull-Rom gjennom forenklede punkter)."""
    m = cv2.resize(mask.astype(np.uint8) * 255, (W * S, H * S), interpolation=cv2.INTER_LINEAR)
    m = (cv2.GaussianBlur(m, (0, 0), S * 0.7) > 127).astype(np.uint8)
    cnts, hier = cv2.findContours(m, cv2.RETR_CCOMP, cv2.CHAIN_APPROX_NONE)
    out = []
    for c in cnts:
        if cv2.contourArea(c) < min_area * S * S:
            continue
        p = cv2.approxPolyDP(c, eps * S, True)[:, 0, :].astype(float) / S
        p[:, 0] += X0
        p[:, 1] += Y0
        n = len(p)
        if n < 3:
            continue
        d = f'M{p[0][0]:.1f},{p[0][1]:.1f}'
        for i in range(n):
            a, b, c2, e = p[i - 1], p[i], p[(i + 1) % n], p[(i + 2) % n]
            c1 = b + (c2 - a) / 6
            cc = c2 - (e - b) / 6
            d += f'C{c1[0]:.1f},{c1[1]:.1f} {cc[0]:.1f},{cc[1]:.1f} {c2[0]:.1f},{c2[1]:.1f}'
        out.append(d + 'Z')
    return ''.join(out)


# ---------- Deler målt fra tegningen ----------
shadow_px = alpha & ~red & (Y >= 316)
boots = fill_holes(cv2.morphologyEx((red & (Y >= 286)).astype(np.uint8), cv2.MORPH_CLOSE, k(3)).astype(bool))
figure = fill_holes(cv2.morphologyEx((alpha & ~shadow_px).astype(np.uint8), cv2.MORPH_CLOSE, k(5)).astype(bool)) | boots
leaves_px = red & ~boots
# Kroppen: alt som ikke er blad, støvle eller skygge, lukket der bladene overlapper kanten
body_all = fill_holes(cv2.morphologyEx((figure & ~leaves_px & ~boots).astype(np.uint8), cv2.MORPH_OPEN, k(3)).astype(bool))
body_all = fill_holes(cv2.morphologyEx(body_all.astype(np.uint8), cv2.MORPH_CLOSE, k(7)).astype(bool)) & ~boots
arm_l = poly([(416, 178), (468, 186), (484, 214), (482, 247), (446, 250), (414, 236)]) & body_all
arm_r = poly([(558, 226), (594, 222), (598, 298), (560, 298), (560, 262)]) & body_all & (Y < 313)
body = body_all & ~(arm_l & (X < 472)) & ~(arm_r & (X > 562))
# Barkstreker og omrisslinjer inne i kroppen: grå piksler godt inne i figuren
inner = cv2.erode(body_all.astype(np.uint8), k(5)).astype(bool)
bark = inner & (img.min(-1) < 222) & (img.min(-1) > 120) & ~cv2.dilate(red.astype(np.uint8), k(5)).astype(bool)
bark &= ~poly([(486, 140), (546, 140), (546, 176), (486, 176)])  # ikke rundt øynene


def part(mask, fill, lines=None):
    # Ingen omriss: en myk skyggeflate litt forskjøvet bak den hvite formen gir kant mot lys bakgrunn
    d = path(mask)
    s = f'<path d="{d}" transform="translate(1.6 2.2)" fill="{SHADE}"/><path d="{d}" fill="{fill}"/>'
    if lines is not None and lines.any():
        s += f'<path d="{path(lines, eps=0.4, min_area=3)}" fill="{BARK}"/>'
    return s


# ---------- Blader ----------
def leaf_svg(comp):
    """Ett blad eller en bladklump: rød flate, mørkere kant og en lys midtnerve per blad."""
    s = f'<path d="{path(comp, eps=0.45, min_area=4)}" fill="{RED}" stroke="{RED_DARK}" stroke-width=".8" stroke-linejoin="round"/>'
    # Midtnerve: langs den lengste aksen til hver del av klumpen som ligner ett blad
    n, cc, st, _ = cv2.connectedComponentsWithStats(cv2.erode(comp.astype(np.uint8), k(3)))
    for i in range(1, n):
        if st[i][4] < 25:
            continue
        cnt, _ = cv2.findContours((cc == i).astype(np.uint8), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_NONE)
        c = max(cnt, key=cv2.contourArea)
        if len(c) < 5:
            continue
        (ex, ey), (ew, eh), ang = cv2.fitEllipse(c)
        L = max(ew, eh) * 0.34
        a = math.radians(ang + (90 if eh >= ew else 0))
        dx, dy = math.cos(a) * L, math.sin(a) * L
        cx, cy = ex + X0 + .5, ey + Y0 + .5
        s += (f'<path d="M{cx - dx:.1f},{cy - dy:.1f} Q{cx + dy * .08:.1f},{cy - dx * .08:.1f} {cx + dx:.1f},{cy + dy:.1f}" '
              f'stroke="{VEIN}" stroke-width="1.1" stroke-linecap="round" fill="none"/>')
    return s


def leaf_groups(mask):
    n, cc, st, cent = cv2.connectedComponentsWithStats(mask.astype(np.uint8))
    out = []
    for i in range(1, n):
        if st[i][4] < 8:
            continue
        comp = cc == i
        x, y, w, h = (int(v) for v in st[i][:4])
        out.append({'cx': round(cent[i][0] + X0, 1), 'cy': round(cent[i][1] + Y0, 1), 'px': int(st[i][4]),
                    'bbox': [x + X0, y + Y0, w, h], 'svg': leaf_svg(comp)})
    return out


n, cc, st, cent = cv2.connectedComponentsWithStats(leaves_px.astype(np.uint8))
loose = np.zeros((H, W), bool)
crown = np.zeros((H, W), bool)
for i in range(1, n):
    (loose if cent[i][1] + Y0 > 200 else crown)[cc == i] = True

# ---------- Øyne: piller med glans, målt fra pupillene ----------
dark = alpha & (img.sum(-1) < 330) & (Y > 130) & (Y < 180) & (X > 485) & (X < 548)  # bare ansiktet
n, cc, st, _ = cv2.connectedComponentsWithStats(dark.astype(np.uint8))
eyes, eye_svg = [], ''
for i in sorted(range(1, n), key=lambda i: -st[i][4])[:2]:
    comp = fill_holes(cc == i)
    ys, xs = np.nonzero(comp)
    x, y, w, h = xs.min() + X0, ys.min() + Y0, xs.max() - xs.min() + 1, ys.max() - ys.min() + 1
    eyes.append({'cx': x + w / 2, 'cy': y + h / 2, 'rx': w / 2, 'ry': h / 2})
    eye_svg += (f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{w / 2}" fill="{EYE}"/>'
                f'<circle cx="{x + w * .62:.1f}" cy="{y + h * .3:.1f}" r="{w * .2:.1f}" fill="oklch(100% 0 0)"/>')
eyes.sort(key=lambda e: e['cx'])

# ---------- Skygge: en myk ellipse under støvlene ----------
ys, xs = np.nonzero(shadow_px)
shadow = (f'<ellipse cx="{(xs.min() + xs.max()) / 2 + X0:.1f}" cy="{ys.mean() + Y0 + 1:.1f}" rx="{(xs.max() - xs.min()) / 2:.1f}" '
          f'ry="{max(4, (ys.max() - ys.min()) / 2):.1f}" fill="oklch(0% 0 0 / .14)"/>')

boot_svg = (f'<path d="{path(boots, eps=0.45)}" fill="{RED}" stroke="{RED_DARK}" stroke-width="1" stroke-linejoin="round"/>'
            f'<path d="{path(boots & (Y >= 318), eps=0.45, min_area=3)}" fill="{RED_DARK}"/>')

art = {
    'box': [X0, Y0, W, H],
    'eyes': eyes,
    'body': part(body, SKIN, lines=bark & body),
    'armL': part(arm_l, SKIN, lines=bark & arm_l),
    'armR': part(arm_r, SKIN, lines=bark & arm_r),
    'boots': boot_svg,
    'shadow': shadow,
    'eyesSvg': eye_svg,
    'loose': leaf_groups(loose),
    'leaves': leaf_groups(crown),
    'skin': SKIN,
    'edge': SHADE,
    'eyeColor': EYE,
}
art['leafIcon'] = min(art['leaves'], key=lambda l: l['cy'])

with open(OUT, 'w') as fh:
    fh.write('// Generert av scripts/draw_soren.py. Ren vektortegning; ingen piksler fra bildet. Ikke rediger for hånd.\n')
    fh.write('window.SorenArt = ' + json.dumps(art, ensure_ascii=False) + ';\n')
print(f'skrev {OUT}: {os.path.getsize(OUT) // 1024} kB, {len(art["leaves"])} bladklumper, {len(art["loose"])} løse blader')
