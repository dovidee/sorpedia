# 036 Answer for models not in the catalogue

## Goal

When the customer asks for a product model that Sør-Tre does not sell, the server says so in one complete sentence, instead of the language model guessing or leaving a sentence unfinished. The server says a product is not sold only when it is sure the customer named a model code that is not in the catalogue. Not found by search is not the same as not sold.

## Before

`ProductRetriever` already noticed a model code with no product (for example DUC254) and then kept word hits out, so no substitute product was shown. It did not report the code, so the question went to the model with an empty fact list, and the answer could end in a colon where a removed price reference had been.

## Detection

In `ProductRetriever::analyse`, which already finds the codes in the question. A code counts as unknown when all of these hold:

1. No product name has it as its own code or mentions it, and it is not a series number shared by many names.
2. It is shaped like a product model: lower-case ASCII letters, digits and optional letters, three characters or more, with two letters or more before the digits (DUC254, XT40) or letters on both sides (H9R). A single letter and digits (P120, K80, E27, M8, A60) are grits, sockets, threads and bulb shapes and never count; numbers with a unit (18V, 500lm, 2x4) are not codes at all.
3. The letter prefix is not a standard or size: IP, GU, MR, BA, BAY, CR, LR, EN, ISO, DIN, DN, PN, NR, CO, RJ, USB, HDMI, AA, AAA.
4. It is not a token of any product name or SKU in the catalogue.
5. It is not a likely typo or part of a code the catalogue has: a catalogue code with the same digits that is one edit away (H7RR, H7T), has the same letters in another order (HR7), or starts with it or is started by it (H7, HF6, H7R2).

`ProductRetriever::forChat` returns these in `unknown`, as the customer wrote them: the code in capitals, a capitalised brand right before it unless it starts the sentence («Makita DUC254»), and up to two capitalised words after it («H9R Signature»). Only letters, digits, spaces and hyphens are kept, at most 40 characters. Ranking, scoring and the product list are unchanged.

## Answer

`AnswerGuard::notSold($names)` gives «Vi har dessverre ikke H9R Signature i sortimentet.» (several names: «… ikke H9R eller XT40 …»). `AnswerGuard::notSoldFact($names)` is a line in the facts that the customer has been told, and that the model is not to describe or compare the missing model.

Shop chat (`public/index.php`): with unknown models and no products, the sentence is the whole answer; the model is not asked, the answer is saved with no product IDs, and the event is tracked as `ikke_solgt`. With products as well (a known model, the page, or the previous answer), the sentence is sent first, the model gets the fact line and answers about the known products, and the saved answer is the sentence followed by the model's answer, with the same product IDs as before.

Public API (`src/PublicApi.php`): the endpoint's product is always there, so the model answers about it with the fact line, and the sentence is put before the answer text. The response fields are unchanged.

`AnswerGuard` checks run on the model's part of the answer as before.

## Acceptance criteria

«Har dere H9R Signature?» and «Hva koster H9R Signature?» get the sentence and no products, without the model. A known model is answered as before. A known and an unknown model: the sentence and an answer about the known product. Vague questions, typos, partial codes, sizes and standards never get the sentence. The sentence is complete, with no colon, ID or reference.
