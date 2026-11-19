SØRPEDIA — MASTER REDESIGN IMPLEMENTATION BRIEF
0. CRITICAL EXECUTION RULES

You are redesigning Sørpedia, the product intelligence platform of Sør-Tre.

This is an existing application with existing functionality, data, APIs, routes, analytics, product relationships, deterministic product discovery and mascot animations.

You are NOT building a new generic e-commerce website.

You are NOT creating a generic SaaS dashboard.

You are NOT creating a generic hardware-store template.

You are redesigning an existing, functional product intelligence platform into a cohesive, distinctive, production-quality experience.

0.1 Strict task boundaries

There are four major workstreams:

DESIGN OVERHAUL — MASTER-DESIGN.md, LAYOUT.md, UI-brutalism.jpg
UNIQUE CATEGORY THEMES — all EXAMPLE-* references and partofdesigniliked.txt
CHATBOX OVERHAUL — CHATBOX.md
PRODUCT MANUAL OVERHAUL — PRODUCTMANUAL.md and productmanualinspo.webp

YOUR ONLY TASK IS WORKSTREAM 1: DESIGN OVERHAUL.

Complete the first workstream, verify it, document what changed and STOP.

Do not begin workstream 2, 3 or 4.

Do not redesign the chatbox, product manuals or BYGGELAB as independent features.

Do not interpret this brief as permission to refactor the entire application.

Work only on the shared design foundation and the UI surfaces required to implement and verify that foundation.

If another workstream appears necessary, document the dependency and leave it untouched.

Do not silently expand the scope.

0.2 Mandatory reference hierarchy

Before writing or modifying any UI:

Read MASTER-DESIGN.md in its entirety.
Read LAYOUT.md in its entirety.
Inspect UI-brutalism.jpg.
Inspect the existing application and its components.
Identify existing functionality that must be preserved.

MASTER-DESIGN.md has the highest visual authority.

LAYOUT.md is mandatory for layout and structural decisions.

UI-brutalism.jpg provides the visual reference for the intended brutalist direction.

The EXAMPLE-INDUSTRIAL.md, EXAMPLE-PRODUCT.md and EXAMPLE-TECHNICAL.md files are secondary references. They must not override the master design.

Do not combine multiple example themes into an incoherent visual system.

If references contradict each other, follow the hierarchy above.

Never invent a new design direction because a reference contains a visually attractive component.

1. PROJECT IDENTITY
1.1 What Sørpedia is

Sørpedia is the digital product intelligence environment of Sør-Tre.

Sør-Tre is an established Norwegian specialist retailer founded in 1950, based in Søgne, Kristiansand.

Its customers include:

Professional tradespeople and contractors.
Homeowners and DIY enthusiasts.
Cabin and property owners.
Boat owners and marine enthusiasts.
Forestry and gardening customers.
Customers purchasing technical equipment who need reliable advice.

The assortment spans tools, machinery, building supplies, fasteners, lighting, electrical equipment, paint, surface treatments, forestry, gardening, marine equipment, outdoor products, workwear and safety equipment.

The platform connects product discovery, technical knowledge, product relationships, compatibility, store inventory, customer insights and contextual assistance.

1.2 Design identity

The visual identity is:

NORDIC INDUSTRIAL INTELLIGENCE.

Combine:

Scandinavian architectural brutalism.
Modernist editorial design.
Premium industrial equipment presentation.
Precision engineering interfaces.
Industrial logistics visualization.
Sophisticated technical information architecture.

The result should feel like a genuine specialist retailer's advanced product intelligence environment.

It should not look like a template purchased from a SaaS marketplace.

1.3 What the design must communicate

Every important screen should communicate at least one meaningful thing:

What the product is.
What it does.
Which technical properties matter.
Which other products it works with.
What problem it solves.
What customers need to consider before buying.
Whether relevant stock information is available.
Why a particular recommendation is appropriate.

Visual decoration is secondary to information and functionality.

2. DESIGN GOVERNANCE
2.1 One design system, multiple expressions

The entire application must use one underlying design system.

