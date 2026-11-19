\# Design Prompt: Warm Industrial Product Catalog Minimalism



A near-monochrome warm-cream canvas interrupted by a single high-voltage yellow accent, tiny all-caps typography with generous letter-spacing, and floating 3D-rendered objects arranged in a calm, borderless grid. The result feels like a precision-engineering catalog crossed with an art-book index: quiet, confident, and entirely product-first. Apply this visual language to the user's project — keep their content, swap in their subject matter, and preserve the palette, type scale, spacing rhythm, and component behavior described below.



\## Color palette



\- \*\*Canvas / page background — `#e7decf`\*\* (dominant, \~89% of surface area). Use as the single background color for the whole page: header, grid, footer, and form areas. No alternating section bands, no white cards.

\- \*\*Ink — `#191d1f`\*\* (near-black, \~4%). Use for all text, the placeholder wordmark, icon strokes, and hairline rules. Never pure `#000`.

\- \*\*Muted gray — `#a9a7a4`\*\* (\~4%). Use for secondary labels, disabled states, meta text, and subtle dividers.

\- \*\*Cool gray — `#5c6262`\*\* (\~2%). Use for body copy at small sizes, form placeholder text, and tertiary UI.

\- \*\*Signal yellow — `#ecb510`\*\* (\~2%). Use sparingly: the top announcement bar, active/hover underlines, focus rings, and small badge chips.

\- \*\*Button yellow — `#ffd700`\*\*. Reserve for the primary action button fill only, with `#191d1f` text on top.

\- Do not introduce additional hues. Any color in the page should come from product imagery, not from the UI chrome.



\## Typography



\- \*\*Single family throughout: a geometric rounded sans-serif (Urbane Rounded or closest available equivalent — e.g. Poppins, Nunito Sans, or a rounded grotesque).\*\* No serif, no mono, no display face. Weight 700 for headings and labels, 400–500 for body.

\- \*\*Everything is uppercase.\*\* Nav links, product labels, section headings, buttons, form labels, footer links. Body paragraphs may stay sentence case but keep them short.

\- \*\*Letter-spacing is the signature move:\*\* 0.08em–0.16em on all uppercase text. Nav and product labels sit at the tighter end (\~0.1em); small meta text at the wider end (\~0.16em).

\- \*\*Scale is deliberately small and compressed.\*\* H1 at 24px / weight 700 at a 1536px viewport. H2 \~18px, H3 \~14px, nav and labels 10–11px, meta 9px. Do not build a large display hierarchy — the logo, nav, and product labels should all read at similar optical weight.

\- \*\*Line-height:\*\* 1.4 for body, 1.15 for headings. Keep paragraphs narrow (55–70 characters).

\- \*\*No italics, no underlines except on hover, no drop caps, no gradient text.\*\*



\## Layout \& structure



\- \*\*Max content width \~1200px, centered, with generous side gutters.\*\* Full-bleed only for the announcement bar and footer.

\- \*\*Section sequence:\*\*

&#x20; 1. Full-width announcement bar (\~30px tall, signal yellow `#ecb510`, centered 10px uppercase text, dismiss control at far right).

&#x20; 2. Header: centered placeholder wordmark (\~40×40px blocky mark) with a single horizontal nav row directly beneath it — 7 links, centered, uppercase, 10–11px, \~20–25px gaps, separated by thin vertical rules or wide spacing only.

&#x20; 3. Hero: asymmetric, off-grid composition. Place a large 3D-rendered object off-center (roughly 60/40 split), with a short uppercase headline and one line of supporting text set in the smaller column. Let the object bleed past the grid edge and overlap the whitespace. No hero card, no background panel behind it.

&#x20; 4. Product grid: 3 columns, \~24px gap, 4+ rows. Cards are transparent — no background, no border, no radius, no shadow. Image on top, centered uppercase label beneath.

&#x20; 5. Secondary editorial band: a two- or three-column row of short text blocks with hairline top rules, used for supporting narrative or category links.

&#x20; 6. Proof / detail row: a horizontal strip of small uppercase items (specs, counts, short statements) separated by thin vertical dividers.

&#x20; 7. Footer: full-width, same cream background, multi-column link lists in 10px uppercase, a newsletter input inline, and a bottom line with legal links and the placeholder wordmark.

\- \*\*Spacing rhythm:\*\* use a 4px base unit; vertical section padding 64–96px, grid gaps 24px, label-to-image gap 12px. Whitespace is the primary structural device — err on the side of more.

