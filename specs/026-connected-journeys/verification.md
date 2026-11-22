# 026 Verification

Run in the app (dev server, logged in as admin): a guide walk for Lys og lykter (type, sted, budsjett) ended on the result page with "Sammenlign forslagene" (`/sammenlign?ids=297992,298011,298018`) and "Bygg oppsettet i Byggelab" (`/byggelab/lys?c=297992:1,298011:1,298018:1`); the Byggelab page loaded with those product IDs and its check ("Mangler noe", compatibility unknown). Innsikt showed Muligheter (a Byggelab missing component and two searches without results with their actions), Byggelab (configurations, left without saving or cart, verdicts, most used products and pairs) and Produktguiden (started, finished, "Hva slags?" as the question where a session stopped).

Chat: a vague question on the home page streamed `products: []` and then `guide` with `/guide`; the same question on `/tema/tur` streamed the category's products and no guide offer, as specified.

Syntax checked: `php -l` on the changed PHP, `node --check` on the changed scripts.

Not verified: "Merk som vurdert" and "Lag oppgave" clicked from Innsikt (they call the same endpoints as the floor inspector, which are covered by 022); the guide link drawn in the chat panel (only the stream was read); the English view of the new panels; phone width of the opportunity rows.

Known weaknesses: one-off typos count as opportunities until reviewed; a configuration is attributed to the session's last check per builder, so two different setups in one session count once; guide abandonment needs at least one `guide_step`, so sessions before this change are not counted.