Pages may have different visual compositions, imagery and category atmospheres.

They must not have different unrelated design languages.

The following must remain consistent:

Global navigation.
Typography roles.
Type scale.
Spacing scale.
Content width rules.
Grid logic.
Buttons.
Form fields.
Focus states.
Dividers.
Iconography.
Motion principles.
Responsive breakpoints.
Light and dark mode behavior.
Accessibility standards.

Category-specific color is an extension of the design system, not a separate theme.

2.2 No arbitrary design decisions

Before implementation, establish:

Design tokens.
Typography tokens.
Semantic color tokens.
Category color tokens.
Spacing tokens.
Component specifications.
Layout specifications.
Motion tokens.
Light mode mapping.
Dark mode mapping.

Avoid hardcoded, unrelated values scattered throughout components.

Use the project's existing token architecture where possible.

2.3 Do not blindly follow the example designs

The example files are references for individual treatments.

They are not complete templates.

Do not:

Copy an entire example website.
Apply an unrelated serif typeface to selected pages.
Switch between unrelated palettes.
Introduce a different radius system on every page.
Change typography because a reference uses a different font.
Create a new navigation design for individual categories.
Create multiple versions of the same component.

The master design always wins.

3. VISUAL DIRECTION
3.1 Overall appearance

The application should be visually confident and unmistakably engineered.

Use:

Strong typography.
Deliberate asymmetric composition.
Visible grid structure where appropriate.
Large product imagery.
Technical linework.
Thin, precise borders.
Strong contrasts between structural regions.
Controlled negative space.
Purposeful density.
Interesting relationships between typography, data and imagery.

Use brutalism through structure, composition and typography, not through random decoration.

Brutalism does not mean making everything ugly, oversized, chaotic or difficult to navigate.

The application should remain refined and usable.

3.2 Typography

Do not default to generic Inter-style SaaS typography for every role.

Investigate available fonts and choose a distinctive but legible system.

Use three clear typographic roles:

Display typography: distinctive modernist or architectural character.
Functional typography: highly readable sans-serif for navigation, body copy and controls.
Technical typography: monospace for measurements, identifiers, metadata, technical values and operational information.

Establish a limited scale with clear hierarchy.

Avoid:

Unnecessary italics.
Random font combinations.
Excessive uppercase labels.
Oversized headings on every section.
Decorative section numbers.
Excessive letter spacing.
Decorative serif headings copied from unrelated references.

Typography must reinforce the product's identity.

3.3 Color system

Use OKLCH-based design tokens.

The primary identity should derive from:

Deep pine-charcoal.
Warm birch neutrals.
Muted amber.
Sør-Tre red.
Carefully controlled technical neutrals.

Sør-Tre red should be recognizable and used deliberately for important actions, selected states and emphasis.

Amber should support technical hierarchy and subtle information accents.

Do not introduce unrelated saturated colors simply because a page needs variety.

Use semantic tokens such as:

--color-background
--color-surface
--color-surface-raised
--color-text-primary
--color-text-secondary
--color-border
--color-accent
--color-accent-hover
--color-focus
--color-selection

Provide complete light and dark mode mappings.

Never assume that inverting colors automatically creates a usable light theme.

Check text contrast, product images, borders, hover states, inputs, disabled states and overlays independently.

4. CATEGORY COLOR ARCHITECTURE

Category differentiation must use semantic color tokens.

It must not create separate unrelated component themes.

Suggested category atmospheres:

Category	Atmosphere
Lys og lykter	Warm amber, muted graphite, technical illumination
Bygg og feste	Industrial steel, concrete, construction neutrals
Maling og sparkel	Controlled pigment-inspired pink, muted rose and warm neutral
Balkong og levegg	Natural timber, muted outdoor neutrals
Kjetting og løft	Brushed steel, silver, graphite
Skog og ved	Deep forest green, natural earth
Tur og friluft	Muted moss, stone and outdoor neutrals

These are category-specific accents, not replacements for the Sørpedia identity.

Implement category tokens that feed into the shared component system.

