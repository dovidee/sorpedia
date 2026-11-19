\# Design Prompt: Dark Industrial Editorial with Red Accent



Build a near-black, industrial-editorial interface with high-contrast white type, a single saturated red accent, and restrained, precise detailing. Apply this visual language to the user's own project: keep the structure, spacing rhythm, and surface treatments described below, and replace all content with the user's business material.



\## Design direction



The look is dark, engineered, and quietly confident: a near-black canvas, a display serif that carries the emotional weight, a small neutral sans for everything functional, and one hot red reserved for actions and emphasis. Surfaces are flat and borderless rather than carded; separation comes from hairline strokes, generous vertical space, and grayscale imagery. Everything is centered on a narrow measure inside a wide, full-bleed page. Apply this system end to end to the user's project.



\## Color palette



\- \*\*Base canvas — `#0A0A0A`\*\* (near-black): the page background everywhere. This is the overwhelming majority of the surface area; keep it dominant and never replace it with pure `#000`.

\- \*\*Deep warm shadow tone — `#3A2525`\*\*: a desaturated maroon-brown used in large soft areas — gradient falloff behind hero media, image overlays, and shadowed section transitions. It should read as a tint in the darkness, not as a visible color block.

\- \*\*Mid neutral — `#6A6868`\*\*: muted gray for secondary body copy, captions, and de-emphasized metadata.

\- \*\*Accent red — `#E53935`\*\*: reserved exclusively for primary buttons, one small badge per section, thin decorative rules under section headings, and occasional accent details inside imagery. Never use it for large fills or backgrounds beyond small pills.

\- \*\*Light neutral — `#BFBDBD`\*\*: light gray for tertiary text, logo marks, and icon strokes.

\- \*\*Text primary — `#FFFFFF`\*\*: headings and key labels.

\- \*\*Text secondary — `#999999`\*\*: supporting paragraphs and sub-labels.

\- \*\*Hairlines — `rgba(255,255,255,0.10)`\*\*: 1px borders on badges, dividers, table rules, and input outlines. Nothing heavier than 1px except deliberate accent rules.



Keep the ratio roughly: \~77% near-black, \~13% warm shadow tone, \~5% mid gray, \~4% red, \~2% light neutral.



\## Typography



\- \*\*Headings\*\*: a high-contrast display serif with fine hairlines and pronounced thick/thin modulation. Set in white, sentence case, tight line-height (\~1.15–1.2), and slightly negative letter-spacing at large sizes.

&#x20; - Hero headline: 40–48px desktop, \~30px mobile, centered, constrained to a \~500px measure so it breaks across 2–3 lines.

&#x20; - Section headings: \~32px, centered.

&#x20; - Sub-section / card headings: 18–20px.

\- \*\*Body and UI\*\*: a neutral sans-serif with a low-contrast, modern grotesque feel. Small sizes throughout — this is a deliberate part of the aesthetic.

&#x20; - Body paragraphs: 12–13px, line-height \~1.7, color `#999999`, max measure \~520px, centered in hero and section intros.

&#x20; - Navigation links: 13px, regular weight, white, \~30px gaps between items.

&#x20; - Wordmark: 14px, bold, uppercase, wide letter-spacing (\~0.08em).

&#x20; - Badges and micro-labels: 10–11px, uppercase optional, letter-spacing \~0.06em.

\- \*\*Emphasis inside body copy\*\*: use a white or `#BFBDBD` semibold span rather than italics or color, to keep the palette disciplined.

\- Never exceed three type sizes within a single section block.



\## Layout \& structure



\- \*\*Page frame\*\*: full-bleed dark background with content constrained to a \~1100px max width, centered, with generous side gutters (\~24px mobile, \~48px+ desktop).