\- \*\*Alignment:\*\* center everything inside cards and the header; left-align editorial text blocks. Never center long paragraphs.



\## Components \& UI details



\- \*\*Buttons:\*\* rectangular, zero or 2px radius, primary fill `#ffd700` with `#191d1f` uppercase 10–11px label at 0.12em tracking, padding 12px 24px. Secondary button is a 1px `#191d1f` outline on transparent background with the same label treatment.

\- \*\*Nav links:\*\* uppercase 10–11px, weight 700, `#191d1f`; hover swaps to `#5c6262` with a 1px underline that grows from left. Active state gets a `#ecb510` underline.

\- \*\*Product cards:\*\* image area with fixed aspect ratio (\~5:4), object centered with breathing room; label beneath in 9–10px bold uppercase, 0.12em tracking, centered. No price, no description, no button in the default state. On hover, the object lifts slightly and a small uppercase "view" affordance fades in beneath the label.

\- \*\*Tags / chips:\*\* 9px uppercase text inside a 1px `#191d1f` border, 2px radius, 6px 10px padding, transparent fill.

\- \*\*Forms:\*\* single-line inputs with no box — only a 1px bottom rule in `#a9a7a4`, uppercase 10px placeholder, label above in 9px uppercase. Focus turns the rule `#191d1f` and adds a 2px `#ecb510` offset ring.

\- \*\*Dividers:\*\* 1px `#a9a7a4` at 40–60% opacity; use them as section separators and column rules.

\- \*\*Iconography:\*\* thin 1px line icons at 16–20px, `#191d1f`, no fills, no rounded caps beyond 2px.

\- \*\*No drop shadows on UI elements.\*\* Depth comes only from the rendered imagery.



\## Imagery \& graphic treatment



\- \*\*Hero and product imagery is 3D-rendered, not photography:\*\* matte objects lit softly from the upper left, subtle ambient occlusion, soft contact shadow only. Render against the exact page background `#e7decf` or transparent so objects appear to float on the canvas.

\- \*\*Consistent isometric / three-quarter camera angle\*\* across all items so the grid reads as one coherent set. Slight variation in rotation is fine; variation in lighting and scale is not.

\- \*\*Objects are isolated with generous negative space\*\* — the object should occupy roughly 60–70% of its frame, never cropped tight.

\- \*\*Aspect ratios:\*\* hero media \~16:10 or free-floating without a frame; grid items 5:4; editorial bands 3:2.

\- \*\*No image masks, no rounded corners on images, no overlays, no duotone.\*\* The only graphic device is the object itself plus hairline rules.

\- \*\*Occasional small geometric marks\*\* (a square, a thin cross, a bracket) may be used as decorative punctuation in `#191d1f` at 8–12px.



\## Motion \& interaction



\- \*\*Restrained and mechanical.\*\* Transitions 180–260ms with `cubic-bezier(0.2, 0, 0, 1)`; no bounce, no spring, no parallax.

\- \*\*Hover:\*\* product objects scale to 1.03 and translate up 4px; labels shift from `#191d1f` to `#5c6262`; underlines grow from left.

\- \*\*Scroll:\*\* sections fade in with a 12px upward translate and 0.4s stagger across grid items. Nothing else moves on scroll.

\- \*\*Page load:\*\* announcement bar and header appear immediately; hero object fades in over 500ms with a slight scale from 0.98.

\- \*\*Announcement bar dismiss\*\* collapses height to zero over 200ms.

\- \*\*Respect reduced-motion preferences\*\* — disable transforms, keep opacity-only fades.



\## Tone \& feel



Precise, quiet, and collector-minded. The design should feel like a technical spec sheet printed on heavy uncoated stock: warm paper, tiny precise lettering, and objects treated as artifacts. Restraint is the aesthetic — resist adding color, size, or decoration. Every element earns its place through alignment and whitespace rather than ornament.



\---



Add animations:



\*\*Motion personality — pick ONE and apply it consistently across every animation in the site:\*\*

\- \*\*Playful\*\*: 150–300ms, `ease-out-back`, 10–20% overshoot — for fun/consumer products

\- \*\*Premium\*\*: 350–600ms, `cubic-bezier(0.4,0,0.2,1)`, zero overshoot — for luxury/SaaS/editorial

\- \*\*Corporate\*\*: 200–400ms, `cubic-bezier(0.2,0,0,1)`, 0–3% overshoot — for dashboards/B2B