For example:

--category-accent --category-muted --category-surface-tint --category-ambient

The category theme may influence:

Hero atmospheric details.
Small graphic accents.
Selected filter states.
Technical diagrams.
Category-specific illustrations.
Subtle background treatments.
Active category indicators.

It must not arbitrarily change:

The header.
The typography system.
Button geometry.
Navigation behavior.
Component spacing.
Global interface conventions.
4.1 Light and dark modes

Every category must work in both modes.

Use separate semantic OKLCH values for light and dark appearances.

Do not simply lower opacity or invert the category colors.

Verify every category's contrast and visual hierarchy in both modes.

5. PAGE ARCHITECTURE AND CONTENT RULES
5.1 The homepage and category pages have different purposes

The homepage introduces Sørpedia and the wider product intelligence experience.

Category pages help customers understand and navigate a particular product category.

Product pages help customers evaluate a particular product.

These are three different information problems.

Do not reuse homepage copy on category pages.

Do not reuse category introductions on product pages.

Do not force every route into the same hero composition.

Shared components are encouraged. Shared irrelevant content is forbidden.

5.2 Homepage

The homepage may introduce:

Sør-Tre and its specialist knowledge.
Product discovery.
The product catalogue.
The deterministic product guide.
Product compatibility.
Product intelligence.
Warehouse and inventory intelligence.
BYGGELAB as an existing capability.
Søren as a contextual assistant.
The wider product ecosystem.

The homepage owns the primary introductory product-guide CTA.

Do not duplicate the homepage's entire introductory pitch on every subsequent page.

5.3 Category pages

Category pages must contain information specific to the actual category.

A category page should be able to answer:

What products belong here?
What are the important differences between them?
Which technical properties should customers compare?
What are the relevant selection criteria?
What are the typical applications?
Which product relationships are useful?
Which filters actually help customers make a decision?

Use existing product data and relationships.

Examples:

Lys og lykter

Relevant information may include:

Lumens.
Beam distance.
Beam angle.
Battery chemistry and capacity.
Runtime.
Charging.
IP protection.
Mounting and application.
Kjetting og løft

Relevant information may include:

Working load limits.
Material.
Chain grade.
Dimensions.
Standards.
Intended applications.
Compatibility between components.
Maling og sparkel

Relevant information may include:

Surface type.
Coverage.
Finish.
Indoor and outdoor applications.
Drying time.
Preparation.
Compatibility with primers and coatings.

Only show attributes that are relevant and supported by the existing product catalogue.

Do not invent specifications.

Do not invent standards.

Do not claim compatibility without supporting data.

Do not present inferred information as verified.

5.4 Category page layout consistency

Use a consistent underlying page framework.

A suitable category page may contain:

Category introduction.
Relevant category-specific information.
Subcategory navigation.
Useful technical selection criteria.
Filters.
Product listing.
Relevant compatibility or buying guidance where supported.

The exact layout may vary according to the amount and type of available information.

Do not force identical proportions or identical component placement where they reduce usability.

However, maintain a consistent visual grid and layout logic.

For example, do not randomly place product imagery beside the title on one page, below it on another and above it on a third without an intentional, reusable layout rule.

Choose a small number of approved layout patterns.

5.5 Product pages

Product pages should prioritize the actual product.

Relevant information includes:

Product identity.
Product images.
Product specifications.
Variants.
Availability.
Product documentation.
Related products.
Verified compatibility.
Applications.
Relevant buying considerations.

Do not turn every product into a marketing landing page.

Do not repeat generic platform descriptions.

Do not invent technical information.

6. ANTI-SLOP: STRICT CONTENT REQUIREMENTS

This section is mandatory.

The current implementation contains repeated generic content that consumes valuable space without giving the customer useful information.

This must stop.

6.1 Forbidden repeated category copy

Do not insert the following on every category page:

"Filtrer på mål og egenskaper, se hva som passer sammen, og sjekk om varen er inne i Kristiansand."

This is generic platform-level messaging.

It does not explain any particular category.