\- \*\*Section sequence\*\* (preserve this order, swap in the user's content):

&#x20; 1. Fixed top navigation bar

&#x20; 2. Hero: badges → headline → subtext → primary CTA → large stacked media block

&#x20; 3. Proof strip: short centered intro line above a two-row logo grid

&#x20; 4. Services / offering section: centered heading with accent rule, centered intro paragraph, then a grid of cards

&#x20; 5. Additional content sections following the same centered-heading-plus-grid rhythm

&#x20; 6. Footer

\- \*\*Navigation\*\*: fixed to the top, transparent over the hero and gaining a subtle dark backdrop with a hairline bottom border on scroll. Three zones: placeholder wordmark left, 5 links centered, one pill CTA right. On mobile collapse the links into a full-screen dark overlay menu.

\- \*\*Hero\*\*: single column, everything centered. Stack order is badges (inline row, 8–10px gap) → headline → subtext → CTA → media. Vertical rhythm: \~32px between badge row and headline, \~20px to subtext, \~28px to CTA, \~56–72px to the media block.

\- \*\*Logo grid\*\*: 5 columns × 2 rows on desktop, \~40px gaps, collapsing to 3 columns then 2 on smaller screens. Cells are equal-height and center-aligned; no card backgrounds, no borders.

\- \*\*Card grids\*\*: 3 columns desktop, 2 tablet, 1 mobile, with \~24px gaps. Cards are flat panels slightly lifted from the canvas (see surfaces) rather than bordered boxes.

\- \*\*Spacing rhythm\*\*: use a consistent 8px base scale. Section padding \~96–120px desktop, \~64px mobile. Keep vertical space generous — the darkness needs air.



\## Components \& UI details



\- \*\*Primary button\*\*: `#E53935` fill, white text, 13px, padding \~10px 24px, fully rounded pill (border-radius \~20px / 999px), no shadow. Hover: darken fill slightly and lift 1px. Focus: 2px offset ring in `rgba(229,57,53,0.45)`.

\- \*\*Secondary button\*\*: transparent fill, 1px `rgba(255,255,255,0.18)` border, white text, same pill geometry. Hover: border brightens to `rgba(255,255,255,0.4)` and background shifts to `rgba(255,255,255,0.05)`.

\- \*\*Badges / tags\*\*: pill shape, border-radius \~12px, 10–11px text, padding \~4px 12px. Two variants — neutral (transparent with a `rgba(255,255,255,0.1)` hairline border, `#BFBDBD` text) and accent (`#E53935` fill, white text). Use at most two side by side.

\- \*\*Section heading treatment\*\*: centered serif heading with a short red rule (\~40px wide, 2px tall) or a small red dot centered beneath it, followed by a centered gray intro paragraph.

\- \*\*Cards\*\*: background `rgba(255,255,255,0.03)` or a very dark warm gradient toward `#3A2525`, radius \~12–16px, no border, no drop shadow. Media sits flush to the top edge with the same top radius; text padding \~20–24px. Hover: background lightens a step and the accent detail inside brightens.

\- \*\*Links in body\*\*: white with a 1px underline in `rgba(255,255,255,0.25)`; hover switches the underline to `#E53935`.

\- \*\*Forms\*\*: transparent fields, 1px hairline border, radius \~10px, 13px text, placeholder in `#6A6868`, focus border in `#E53935`.

\- \*\*Iconography\*\*: thin 1–1.5px line icons in `#BFBDBD` or white, 20–24px, no filled shapes, no rounded cartoon styling.

\- \*\*Dividers\*\*: full-width 1px `rgba(255,255,255,0.08)` rules between major sections where extra separation is needed.



\## Imagery \& graphic treatment



\- \*\*Hero media\*\*: a large 3D-rendered technical visualization — a grayscale/silver wireframe mesh of the product or system, photorealistically shaded, floating on the dark background with no frame or card. \~400px tall, centered, with soft falloff into the background and a faint warm `#3A2525` glow behind it. Render it as a transparent or dark-matched asset so it appears to emerge from the canvas.

\- \*\*Background texture\*\*: an extremely subtle dark fabric/silk-like texture or fine noise overlay in the hero, at very low opacity (2–5%), so it reads as depth rather than pattern.

\- \*\*Photography\*\*: industrial, high-contrast, low-saturation imagery — machinery, materials, and process shots graded toward cool grays and near-black, with occasional small red accents in the frame. Crop tight and wide (16:9 or 3:2); never use rounded-corner stock-photo framing with white borders.

\- \*\*Partner/logo strip\*\*: all marks rendered in flat white or `#BFBDBD` at a uniform optical height (\~40–50px), monochrome, no boxes. Apply a slight opacity reduction (\~0.7) that returns to 1 on hover.

\- \*\*Data and diagram accents\*\*: thin technical linework, measurement ticks, or grid overlays in `rgba(255,255,255,0.12)` may be used sparingly to reinforce the engineered feel.



\## Motion \& interaction



\- Keep motion restrained and mechanical: 200–300ms ease-out transitions on color, opacity, border, and transform. No bounce, no spring, no large parallax.

\- On scroll, fade-and-rise content blocks by 12–16px with a short stagger (\~60ms) as they enter the viewport; trigger once.

\- Hero media may rotate or drift extremely slowly (20s+ loop) to suggest a live 3D render, or respond subtly to pointer position.

\- Hover states are the primary feedback: buttons shift fill and lift 1px, cards lighten their surface, logos regain full opacity, nav links reveal a 1px underline.

\- The fixed navigation transitions from fully transparent to a blurred dark bar with a hairline border after \~40px of scroll.

\- Respect reduced-motion preferences by disabling drift and stagger while keeping opacity fades.



\## Tone \& feel



Industrial editorial: dark, precise, and premium rather than aggressive. The personality comes from the tension between a romantic high-contrast serif and cold, engineered grayscale imagery, punctuated by a single disciplined red. Restraint is the rule — no gradients as decoration, no shadows, no colorful icons, no rounded friendly cards. Every element should feel measured, aligned, and deliberate, with the darkness doing most of the work.



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

