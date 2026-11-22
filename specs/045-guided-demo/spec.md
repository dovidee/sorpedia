# 045 Guided demo

Status: implemented and verified. Priority: P2.

## Problem

A presentation has to show every part of the demo while the presenters talk. Clicking through by hand takes attention away from the talk and depends on remembering the order.

## Decision

An «Autodemo» button on the Demo Hub (`/presentasjon`), below the theme toggle, starts a guided tour in the same tab. `public/assets/autodemo.js` is loaded by the hub, the shop layout and the Control Tower. The step index, start time and paused time are kept in sessionStorage, so the tour continues across page loads; nothing is stored on the server for the tour itself.

Each step shows a caption (title, a sentence to talk over, step number, elapsed time against 5:00, a progress bar) and then acts through the real interface: typing into fields, clicking buttons and submitting forms, with the element outlined while it is used. Pause and Stop are always visible, and Escape stops. A step that submits a form waits for the next page, so a pending POST is never cut off.

The steps, in order: log out and log in as the demo customer; front page; search; the product page with sources and levels; three questions to Søren (a fact, a recommendation, the follow-up comparison) and simple arithmetic; add to cart, cart, checkout and the order page; Byggelab with a preset and a test run; a request for a human in the chat, which creates a customer service case, and a refund request in it; log out and log in as staff; Control Tower overview; Kundeservice, where the case is opened and the order refunded; Innsikt, where one missing product goes on the order list, and the trends tab; Transport, where the clock plays, the order list is fetched, the speed goes up and the storm scenario is shown and turned off; Gulv; back to the hub.

The product page used is chosen on the server from the database (a Ledlenser H7R Core in stock in the receiving store), and the button is disabled when none is in stock. The demo users share the seeded demo password.

The tour writes real demo rows: one order for the demo customer, which is refunded, one customer service case, one line on the order list and the trips the order list creates. The captions are Norwegian only, like the rest of the hub.

## Acceptance criteria

1. The Autodemo button sits below «Mørk visning» on the hub and starts the tour.
2. The tour runs from the hub back to the hub in under five minutes, with time on each page to talk.
3. The purchase creates an order, and the refund in Kundeservice marks it refunded.
4. Pause holds the tour; Stop and Escape end it and remove the caption.
