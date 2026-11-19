LAYOUT.md
UI/UX Layout, Spacing & Visual Rhythm Guidelines

Purpose: Establish a consistent, intentional, and responsive layout system across all pages, components, and interfaces.

Primary philosophy: White space is a functional design element, not wasted space. Every spacing decision must support hierarchy, readability, focus, and visual rhythm.

These guidelines apply to all UI elements, including:

Page layouts
Navigation
Content sections
Cards and containers
Typography and text blocks
Buttons and CTAs
Lists and repeated components
Mobile and desktop interfaces
1. WHITE SPACE IS STRUCTURE
Principle

Never treat empty space as wasted space. White space creates visual separation, establishes hierarchy, and directs user attention.

Requirements
Give independent elements enough breathing room.
Separate icons, headings, descriptions, and CTA buttons.
Avoid elements visually colliding or appearing compressed.
Use padding to establish internal component structure.
Use margins and gaps to establish relationships between components.
Preferred implementation
Generous vertical padding between unrelated content blocks.
Tighter spacing between elements that belong together.
Clear separation between content and actions.
Consistent spacing around visual elements.
Avoid
Cramped vertical margins.
Text touching adjacent components.
Buttons positioned too close to descriptions.
Removing spacing simply to fit more content.

Rule: If removing whitespace makes the interface harder to scan, that whitespace is necessary.

2. CLEAR SEPARATION BETWEEN SECTIONS
Principle

Every independent section must be visually distinguishable.

Users should immediately understand where one content group ends and another begins.

Requirements
Establish clear vertical separation between content sections.
Use consistent gaps between repeated list items.
Keep thumbnails, text, metadata, and tags grouped together.
Separate individual content groups using spacing rather than unnecessary borders.
Make section boundaries obvious without overusing visual decoration.
Preferred implementation
Use larger spacing between independent sections.
Use smaller spacing between related elements inside a section.
Maintain consistent vertical separation between repeated content rows.
Avoid
Sections visually bleeding into each other.
Dense lists without sufficient separation.
Using multiple borders and dividers to compensate for poor spacing.
Treating every element as an independent section.

Rule: Related elements should feel connected. Unrelated elements should feel separated.

3. USE A CONSISTENT SPACING SYSTEM
Principle

All spacing must follow a deliberate, reusable spacing scale.

Random spacing values create visual inconsistency and make interfaces feel unfinished.

Default spacing scale

Use the following spacing tokens as the foundation:

Token	Value	Primary use
space-1	4px	Micro adjustments
space-2	8px	Closely related elements
space-3	12px	Text and compact components
space-4	16px	Standard component spacing
space-5	24px	Content block separation
space-6	32px	Larger component separation
space-7	40px	Section spacing
space-8	48px	Major layout padding
space-9	64px	Large section separation
space-10	80px	Hero and major page spacing
Default layout recommendations
Outer desktop container padding: 48px.
Heading-to-description spacing: 12px.
Related text spacing: 8–12px.
Content-to-button spacing: 24px.
Card internal padding: 24px.
Independent section spacing: 40–64px.
Requirements
Reuse spacing tokens instead of arbitrary values.
Keep repeated components geometrically consistent.
Introduce new spacing values only when justified.
Use design tokens or centralized CSS variables.
Avoid
Arbitrary combinations such as 18px, 22px, 27px, and 39px without a clear reason.
Different spacing between visually identical components.
Fixing alignment issues with random margin overrides.

Rule: Consistency is more important than inventing new spacing values.

4. TYPOGRAPHY NEEDS ROOM TO BREATHE
Principle

Text readability depends on more than font size. Line height, paragraph spacing, and text width are equally important.

Requirements
Use comfortable line heights.
Separate headings from supporting descriptions.
Maintain readable paragraph widths.
Avoid excessively dense text blocks.
Establish clear heading and body typography hierarchy.
Recommended typography defaults
Element	Recommended line height
Large headings	1.1–1.2
Standard headings	1.2–1.3
Body text	1.5–1.7
Supporting text	1.4–1.6
Compact labels	1.3–1.4
Additional guidance
Keep paragraphs visually distinct.
Use adequate space between paragraphs.
Avoid long uninterrupted blocks of text.
Use font weight and size to establish hierarchy rather than relying exclusively on color.
Avoid
Compressed line heights.
Overcrowded paragraphs.
Headings touching body text.
Excessively wide text columns.

Rule: Optimize typography for comfortable reading, not maximum information density.

5. MAINTAIN CONSISTENT VERTICAL RHYTHM
Principle

Vertical spacing determines the visual pace of an interface.

Repeated elements should follow the same spacing logic throughout the page.

Requirements
Maintain consistent spacing between repeated cards.
Use predictable distances between media, headings, descriptions, and buttons.
Establish a repeatable vertical pattern for components.
Keep the rhythm consistent when scrolling through long pages.
Example component rhythm

A standard content card should follow:

Top internal padding.
Media or visual element.
Spacing below media.
Heading.
Spacing between heading and description.
Description.
Spacing before CTA.
Primary action.
Bottom internal padding.
Avoid
Different vertical gaps between identical cards.
Inconsistent positioning of buttons.
Arbitrary spacing changes between sections.
Visually uneven stacked components.

