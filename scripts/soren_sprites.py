"""Prepares the drawn Søren sprite sheets in public/assets/soren/ for the site: python3 scripts/soren_sprites.py

Each sheet holds 60 frames of 360 x 360 px in a 10 x 6 grid, played at 12 fps. The sources are read from git at
SOURCE_REV, never from the output folder, so the script gives the same result every time it runs.

1. Frames 55 to 59 of every source carry a large half transparent watermark over the figure. They are dropped and
   frames 0 to 54 are spread over the 60 slots (five of them shown twice). No frame is blended with another, so the
   figure never turns half transparent.
2. In the wave, shrug and celebrating poses the cut-out left dark blotches between the trunk and the boots. They
   are cleared and covered with a skirt of Søren's own leaves, the same in every frame of the pose (see skirt()).
3. The art was cut out from a white backdrop. Only backdrop pixels that are connected to the transparent outside
   are removed: white, pink or cool light pixels (not Søren's cream, not the cart's metal), reached by a flood fill
   from the transparent area. White that is enclosed by the figure, his body, the highlights in his eyes and every
   other pixel keep their colour and opacity. The same rule runs on every frame.
4. A face sheet per pose, soren_head_<pose>.png: each frame cropped around the eyes (tracked per frame and smoothed)
   and scaled to 128 px, same 10 x 6 grid. The chat shows these, so every expression comes from the drawn art.

An optional folder argument writes the sheets there instead, for checking them before they replace the live ones.
Needs Pillow and git.
"""
import io
import random
import subprocess
import sys
from collections import deque
from pathlib import Path

from PIL import Image, ImageChops

DIR = Path(__file__).resolve().parent.parent / 'public' / 'assets' / 'soren'
S, COLS, ROWS, N = 360, 10, 6, 60
OUT_DIR = Path(sys.argv[1]) if len(sys.argv) > 1 else DIR
HEAD, OUT = 165, 128          # face crop in source px, face frame in output px
SOURCE_REV = 'efde056'      # the commit that added the drawn sheets
KEEP = 55                    # frames before the watermark
POSES = ['01_default', '02_wave', '03_thinking', '04_cart', '05_hammer', '06_lumber', '07_pointing', '08_shrug', '09_celebrating']