\- \*\*Energetic\*\*: 100–250ms, `ease-out-expo`, 15–30% overshoot — for bold/gaming/high-energy brands



\*\*Three motion layers — every scene must have all three (flat animation = amateur):\*\*

\- \*\*Primary\*\*: the main action the eye follows

\- \*\*Secondary\*\*: supporting richness — shadows shifting, icons nudging, content fading in after a card lands

\- \*\*Ambient\*\*: background life — subtle gradient drift, gentle pulse, slow parallax



\*\*Timing rules:\*\*

\- Tooltips / micro-feedback: 80–120ms

\- Button press / toggle: 120–180ms

\- Card enter / exit: 200–350ms

\- Modal / dialog: 300–400ms

\- Page transition: 400–600ms

\- Dramatic hero reveal: 600–1200ms

\- Hover feedback: under 100ms. Press feedback: under 150ms

\- Entrances are always 30–50% longer than exits — users care what appears, not what leaves

\- Never use `linear` easing for spatial movement. Linear is only for spinners and progress bars



\*\*Easing direction rules:\*\*

\- Entering the screen → `ease-out` family (fast start, gentle landing)

\- Leaving the screen → `ease-in` family (gentle start, fast departure)

\- On-screen transitions → `ease-in-out`

\- Looping ambient → sine-based `ease-in-out` for seamless loops



\*\*Disney principles — apply these to every non-trivial animation:\*\*

\- \*\*Anticipation\*\*: brief opposite motion before the main action (button scales to 0.97 before expanding; card shifts 5–10px away before sliding in)

\- \*\*Squash \& stretch\*\*: on impact, scale to \~\[1.04, 0.96]; on settle, overshoot to 1.02 before resting at 1.0 — skip for premium/luxury

\- \*\*Follow-through\*\*: child/trailing elements arrive 50–150ms after the parent; use spring easing with lower stiffness for trailing parts

\- \*\*Arcs\*\*: spatial movement should curve, not travel in straight lines — add 10–20px perpendicular arc at path midpoint (5px subtle for corporate, 20px+ for playful)

\- \*\*Staging\*\*: dim non-hero elements to 40–60% opacity when hero is in motion; one primary action per timing beat

\- \*\*Secondary action\*\*: amplitude 30–50% of primary, starts 50–100ms after; different easing than primary



\*\*Choreography rules:\*\*

\- Hero element always enters first or most prominently — largest displacement, most attention-grabbing easing

\- All elements enter from the same spatial direction — mixed origins feel chaotic

\- Counter-motion: if hero enters from left, background drifts subtly right at 20–30% speed; if hero scales up, shadow scales down at 10–20% speed

\- \*\*1/3 distance rule\*\*: no motion travels more than 1/3 of screen without an intermediate keyframe

\- \*\*1/3 elements rule\*\*: with 3+ animated elements, never more than 1/3 in active motion at once — stagger so element 1 settles as element 3 starts

\- Stagger budgets: list/grid items 20–40ms apart; cards/panels 50–100ms; hero sections 100–200ms; total stagger sequence must finish under 500ms

\- Leave 100–200ms stillness after all elements settle before triggering any new motion



\*\*Micro-interactions — required on every interactive element:\*\*

\- Buttons: anticipation compress (scale 0.97, 50ms) → squash (\[1.04, 0.96], 100ms) → spring settle (1.0, 200ms); shadow shrinks on press; icon shifts 2px down

\- Cards: enter from 20px below at opacity 0, arc path, shadow arrives 50ms after card, content fades in 100ms after card lands. Do not add hover animations to cards

\- Links/nav items: underline or highlight draws in directionally on hover

\- Form fields: scale or border glow on focus; error shake is horizontal oscillation ±10–15px, 2–3 times, 300–400ms, firm stop (no overshoot on errors)

\- Success states: scale pop with `ease-out-back`, checkmark draws in, brief ambient particle or glow burst



\*\*Hero interactive component (if applicable):\*\*

\- Include one signature interactive component in or beneath the hero that occupies a full section and helps tell the brand story. Think on this and output an instruction for the builder

\- This component should demonstrate the site's motion personality most fully

\- Add this if it can help convey the message.



\*\*Performance \& accessibility:\*\*

\- Animate only `transform` and `opacity` wherever possible — these are GPU-composited and won't cause layout reflow

