"""
Cuts Søren out of public/assets/mascot.png with ready made tools (rembg), and saves the transparency
as public/assets/mascot-alpha.png (greyscale, same size as mascot.png). trace_soren.py uses it.

The body is white on a white background, so one tool isn't enough:
  Segment Anything (SAM) with points on Søren finds the whole body, but tears up the outermost leaves.
  BiRefNet finds the leaves cleanly, but leaves out the white body.
Søren is whatever at least one of them says is Søren (highest value per pixel).

Requires: pip install "rembg[cpu]"  (downloads the models the first time, about 1 GB)
Run:      python scripts/cutout_soren.py            (takes 10 to 20 minutes on one CPU)
"""
import os
import cv2
import numpy as np
from PIL import Image
from rembg import new_session, remove

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'public/assets/mascot.png')
OUT = os.path.join(ROOT, 'public/assets/mascot-alpha.png')
X0, Y0, X1, Y1 = 375, 0, 652, 341   # Søren with a margin in the original image
UP = 3                               # upscaled, so the models see the details
# Points on Søren for SAM (coordinates in the original image): trunk, head, arms, crown, boots
POINTS = [(512, 230), (512, 290), (500, 170), (445, 205), (580, 260), (480, 90), (560, 120), (500, 315), (545, 315),
          (515, 125), (493, 178), (537, 178), (515, 150)]  # forehead and cheeks
# White background enclosed by Søren (SAM includes it otherwise): above the waving arm, by the trunk, between the branches
NOT = [(465, 200), (474, 186), (498, 109)]


def alpha_of(img, **kw):
    return np.array(remove(img, **kw).convert('RGBA'))[..., 3]


def main():
    src = Image.open(SRC).convert('RGB')
    crop = src.crop((X0, Y0, X1, Y1)).resize(((X1 - X0) * UP, (Y1 - Y0) * UP), Image.LANCZOS)
    pts = ([{'type': 'point', 'data': [(x - X0) * UP, (y - Y0) * UP], 'label': 1} for x, y in POINTS]
           + [{'type': 'point', 'data': [(x - X0) * UP, (y - Y0) * UP], 'label': 0} for x, y in NOT])
    a_sam = alpha_of(crop, session=new_session('sam'), sam_prompt=pts, post_process_mask=True)  # rembg removes small holes
    a_bir = alpha_of(crop, session=new_session('birefnet-general'))
    a = np.maximum(a_sam, a_bir)
    # Closing (OpenCV): fills holes of a few pixels where the leaf edges meet the trunk
    a = cv2.morphologyEx(a, cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (9, 9)))
    small = Image.fromarray(a).resize((X1 - X0, Y1 - Y0), Image.BOX)
    full = Image.new('L', src.size, 0)
    full.paste(small, (X0, Y0))
    full.save(OUT)
    print('skrev', OUT)


if __name__ == '__main__':
    main()
