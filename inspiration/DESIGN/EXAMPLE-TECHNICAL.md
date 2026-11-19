\# Design Prompt: Dark Technical Showroom with Amber Accent



A near-black, hardware-showroom aesthetic built on a deep charcoal canvas, warm amber accent, and full-bleed product photography, with restrained sans-serif type and pill-shaped controls. Apply this visual language to the user's project: keep every color, type rule, spacing rhythm, and component behavior below, and substitute the user's own content, imagery, and offerings.



\## Color palette



\- \*\*Base canvas — #0d0d0d\*\*: the dominant page background. Use it for the body, the header, the hero, and every full-bleed dark band. It should account for roughly half of all visible pixels.

\- \*\*Raised surface — #32312f\*\*: the second most common tone. Use for cards, panels, embedded media frames, dropdown menus, and any block that needs to separate from the canvas without a border.

\- \*\*Muted mid-tone — #63615c\*\*: use for secondary borders, dividers, inactive icon strokes, disabled states, and caption text on dark surfaces.

\- \*\*Warm neutral — #998f85\*\*: use for supporting body copy on dark backgrounds, small meta labels, and the soft neutral wash inside photography. Never use it for primary headings.

\- \*\*Light surface — #ddd9d4\*\*: the off-white used for the light content band and for reversed text. Not pure white — keep it slightly warm and paper-like.

\- \*\*Accent — #f5a623\*\*: the single saturated color in the system. Reserve it for primary buttons, active carousel dots, small icon highlights, link hover underlines, and thin accent rules. Never flood a large area with it; it works because it is scarce.

\- \*\*Ink on light — #1a1a1a\*\*: text on the light band and label text inside amber buttons.