Do not reuse the homepage introduction:

"Svar på fire spørsmål, så foreslår Søren produkter og forklarer hvorfor. Du ser også hva som passer sammen, hva andre kjøpere har lagt til av info, og om varen er inne i ditt varehus."

That introduction belongs to the homepage or the relevant product-guide experience.

It does not belong on every category page.

6.2 Remove unnecessary editorial decoration

Do not automatically generate:

"01 / SE BEDRE I MØRKET"
"05 / LØFTE, SIKRE ELLER FORTØYE"
"Forsiden / Lys og lykter"
"Forsiden / Kjetting og løft"

These labels currently create the appearance of design sophistication without providing meaningful information.

Only use a category number if the number is a real part of the information architecture.

Only use an eyebrow if it communicates actual useful context.

Only show breadcrumbs when they genuinely help navigation, particularly for deeper routes.

A page does not need to announce the category it is already displaying.

Do not manufacture editorial storytelling for ordinary product categories.

6.3 No meaningless descriptions

Every paragraph must provide at least one of:

Product information.
Technical explanation.
Buying advice.
Application information.
Compatibility information.
Useful navigation context.
A meaningful explanation of a feature.

If it does not, remove it.

6.4 Copywriting standards

Write in natural Norwegian.

Avoid generic AI phrases such as:

Elevate.
Seamless.
Unlock.
Discover the power of.
Next-generation.
Transformative.
Experience the difference.
Your ultimate destination.

Use concrete, informative language.

Bad:

"Oppdag en verden av innovative løsninger for dine behov."

Good:

"Velg hodelykt etter lysstyrke, batteritid og hvor langt du trenger å se."

Bad:

"Vi gjør det enklere å finne den perfekte løsningen."

Good:

"Sammenlign løftekapasitet, kjettingdimensjon og godkjente komponenter."

Do not add copy merely to fill empty space.

A page with less text and more relevant information is better than a page full of marketing language.

6.5 No automatic section numbering

Numbers such as 01, 02, 03 are reserved for genuinely sequential processes, steps or meaningful classifications.

Do not decorate every page with arbitrary numbers.

6.6 No duplicate CTAs

The homepage owns the primary introductory product-guide CTA.

Do not automatically place both:

"Start produktguiden"

and

"Spør Søren"

on every category page.

Do not add repeated floating calls to action that compete with the actual shopping experience.

Søren may remain available through the existing assistant interface, but the assistant must not become a repetitive marketing section on every route.

Feature-specific actions must be contextual and useful.

7. PRODUCT PRESENTATION AND INFORMATION DENSITY

The fourth reference screenshot is a particularly important functional inspiration.

Study its information hierarchy.

It communicates:

Physical relationships.
Operational state.
Technical structure.
Movement.
Live status.
Exceptions.
Performance.
Contextual information.

This is the level of substance Sørpedia should aspire to.

Do not copy an airport baggage-control interface literally.

Translate its principles into the retail and product intelligence domain.

Examples:

Product compatibility maps.
Technical attribute comparisons.
Component relationships.
Product assembly structures.
Warehouse availability.
Stock provenance.
Product specification panels.
Relevant operational events.
Demand information.
Product selection flows.

The goal is to make information understandable through visualization.

Do not substitute decorative cards for meaningful relationships.

7.1 Avoid card-heavy layouts

Do not default to three identical cards in a horizontal row.

Avoid repetitive grids of identical marketing cards.

Use cards only when elevation or containment communicates hierarchy.

Prefer deliberate compositions:

Asymmetric grids.
Split editorial layouts.
Product-first compositions.
Technical tables.
Data-rich panels.
Full-width visualizations.
Structured comparison layouts.
Spatial relationship diagrams.

Use the existing application's data structures.

Never fabricate warehouse activity, demand figures, product compatibility or inventory.

Distinguish actual information from simulated prototype information.

8. MOTION DESIGN SYSTEM

Motion must be treated as a design discipline, not as decoration added at the end.

Establish one motion personality across the application.

8.1 Motion personality

