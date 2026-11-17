# Product selection, filters and "goes well with"

## Chosen subcategories (from sor-tre.no, November 2026)

Chosen so that every group has technical specifications to filter on, natural accessories, and something that needs assembly or guidance. The numbers are product counts at Sør-Tre.

| Theme (guide) | Subcategories (ID, count) | Why |
|---|---|---|
| Light and range | Lykt (7111, 18), Lykter og batterier (7129, 12), Lyskilder (7117, 25), Arbeidslys (7160, 5) | Core problem P1: lumen, range in meters, battery life, IP rating. Headlamp goes with battery (documented in Sør-Tre's own text: "Passer blant annet Ledlenser H7R") |
| Building and fastening | Hammer og slegge (7207, 5), Spiker (7137, 23), Skruer stål (7138, 45), Beslag (7663, 28), Skyvelær og tommestokk (7118, 6), Kapp og slipeskive (7128, 16) | Hammer and nails. Filters on length, diameter, material, finish |
| Painting | Rulle og pensel (7364, 72), Sparkel (7179, 32), Maskering (7193, 11), Skrape og stålbørste (7206, 13), Sandpapir (7253, 6) | Persona Ingrid is a painter. Complete "paint a room" kit |
| Balcony and assembly | Balkongskjerming glass (7678, 12), stendere (7680, 12), dekklist (7679, 6) | The customer journey mentions glass railings. Step by step assembly guide, measurements in mm, materials |
| Chain and lifting | Sjakkel (7499, 37), Kjetting (7504, 33), Kroker (7220, 17) | Technical numbers (WLL, dimension), galvanized or acid proof |
| Forest and safety | Motorsag (7070, 11), Hogsttilbehør (7079, 8), Arbeidshansker (7088, 35), Hørselvern (7087, 4) | Power, bar length. Safety gear as a strongly recommended accessory |
| Outdoors | Kikkerter (7246, 11), Grill og kokeutstyr (7258, 25) | Magnification and objective size for comparison. Grills need assembly |

The demo has 496 products: at most 40 per subcategory, plus single products from other categories needed for "goes well with".

## Filter facets (spec_keys with facet = 1)

| Key | Unit | Applies to | How it is filled |
|---|---|---|---|
| pris | kr | all | Sør-Tre |
| materiale | text | all | Derived from title and text (steel, stainless, acid proof, galvanized, aluminium, wood, plastic, rubber, glass, nylon) |
| farge | text | all | Derived (black, white, red, green, blue, yellow, orange, grey, transparent) |
| lengde_mm, diameter_mm, bredde_mm | mm | fastening, painting, balcony | Derived from patterns like 4,0x75 mm, 50 mm, 1000 mm |
| lysstyrke_lm | lumen | light | Derived ("2500 Lumen") |
| rekkevidde_m | meter | light | Usually unknown. Filled in by verified buyers in the open version (demo example) |
| batteri | text | light, tools | Derived (rechargeable, AA, AAA, 21700, Li-ion) |
| ip_klasse | text | light, electrical | Derived (IPX4, IP67) |
| effekt_w, sverdlengde_cm | W, cm | forest, electrical | Derived |
| arbeidslast_kg | kg | chain | Derived (WLL) |
| forstorrelse | x | binoculars | Derived (10x42) |
| merke | text | all | Derived from the title (Ledlenser, Einhell, Osean, Husqvarna etc.) |
| bruksomrade | text | all | Rule per subcategory (indoor, outdoor, marine, workshop) |

Every derived value is stored with the source `utledet` and the text snippet it came from, so the customer sees "Derived from product text: '2500 Lumen'".

## "Goes well with" rules

| Level | Meaning | Example |
|---|---|---|
| Documented | Stated explicitly in Sør-Tre's product text | Battery 21700 "passer Ledlenser H7R" |
| Rule based | Deterministic rule between categories and facets | Hammer to nails. Roller to filler and masking tape. Chainsaw to gloves and hearing protection. Shackle to chain of the same dimension. Glass to posts and cover strip |
| Assumed | Bought together by others (demo orders) | Binoculars and grill |

Assumed is shown as "Others also bought", never as "goes well with".
