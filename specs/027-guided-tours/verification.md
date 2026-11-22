# 027 Verification

Run in the app (headless Chrome against the dev server, logged in as admin):

Byggelab `/byggelab/lys?omvisning`: the tour opened on "Velg hva du skal bygge" with the builder row lit and the rest greyed. Clicking + in the product list moved it from "Legg til et produkt" to the next step; "Fjern" in the check panel moved it from "Fjern et produkt"; "Testkjør" moved it on when the run finished; "Legg oppsettet i kurven" moved it to "Videre til kassen". The clicks went to the page while the tour was open.

Control Tower `/ansatt/tarn?omvisning`: "Neste" through all eleven steps showed each step's view (gulv, transport, kundeservice, innsikt, back to gulv). The buyer step opened a product with an open contribution and its Merge and Reject buttons. Without `?omvisning` the tour also opened on a first visit (empty browser storage).

Syntax checked: `php -l` and `node --check` on the changed files.

Customer service steps: "Neste" through the six steps lit the case list, the conversation, the reply field, the customer and orders panel, the close button and the searches without results. On the current data the open case is a guest without orders, so no refund lines were shown.

Not verified: the seed changes (one buyer contribution and the demo case) were only syntax checked: the database in use was built before them, an isolated database could not be created with the application's database user, and a full import also rewrites the manual PDFs (the database in use was built before it and still holds the earlier contributions; the tour picks the first open one either way); phone width; the English text in the browser; keyboard only use of the card.

Known weaknesses: the card position is recomputed every 300 ms, so it can lag a moving 3D label; a step whose element is hidden (for example on a narrow screen) shows the card centred without a spotlight.
