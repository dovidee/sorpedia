# 027 Guided tours for Byggelab and Control Tower

## Goal

The two pages with the most controls explain themselves on the first visit without taking the page over, and the whole workflow can be shown from start to finish in one run.

## Scope

1. One shared module, `public/assets/tour.js` with `public/assets/tour.css`, used by `byggelab.js` and `tower.js`.
2. Non-blocking: a soft grey veil with a cut-out around the element being explained, and a small card beside it. The veil never takes clicks, focus is never trapped, the page keeps working. Esc or the close button ends the tour.
3. Shown once per page on the first visit (browser storage, a convenience only), again from the page's "Omvisning" button, or with `?omvisning` in the address.
4. A step can wait for the action it explains and then move on by itself; "Neste" and "Tilbake" always work.
5. Byggelab steps: choose a builder, add a product (moves on when one is added), the workbench, the check, remove a product (moves on when one is removed), undo and history, test run (moves on when the run is done), save, export and ask Søren, add the setup to the cart (moves on when it is in the cart), on to checkout.
6. Control Tower steps, each switching to its view first: the four views, the floor, a contribution from a verified buyer (opens the first product with an open contribution, with Merge and Reject), key figures, the log, order a demo delivery, the map with weather, road messages and live ferries, the shipments; customer service in six steps (the cases, the conversation with Søren and the thread, replying with or without Søren's draft, the customer's orders and refunds, closing the case, searches without results); insight with opportunities, ask Søren.
7. Seed data: `scripts/import.php` creates exactly one open contribution, from a verified buyer (confirmed email and a paid order), on the H7R Core headlamp, so the buyer step has one edit to merge or reject. It also creates one open customer service case (demo) from the same buyer about the same headlamp, with her conversation with Søren, one message from her and her paid orders beside it, so reply, Søren's draft and refund can be shown. Demo text is marked "(demo)".

## Rules

1. Tour text is customer and staff UI text: Norwegian bokmål with English through the language toggle, no dashes.
2. The tour reads only what the page already shows. It never sends anything and changes no data; the actions are the page's own.
3. With reduced motion the spotlight and card do not animate.

## Acceptance

1. A first visit to `/byggelab/{theme}` and `/ansatt/tarn` shows the tour; a second visit does not; "Omvisning" starts it again.
2. While the tour is open, the products list, bench, buttons and views still respond to clicks.
3. In Byggelab, adding, removing, test running and adding to the cart each move the tour on without "Neste".
4. In the Control Tower every step shows its view; the buyer step shows the contribution with Merge and Reject.
