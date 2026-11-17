# Produktutvalg, filtre og "passer sammen"

## Valgte underkategorier (fra sor-tre.no, november 2026)

Valgt slik at hver gruppe har tekniske spesifikasjoner å filtrere på, naturlige tilbehør, og noe som kan monteres eller trenger veiledning. Tallene er antall produkter hos Sør-Tre.

| Tema (guide) | Underkategorier (ID, antall) | Hvorfor |
|---|---|---|
| Lys og rekkevidde | Lykt (7111, 18), Lykter og batterier (7129, 12), Lyskilder (7117, 25), Arbeidslys (7160, 5) | Kjerneproblem P1: lumen, rekkevidde i meter, batteritid, IP-klasse. Hodelykt passer med batteri (dokumentert i Sør-Tre sin egen tekst: "Passer blant annet Ledlenser H7R") |
| Bygg og feste | Hammer og slegge (7207, 5), Spiker (7137, 23), Skruer stål (7138, 45), Beslag (7663, 28), Skyvelær og tommestokk (7118, 6), Kapp og slipeskive (7128, 16) | Hammer og spiker. Filter på lengde, diameter, materiale, overflate |
| Maling | Rulle og pensel (7364, 72), Sparkel (7179, 32), Maskering (7193, 11), Skrape og stålbørste (7206, 13), Sandpapir (7253, 6) | Persona Ingrid er maler. Komplett "male et rom"-pakke |
| Balkong og montering | Balkongskjerming glass (7678, 12), stendere (7680, 12), dekklist (7679, 6) | Kundereisen nevner glassrekkverk. Monteringsveiledning steg for steg, mål i mm, materialer |
| Kjetting og løft | Sjakkel (7499, 37), Kjetting (7504, 33), Kroker (7220, 17) | Tekniske tall (WLL, dimensjon), galvanisert eller syrefast |
| Skog og sikkerhet | Motorsag (7070, 11), Hogsttilbehør (7079, 8), Arbeidshansker (7088, 35), Hørselvern (7087, 4) | Effekt, sverdlengde. Sikkerhetsutstyr som obligatorisk anbefalt tilbehør |
| Tur | Kikkerter (7246, 11), Grill og kokeutstyr (7258, 25) | Forstørrelse og objektiv for sammenligning. Grill må monteres |

Totalt cirka 480 produkter. Importen tar maks 40 per underkategori, cirka 350 produkter.

## Filterfasetter (spec_keys med facet = 1)

| Nøkkel | Enhet | Gjelder | Hvordan den fylles |
|---|---|---|---|
| pris | kr | alle | Sør-Tre |
| materiale | tekst | alle | Utledet fra tittel og tekst (stål, rustfri, syrefast, galvanisert, aluminium, tre, plast, gummi, glass, nylon) |
| farge | tekst | alle | Utledet (svart, hvit, rød, grønn, blå, gul, oransje, grå, transparent) |
| lengde_mm, diameter_mm, bredde_mm | mm | feste, maling, balkong | Utledet fra mønstre som 4,0x75 mm, 50 mm, 1000 mm |
| lysstyrke_lm | lumen | lys | Utledet ("2500 Lumen") |
| rekkevidde_m | meter | lys | Oftest ukjent. Fylles av verifiserte kjøpere i åpen versjon (demo-eksempel) |
| batteri | tekst | lys, verktøy | Utledet (oppladbar, AA, AAA, 21700, Li-ion) |
| ip_klasse | tekst | lys, el | Utledet (IPX4, IP67) |
| effekt_w, sverdlengde_cm | W, cm | skog, el | Utledet |
| arbeidslast_kg | kg | kjetting | Utledet (WLL) |
| forstorrelse | x | kikkert | Utledet (10x42) |
| merke | tekst | alle | Utledet fra tittel (Ledlenser, Einhell, Osean, Husqvarna osv.) |
| bruksomrade | tekst | alle | Regel per underkategori (innendørs, utendørs, marint, verksted) |

Hver utledet verdi lagres med kilde `utledet` og tekstutdraget den kom fra, slik at kunden ser "Utledet fra produkttekst: '2500 Lumen'".

## "Passer sammen"-regler

| Nivå | Betydning | Eksempel |
|---|---|---|
| Dokumentert | Står eksplisitt i produkttekst hos Sør-Tre | Batteri 21700 "passer Ledlenser H7R" |
| Regelutledet | Deterministisk regel mellom kategorier og fasetter | Hammer til spiker. Rulle til sparkel og maskering. Motorsag til hansker og hørselvern. Sjakkel til kjetting med samme dimensjon. Glass til stender og dekklist |
| Antatt | Kjøpt sammen av andre (demo-ordrer) | Kikkert og grill |

Antatt vises som "Andre kjøpte også", aldri som "passer".
