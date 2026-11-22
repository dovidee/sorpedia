# Design pre-flight: Control Tower and Byggelab redesign (feature 043)

## 1. Task and scope

Changes: the Control Tower shell gets light and dark themes that follow the account's theme setting (Settings: system, lys, mork); with no explicit choice it opens light. Oversikt and Innsikt are regrouped by importance as `inspiration/CONTROLTOWER/CONTROLTOWER-REDESIGN.md` asks: exceptions first, a small KPI row with denominators, trends, then diagnostics folded away. Spør Søren becomes a drawer opened on purpose instead of a docked column. Transport gets a vehicle layer (every demo vehicle, busy or free), a vehicle panel with Spill av, a demo trip to a clicked point, single and batch ordering with coverage marks, and the sources of weather, road and ferry data next to what they explain. Gulv gets the order list and goods in. Innsikt gets the list of products missing on the shelf with Legg i bestilling. Observations get the review states of `inspiration/CONTROLTOWER/CONTROLTOWER-TRIAGE.md`. Byggelab gets a quieter, clearer hierarchy in its right panel and action bar on its existing tokens.

Does not change: the Three.js floor and workbench, data and calculations, routes, permissions, the Norwegian copy rules, the map projection.

## 2. Skills selected and read

impeccable (`~/.claude/skills/impeccable/SKILL.md` and its product register reference): mandatory on UI work in this project; the product register fits a staff tool. animate (`~/.claude/skills/animate/SKILL.md`): the redesign brief asks for purposeful motion. dataviz (built in): Innsikt charts need a semantic, non amber palette that works in both themes. Rejected: cosmic-glass-dashboard, industrial-brutalist-ui, gpt-taste, high-end-visual-design (each brings its own visual language, which the priority order forbids over the project's tokens); overdrive and delight (decorative motion is banned in the product register).

## 3. Conflicts and decisions

PRODUCT.md says an unset theme follows the device; the redesign brief says an unset preference opens light. The brief is an explicit user requirement (rule 1): unset means light in the tower; Byggelab, a customer page, keeps following the device. animate suggests page-load choreography and hover scaling; impeccable's product register bans orchestrated loads and decorative motion (rule 4 over 5): motion only for state changes (view switch, drawer, expand, value change), 150 to 250 ms, ease-out, none under reduced motion. The existing tower uses glass cards over a light field; impeccable bans glass as default (rule 5, but it agrees with the brief's "reduce visual noise"): solid panels on the tower tokens, one surface step for panels. Amber everywhere loses to the brief's semantic colour rule: neutral series in a blue-grey info hue, amber only for warning, red only for errors and urgent, green for done.

## 4. Direction

Restrained product UI. Tower tokens in `tower.css` (`--bg`, `--panel`, `--raise`, `--line`, `--ink`, `--muted`, `--amber`, `--red`, `--ok`, source colours) get a light set as the default and the current dark set under `[data-theme="dark"]`, so every component that already reads the tokens follows. New semantic tokens: `--info` for neutral series, `--accent` for selection. Archivo stays the one family; scale steps 12, 13, 15, 18, 24 px. Status always pairs colour with text or an icon. The map keeps its SVG and gets theme-aware land, sea and road colours through the same tokens.

## 5. Preserve

The exit arrow and own shell, the five views plus Omvisning and Spør Søren, keyboard access to every card and vehicle, `prefers-reduced-motion`, English through the language toggle, DEMO markers, the camera rules on the map (the presenter owns it), the floor synchronisation sequence, Byggelab's address-bar configuration and its no-WebGL list.

## 6. Review focus

Contrast of text, chips and map labels in both themes; that an unset theme renders light and an explicit dark renders dark on every view; whether the Transport panel makes the order flow (pick, assign, missing marks, deselect) understandable without reading help text; whether every number in Oversikt and Innsikt shows its denominator and period; layout at 390, 768 and 1440 px.