Use PREMIUM / ENGINEERED motion.

The motion should feel:

Precise.
Deliberate.
Responsive.
Controlled.
Physically coherent.
Sophisticated.

Avoid playful bouncing, exaggerated overshoots and arbitrary floating elements.

Use a consistent easing family based on:

cubic-bezier(0.4, 0, 0.2, 1)

Use faster easing for simple interactive feedback.

Use appropriate enter and exit easing rather than one universal transition.

8.2 Motion hierarchy

Use three layers where they genuinely improve a scene.

Primary motion

The principal action.

Examples:

A product assembly becoming visible.
A hero visualization entering.
A major content section appearing.
A technical diagram revealing its relationships.
Secondary motion

Supporting feedback.

Examples:

Labels appearing after the main element.
Icons responding to interaction.
Supporting data becoming visible.
Small interface elements following the main movement.
Ambient motion

Subtle background activity.

Examples:

Extremely restrained lighting changes.
A technical visualization slowly rotating.
A subtle depth shift in a complex visual.
Controlled environmental movement.

Do not force ambient animation into every component.

Do not introduce constantly moving backgrounds simply to satisfy a checklist.

8.3 Motion timing

Use sensible starting ranges:

Interaction	Duration
Tooltip	80–120ms
Hover feedback	100–180ms
Button feedback	120–180ms
Component transition	200–350ms
Dialog	250–400ms
Page transition	400–600ms
Hero reveal	600–1000ms

These are design tokens, not mandatory values for every animation.

8.4 Page-load animation

The first impression should feel intentionally choreographed.

Suggested sequence:

Structural page frame appears.
Primary hero element enters.
Main typography becomes visible.
Supporting product imagery follows.
Technical details and secondary controls appear.
The page settles into a stable state.

Use restrained stagger.

Do not animate everything simultaneously.

Do not make the customer wait for the interface to become usable.

8.5 Scroll animation

Use scroll reveals selectively.

Examples:

Product imagery enters as the section becomes visible.
Technical relationships reveal progressively.
A large visualization responds to scrolling where it adds context.
Data panels appear in a controlled sequence.
Major sections transition without disrupting reading.

Do not animate every paragraph.

Do not attach a reveal animation to every HTML element.

Do not make normal scrolling feel like a slideshow.

Use Intersection Observer or an appropriate animation library.

Avoid unnecessary JavaScript scroll handlers.

8.6 Micro-interactions

Interactive elements must communicate their state.

Implement appropriate feedback for:

Buttons.
Navigation.
Filters.
Search.
Dropdowns.
Tabs.
Inputs.
Selected products.
Comparison states.
Expandable information.
Dialogs.

Avoid exaggerated button squash-and-stretch.

The system should feel precise rather than cartoonish.

8.7 Performance

Prefer transforms and opacity.

Avoid repeatedly animating layout dimensions, filters and expensive shadows.

Use lazy loading and appropriate media loading strategies.

Do not introduce unnecessary animation libraries.

Audit existing dependencies before adding anything.

8.8 Accessibility

Respect prefers-reduced-motion.

When reduced motion is enabled:

Remove parallax.
Disable decorative continuous animation.
Reduce or remove spatial transitions.
Preserve functional feedback.
Use simpler opacity transitions.
Never make information dependent on motion.

On mobile, simplify complex motion.

Motion must never interfere with navigation or purchasing.

9. SIGNATURE INTERACTION

The homepage needs one memorable, useful interaction that demonstrates Sørpedia's identity.

Do not build another generic animated hero with floating cards.

Investigate a signature product intelligence visualization.

A suitable concept:

The product relationship explorer

A full-width interactive technical environment that demonstrates how a customer's product choice connects to other products.

Possible structure:

A central selected product.
Related compatible components.
Technical attribute information.
Product variants.
Relevant supporting equipment.
Verified compatibility relationships.
Contextual product details.

For example, selecting a lighting product could reveal its actual compatible batteries or mounting accessories if those relationships exist in the data.

Use genuine product records and existing relationships.

Do not invent relationships.

