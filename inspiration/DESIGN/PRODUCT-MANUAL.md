PRODUCTMANUAL.md
SØR-TRE — PRODUCT MANUAL DESIGN & ARCHITECTURE
1. PURPOSE

Define the frontend architecture and visual presentation of Sør-Tre's product manuals and practical guides.

This document covers ONLY the product manual experience.

It does not define the general website, homepage, product catalogue, Control Tower, BuildLab or other application interfaces.

Claude has access to the supplied visual reference.

Use that reference to establish the visual direction of the product manual interface.

The objective is to combine the reference's distinctive editorial, Scandinavian industrial design with a highly readable and practical instruction experience.

Follow:

MASTER-DESIGN.md
LAYOUT.md
This document for product manual architecture and presentation.
2. VISUAL DIRECTION

The supplied reference is the visual authority for the design.

Study the image before implementation.

Translate its visual characteristics into the product manual experience:

Strong editorial typography.
Large, confident headings.
Asymmetric layouts.
High-contrast light and dark sections.
Restrained industrial yellow/amber visual accents, interpreted through the established Sør-Tre palette.
Technical illustrations and photography.
Clear separation between content sections.
Generous whitespace.
Minimal decorative interface elements.
Strong visual hierarchy.
Precise alignment and composition.

Do not reproduce the reference website literally.

Do not create a generic documentation website, Bootstrap interface or conventional ecommerce help page.

The product manual must feel like a designed editorial publication with the usability of a technical instruction manual.

3. PRODUCT MANUAL CONTENT ARCHITECTURE

Every manual must support the following content structure.

A. Manual heading
Manual title.
Manual type or source attribution, when available.
Short introductory description.

Example:

Male tak inne uten striper

Demoveiledning. Ikke fra produsenten.

Med riktig forarbeid og rulleteknikk blir taket jevnt. Mal i én økt uten pause så kantene ikke tørker.

B. Required equipment and materials

A clearly identifiable section presenting the required tools and materials.

Example:

Du trenger:

Rulle med skaft
Rullebrett
Pensel
Malertape
Sparkel
Sandpapir korn 120

Present this as a compact, readable list.

Use actual manual content only.

Do not add equipment or materials that are not present in the source.

C. Numbered instructions

The central part of the manual.

Each instruction consists of:

Step number.
Short descriptive heading.
Instructional text.
Supporting image or illustration, if supplied.

Example:

1. Sparkle og slip

Fyll hull og sprekker med sparkel. La tørke, slip med korn 120 og tørk av støvet.

2. Masker kantene

Tape langs vegger, lister og lamper. Trykk tapen godt ned så malingen ikke kryper under.

3. Skjær inn med pensel

Mal en stripe på 5 cm langs kantene med pensel før du ruller.

4. Rull vått i vått

Rull i felt på cirka en kvadratmeter, først på kryss, så glatt ut i samme retning, mot lyset fra vinduet.

The number of steps must be dynamic.

Never assume every manual has four steps.

Never invent missing instructions.

4. PAGE LAYOUT ARCHITECTURE

Build a reusable manual page template.

Section 1 — Editorial introduction

A distinctive opening composition.

Large manual title.
Source/type label.
Short introduction.
Optional relevant visual supplied by the manual.

The title must be the primary visual focus.

Use the asymmetric editorial composition and typography of the supplied reference.

Section 2 — Equipment and preparation

Present required equipment in a clearly separated content area.

The section should be easy to scan before starting the instructions.

Avoid excessive cards, badges and decorative containers.

Section 3 — Instructions

The main reading experience.

Use a consistent numbered instruction structure.

Each step should have sufficient spacing and a clear visual relationship between its number, heading, text and supporting media.

Allow images to occupy meaningful space rather than reducing them to small thumbnails.

Support manuals with and without images.

Section 4 — Completion

Do not automatically generate a conclusion, safety warning or additional recommendations.

Only render completion content when it exists in the actual manual data.