def box(i):
    x, y = (i % COLS) * S, (i // COLS) * S
    return (x, y, x + S, y + S)


def source(pose):
    blob = subprocess.run(['git', 'show', f'{SOURCE_REV}:public/assets/soren/soren_{pose}_sprite.png'],
                          cwd=DIR.parent.parent.parent, check=True, capture_output=True).stdout
    return Image.open(io.BytesIO(blob)).convert('RGBA')


def retime(im):
    """Drops the watermarked frames and spreads frames 0 to KEEP - 1 over all N slots, without blending."""
    src = [im.crop(box(i)) for i in range(KEEP)]
    for i in range(N):
        im.paste(src[i * KEEP // N], box(i)[:2])


def masks(im):
    r, g, b, a = im.split()
    # Backdrop white and its pink blend with the red leaves: light, with blue not below green (Søren's cream has
    # green clearly above blue) and red not below green (the cart's light metal is bluish grey)
    light = ImageChops.darker(ImageChops.darker(r, g), b).point(lambda v: 255 if v >= 200 else 0)
    not_cream = ImageChops.subtract(g, b).point(lambda v: 255 if v <= 1 else 0)
    not_metal = ImageChops.subtract(g, r).point(lambda v: 255 if v <= 2 else 0)
    seen = a.point(lambda v: 255 if v > 0 else 0)
    white = ImageChops.multiply(ImageChops.multiply(ImageChops.multiply(light, not_cream), not_metal), seen)
    # The eyes: dark and bluish
    dark = ImageChops.add(ImageChops.add(r, g), b).point(lambda v: 255 if v < 200 else 0)
    navy = ImageChops.subtract(b, r).point(lambda v: 255 if v > 8 else 0)
    eyes = ImageChops.multiply(ImageChops.multiply(dark, navy), a.point(lambda v: 255 if v > 200 else 0))
    # Eyes sit in the upper 60 percent of a frame; lower down the same dark blue is wheels and boot shadows
    upper = Image.new('L', im.size, 0)
    for row in range(ROWS):
        upper.paste(255, (0, row * S, im.size[0], row * S + int(S * .6)))
    return white, ImageChops.multiply(eyes, upper)


def backdrop(im):
    """Flood fill from the transparent outside through backdrop coloured pixels; only those become transparent."""
    white, _ = masks(im)
    w, h = im.size
    m = white.tobytes()
    alpha = bytearray(im.getchannel('A').tobytes())
    q = deque(i for i in range(w * h) if alpha[i] == 0)
    seen = bytearray(w * h)
    for i in q:
        seen[i] = 1
    while q:
        i = q.popleft()
        x = i % w
        for j in (i - 1 if x else -1, i + 1 if x < w - 1 else -1, i - w, i + w):
            if 0 <= j < w * h and not seen[j] and m[j]:
                seen[j] = 1
                alpha[j] = 0
                q.append(j)
    im.putalpha(Image.frombytes('L', (w, h), bytes(alpha)))


SKIRT = {'wave', 'shrug', 'celebrating'}
CELL = 12


def red(p):
    return p[3] >= 200 and p[0] > 150 and p[1] < 90 and p[2] < 90


def blot(p):
    return p[3] > 20 and sum(p[:3]) < 150


def boots(px, f):
    """Top row and middle of the boots: the lowest band of red rows in frame f."""
    x0, y0 = (f % COLS) * S, (f // COLS) * S
    rows = [[x for x in range(S) if red(px[x0 + x, y0 + y])] for y in range(S)]
    y = S - 1
    while y > S * .5 and len(rows[y]) <= 10:
        y -= 1
    if y <= S * .5:
        return None
    while y > S * .5 and len(rows[y - 1]) > 10:
        y -= 1
    xs = rows[y] + rows[min(y + 8, S - 1)]
    return y, (min(xs) + max(xs)) // 2


def skirt(im, leaf):
    """Clears the dark blotches in the band above the boots and covers the cells that are dark in at least a third
    of the frames with copies of one of Søren's falling leaves. Each cell has a fixed size and turn, so the skirt
    stays the same from frame to frame."""
    px = im.load()
    at = [boots(px, f) for f in range(N)]
    count = {}
    for f, a in enumerate(at):
        if not a:
            continue
        top, cx = a
        x0, y0 = (f % COLS) * S, (f // COLS) * S
        cells = {}
        for y in range(max(top - 60, 0), top):
            for x in range(max(cx - 90, 0), min(cx + 90, S)):
                if blot(px[x0 + x, y0 + y]):
                    k = ((x - cx) // CELL, (y - top) // CELL)
                    cells[k] = cells.get(k, 0) + 1
        for k, n in cells.items():
            if n >= 12:
                count[k] = count.get(k, 0) + 1
    chosen = sorted((k for k, n in count.items() if n >= N // 3), key=lambda k: (-k[1], k[0]))
    for f, a in enumerate(at):
        if not a:
            continue
        top, cx = a
        x0, y0 = (f % COLS) * S, (f // COLS) * S
        for y in range(max(top - 60, 0), top):
            for x in range(max(cx - 90, 0), min(cx + 90, S)):
                if blot(px[x0 + x, y0 + y]):
                    px[x0 + x, y0 + y] = (0, 0, 0, 0)
        frame = im.crop((x0, y0, x0 + S, y0 + S))
        for k in chosen:   # lower rows first, so the upper leaves overlap them
            r = random.Random(k[0] * 131 + k[1] * 17)
            size = r.uniform(.85, 1.05)
            one = leaf.resize((round(leaf.width * size), round(leaf.height * size)), Image.LANCZOS)
            one = one.rotate(r.uniform(-60, 60), Image.BICUBIC, expand=True)
            frame.alpha_composite(one, (round(cx + k[0] * CELL + CELL / 2 - one.width / 2), round(top + k[1] * CELL + CELL / 2 - one.height / 2)))
        im.paste(frame, (x0, y0))
        px = im.load()
    return len(chosen)


def heads(im, name):
    _, eyes = masks(im)
    centres = []
    for i in range(N):
        bb = eyes.crop(box(i)).getbbox()
        centres.append(((bb[0] + bb[2]) / 2, (bb[1] + bb[3]) / 2) if bb else None)
    known = [c for c in centres if c]
    mean = (sum(c[0] for c in known) / len(known), sum(c[1] for c in known) / len(known)) if known else (S / 2, S / 3)
    centres = [c or mean for c in centres]
    sheet = Image.new('RGBA', (COLS * OUT, ROWS * OUT), (0, 0, 0, 0))
    for i in range(N):
        near = [centres[(i + d) % N] for d in range(-3, 4)]
        cx = sum(c[0] for c in near) / len(near)
        cy = sum(c[1] for c in near) / len(near) - 14   # the crown sits above the eyes
        x0 = int(min(max(cx - HEAD / 2, 0), S - HEAD))
        y0 = int(min(max(cy - HEAD / 2, 0), S - HEAD))
        bx, by = box(i)[:2]
        face = im.crop((bx + x0, by + y0, bx + x0 + HEAD, by + y0 + HEAD)).resize((OUT, OUT), Image.LANCZOS)
        sheet.paste(face, ((i % COLS) * OUT, (i // COLS) * OUT))
    sheet.save(OUT_DIR / f'soren_head_{name.split("_", 1)[1]}.png', optimize=True)


# One whole falling leaf from the celebrating animation (frame 10)
_celebrating = source('09_celebrating')
LEAF = _celebrating.crop(((10 % COLS) * S + 269, (10 // COLS) * S + 213, (10 % COLS) * S + 301, (10 // COLS) * S + 241))

for pose in POSES:
    path = OUT_DIR / f'soren_{pose}_sprite.png'
    im = source(pose)
    retime(im)
    if pose.split('_', 1)[1] in SKIRT:
        print(pose, 'leaf skirt of', skirt(im, LEAF), 'leaves')
    backdrop(im)
    im.save(path, optimize=True)
    heads(im, pose)
    print('done', pose)
