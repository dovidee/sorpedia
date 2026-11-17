# English word list for Sørpedia. Run: python3 scripts/i18n_en.py  (writes src/I18nEn.php)
# Key = the Norwegian text as it appears in the code.
T = {
# Shared
"Søk": "Search", "Handlekurv": "Cart", "Innboks": "Inbox", "Innstillinger": "Settings", "Logg inn": "Log in", "Logg ut": "Log out",
"Registrer deg": "Sign up", "Kontoen din": "Your account", "Logg inn eller registrer deg": "Log in or sign up", "Arbeid": "Work",
"Varehus": "Store", "Varehus, konto og handlekurv": "Store, account and cart", "Varehuset ditt. Lagerstatus vises for dette.": "Your store. Stock is shown for this one.",
"Vises som": "Shown as", "Verifisert kjøper": "Verified buyer", "varer": "items", "Produktguide": "Product guide", "Spør Søren": "Ask Søren",
"Søk etter produkt eller hva du skal gjøre, f.eks. «male tak»": "Search for a product or a task, e.g. “paint ceiling”",
"Trykk Tab for å fullføre det grå forslaget.": "Press Tab to complete the grey suggestion.",
"Demo. Produkter og bilder er hentet fra sor-tre.no. Lager, brukere, anmeldelser og ordrer er oppdiktet. Ingen ekte betaling.":
  "Demo. Products and images come from sor-tre.no. Stock, users, reviews and orders are made up. No real payment.",
"Sørpedia er en prototype. Ikke tilknyttet Sør-Tre AS. Spesifikasjoner merket «Utledet» eller «Kjøper» er ikke kontrollert av Sør-Tre.":
  "Sørpedia is a prototype. Not affiliated with Sør-Tre AS. Specifications marked “Derived” or “Buyer” are not checked by Sør-Tre.",
"Forsiden": "Home", "Sammenlign": "Compare", "Send": "Send", "Avbryt": "Cancel", "Lagre": "Save", "Fjern": "Remove", "og": "and", "til": "to", "fra": "from",
"Fra": "From", "Til": "To", "Obs": "Note", "Sum": "Total", "Ingen": "None", "Ukjent": "Unknown", "ukjent": "unknown", "Kilde": "Source",
"Veiledning": "Guide", "Har monteringsveiledning": "Has an assembly guide", "Monteringsveiledning": "Assembly guide",
"Fant ikke siden": "Page not found", "Fant ikke produktet": "Product not found", "Fant ikke veiledningen": "Guide not found",
"Prøv søket øverst, eller <a href=\"/guide\">la Søren hjelpe deg</a>.": "Try the search at the top, or <a href=\"/guide\">let Søren help you</a>.",
"Kasse": "Checkout", "Produktguiden": "Product guide", "Forslag fra Søren": "Suggestions from Søren", "Forslag fra produktguiden": "Suggestions from the product guide",
"Sammenligning": "Comparison", "Kundeservice": "Customer service", "Flettekø": "Merge queue", "Admin": "Admin", "Sørpedia": "Sørpedia",
# Roles, status, payment
"bruker": "customer", "ansatt": "employee", "support": "support", "admin": "admin",
"aktiv": "active", "flettet": "merged", "avvist": "rejected", "tilbakestilt": "reverted", "status": "status",
"Vipps": "Vipps", "Klarna": "Klarna", "Kort": "Card",
# Categories (Catalog::THEMES)
"Lys og lykter": "Lights", "Hodelykter, lommelykter, arbeidslys og batterier": "Headlamps, flashlights, work lights and batteries",
"Bygg og feste": "Building", "Hammer, spiker, skruer, beslag og måleverktøy": "Hammers, nails, screws, brackets and measuring tools",
"Maling og sparkel": "Paint and filler", "Ruller, pensler, sparkel, maskering og slipepapir": "Rollers, brushes, filler, masking and sandpaper",
"Balkong og levegg": "Balcony", "Balkongglass, stendere og dekklister": "Balcony glass, posts and cover strips",
"Kjetting og løft": "Chain and lifting", "Sjakler, kjetting og kroker": "Shackles, chain and hooks",
"Skog og ved": "Forest and wood", "Sagkjeder, filer, hansker og hørselvern": "Saw chains, files, gloves and hearing protection",
"Tur og friluft": "Outdoors", "Kikkerter, grill og kokeutstyr": "Binoculars, grills and cooking gear",
# The product guide (Guide::QUESTIONS)
"Hva skal du gjøre?": "What do you want to do?", "Se bedre i mørket": "See better in the dark", "Snekre eller feste noe": "Build or fix something",
"Male eller sparkle": "Paint or fill", "Skjerme balkongen": "Shelter the balcony", "Løfte, sikre eller fortøye": "Lift, secure or moor",
"Sage eller hogge ved": "Cut or chop firewood", "Tur, båt eller grilling": "Hiking, boating or grilling",
"Hvor skal det brukes?": "Where will you use it?", "Inne": "Indoors", "Ute": "Outdoors", "Ved sjøen eller i båten": "By the sea or in the boat", "Litt av hvert": "A bit of everything",
"Hva vil du bruke?": "What do you want to spend?", "Under 200 kr": "Under 200 kr", "200 til 1 000 kr": "200 to 1 000 kr", "Over 1 000 kr": "Over 1 000 kr", "Spiller ingen rolle": "Doesn't matter",
"Hvor vant er du med dette?": "How experienced are you?", "Første gang": "First time", "Har gjort det før": "Done it before", "Gjør det ofte, gjerne proff": "Often, maybe professionally",
"Fire raske spørsmål. Søren forklarer hvorfor han foreslår det han gjør.": "Four quick questions. Søren explains why he suggests what he does.",
"{n} av {of}": "{n} of {of}", "Se forslag": "See suggestions", "Søren foreslår": "Søren suggests", "Ta guiden på nytt": "Take the guide again",
"Hvorfor passer disse forslagene for meg, og hva er forskjellen på dem?": "Why do these suggestions suit me, and how do they differ?",
"Spør Søren om forslagene": "Ask Søren about the suggestions", "Beste treff": "Best match", "Populær i denne kategorien": "Popular in this category",
"Legg i kurv": "Add to cart", "Dette passer sammen med {name}": "This goes with {name}", "Slik ble forslagene valgt": "How the suggestions were chosen",
"Rangeringen bruker ikke språkmodellen. Da er den rask, lik hver gang og kan ikke finne på produkter. Vil du ha det forklart med ord, trykk «Spør Søren om forslagene».":
  "The ranking doesn't use the language model. That keeps it fast, the same every time, and unable to make up products. For an explanation in words, press “Ask Søren about the suggestions”.",
"Tema: {theme} (fra svaret ditt på hva du skal gjøre)": "Category: {theme} (from your answer to what you want to do)",
"Budsjett: {b} (+4 innenfor, minus 6 over)": "Budget: {b} (+4 within, minus 6 above)",
"Budsjett: {b} (bare produkter i dette prisområdet)": "Budget: {b} (only products in this price range)",
"Endre": "Change",
"Andre med lignende svar fant det de lette etter her": "Others with similar answers found what they were looking for here",
"Fant du det du lette etter?": "Did you find what you were looking for?",
"Ja: {name}": "Yes: {name}",
"Nei, jeg lette etter:": "No, I was looking for:",
"Svaret ditt gjør guiden bedre for neste kunde med lignende svar.": "Your answer makes the guide better for the next customer with similar answers.",
"Takk! Guiden lærer av svaret ditt.": "Thanks! The guide learns from your answer.",
"Til slutt: hva tidligere kunder med lignende svar sa var riktig (opptil +6).": "Finally: what earlier customers with similar answers said was right (up to +6).",
"Velg produktet": "Choose the product",
"Neste": "Next",
"Noen raske spørsmål, fra bredt til smalt. Hvert svar bestemmer neste spørsmål.": "A few quick questions, from broad to narrow. Each answer decides the next question.",
"Spørsmål {n}": "Question {n}",
"Type: {type}": "Type: {type}",
"{n} produkter passer": "{n} products match",
"Hva slags?": "What kind?",
"Lyskilder": "Bulbs", "Lykt": "Torches and headlamps", "Lykter og batterier": "Lamps and batteries", "Arbeidslys": "Work lights",
"Husholdningsbatteri": "Household batteries", "Ladere og invertere": "Chargers and inverters", "Batterier 12V": "12V batteries",
"Skruer stål": "Steel screws", "Beslag": "Brackets and fittings", "Spiker": "Nails", "Kapp- og Slipeskive": "Cutting and grinding discs",
"Skyvelær og Tommestokk": "Calipers and folding rules", "Hammer og Slegge": "Hammers and sledgehammers",
"Rulle og Pensel": "Rollers and brushes", "Sparkel": "Filler", "Skrape og stålbørste": "Scrapers and wire brushes", "Maskering": "Masking", "Sandpapir": "Sandpaper",
"Glass": "Glass", "Stendere": "Posts", "Dekklist": "Cover strips",
"Sjakkel": "Shackles", "Kjetting": "Chain", "Kroker": "Hooks",
"Arbeidshansker": "Work gloves", "Motorsag": "Chainsaws", "Hogsttilbehør": "Wood cutting accessories", "Hørselvern": "Hearing protection",
"Grill og kokeutstyr": "Grills and cookware", "Kikkerter": "Binoculars",
"Bruksområde: {u} (+3 ved treff, minus 3 ved motsatt)": "Use: {u} (+3 when it matches, minus 3 when opposite)",
"Erfaring: {e}": "Experience: {e}", "(sett prioriteres)": "(kits come first)",
"Deretter vurderinger fra kjøpere, lager i ditt varehus og kjente koblinger. {n} produkter ble vurdert.": "Then buyer ratings, stock in your store and known matches. {n} products were considered.",
"Innenfor budsjettet ditt": "Within your budget", "Merket for {use} bruk (utledet fra produkttekst)": "Marked for {use} use (derived from the product text)",
"Komplett sett, fint første gang": "Complete kit, good for a first time", "Profesjonell kvalitet": "Professional quality",
"Snitt {r} av 5 fra {n} kjøpere": "Average {r} of 5 from {n} buyers", "På lager i {wh}": "In stock in {wh}", "valgt varehus": "the chosen store",
"Vi vet hva som passer sammen med den": "We know what goes with it",
# Home page
"Finn riktig utstyr, og alt som hører til": "Find the right gear, and everything that goes with it",
"Svar på fire spørsmål, så foreslår Søren produkter og forklarer hvorfor. Du ser også hva som passer sammen, hva andre kjøpere har lagt til av info, og om varen er inne i ditt varehus.":
  "Answer four questions and Søren suggests products and explains why. You also see what goes together, what other buyers have added, and whether the item is in stock in your store.",
"Start produktguiden": "Start the product guide", "Søren, maskoten til Sørpedia": "Søren, the Sørpedia mascot", "Populært nå": "Popular now",
"To versjoner av produktinfo": "Two versions of product info",
"<b>Verifisert</b> er det Sør-Tre selv skriver. <b>Åpen</b> kan utvides av kjøpere som har handlet hos oss, slik som Wikipedia. Hver verdi viser hvor den kommer fra.":
  "<b>Verified</b> is what Sør-Tre writes itself. <b>Open</b> can be extended by buyers who have shopped with us, like Wikipedia. Every value shows where it comes from.",
"Passer sammen": "Goes together",
"Vi viser bare at noe <i>passer</i> når Sør-Tre har skrevet det, eller når en tydelig regel sier det, som at kjetting og sjakkel har samme dimensjon.":
  "We only say something <i>fits</i> when Sør-Tre has written it, or a clear rule says so, like a chain and shackle having the same size.",
"Ditt varehus": "Your store", "Velg varehus øverst for å se hva som står på hylla, hva som ligger på nettlager og hva som kan bestilles.":
  "Choose a store at the top to see what's on the shelf, what's in the online warehouse and what can be ordered.",
# Product cards and lists
"Ingen bilde": "No image", "{n} i butikk": "{n} in store", "Nettlager": "Online stock", "nettlager": "online stock", "ikke på lager": "out of stock",
"Bestilling, ca. {d}": "Order, about {d}", "Utsolgt": "Sold out", "{n} produkter": "{n} products", "Filtrer": "Filter", "Nullstill alle": "Clear all",
"Pris": "Price", "Pris fra": "Price from", "Pris til": "Price to", "Oppgitt for {n} produkter": "Given for {n} products",
"Sør-Tre har ikke dette som eget felt. Verdien er tolket fra produktteksten.": "Sør-Tre doesn't have this as a separate field. The value is read from the product text.",
"Utledet fra produkttekst": "Derived from the product text", "Bruk filter": "Apply filter", "Sorter": "Sort",
"Mest populære": "Most popular", "Best vurdert": "Best rated", "Laveste pris": "Lowest price", "Høyeste pris": "Highest price", "Navn": "Name",
"Ingen produkter passer filtrene. Mange produkter mangler tall for enkelte filtre fordi Sør-Tre ikke oppgir dem.": "No products match the filters. Many products lack numbers for some filters because Sør-Tre doesn't list them.",
"Nullstill filtre": "Clear filters", "{n} av 5": "{n} of 5",
# Search
"{n} treff": "{n} results", "Viser treff for <b>{fixed}</b>. Søkte du på «{q}»?": "Showing results for <b>{fixed}</b>. Did you search for “{q}”?",
"Søkte på norsk: <b>{no}</b>. Produktene har norske navn fra Sør-Tre.": "Searched in Norwegian: <b>{no}</b>. Products have Norwegian names from Sør-Tre.",
"Søren tolket søket som en oppgave": "Søren read the search as a task", "Se alt i {theme}": "See everything in {theme}",
"Ingen produkter matcher «{q}».": "No products match “{q}”.", "Sør-Tre selger ikke alt på nett.": "Sør-Tre doesn't sell everything online.",
"<a href=\"/guide\">Prøv produktguiden</a> eller <a href=\"#\" data-open-soren>spør Søren</a>. Søkeord uten treff lagres slik at butikken ser hva kundene leter etter.":
  "<a href=\"/guide\">Try the product guide</a> or <a href=\"#\" data-open-soren>ask Søren</a>. Searches without results are saved so the store sees what customers look for.",
"Kanskje dette?": "Maybe this?",
# Product page
"Zoom inn": "Zoom in", "Zoom ut": "Zoom out", "Bilde fra Sør-Tre": "Image from Sør-Tre", "Ingen bilder fra Sør-Tre": "No images from Sør-Tre", "Bilde {n}": "Image {n}",
"Klikk på bildet for å zoome inn, og klikk igjen for å zoome ut.": "Click the image to zoom in, and click again to zoom out.",
"{n} bilde": "{n} image", "{n} bilder": "{n} images", "Mangler: ferdig montert og i bruk.": "Missing: assembled and in use.", "Merke": "Brand",
"{n} anmeldelse fra kjøpere": "{n} review from buyers", "{n} anmeldelser fra kjøpere": "{n} reviews from buyers", "Ingen anmeldelser ennå": "No reviews yet",
"I butikk": "In store", "{n} stk": "{n} pcs", "ikke inne": "not in stock", "{n} stk, sendes i dag": "{n} pcs, ships today", "tomt": "empty",
"Bestillingsvare: ventes ca. {d}": "Order item: expected around {d}", "Se alle varehus": "See all stores", "Butikk": "Store", "Nett": "Online",
"Antall": "Quantity", "Legg i handlekurv": "Add to cart", "Legg til sammenligning": "Add to comparison", "Passer ikke": "Doesn't fit",
"Fortell meg kort om {name}. Hva passer den til, og hva bør jeg tenke på?": "Tell me briefly about {name}. What does it fit, and what should I keep in mind?",
"Spør Søren om dette produktet": "Ask Søren about this product", "Passer sammen med": "Goes with",
"Merket <span class=\"lvl lvl-dokumentert\">Dokumentert</span> når Sør-Tre skriver det selv, og <span class=\"lvl lvl-regelutledet\">Regel</span> når vi har en tydelig regel. Hold over merket for begrunnelsen.":
  "Marked <span class=\"lvl lvl-dokumentert\">Documented</span> when Sør-Tre says so itself, and <span class=\"lvl lvl-regelutledet\">Rule</span> when we have a clear rule. Hover the label for the reason.",
"Last ned PDF": "Download PDF", "Spesifikasjoner": "Specifications", "Verifisert": "Verified", "Åpen (Sørpedia)": "Open (Sørpedia)",
"Bare det Sør-Tre selv oppgir, eller som en ansatt har godkjent.": "Only what Sør-Tre states itself, or what an employee has approved.",
"Sør-Tre oppgir ingen tekniske data for dette produktet. Se den åpne versjonen.": "Sør-Tre gives no technical data for this product. See the open version.",
"Alt vi vet, inkludert det som er tolket fra teksten og lagt inn av kjøpere. Hold over merket for å se kilden. Ser du en feil, trykk på {icon} eller høyreklikk på raden.":
  "Everything we know, including what's read from the text and added by buyers. Hover the label to see the source. See a mistake? Press {icon} or right-click the row.",
"Høyreklikk for å melde fra om feil.": "Right-click to report a mistake.", "Meld fra om feil": "Report a mistake", "Meld fra om feil i {label}": "Report a mistake in {label}",
"Ingen kilde oppgir dette ennå": "No source gives this yet", "Ny egenskap, f.eks. «Ladetid»": "New property, e.g. “Charging time”", "Verdi": "Value", "Enhet": "Unit",
"Blir en ny rad, ingen migrering": "Becomes a new row, no migration", "Kilde eller kommentar": "Source or comment",
"Hvor har du dette fra? F.eks. «målte selv» eller lenke til datablad": "Where is this from? E.g. “measured myself” or a link to a data sheet",
"Lagre i åpen versjon": "Save to the open version",
"Har du kjøpt dette produktet? Som <b>verifisert kjøper</b> (bekreftet e-post og minst ett kjøp) kan du fylle inn det som mangler.":
  "Bought this product? As a <b>verified buyer</b> (confirmed email and at least one purchase) you can fill in what's missing.",
"Endringshistorikk": "Change history", "Vanlige grunner": "Common reasons", "Feil tall eller enhet": "Wrong number or unit",
"Gjelder et annet produkt": "About a different product", "Står annerledes på pakken": "The package says otherwise", "Hva er feil?": "What's wrong?",
"Hva er feil? For eksempel «pakken sier 220 lumen»": "What's wrong? For example “the package says 220 lumens”",
"Du trenger ikke logge inn. Meldingen går til en ansatt, og ingen andre ser hvem som sendte den.": "You don't need to log in. The report goes to an employee, and nobody else sees who sent it.",
"Beskrivelse fra Sør-Tre": "Description from Sør-Tre", "Produktbeskrivelsen kommer fra Sør-Tre og finnes bare på norsk.": "The product description comes from Sør-Tre and is only available in Norwegian.",
"Se produktet på sor-tre.no": "See the product on sor-tre.no", "Anmeldelser": "Reviews", "Bare kunder som har kjøpt produktet kan anmelde det.": "Only customers who bought the product can review it.",
"verifisert kjøp": "verified purchase", "Karakter": "Rating", "Tittel": "Title", "Hva bør andre vite?": "What should others know?", "Publiser anmeldelse": "Publish review",
"Andre kjøpte også": "Others also bought", "Basert på hva som er kjøpt sammen. Det betyr ikke at produktene passer sammen.": "Based on what was bought together. It doesn't mean the products fit together.",
# Sources and level (helpers.php)
"Sør-Tre": "Sør-Tre", "Står i Sør-Tre sin produkttekst": "Stated in Sør-Tre's product text", "Ansatt": "Employee", "Kontrollert og godkjent av en ansatt": "Checked and approved by an employee",
"Utledet": "Derived", "Tolket automatisk fra produktteksten, ikke kontrollert": "Read automatically from the product text, not checked",
"Kjøper": "Buyer", "Lagt inn av en verifisert kjøper, ikke kontrollert av Sør-Tre": "Added by a verified buyer, not checked by Sør-Tre",
"Dokumentert": "Documented", "Regel": "Rule", "Kjøpt sammen": "Bought together",
# Cart, checkout, order
"{b} passer ikke til {a}.": "{b} doesn't fit {a}.", "Handlekurven er tom.": "Your cart is empty.", "På lager i butikk": "In stock in store",
"Sendes fra nettlager": "Ships from the online warehouse", "Bestillingsvare": "Order item", "Til kassen": "Go to checkout", "Har du husket?": "Did you remember?",
"Ting som passer sammen med det du har i kurven. Hvert forslag sier hvorfor.": "Things that go with what's in your cart. Each suggestion says why.",
"Kjøpet gjør deg til verifisert kjøper hvis e-posten din er bekreftet.": "The purchase makes you a verified buyer if your email is confirmed.",
"Hentes i eller sendes fra": "Picked up at or shipped from", "Betaling": "Payment", "Bekreft kjøp": "Confirm purchase", "Takk for handelen!": "Thanks for your purchase!",
"Ordre {id}, betalt med {pay}.": "Order {id}, paid with {pay}.",
"Du er verifisert kjøper. Gå til et produkt du har kjøpt og velg <b>Åpen (Sørpedia)</b> under spesifikasjoner for å fylle inn det som mangler, eller skriv en anmeldelse.":
  "You're a verified buyer. Go to a product you bought and choose <b>Open (Sørpedia)</b> under specifications to fill in what's missing, or write a review.",
# Login, registration, inbox
"E-post": "Email", "Passord": "Password", "Ingen konto?": "No account?", "Testbrukere": "Test users",
"Alle har passordet <code>admin</code>. Klikk for å fylle inn.": "All have the password <code>admin</code>. Click to fill in.",
"Ingrid (kunde@) er verifisert kjøper og eier en H7R Core. Nora (ny@) har ikke bekreftet e-post. Erik (epost@) har bekreftet e-post, men har ikke handlet ennå.":
  "Ingrid (kunde@) is a verified buyer and owns an H7R Core. Nora (ny@) hasn't confirmed her email. Erik (epost@) has confirmed his email but hasn't bought anything yet.",
"For å bli <b>verifisert kjøper</b> må du bekrefte e-posten og handle minst én gang. Da kan du fylle inn produktinfo og skrive anmeldelser. E-posten finner du under Innboks øverst på siden.":
  "To become a <b>verified buyer</b> you confirm your email and buy at least once. Then you can fill in product info and write reviews. You'll find the email under Inbox at the top of the page.",
"Vises aldri for andre. Bidragene dine vises som «Kjøper #a1b2c3» til du eventuelt velger noe annet under Innstillinger.": "Never shown to others. Your contributions show as “Kjøper #a1b2c3” unless you choose otherwise in Settings.",
"Opprett konto": "Create account", "Har du konto?": "Have an account?",
"Prototypen sender ikke ekte e-post. Alt som ville blitt sendt til deg havner her.": "The prototype doesn't send real email. Everything that would be sent to you ends up here.",
"E-postene er på norsk.": "The emails are in Norwegian.", "for å se e-posten din.": "to see your email.", "Ingen e-post ennå.": "No email yet.",
# Guides and compare
"Demoveiledning, ikke fra produsenten. Følg alltid produsentens anvisning.": "Demo guide, not from the manufacturer. Always follow the manufacturer's instructions.",
"Veiledningen finnes bare på norsk.": "The guide is only available in Norwegian.", "Du trenger": "You need", "Skriv ut": "Print", "Produkter denne veiledningen gjelder": "Products this guide covers",
"Velg minst to produkter med «Sammenlign» på produktkortene.": "Choose at least two products with “Compare” on the product cards.",
"Kjøpere": "Buyers", "Lager her": "Stock here", "Ikke inne": "Not in stock",
"«Ukjent» betyr at verken Sør-Tre eller kjøpere har oppgitt verdien. Vi gjetter ikke.": "“Unknown” means neither Sør-Tre nor buyers have given the value. We don't guess.",
# Settings
"Profil": "Profile", "Utseende": "Appearance", "Personvern": "Privacy", "Rolle": "Role", "verifisert kjøper": "verified buyer",
"blir verifisert kjøper etter første kjøp": "becomes a verified buyer after the first purchase", "bekreft e-posten for å bli verifisert kjøper": "confirm your email to become a verified buyer",
"Profilbildet ditt": "Your profile picture", "Velg bilde": "Choose image",
"JPEG eller PNG, maks 3 MB. Bare du ser bildet: i menyen og i chatten med Søren. Posisjon og kameradata fjernes automatisk.": "JPEG or PNG, up to 3 MB. Only you see the picture: in the menu and in the chat with Søren. Location and camera data are removed automatically.",
"Fullt navn": "Full name", "Privat. Brukes bare i e-post til deg.": "Private. Only used in emails to you.", "Telefon": "Phone", "Valgfritt": "Optional",
"Privat. Bare kundeservice kan bruke det hvis du ber dem ringe deg.": "Private. Only customer service can use it if you ask them to call you.",
"Slik vises du for andre": "How others see you", "Gjelder anmeldelser og bidrag i Sørpedia, også de du allerede har skrevet.": "Applies to reviews and contributions in Sørpedia, including the ones you've already written.",
"Anbefalt": "Recommended", "Anonymt. Koden er laget av et tilfeldig tall og kan ikke spores tilbake til deg.": "Anonymous. The code is made from a random number and can't be traced back to you.",
"Bare fornavnet. Etternavn, e-post og telefon vises aldri.": "First name only. Last name, email and phone are never shown.", "Lagre profil": "Save profile",
"Lys eller mørk": "Light or dark", "Som enheten": "Like your device", "Følger innstillingen på telefonen eller datamaskinen din.": "Follows the setting on your phone or computer.",
"Lys": "Light", "Alltid lys bakgrunn.": "Always a light background.", "Mørk": "Dark", "Alltid mørk bakgrunn. Skåner øynene om kvelden.": "Always a dark background. Easier on the eyes at night.",
"Nå": "Now", "bekreftet": "confirmed", "ikke bekreftet": "not confirmed", "Endrer du den, må den nye adressen bekreftes før du er verifisert kjøper igjen.": "If you change it, the new address must be confirmed before you're a verified buyer again.",
"Ny e-post": "New email", "Endre e-post": "Change email", "Nåværende passord": "Current password", "Nytt passord": "New password", "Minst 8 tegn.": "At least 8 characters.",
"Gjenta nytt passord": "Repeat new password", "Bytt passord": "Change password", "Personvern og dine data": "Privacy and your data", "Opplysning": "Information", "Hvem ser den": "Who sees it",
"Navn, e-post, telefon": "Name, email, phone", "Bare du. Ansatte og kundeservice ser aliaset ditt.": "Only you. Employees and customer service see your alias.", "Profilbilde": "Profile picture",
"Bare du": "Only you", "Anmeldelser og bidrag": "Reviews and contributions", "Alle, som «{alias}»": "Everyone, as “{alias}”", "Ordrer": "Orders",
"Du og regnskapet. Lagres i fem år etter bokføringsloven.": "You and accounting. Kept for five years under the Norwegian Bookkeeping Act.", "Søk og klikk": "Searches and clicks",
"Telles uten å knyttes til kontoen din": "Counted without being linked to your account", "Last ned dataene mine": "Download my data", "Slett kontoen": "Delete account",
"Navn, e-post, telefon, profilbilde og anmeldelser slettes. Bidrag i den åpne versjonen blir stående som «Slettet bruker». Ordrer beholdes uten navn fordi loven krever det. Dette kan ikke angres.":
  "Name, email, phone, profile picture and reviews are deleted. Contributions to the open version stay as “Slettet bruker” (deleted user). Orders are kept without a name because the law requires it. This can't be undone.",
"Skriv passordet for å bekrefte": "Enter your password to confirm", "Slett kontoen for godt": "Delete the account for good",
# Messages (flash)
"Bare verifiserte kjøpere (bekreftet e-post og minst ett kjøp) kan redigere.": "Only verified buyers (confirmed email and at least one purchase) can edit.",
"Ingen endringer å lagre.": "No changes to save.", "Takk! Endringen er synlig i den åpne versjonen. En ansatt vurderer om den skal inn i den verifiserte.": "Thanks! The change is visible in the open version. An employee will consider moving it to the verified one.",
"Bare verifiserte kjøpere av dette produktet kan skrive anmeldelse.": "Only verified buyers of this product can write a review.", "Takk for anmeldelsen!": "Thanks for the review!",
"Lagt i handlekurven.": "Added to the cart.", "Logg inn for å fullføre kjøpet. Testbrukerne har passordet «admin».": "Log in to complete the purchase. The test users have the password “admin”.",
"Kjøpet er registrert. Du er nå en verifisert kjøper og kan redigere produktinfo og skrive anmeldelser!": "Purchase registered. You're now a verified buyer and can edit product info and write reviews!",
"Kjøpet er registrert. Bekreft e-posten din for å bli verifisert kjøper.": "Purchase registered. Confirm your email to become a verified buyer.",
"Feil e-post eller passord.": "Wrong email or password.", "Fyll inn navn, gyldig e-post og passord på minst 8 tegn.": "Fill in a name, a valid email and a password of at least 8 characters.",
"Det finnes allerede en bruker med den e-posten.": "A user with that email already exists.", "Konto opprettet. Vi har sendt en bekreftelse til innboksen.": "Account created. We've sent a confirmation to the inbox.",
"E-posten er bekreftet. Etter første kjøp blir du verifisert kjøper.": "Email confirmed. After your first purchase you'll be a verified buyer.", "Lenken er brukt eller ugyldig.": "The link is used or invalid.",
"Navnet kan ikke være tomt.": "The name can't be empty.", "Telefonnummeret ser ikke riktig ut. Bruk bare tall, for eksempel 912 34 567.": "The phone number doesn't look right. Use digits only, for example 912 34 567.",
"Profilen er lagret.": "Profile saved.", "Profilbildet er slettet.": "Profile picture deleted.", "Bildet må være mindre enn 3 MB.": "The image must be smaller than 3 MB.", "Velg et bilde først.": "Choose an image first.",
"Profilbildet er lagret. Posisjon og annen metadata i bildet ble fjernet.": "Profile picture saved. Location and other metadata in the image were removed.",
"Bruk et JPEG- eller PNG-bilde.": "Use a JPEG or PNG image.", "Bildet er for stort. Maks 6000 × 6000 piksler.": "The image is too large. Max 6000 × 6000 pixels.", "Klarte ikke å lese bildet. Prøv et annet.": "Couldn't read the image. Try another one.",
"Utseendet er lagret.": "Appearance saved.", "Feil passord. E-posten er ikke endret.": "Wrong password. The email wasn't changed.", "Skriv en gyldig e-postadresse.": "Enter a valid email address.",
"Det er den samme e-posten som før.": "That's the same email as before.", "Den e-posten er allerede i bruk.": "That email is already in use.",
"E-posten er endret. Bekreft den nye adressen fra innboksen for å være verifisert kjøper igjen.": "Email changed. Confirm the new address from the inbox to be a verified buyer again.",
"Nåværende passord er feil.": "The current password is wrong.", "Det nye passordet må ha minst 8 tegn.": "The new password must have at least 8 characters.", "Passordene er ikke like.": "The passwords don't match.",
"Passordet er endret.": "Password changed.", "Feil passord. Kontoen er ikke slettet.": "Wrong password. The account wasn't deleted.",
"Testbrukerne kan ikke slettes, ellers slutter demoen å virke. Registrer en egen bruker for å prøve sletting.": "The test users can't be deleted, or the demo would stop working. Sign up with your own user to try deleting.",
"Kontoen din er slettet. Bidrag i den åpne versjonen står igjen som «Slettet bruker».": "Your account is deleted. Contributions to the open version remain as “Slettet bruker” (deleted user).",
"Flettet inn i verifisert versjon.": "Merged into the verified version.", "Avvist og fjernet fra åpen versjon.": "Rejected and removed from the open version.",
"Verdien er fjernet fra den åpne versjonen.": "The value was removed from the open version.", "Meldingen er lukket. Verdien står som før.": "The report is closed. The value stays as before.",
"Fant ikke verdien du meldte fra om.": "Couldn't find the value you reported.", "Du har allerede meldt fra om denne verdien. En ansatt ser på det.": "You've already reported this value. An employee is looking at it.",
"Takk! En ansatt ser på verdien. Den er ikke endret ennå.": "Thanks! An employee will look at the value. It hasn't been changed yet.", "Skriv kort hva som er feil.": "Briefly write what's wrong.",
# Søren (server)
"Det kan jeg dessverre ikke hjelpe med. Jeg holder praten hyggelig for alle aldre. Spør meg gjerne om produkter, mål eller hva som passer sammen!": "Sorry, I can't help with that. I keep the chat friendly for all ages. Feel free to ask me about products, sizes or what goes together!",
"Resonnementet er skjult fordi det inneholdt ord som ikke passer for alle aldre.": "The reasoning is hidden because it contained words not suitable for all ages.",
"Jeg svarer ikke på meldinger med banning eller stygge ord. Spør gjerne på nytt.": "I don't reply to messages with swearing or offensive words. Feel free to ask again.",
"Jeg kan bare hjelpe med produkter og prosjekter hos Sør-Tre.": "I can only help with products and projects at Sør-Tre.",
"12 og 24 volt er for bil og båt, så de teller som motsatt.": "12 and 24 volt are for cars and boats, so they count as the opposite.",
"Jeg hjelper ikke med noe som kan skade mennesker. Er noen i fare, ring 113. Spør meg gjerne om hvordan verktøyet brukes trygt.": "I won't help with anything that could hurt people. If someone is in danger, call 113. Feel free to ask me how to use the tool safely.",
"Søren tenkte på engelsk denne gangen, så tankene vises ikke.": "Søren thought in English this time, so the thoughts aren't shown.",
"Søren tenkte på norsk denne gangen, så tankene vises ikke.": "Søren thought in Norwegian this time, so the thoughts aren't shown.",
"Søren våkner … (modellen lastes inn, bare første gang)": "Søren is waking up … (loading the model, first time only)",
"Søren stoppet tankene her og svarer med det han har kommet fram til.": "Søren stopped thinking here and answers with what he has worked out.",
"Merk: Søren brukte ukontrollert info fra Åpen-versjonen (skrevet av kjøpere eller gjettet) uten å si det tydelig: {list}. Sjekk Verifisert-fanen eller spør en ansatt.": "Note: Søren used unchecked info from the Open version (written by buyers or guessed) without saying so clearly: {list}. Check the Verified tab or ask an employee.",
"Obs: Søren nevnte {list}, som ikke er en pris fra butikken. Stol på prisen på produktkortet.": "Note: Søren mentioned {list}, which isn't a price from the store. Trust the price on the product card.",
"Søren brukte for lang tid, så her er svaret fra katalogen i stedet.": "Søren took too long, so here's the answer from the catalogue instead.",
"Søren tenkte for lenge, så her er svaret fra katalogen i stedet.": "Søren thought for too long, so here's the answer from the catalogue instead.",
"Søren svarte på feil språk, så her er svaret fra katalogen i stedet.": "Søren answered in the wrong language, so here's the answer from the catalogue instead.",
"Språkmodellen (Ollama) svarer ikke. Søren bruker enkle regler i stedet.": "The language model (Ollama) isn't responding. Søren uses simple rules instead.",
"Jeg fant ingen produkter som passer «{q}». Sør-Tre selger ikke alt på nett. Prøv produktguiden, eller trykk «Snakk med et menneske».": "I didn't find any products matching “{q}”. Sør-Tre doesn't sell everything online. Try the product guide, or press “Talk to a human”.",
"Her er det jeg fant i katalogen.": "Here's what I found in the catalogue.", "Du ser på {name}.": "You're looking at {name}.", "Beste treff er {name}.": "The best match is {name}.",
"{name} passer sammen med {list}.": "{name} goes with {list}.",
"Søren tenkte på engelsk denne gangen, så tankene vises ikke.": "Søren thought in English this time, so the reasoning is hidden.",
"Det du skriver høres ut som du har det veldig vondt, og det tar jeg på alvor. Du fortjener å snakke med et menneske nå.\n\nMental Helse hjelpetelefon: 116 123 (hele døgnet)\nKirkens SOS: 22 40 00 40 (hele døgnet)\nEr du i fare nå, ring 113.\n\nDu kan også trykke «Snakk med et menneske» under.": "What you write sounds like you are in a lot of pain, and I take that seriously. You deserve to talk to a person now.\n\nMental Helse helpline (Norway): 116 123 (24/7)\nKirkens SOS (Norway): 22 40 00 40 (24/7)\nIf you are in danger now, call 113.\n\nYou can also press “Talk to a person” below.", "Obs: {name} passer ikke.": "Note: {name} doesn't fit.",
# Catalog questions (CatalogIntent)
"Ingen av produktene som passer «{scope}» har monteringsveiledning.": "None of the products matching “{scope}” have an assembly guide.",
"Butikken har ingen monteringsveiledninger ennå.": "The store has no assembly guides yet.", "«{title}» ({n} produkter)": "“{title}” ({n} products)",
"Blant produktene som passer «{scope}» har {n} produkter monteringsveiledning: {list}.": "Among the products matching “{scope}”, {n} have an assembly guide: {list}.",
"Vi har {n} monteringsveiledninger: {list}.": "We have {n} assembly guides: {list}.",
"Produkter med veiledning har merket «Veiledning» på kortet, og du kan filtrere på det i hver kategori.": "Products with a guide carry the “Guide” label on the card, and you can filter on it in every category.",
"{name} ({r} av 5 fra {n} kjøpere)": "{name} ({r} of 5 from {n} buyers)", "{name} ({price})": "{name} ({price})", "Dårligst vurdert": "Worst rated", "Billigst": "Cheapest", "Dyrest": "Most expensive",
"blant «{scope}»": "among “{scope}”", "Vurderingene er meninger fra kjøpere.": "The ratings are buyers' opinions.",
"Ingen av disse produktene har anmeldelser ennå.": "None of these products have reviews yet.", "Fant ingen produkter som passer.": "Found no matching products.",
# Error pages
"Adressen eller skjemaet inneholdt noe vi ikke forstår.": "The address or form contained something we don't understand.",
"Noe gikk galt hos oss. Prøv igjen om litt.": "Something went wrong on our side. Try again in a moment.",
"Noe gikk galt. Prøv igjen, eller snakk med et menneske.": "Something went wrong. Try again, or talk to a human.",
"Ugyldig forespørsel": "Invalid request", "Noe gikk galt": "Something went wrong",
"Referanse: {ref}. Oppgi den hvis du kontakter oss.": "Reference: {ref}. Mention it if you contact us.", "Til forsiden": "Go to home page",
}
# Text in app.js (tr)
JS = {
"Beklager, noe gikk galt. Prøv igjen, eller snakk med et menneske.": "Sorry, something went wrong. Try again, or talk to a human.",
"Chat med Søren": "Chat with Søren", "Fjern {x}": "Remove {x}",
"Hei, jeg er Søren! Jeg kan finne produkter, forklare hva som passer sammen og svare på spørsmål om spesifikasjoner. Priser og lager henter jeg alltid fra butikken, ikke fra hukommelsen.":
  "Hi, I'm Søren! I can find products, explain what goes together and answer questions about specifications. I always get prices and stock from the store, never from memory.",
"Ingen treff. Trykk Enter for å søke, eller spør Søren.": "No results. Press Enter to search, or ask Søren.", "KI-assistent, kjører lokalt": "AI assistant, running locally",
"Klarte ikke å sende. Prøv igjen.": "Couldn't send. Try again.", "Kunne ikke kontakte kundeservice.": "Couldn't reach customer service.", "Lagt i kurv": "Added to cart", "Lukk": "Close",
"Melding til Søren": "Message to Søren", "Mente du <b>{x}</b>?": "Did you mean <b>{x}</b>?", "Mistet kontakten med kundeservice.": "Lost contact with customer service.",
"Ny samtale": "New chat", "Sammenlign": "Compare", "Se alt i {theme}": "See everything in {theme}", "Send": "Send", "Snakk med et menneske i stedet": "Talk to a human instead",
"Spør om produkter, mål eller hva som passer": "Ask about products, sizes or what fits", "Spør om {x}": "Ask about {x}", "Start en ny samtale": "Start a new chat",
"Svaret ble avbrutt fordi du byttet side. Spør gjerne igjen.": "The answer was interrupted because you changed page. Feel free to ask again.",
"Søren er en språkmodell som kjører lokalt på denne maskinen. Han ser bare offentlig produktinfo fra siden du er på, aldri kontoen din. Det han tenker er generert og kan være feil.":
  "Søren is a language model running locally on this machine. He only sees public product info from the page you're on, never your account. What he thinks is generated and can be wrong.",
"Søren får bare offentlig produktinfo fra denne siden. Han ser aldri passord, konto, handlekurv eller ordre.": "Søren only gets public product info from this page. He never sees passwords, account, cart or orders.",
"Søren ser:": "Søren sees:", "Søren tenker …": "Søren is thinking …", "Sørens resonnement (generert, kan være feil)": "Søren's reasoning (generated, may be wrong)",
"Tidligere søk": "Recent searches", "Tøm": "Clear", "Velg minst to": "Choose at least two", "Zoom inn": "Zoom in", "Zoom ut": "Zoom out", "bare produktinfo": "product info only",
"kundeservice": "customer service", "skriver om …": "rewriting …", "svarer …": "answering …", "tenker …": "thinking …", "våkner …": "waking up …",
}
# Values from the database (use case, material, color)
VALUES = {
"Bygg og snekring": "Building and carpentry", "Galvanisert stål": "Galvanized steel", "Varmgalvanisert stål": "Hot-dip galvanized steel", "Maling og overflate": "Paint and surface",
"Utendørs": "Outdoor", "Innendørs": "Indoor", "Løft og sikring": "Lifting and securing", "Marint": "Marine", "Skog og hage": "Forest and garden", "Belysning": "Lighting",
"Tur og friluft": "Outdoors", "Aluminium": "Aluminium", "Syrefast stål": "Acid-proof steel", "Glass": "Glass", "Tre": "Wood", "Messing": "Brass", "Elforsinket stål": "Electro-galvanized steel",
"Rustfritt stål": "Stainless steel", "Hvit": "White", "Svart": "Black", "Nitril": "Nitrile", "Stål": "Steel", "Nylon": "Nylon", "Kobber": "Copper", "Kompositt": "Composite",
"Latex": "Latex", "Polyester": "Polyester", "Gummi": "Rubber", "Blå": "Blue", "Frostet": "Frosted", "Klar": "Clear", "Rød": "Red", "Grønn": "Green", "Gul": "Yellow", "Grå": "Grey",
"Oransje": "Orange", "Plast": "Plastic", "Ja": "Yes", "Nei": "No",
}
# Specification types (spec_keys.key)
SPECS = {
"merke": "Brand", "materiale": "Material", "farge": "Colour", "bruksomrade": "Use", "lysstyrke_lm": "Brightness", "rekkevidde_m": "Beam range", "brenntid_t": "Burn time",
"batteri": "Battery", "ip_klasse": "Water resistance (IP)", "sokkel": "Socket", "effekt_w": "Power", "fallhoyde_m": "Drop resistance", "diameter_mm": "Diameter", "lengde_mm": "Length",
"bredde_mm": "Width", "kornstorrelse": "Grit", "pakning_stk": "Pieces per pack", "vekt_g": "Weight", "bredde_cm": "Width", "hoyde_cm": "Height", "tykkelse_mm": "Glass thickness",
"dimensjon_mm": "Size", "dimensjon_tomme": "Size (inches)", "arbeidslast_kg": "Working load (WLL)", "bruddstyrke_kg": "Breaking strength", "sverdlengde_tomme": "Bar length",
"drivlenker": "Drive links", "storrelse": "Size", "standard": "Standard", "volum_l": "Volume", "forstorrelse": "Magnification", "objektiv_mm": "Objective diameter",
}
# English search words to Norwegian words in the catalog. Plurals (-s, -es, -ies) are handled in Search::fromEnglish.
SEARCH = {
"screw": "skrue", "nail": "spiker", "bolt": "bolt", "nut": "mutter", "washer": "skive", "anchor": "plugg", "hook": "krok", "eye bolt": "øyebolt", "eye screw": "øyeskrue",
"bracket": "vinkel", "hinge": "hengsel", "staple": "stift", "dowel": "plugg", "rivet": "nagle", "measuring tape": "målebånd", "tape measure": "målebånd", "level": "vater",
"spirit level": "vater", "ruler": "tommestokk", "folding rule": "tommestokk", "pencil": "blyant", "chisel": "stemjern", "screwdriver": "skrutrekker", "wrench": "fastnøkkel",
"spanner": "fastnøkkel", "pliers": "tang", "drill bit": "bor", "bit": "bits", "saw": "sag", "handsaw": "håndsag", "hacksaw": "baufil", "knife": "kniv", "utility knife": "tapetkniv",
"flashlight": "lommelykt", "torch": "lommelykt", "headlamp": "hodelykt", "head lamp": "hodelykt", "headlight": "hodelykt", "lantern": "lykt", "light": "lys", "lamp": "lampe",
"work light": "arbeidslys", "floodlight": "lyskaster", "bulb": "lyspære", "light bulb": "lyspære", "battery": "batteri", "charger": "lader", "candle": "lys",
"paint": "maling", "brush": "pensel", "paintbrush": "pensel", "roller": "rull", "paint roller": "malerull", "filler": "sparkel", "putty": "sparkel", "spackle": "sparkel",
"masking tape": "maskeringstape", "sandpaper": "slipepapir", "sanding": "slipe", "primer": "grunning", "tray": "brett", "scraper": "skrape", "ceiling": "tak", "wall": "vegg",
"balcony": "balkong", "glass": "glass", "railing": "rekkverk", "post": "stender", "screen": "levegg", "fence": "levegg", "windbreak": "levegg",
"chain": "kjetting", "shackle": "sjakkel", "rope": "tau", "lifting": "løft", "sling": "stropp", "strap": "stropp", "mooring": "fortøyning", "anchor chain": "ankerkjetting",
"chainsaw": "motorsag", "saw chain": "sagkjede", "file": "fil", "glove": "hanske", "gloves": "hansker", "ear protection": "hørselvern", "hearing protection": "hørselvern",
"ear muffs": "hørselvern", "earmuffs": "hørselvern", "axe": "øks", "ax": "øks", "firewood": "ved", "wood": "tre", "log": "ved", "splitter": "kløyver", "bar": "sverd",
"binoculars": "kikkert", "telescope": "kikkert", "grill": "grill", "barbecue": "grill", "bbq": "grill", "stove": "kokeapparat", "cooker": "kokeapparat", "pot": "kjele",
"pan": "panne", "cup": "kopp", "thermos": "termos", "camping": "tur", "hiking": "tur", "boat": "båt", "outdoor": "utendørs", "indoor": "innendørs", "waterproof": "vanntett",
"rechargeable": "oppladbar", "stainless": "rustfri", "galvanized": "galvanisert", "steel": "stål", "aluminium": "aluminium", "aluminum": "aluminium", "brass": "messing",
"white": "hvit", "black": "svart", "red": "rød", "blue": "blå", "green": "grønn", "kit": "sett", "set": "sett",
}

import json
def php_str(s): return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"
def php_arr(d): return "[\n" + "\n".join(f"        {php_str(k)} => {php_str(v)}," for k, v in d.items()) + "\n    ]"
out = """<?php
declare(strict_types=1);

// GENERATED by scripts/i18n_en.py. Change the word list there and run the script again.

/** English translations. Key = Norwegian text. See src/I18n.php. */
final class I18nEn
{
    public const T = """ + php_arr(T) + """;

    /** Text in app.js, written into the page as window.I18N when the language is English. */
    public const JS = """ + php_arr(JS) + """;

    /** Values from the database (use case, material, color). */
    public const VALUES = """ + php_arr(VALUES) + """;

    /** Specification types (spec_keys.key => English name). */
    public const SPECS = """ + php_arr(SPECS) + """;

    /** English search words => Norwegian words in the catalog. */
    public const SEARCH = """ + php_arr(SEARCH) + """;
}
"""
import os
open(os.path.join(os.path.dirname(__file__), '..', 'src', 'I18nEn.php'), 'w').write(out)
print(len(T), len(JS), len(VALUES), len(SPECS), len(SEARCH))
