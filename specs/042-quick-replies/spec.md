# 042 Quick replies

Status: implemented and verified. Priority: P1 (trust), P2 (speed).

## Problem

A greeting went through the retriever, the model reading and the small talk model, and took up to 162 s. Messages that need no language understanding should be answered at once, without the model.

## Decision

`QuickReply::match($q)` runs right after the blocked-input check in the shop chat and the public API. It normalizes the whole message (lower case, harmless punctuation removed, whitespace collapsed) and matches it exactly against a short list:

1. Greetings: hi, hei, hello, hallo, hey, yo, god morgen, god dag, god kveld.
2. Thanks and acknowledgements: takk, ok, greit.
3. Identity: hvem er du, hva er du, who are you.
4. Help: hva kan du, hvordan fungerer dette, what can you do, hjelp.
5. Filler: test, asdf, lol.
6. Weather and ferries: a message with a weather or ferry word and no product type. Customers are told the shop chat does not cover it and are offered help with products; no internal address is shown. Staff (roles ansatt, support, admin) get the link to the Control Tower, `/ansatt/tarn`. The Control Tower stays staff only.

A match is answered with a fixed text from PHP (`t()`, Norwegian and English) and recorded in the chat history; no product cards, no retriever, no catalogue lookup and no call to Ollama. The route and its time are traced (`route: fast:<kind>`). Anything else, including a greeting followed by a question («hei, har dere hodelykter?»), goes on to the normal path.

The list is not a ranking of customer questions. A data-derived list of the most asked questions was examined and could not be built; see the evidence report in `verification.md`. Homework, coding questions and jokes are left to the existing path (blocked input, then small talk).

## Acceptance criteria

1. The listed forms match whatever the case, spacing and punctuation.
2. A greeting with a question, and any product, cart or order question, is not matched.
3. A match makes no call to Ollama (`Chat::$calls`).
4. Customers never get an internal address; staff get the Control Tower link.
5. Measured server time under 50 ms for a match.