5. COMPONENT ARCHITECTURE

Implement reusable components appropriate to the existing framework.

Suggested logical components:

ProductManualPage — page composition.
ManualHeader — title, source attribution and introduction.
ManualMaterials — required equipment and materials.
ManualSteps — instruction sequence.
ManualStep — individual numbered instruction.
ManualMedia — supporting images or illustrations.

These are architectural suggestions, not mandatory filenames.

Inspect existing components before creating new ones.

Reuse existing components and data structures wherever possible.

Avoid unnecessary abstraction.

The page must work with different manual lengths and content variations.

6. DATA INTEGRITY

The existing deterministic product guide must be preserved.

Before implementation, inspect how manuals are currently stored, retrieved and associated with products.

Reuse existing:

Manual content.
Product relationships.
APIs.
Data structures.
Images and media.
Existing rendering functionality.

Do not invent manual content, technical instructions, specifications or product relationships.

Do not automatically attribute a manual to a manufacturer.

Preserve actual source attribution and content classification.

For example, "Demoveiledning. Ikke fra produsenten." must only appear when supported by the actual manual metadata.

Unknown information must remain unknown.

The design must adapt to the available content rather than requiring fabricated content to fill visual gaps.

7. RESPONSIVE BEHAVIOR

Follow LAYOUT.md.

Desktop:

Large editorial headings.
Generous content margins.
Balanced asymmetric compositions.
Large supporting imagery.
Comfortable reading widths.

Tablet:

Adjust columns and image proportions.
Preserve typography hierarchy.
Maintain clear separation between sections.

Mobile:

Single-column instruction flow.
Readable headings and body text.
Clear step numbering.
Full-width supporting images where appropriate.
Compact but generous equipment presentation.
No horizontal overflow.

Instructions must remain comfortable to read throughout the entire page.

8. TECHNICAL IMPLEMENTATION

Before coding:

Inspect the existing manual implementation.
Locate the deterministic product guide.
Identify its data structures and API integrations.
Identify existing manual-related components.
Inspect available manual images and assets.
Read MASTER-DESIGN.md and LAYOUT.md.
Identify the actual framework, installed versions and dependencies.
Search and consult the official documentation for the actual installed framework and relevant libraries.

Do not assume the framework or introduce dependencies based on guesswork.

Use official documentation corresponding to installed versions.

Preserve the existing deterministic guide and its functionality.

Implement the visual architecture independently of the content source wherever possible.

Do not refactor unrelated application functionality.

9. IMPLEMENTATION STEPS

Step 1 — Audit

Understand the current manual implementation, data, components and relationships.

Step 2 — Design architecture

Establish the manual page composition and reusable component structure based on the supplied reference.

Step 3 — Implement visual foundation

Apply typography, spacing, colors, content widths and responsive behavior using the existing design system.

Step 4 — Implement manual components

Build the header, materials section and numbered instruction sequence.

Step 5 — Integrate existing data

Connect the new presentation to the existing deterministic product guide.

Step 6 — Verify

Test different manual lengths, missing optional images, source attribution, mobile layouts and actual content rendering.

Verify that no existing functionality has regressed.

10. ACCEPTANCE CRITERIA
The manual interface visually follows the supplied reference.
The result feels like a premium Nordic industrial editorial publication.
The manual structure is clear and consistent.
Instructions are easy to read and follow.
Materials and equipment are easy to identify.
Step numbering is dynamic.
Images are presented meaningfully when available.
Missing content does not generate fabricated information.
Existing deterministic functionality remains intact.
Responsive layouts work correctly.
MASTER-DESIGN.md and LAYOUT.md are respected.
FINAL DIRECTIVE

Build the architecture and visual presentation of the product manual experience — nothing else.

The supplied image defines the visual ambition.

The actual product manual content defines what must be displayed.

The existing repository defines what functionality is available.

Do not invent content, functionality or data.

Make the product manual look like the supplied design reference, while keeping the instructions exceptionally clear, practical and readable.