The visualization should communicate the difference between simply searching a catalogue and understanding how products work together.

This should feel like an engineered product environment rather than a generic promotional animation.

Treat this as a proposed design concept. Do not implement a new backend or fabricate missing data during the master design overhaul.

10. INTERFACE STATES AND DETAILS
10.1 Text selection

Implement a custom selection color using the Sør-Tre accent.

Use:

::selection

The selection background must be a visible, accessible red or Sør-Tre red tint.

The selected text must remain readable in both light and dark mode.

Do not leave the browser's default selection color if it conflicts with the visual identity.

10.2 Scrollbars

Implement restrained scrollbar styling using the semantic design tokens.

The scrollbar should use the same visual identity in both themes.

Avoid browser-specific styling that breaks scrolling or accessibility.

10.3 Focus states

Every interactive control needs a visible focus indicator.

Use the red accent or an appropriate semantic focus token.

Never remove outlines without replacing them.

10.4 Icons

Use one consistent icon family.

Avoid mixing unrelated icon packs.

Avoid excessive decorative icons.

Use icons to improve recognition, not to fill empty space.

10.5 Imagery

Use actual product images wherever possible.

Maintain consistent image treatment and aspect ratios within a given product grid.

Do not place images inside arbitrary white cards if the surrounding visual system calls for a different treatment.

Do not use generic stock imagery when real Sør-Tre imagery is available.

Preserve image provenance.

11. TECHNICAL IMPLEMENTATION REQUIREMENTS

Before changing code, inspect:

Existing routes.
Layout components.
Navigation.
Global styles.
Design tokens.
Theme implementation.
Product components.
Product data.
Search.
Filters.
Existing animation libraries.
Responsive behavior.
Existing tests.
Existing Søren behavior.

Identify which components should become shared design primitives.

Avoid unnecessary rewrites.

Do not replace functioning application architecture merely to simplify the redesign.

Do not create parallel implementations of existing functionality.

Do not duplicate product data.

Do not alter API contracts without a demonstrated requirement.

Do not change the deterministic product guide.

Do not modify existing mascot animations.

Preserve all working functionality.

11.1 Reusable architecture

Where appropriate, establish:

Global design tokens.
Shared typography components.
Shared buttons.
Shared input components.
Shared navigation.
Shared category layout.
Shared product image treatment.
Shared technical information components.
Shared responsive grid primitives.
Shared motion primitives.

Do not abstract components unnecessarily.

A reusable component must represent a genuine shared design or behavior pattern.

11.2 Theme implementation

Use the existing theme architecture if available.

If it is inadequate, propose the smallest necessary improvement.

All semantic tokens must work across light and dark modes.

Category accents must not override the global design identity.

11.3 Responsive design

Design and test:

Large desktop.
Standard desktop.
Tablet.
Mobile.

Do not simply shrink the desktop layout.

Reconsider composition and information hierarchy on mobile.

Preserve access to essential product information and filters.

12. IMPLEMENTATION WORKFLOW

You must follow these stages in order.

Do not skip stages.

Stage A — Repository audit

Read the required design references.

Inspect the repository.

Identify existing functionality and architecture.

Record:

Existing routes.
Shared components.
Theme architecture.
Design tokens.
Existing visual inconsistencies.
Existing functionality that must not change.
Relevant technical constraints.

Do not modify code during this stage.

Stage B — Design diagnosis

Explain precisely why the current implementation fails the brief.

Address:

Inconsistent themes.
Generic category copy.
Repeated calls to action.
Unnecessary breadcrumbs and labels.
Inconsistent layout patterns.
Typography.
Visual hierarchy.
Missing product information.
Insufficient functional substance.
Inconsistent light and dark modes.
Motion quality.

Use screenshots from the existing implementation as evidence.

Stage C — Design proposal

Before coding, produce:

DESIGN-OVERHAUL-PLAN.md

It must contain:

Final visual direction.
Token architecture.
Typography choices.
Light and dark color mapping.
Category color architecture.
Global layout rules.
Homepage composition.
Category page composition.
Product presentation rules.
Motion principles.
Shared component inventory.
Responsive rules.
Functionality preservation plan.
Testing plan.

