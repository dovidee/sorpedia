# 035 Answer guard for prices and fit

## Goal

The database decides what is true; the language model decides how to say it. Søren's answers must quote only database prices, add prices correctly, never move a price to another product, and never say that products fit when the catalogue documents that they don't. A rule in the prompt alone is not enough, so the server checks the answer.

## Before

The facts already gave prices as references (`[[pris:ID]]`, feature 015), and related products got their own references in feature 034. The model still added prices itself (or didn't), wrote amounts of its own, and could say a charging cable fits two lamps when the facts listed it as «PASSER IKKE». Nothing checked the answer against the relations.

## Design

`src/AnswerGuard.php`, used by `Chat::answer` for every answer (shop chat and public API) and by the chat route for plain totals.

Totals. `AnswerGuard::total($ids)` adds the database prices in øre (integers), one of each product. A product not in the catalogue, or with a price of 0 (the shop has no price for it), is listed as missing and there is no total. `ProductRef::sum($ids)` writes `[[sum:ID+ID]]`; `ProductRef::resolve` fills it in with `kr()`, and `ProductRef::clean` removes it when a product in it is not in the facts. A total question (`isTotal`: «sammen», «totalt», «summen», «hele pakken» with a price word) gets a SUM line in the facts. A question that is only a total (`pureTotal`) is answered by the server with `totalSentence`, which names the products and gives each price; the model is not asked. An answer to a total question that leaves out the sum reference gets the total sentence added at the end.

Sentence check. `AnswerGuard::context($ids, $facts)` collects the products in the facts with their prices, the words that identify them (the model code the name starts with, when no other product starts with the same one, or the first word of the name together with a word only that name has), and the documented «passer ikke» pairs among them. `AnswerGuard::check` runs on each sentence before it is sent, in the reasoning and in the answer:

1. A fit claim («passer», «kompatibel», «fungerer med», without a negation in the same clause) about products with a documented «passer ikke» between them is replaced by «A passer ikke til B. Det har Sør-Tre dokumentert.» A group word («begge», «lyktene») counts the products the answer is about. Only «passer ikke» triggers this, so an uncertain fit is never turned into a claim, and an unknown fit is left to the prompt.
2. A price reference in a sentence that names one other product is moved to that product.
3. An amount in kroner must be the price of a product the sentence names, the total of the products it names, or an amount that stands in the facts. A sentence that names no product may use any price or the total of the answer's products. A wrong amount becomes the named product's own price reference, or «(prisen står på produktkortet)».

Fit order, also stated in the facts and the prompt: documented «passer ikke» always wins; «passer sammen» documented by Sør-Tre is confirmed; rule-based is shown as not confirmed; «kjøpt sammen» is never a fit and is not in the facts as one; no relation is unknown.

Prompt. The rules now say that the facts decide what is true, that prices come only from the product's own reference and the SUM reference, that technical numbers and standards come only from the facts, how each fit level is to be presented, that bought together is not a fit, that internal numbers are not available, and that one short question is better than a guess. The reasoning stays in Norwegian and does not mention the rules.

## Unchanged

`ProductRetriever` (only two helpers became public), `Search::run`, the catalogue and relations data, the public API contract, conversation memory and the cards.

## Acceptance criteria

Prices in answers match the database for the product they are about. «Hva koster de to sammen?» after a question about a pair gives the correct sum, from the server. A missing price gives no total. A fit claim against a documented «passer ikke» never reaches the customer. Bought together is never presented as a fit. Tests, evals, PHPStan and the hooks pass.