Rule: Repeated components must share the same vertical rhythm unless their content genuinely requires an exception.

6. KEEP CARDS AND COMPONENTS FOCUSED
Principle

A component should have one clear purpose. Adding more content does not automatically increase its value.

Requirements

Each component should prioritize:

One primary visual element.
One clear heading.
Concise supporting information.
One primary CTA, when appropriate.

Secondary information should only be included when it improves the user's understanding or decision-making.

Card design priorities

Preferred structure:

Focused media.
Clear heading.
Short description.
Single primary action.

Avoid:

Excessive badges and pills.
Multiple competing highlight banners.
Long, dense descriptions.
Too many secondary actions.
Unnecessary decorative elements.
Multiple competing primary buttons.
Component hierarchy
Primary information.
Supporting information.
Secondary metadata.
Optional actions.

Rule: Every component must have a clear visual priority. Remove elements that compete with its primary purpose.

7. RESPONSIVE SPACING AND SCREEN ADAPTATION
Principle

Spacing must adapt to the available screen size.

Desktop spacing should not simply be copied onto mobile devices.

Mobile layout (below 768px)

Recommended defaults:

Element	Spacing
Top/media padding	24px
Below media	24px
Heading to description	12px
Before CTA	16px
Card padding	16–20px
Between sections	32–40px

Requirements:

Reduce excessive horizontal padding.
Preserve readable text widths.
Maintain sufficient separation between components.
Avoid horizontal overflow.
Keep primary actions accessible.
Stack multi-column layouts where appropriate.
Tablet layout (768–1023px)

Requirements:

Use intermediate spacing values.
Preserve content hierarchy.
Adjust grid columns to available width.
Avoid prematurely applying desktop-sized gutters.
Desktop layout (1024px and above)

Recommended defaults:

Element	Spacing
Top/media padding	40px
Below media	40px
Heading to description	12px
Before CTA	24px
Card padding	24–32px
Between sections	48–64px

Requirements:

Allow more generous whitespace.
Use balanced content widths.
Avoid excessively stretched text columns.
Maintain consistent alignment across sections.

Rule: Responsive design must preserve the same visual hierarchy, not necessarily the same pixel measurements.

8. IMPLEMENTATION STANDARDS

When implementing or modifying an interface:

Inspect the existing layout before making changes.
Identify the primary content hierarchy.
Establish relationships between related and unrelated elements.
Apply the spacing scale consistently.
Use reusable spacing variables or design tokens.
Ensure consistent vertical rhythm.
Check typography and line-height.
Remove unnecessary visual clutter.
Test the layout at mobile, tablet, and desktop widths.
Verify that spacing remains intentional across all breakpoints.
Suggested CSS variables
css
:root {
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 24px;
  --space-6: 32px;
  --space-7: 40px;
  --space-8: 48px;
  --space-9: 64px;
  --space-10: 80px;
}

.container {
  padding-inline: var(--space-8);
}

.card {
  padding: var(--space-5);
  gap: var(--space-4);
}

.section {
  margin-bottom: var(--space-8);
}

.text-block {
  line-height: 1.6;
}

@media (max-width: 767px) {
  .container {
    padding-inline: var(--space-4);
  }

  .card {
    padding: var(--space-4);
  }

  .section {
    margin-bottom: var(--space-6);
  }
}

9. FINAL DESIGN QUALITY CHECKLIST

Before considering any page or component complete, verify:

Spacing
White space is intentional and functional.
Related elements are grouped together.
Independent sections have sufficient separation.
Spacing follows the established scale.
Repeated components have consistent gaps.
Typography
Text has comfortable line height.
Headings and descriptions are clearly separated.
Paragraphs are easy to scan.
Text widths are appropriate.
Components
Cards have a clear primary purpose.
Unnecessary content has been removed.
Primary actions are visually obvious.
Components do not feel overcrowded.
Responsive design
Mobile spacing has been optimized.
Tablet layouts remain balanced.
Desktop layouts use appropriate whitespace.
No horizontal overflow exists.
Visual hierarchy remains consistent across screen sizes.
10. INSTRUCTIONS FOR CLAUDE

Treat this document as the default layout and spacing specification for the project.

When generating, modifying, or reviewing UI:

Follow these rules by default.
Prioritize visual clarity over content density.
Never introduce arbitrary spacing without a reason.
Reuse existing design tokens and component patterns.
Preserve the established visual identity of the project.
Do not redesign unrelated elements unnecessarily.
When existing components violate these principles, improve them without breaking their functionality.
Apply responsive spacing deliberately rather than scaling everything proportionally.
Prefer simple, intentional layouts over decorative complexity.
Priority order

When design decisions conflict, prioritize:

Usability and readability.
Clear visual hierarchy.
Consistent spacing.
Responsive behavior.
Visual simplicity.
Decorative details.

FINAL DIRECTIVE: Design with intention. Every gap, margin, padding, and alignment decision must have a purpose. White space is part of the interface, not an absence of interface.