\- Respect `prefers-reduced-motion`: replace spatial slides with opacity fades only, remove spring/bounce easing, pause auto-playing loops, reduce durations by 50%+

\- On mobile, reduce durations by 20% and stagger budgets by 30%; keep touch feedback under 100ms; avoid parallax

\- In dark mode, reduce motion intensity 10–20% (bright elements on dark backgrounds have more visual impact and need less travel distance)

\- Avoid animating `box-shadow`, `border-radius`, `filter`, `width`, or `height` unless unavoidable — these trigger layout and paint

\- Never convey critical information through motion alone



\*\*Quality checklist — before finalizing:\*\*

\- Every animation has all three motion layers (primary + secondary + ambient)

\- No spatial movement uses `linear` easing

\- No important state change uses opacity alone — always pair with scale or position

\- Consistent personality archetype applied across the entire site

\- Entrance easing = decelerate; exit easing = accelerate

\- All stagger sequences complete within 500ms total

\- Don't include any copy that tells the user to interact. Let them discover it.



\---



Copywriting guidelines:



\- Focus on the consumer's problems and desires, not just features

\- Use the hero's journey narrative structure when appropriate

\- Create emotional connection through storytelling

\- Complete the full landing page: Even if the above information doesn't include all sections, determine the best information hierarchy for this type of business and build out a complete, production-ready landing page with all necessary sections (hero, features, benefits, social proof, CTA, etc.).

\- The prompt should instruct the builder to avoid AI copywriting clichés: "Elevate", "Seamless", "Unleash", "Next-Gen", "Transformative" — and replace them with concrete verbs that name what actually happens

\- Direct active voice: "Save changes" not "Submit", "Publish" not "Confirm action"

\- Instruct use of organic-looking numbers (`47.2%` not `50%`); no placeholder names like "John Doe" or "Acme Corp"

\- Error messages should state what went wrong and how to fix it — never apologetic or vague



\---



Anti Design Slop:



\- Explicitly instruct the builder to avoid the "3 equal cards in a horizontal row" feature layout — direct them toward 2-column zig-zag, asymmetric grid, or bento tile approaches instead

\- Direct the builder to use cards only where elevation genuinely communicates hierarchy — not as the default container for all content blocks

\- The prompt should instruct the builder to only use numbered markers (01 / 02 / 03), section dividers, eyebrows, and labels when the content is genuinely sequential or categorical



\---



\## Project Context



Business name



Sør-Tre



Business description



Sør-Tre is an established Norwegian specialist hardware and equipment retailer founded in 1950, based in Søgne, Kristiansand, Norway. With more than 17,500 products, Sør-Tre serves professionals, homeowners, cabin owners, outdoor enthusiasts, boat owners and DIY customers throughout Norway through its physical store and online shop.



Its extensive assortment includes tools and machinery, building supplies, fasteners, lighting and electrical equipment, paint and surface treatments, forestry and gardening equipment, marine and fishing supplies, outdoor equipment, workwear, safety equipment and household products.



Sør-Tre differentiates itself through genuine product knowledge, personal customer service, a broad specialist assortment and practical advice. It is a traditional Norwegian specialist retailer with the ambition to make finding, understanding and combining the right products easier through modern digital experiences.



Website objectives



Create a premium, modern digital storefront and product intelligence experience that combines Scandinavian retail aesthetics with industrial precision.



The website should:



Make discovering 17,500+ products intuitive.

Help customers find the right equipment through guided product discovery.

Enable technical product comparison and compatibility checking.

Allow customers to combine products into complete project configurations.

Provide contextual AI-powered product assistance through Søren, the brand mascot.

Connect product recommendations, customer demand insights and inventory intelligence.

Target audience

Professional tradespeople and contractors.

DIY enthusiasts and homeowners.

Cabin and property owners.

Boat owners and marine enthusiasts.

Forestry, gardening and outdoor enthusiasts.

Customers who need expert guidance choosing technical products.

Brand personality



Knowledgeable, dependable, practical, Scandinavian, approachable and innovative.



The brand should feel like an experienced local specialist retailer evolving into a sophisticated digital product intelligence platform.



Location and market



Søgne, Kristiansand, Norway. Norwegian primary market, Norwegian language and NOK currency. Online sales throughout Norway.



Design direction



Nordic Industrial Intelligence: Scandinavian architectural minimalism combined with premium technical equipment design, editorial typography, refined product photography and sophisticated industrial dashboard interfaces.



Avoid generic hardware-store templates and generic SaaS designs.