\- \*\*Rules\*\*: no gradients as primary surfaces, no second accent hue. Depth comes from tone steps (#0d0d0d → #32312f → #63615c), not from color.



\## Typography



\- Set all text in a clean geometric/neo-grotesque sans-serif. Use one family across the site with weight and size doing the differentiation; if a second family is used, restrict it to display headings only and keep it a neutral sans-serif, not a decorative serif.

\- \*\*Display / hero heading\*\*: 36–44px, weight 600–700, tight tracking (-0.01em to -0.02em), sentence case, centered over the hero image.

\- \*\*Section headings\*\*: 28–32px, weight 600, tight tracking, centered in narrow text blocks and left-aligned in wide grids.

\- \*\*Oversized media headline\*\*: 48–60px, weight 700–800, uppercase, tight leading (0.95–1.0), stacked across 2–3 lines, set in the light tone over dark media.

\- \*\*Navigation links\*\*: 13px, weight 500, title case, letter-spacing 0.01em.

\- \*\*Announcement bar text\*\*: 12px, weight 500, letter-spacing 0.04em, amber on near-black, centered.

\- \*\*Body copy\*\*: 14–15px, weight 400, line-height 1.6, max-width 480–520px when centered, color #998f85 on dark and a mid-gray on light.

\- \*\*Buttons\*\*: 14px, weight 600, no uppercase transform, letter-spacing 0.01em.

\- \*\*Small meta / eyebrow labels\*\*: 11–12px, weight 600, uppercase, letter-spacing 0.12em, in #63615c or amber.

\- Keep the type scale short — five or six sizes total. No italics, no script, no outlined type.



\## Layout \& structure



\- Max content width 1200px, centered, with 24px side gutters on mobile and 40–64px on desktop.

\- \*\*Section sequence to preserve:\*\*

&#x20; 1. Thin announcement bar, full-bleed, \~30px tall, single centered line.

&#x20; 2. Sticky header, \~50–64px tall, three zones: wordmark left, six nav links centered, utility cluster right (locale/currency selector, search icon, cart icon). Dark background, no shadow until scroll.

&#x20; 3. Full-bleed hero, 55–60% of viewport height, text overlaid on a single photographic background, with a centered heading, one pill CTA, side chevron arrows, and 5–6 dot indicators pinned to the bottom.

&#x20; 4. Light content band on #ddd9d4: a single centered text block, max-width \~500px, 40–50px vertical padding.

&#x20; 5. Full-width dark media band with an oversized stacked headline overlaid and a centered circular play control, plus a chevron at the left edge.

&#x20; 6. Product/feature grid on the dark canvas (3 or 4 columns desktop, 2 tablet, 1 mobile) with generous 32–48px gaps.

&#x20; 7. A proof or detail band using the raised surface tone.

&#x20; 8. Closing CTA band, then a multi-column footer on the darkest tone with small utility links.

\- Alternate dark and light bands in a clear rhythm: no more than one light band between dark sections, so the page reads as predominantly dark.

\- Use a 12-column grid for the header and grids; let the hero and media bands break the grid and go edge to edge.

\- Vertical rhythm: 64–96px between major sections on desktop, 40–56px on mobile. Keep the light band's padding noticeably tighter than the dark bands to create tempo.

\- Centered, symmetric composition for text-led sections; left-aligned, asymmetric composition inside grids where an image sits beside copy.



\## Components \& UI details



\- \*\*Primary button\*\*: #f5a623 background, #1a1a1a text, pill radius (999px or \~20px), 14px/600 label, 12px 28px padding, no border, no shadow. Hover: darken the fill slightly and lift 1px.

\- \*\*Secondary button\*\*: transparent fill, 1px border in #63615c, light text; hover swaps border to amber and text to amber.

\- \*\*Nav links\*\*: light text at 90% opacity, hover to full opacity with a 1px amber underline that animates in from the left. Dropdown triggers get a small chevron glyph; dropdown panels use #32312f with 8px radius and a soft dark shadow.

\- \*\*Announcement bar\*\*: near-black strip, amber text, no border, dismissible-looking but visually minimal.

\- \*\*Carousel controls\*\*: circular ghost buttons \~40px with a thin chevron stroke, positioned at the vertical center of the hero edges; dot indicators 6px, #63615c when inactive, #f5a623 when active, 8px gap.

\- \*\*Cards / product tiles\*\*: #32312f surface, 8–12px radius, 1px border in a slightly lighter charcoal, image on top with 1:1 or 4:5 aspect, 16–20px inner padding, title at 16px/600, price or meta at 13px in #998f85.

\- \*\*Tags / badges\*\*: small pill, 11px uppercase, 0.1em tracking, amber text on a translucent amber tint or #63615c text on #32312f.

\- \*\*Forms\*\*: dark inputs with #32312f fill, 1px #63615c border, 8px radius, 12px 16px padding, amber focus ring at 2px with a subtle outer glow.

\- \*\*Icons\*\*: 20–24px line icons, 1.5px stroke, monochrome light; amber only on the active state.

\- \*\*Radii\*\*: pill for buttons and tags, 8–12px for cards and panels, 0 for full-bleed media.

\- \*\*Shadows\*\*: minimal and dark-only — a soft 0 8px 24px rgba(0,0,0,0.4) for floating menus. Never use light or colored shadows.

\- \*\*Dividers\*\*: 1px #32312f hairlines; use sparingly, prefer spacing.



\## Imagery \& graphic treatment



\- Lead with full-bleed photography: dark, high-contrast, dramatic side or rim lighting, deep shadows, and a single warm highlight so the amber accent feels native to the images.

\- Shoot products as isolated hardware on dark seamless backgrounds, slightly angled, with visible material detail (metal, matte plastic, cable texture). Keep the background of the photo close to #0d0d0d so images blend into the page with no visible frame.

\- Overlay text directly on the image with a subtle bottom-up dark scrim (rgba(0,0,0,0.55) to transparent) so type stays legible; never place a solid panel behind hero type.

\- Media thumbnails: 16:9, full-bleed, with a centered white circular play button (\~64px) at 90% opacity and a thin light ring.

\- Product tiles use tight crops with consistent aspect ratios; keep one aspect ratio per grid.

\- No illustration, no collage, no drop-shadowed cutouts, no stock-photo brightness. Everything should look like it was shot in the same dim studio.

\- Wordmark: a neutral placeholder wordmark, bold sans-serif, light on dark, \~20px cap height, placed at the far left of the header with 24px left offset. Keep it small and quiet; it should never compete with the hero.



\## Motion \& interaction



\- Transitions 200–300ms with ease-out. Nothing bouncy, nothing longer than 400ms.

\- Sticky header: transparent-to-solid #0d0d0d on scroll with a 1px bottom hairline fading in.

\- Hero carousel: cross-fade or slow horizontal slide between slides, \~600ms, autoplay optional with pause on hover; dots and chevrons reflect the active slide instantly.

\- Hover states: images scale 1.03 inside a fixed overflow-hidden frame; cards lift 2px and brighten the border to #63615c; buttons shift fill and lift 1px.

\- Scroll reveals: short fade-and-rise (12–16px, 400ms, staggered 60ms within a grid). Keep them subtle — this is a technical, restrained system, not a playful one.

\- Dropdowns open with a 150ms fade plus 4px downward translate.

\- Respect reduced-motion preferences: disable autoplay, parallax, and reveal transforms.



\## Tone \& feel



Premium, technical, and low-key confident — the visual equivalent of a matte-black instrument with one amber indicator light. Design principles: (1) darkness is the default, light is the exception; (2) one accent color, used sparingly and consistently; (3) photography carries the emotion, type stays quiet and precise; (4) generous negative space and a tight type scale instead of decoration; (5) every control is pill-shaped or softly rounded, never sharp or brutalist; (6) motion is functional and brief. The result should feel engineered, expensive, and calm — never loud, never playful, never colorful beyond the single amber note.



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

