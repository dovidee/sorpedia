"""
Klipper Søren ut av public/assets/mascot.png med ferdige verktøy (rembg), og lagrer gjennomsiktigheten
som public/assets/mascot-alpha.png (gråtone, samme størrelse som mascot.png). trace_soren.py bruker den.

Kroppen er hvit på hvit bakgrunn, så ett verktøy er ikke nok:
  Segment Anything (SAM) med punkter på Søren finner hele kroppen, men river opp bladene ytterst.
  BiRefNet finner bladene rent, men tar ikke med den hvite kroppen.
Søren er det minst ett av dem sier er Søren (største verdi per piksel).

Krever: pip install "rembg[cpu]"  (laster ned modellene første gang, ca. 1 GB)
Kjør:   python scripts/cutout_soren.py            (tar 10 til 20 minutter på én CPU)
"""
import os
import cv2
import numpy as np
from PIL import Image
from rembg import new_session, remove

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'public/assets/mascot.png')
OUT = os.path.join(ROOT, 'public/assets/mascot-alpha.png')
X0, Y0, X1, Y1 = 375, 0, 652, 341   # Søren med marg i originalbildet
UP = 3                               # forstørres, så modellene ser detaljene
# Punkter på Søren for SAM (koordinater i originalbildet): stamme, hode, armer, krone, støvler
POINTS = [(512, 230), (512, 290), (500, 170), (445, 205), (580, 260), (480, 90), (560, 120), (500, 315), (545, 315),
          (515, 125), (493, 178), (537, 178), (515, 150)]  # panne og kinn
# Hvit bakgrunn som er omsluttet av Søren (SAM tar den ellers med): over den vinkende armen, ved stammen, mellom greinene
NOT = [(465, 200), (474, 186), (498, 109)]


def alpha_of(img, **kw):
    return np.array(remove(img, **kw).convert('RGBA'))[..., 3]


def main():
    src = Image.open(SRC).convert('RGB')
    crop = src.crop((X0, Y0, X1, Y1)).resize(((X1 - X0) * UP, (Y1 - Y0) * UP), Image.LANCZOS)
    pts = ([{'type': 'point', 'data': [(x - X0) * UP, (y - Y0) * UP], 'label': 1} for x, y in POINTS]
           + [{'type': 'point', 'data': [(x - X0) * UP, (y - Y0) * UP], 'label': 0} for x, y in NOT])
    a_sam = alpha_of(crop, session=new_session('sam'), sam_prompt=pts, post_process_mask=True)  # rembg fjerner små hull
    a_bir = alpha_of(crop, session=new_session('birefnet-general'))
    a = np.maximum(a_sam, a_bir)
    # Lukking (OpenCV): fyller hull på noen få piksler der bladkantene møter stammen
    a = cv2.morphologyEx(a, cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (9, 9)))
    small = Image.fromarray(a).resize((X1 - X0, Y1 - Y0), Image.BOX)
    full = Image.new('L', src.size, 0)
    full.paste(small, (X0, Y0))
    full.save(OUT)
    print('skrev', OUT)


if __name__ == '__main__':
    main()