Explain how the proposal follows MASTER-DESIGN.md and LAYOUT.md.

Do not ask me to approve every tiny implementation detail.

Present a concise, coherent proposal, and wait for authorization before implementation.

Stage D — Design foundation

After authorization:

Implement the shared design foundation.

Start with:

Global tokens.
Typography.
Color modes.
Spacing.
Navigation.
Buttons.
Form controls.
Focus states.
Selection colors.
Scrollbars.
Shared layout primitives.

Verify the foundation before continuing.

Stage E — Page implementation

Apply the design foundation to the existing homepage and relevant shared page templates.

Respect the distinction between homepage, category pages and product pages.

Remove generic repeated copy.

Remove unnecessary decorative labels.

Preserve the actual product discovery functionality.

Do not move into workstreams 2–4.

Stage F — Motion implementation

Implement the agreed motion system.

Use the existing stack or the smallest appropriate additional dependency.

Verify performance, reduced-motion behavior and mobile behavior.

Do not introduce decorative animation everywhere.

Stage G — Verification

Run the project's existing checks.

Where available, use browser automation and screenshot comparisons.

Test:

Homepage.
Lighting category.
Chain and lifting category.
Paint category.
At least one additional category.
A product detail page.
Light mode.
Dark mode.
Mobile navigation.
Product filters.
Search.
Existing product-guide behavior.
Existing Søren behavior.
Keyboard navigation.
Reduced motion.

Check for:

Horizontal overflow.
Broken imagery.
Inconsistent spacing.
Duplicate copy.
Broken interactions.
Poor contrast.
Inconsistent component styling.
Unintended changes to existing functionality.

Compare screenshots at the same viewport sizes.

Do not declare completion just because the application compiles.

Stage H — Completion report

Create:

DESIGN-OVERHAUL-REPORT.md

Include:

What was changed.
What was preserved.
Files modified.
Components introduced or updated.
Design tokens implemented.
Pages verified.
Tests performed.
Known issues.
Remaining work.
Explicit confirmation that workstreams 2, 3 and 4 were not started.

Then STOP.

Do not start the next task.

13. STRICT ANTI-DESIGN-SLOP CHECKLIST

Before declaring any page complete, verify:

[ ] The page has a clear purpose.

[ ] The content is specific to that page.

[ ] No generic homepage introduction has been copied into a category page.

[ ] No unnecessary breadcrumb or editorial numbering has been added.

[ ] No repeated CTA has been added without a functional reason.

[ ] The typography follows the global system.

[ ] The layout follows an intentional composition.

[ ] Product imagery is consistent.

[ ] The interface contains actual useful product information.

[ ] No meaningless marketing paragraphs exist.

[ ] Cards are used only when appropriate.

[ ] The page is not an obvious three-equal-card template.

[ ] Colors follow the semantic token system.

[ ] Both light and dark modes work.

[ ] Motion follows the global motion system.

[ ] Reduced-motion behavior works.

[ ] Mobile layout has been considered independently.

[ ] Existing functionality is preserved.

[ ] No fabricated specifications, stock information or compatibility claims exist.

If any item fails, fix it before moving on.

14. FINAL CREATIVE STANDARD

The intended result should feel like a sophisticated Scandinavian industrial intelligence platform.

Imagine:

The precision of engineering software.
The information density of a real logistics control room.
The visual confidence of brutalist architecture.
The product presentation of premium industrial equipment.
The typography of a carefully art-directed modernist publication.
The usability of a mature specialist retail platform.

Do not imitate any one reference literally.

Create an identity that belongs to Sør-Tre.

Every visual decision must serve one of three purposes:

Communicate information.
Improve navigation or interaction.
Strengthen the visual identity.

If an element does none of these, remove it.

The objective is not to make the website look more designed. The objective is to make Sørpedia feel like a real, sophisticated product intelligence system.

Complete the first workstream only. Verify it. Report it. Stop.
