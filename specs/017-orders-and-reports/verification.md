# Verification: 017 own orders and error reports

Levels used below: run in the app (`php -S` on a separate port, requests with curl and captures with headless Chromium at 1280 and 390 px), tested (automated test passed), inspected, not verified.

## Run in the app

Logged in as the seeded customer: a report through the dialog closed it, showed the notice bottom left with a link to the inbox, and the inbox icon went from hidden to 1. The inbox showed the confirmation marked as new, and the counter was gone after opening it. Four reports gave three rows in Innstillinger and a "Se alle feilmeldingene (4)" button; the full list showed four. Taking one back removed it and showed a message; taking the same one back again gave the "already handled or taken back" message. The order page rendered with date, payment, store, lines with images, VAT and the withdrawal deadline, in Norwegian and English. Another customer's order id redirected to Innstillinger.

The page context for Innstillinger and the order page was printed from the CLI: orders, deadlines and reports are there, and the customer's email, name and phone are not in the built prompt. Another customer's order gives no context, and a guest gets none.

The empty states for the merge queue and customer service were rendered from the templates with empty data, because the database had open items.

Test rows made during the check were deleted afterwards.

## Tested

`php tests/run.php`: 105 tests, 0 failed. PHPStan level 3: no errors. `node --check` on app.js.

## Not verified

A real answer from the language model about an order or a report (Ollama was not called). The "Takk for handelen!" heading right after checkout was not run end to end, to avoid adding a demo order.

## Known weaknesses

The withdrawal period counts from the order date, not from delivery. Taking back a report deletes it, so the staff count drops without a note.
