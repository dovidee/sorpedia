Byggelab — UX Polish & Interaction Refinement
Purpose: Improve the usability, clarity and perceived quality of Byggelab without rebuilding it.
Scope: Targeted UX refinement of the existing product configurator: product catalogue, central assembly workspace and configuration/details panel.
Implementation instruction: Implement this brief in the existing application. Do not return only a proposal or design mockup. First inspect the current implementation, its available animation skill(s), design tokens, user appearance settings and existing data structures. Then make the changes, test them and report what was changed and verified.
---
1. Core direction: preserve the existing product
The current Byggelab layout has a strong foundation:
Left: searchable product catalogue and product cards.
Centre: isometric assembly workspace showing products and their relationships.
Right: price, compatibility, missing items, calculations and data provenance.
Bottom: history, undo/redo, save/export, test-run and add-to-cart actions.
Keep this three-column architecture and the existing visual identity. The configurator already communicates its purpose well. This is a polish task, not a redesign from scratch.
Do not replace the isometric workspace with generic KPI cards, a conventional dashboard, a table-first interface or a static product list. Do not remove useful functionality merely to make the screen look simpler.
Non-negotiable engineering constraints
Preserve the existing PHP templates and plain JavaScript architecture.
Do not introduce React, React Three Fiber, a new frontend build pipeline or an unnecessary framework.
Do not add CDN dependencies or external runtime calls.
Reuse existing components, styles, libraries, design tokens and interaction patterns wherever practical.
Product facts, prices, stock, specifications and compatibility must continue to come from the database and existing authoritative application logic. Never invent product data or compatibility.
Keep demo data visibly marked as demo.
Do not change catalogue records or compatibility rules as part of a visual polish task.
Preserve existing routes, APIs, persistence, cart behaviour, history and undo/redo semantics.
---
2. Main UX problems to address
2.1 Right-hand panel: reduce cognitive load
The right panel currently contains several different information types in one long vertical stream:
Total price and configuration summary
Compatibility relationships
Missing components and warnings
Calculations and derived values
Specification values
Data provenance
The content is useful, but users must scroll through too much information and repeatedly interpret similar-looking blocks.
Refine the panel using progressive disclosure:
Keep a compact configuration summary at the top.
Put actionable problems and unresolved compatibility near the top.
Group compatibility, missing items, calculations and data provenance into clearly named sections.
Make detailed explanations and evidence expandable/collapsible.
Keep the most important status visible without requiring the user to expand every section.
Avoid nesting multiple layers of accordions.
Preserve access to all existing information; collapse detail rather than deleting it.
Suggested information order:
Configuration summary: total price, known weight/data coverage, component count and overall readiness.
Needs attention: documented incompatibilities, unresolved relationships and missing required components, if any.
Compatibility: concise relationship list with status and a short explanation.
Missing items and notes: relevant warnings and incomplete information.
Calculations: calculated values and their assumptions.
Data basis: source and provenance details.
Use the existing product terminology and Norwegian UI language. Do not introduce unfamiliar labels without checking the existing translations.
2.2 Compatibility: make status scannable
The current panel asks users to read explanatory paragraphs to understand the state of each relationship.
Improve the visual hierarchy:
Show the compatibility status first.
Make the two products in the relationship easy to identify.
Keep the explanation short in the collapsed state.
Put the detailed evidence/source behind an expandable “grunnlag” or equivalent control.
Use consistent status styling everywhere in Byggelab.
The application distinguishes at least these meaningful states:
Documented compatible
Rule-based / inferred compatibility
Unknown
Documented incompatible
Do not visually collapse “unknown” into “incompatible”. Unknown means the catalogue does not establish the relationship; it is not proof that the products fail to fit.
Use semantic, restrained status colours:
Green for documented compatible.
Amber for unresolved/unknown or caution.
Red for documented incompatible.
Neutral styling for ordinary information.
Do not use colour alone: include readable text and, where appropriate, an icon. Keep the existing compatibility rules and source evidence unchanged.
2.3 Incomplete data: make the meaning explicit
The current example shows a known weight of 167 g for only 1 of 4 components. A user could misread this as the total configuration weight.
Make coverage and completeness explicit wherever values are partial.
For example, present the information as:
Known weight: 167 g
Weight data available: 1 of 4 components
Total configuration weight: Incomplete / not fully known
This is an illustrative presentation, not a fixed value. Always render the actual database-derived values.
Apply the same principle to calculated light output, dimensions, capacity and other aggregate specifications:
Distinguish a complete total from a partial sum.
State how many components contribute to the calculation.
State when a value is an estimate, rule-based result or incomplete measurement.
Do not imply that missing values are zero.
Do not invent defaults to make a calculation look complete.
Keep calculation assumptions available in the detail view, but do not force users to read them before understanding the headline result.
2.4 Central workspace: make the assembly the focus
The isometric workspace is the visual centrepiece. Preserve it and make it easier to understand at a glance.
Refinements:
Give the assembled products and their relationships the strongest visual priority.
Reduce unnecessary competition from labels, decorative lines, legends and controls.
Keep labels readable and avoid collisions with product images or other labels.
Use concise labels by default; reveal additional details on selection or hover/focus.
Make selected products visually distinct.
Highlight the selected product's connected components and relevant compatibility relationships.
Keep connection lines and nodes visually consistent with the compatibility states.
Ensure the legend remains available but visually secondary.
Keep zoom, rotate and fit controls discoverable without dominating the workspace.
Check that the workspace remains usable at narrower viewport sizes.
Do not remove information that is necessary to understand the assembly. Prefer contextual visibility over permanently displaying every detail.
2.5 Product catalogue: polish, do not rebuild
The left catalogue is already a familiar and useful pattern.
Improve:
Product image consistency and alignment.
Card spacing and text hierarchy.
Clear distinction between product name, price and key specification.
Add-to-configuration affordance and feedback.
Search/filter active states.
Empty and no-results states.
Keyboard focus and touch target sizes.
Keep product search, category filters, product information and add actions working as they do now. Do not change catalogue retrieval or invent product attributes.
2.6 Bottom action bar: clarify action priority
Preserve the existing actions and their behaviour:
Undo / redo
History
Save
Saved configurations
Export
Overview
Ask Søren
Test-run
Add configuration to cart
Make the primary action visually clear without making every button equally prominent. Keep destructive or irreversible actions visually distinct and appropriately confirmed where the current application requires it.
On smaller screens, ensure the action bar does not obscure content or become an excessively tall second navigation area.
---
3. Motion and animation
Inspect and load the relevant installed animation skill before implementing motion. Use the skill's recommendations where they fit the existing stack. Do not install a framework or add a build step just to obtain animations.
Motion should communicate cause and effect, state changes and spatial relationships—not decorate the interface.
Recommended interactions
Add product: animate the product into its position in the assembly, with a restrained transition.
Remove product: animate its removal and update connected relationship visuals.
Select product: smoothly highlight the selected item and its connected components.
Compatibility update: transition the relationship indicator when the actual application state changes.
Totals: use a short, subtle transition when price, count or known aggregate values update.
Expand/collapse: animate detail sections without causing large layout jumps.
Undo/redo: provide clear visual feedback that the configuration has changed.
Validation: reveal warnings in context rather than flashing or shaking the entire interface.
Motion constraints
Prefer short, restrained transitions.
Avoid continuous animation when nothing is happening.
Do not animate every card, label or number simultaneously.
Avoid large bouncing, elastic or attention-grabbing effects.
Respect `prefers-reduced-motion`.
Ensure motion does not delay input or make the interface feel slower.
Keep all important state changes understandable without animation.
---
4. Appearance: light and dark mode
Byggelab must follow the user's existing ChatGPT/application appearance setting.
Do not hardcode dark mode as the default. Do not force light mode either.
Requirements:
Read and use the existing user appearance preference mechanism already used by the application.
When the user's setting is light, Byggelab must render in light mode.
When the user's setting is dark, Byggelab must render in dark mode.
If the application supports a system-following appearance setting, respect it.
Avoid a separate Byggelab-only theme preference unless the application already has one.
Do not infer the theme from a hardcoded default or from the screenshot.
Ensure all surfaces, borders, text, charts/visual indicators, tooltips, popovers, canvas/workspace elements and status states work in both themes.
Check contrast in both themes, including muted text and disabled controls.
Avoid simply inverting colours. Define or reuse semantic theme tokens.
The existing warm/light appearance shown in the supplied Byggelab screenshots is a useful reference for light mode. Preserve its clean, calm, workshop-like feel. Dark mode should use the existing product's dark design language rather than becoming a separate redesign.
---
5. Responsive behaviour
The desktop three-column arrangement is appropriate, but it must degrade deliberately on smaller screens.
Inspect the current breakpoints and implement the smallest necessary changes:
Desktop: retain the three-column layout.
Medium widths: allow the catalogue or details panel to become a collapsible/drawer-style region if needed, while keeping the workspace usable.
Mobile: prioritise the assembly workspace and provide clear ways to open the catalogue and details without squeezing all three columns into narrow widths.
Keep primary actions reachable.
Prevent horizontal overflow.
Ensure expanded detail sections remain scrollable and do not trap the page.
Do not let sticky controls cover the product workspace or important content.
Do not choose breakpoints arbitrarily; test the actual layout at representative widths.
---
6. Accessibility and usability checks
Implement and verify:
Visible keyboard focus.
Keyboard-operable controls, including expand/collapse and workspace actions where supported.
Meaningful accessible names for icon-only buttons.
Sufficient contrast in light and dark themes.
Status conveyed through text/icons as well as colour.
Adequate touch target sizes.
Reduced-motion support.
No essential information available only on hover.
Clear feedback after add, remove, save, undo and redo actions.
No unexpected focus loss when panels expand or collapse.
Use plain, concise language. Prefer familiar product-configuration terms over internal technical terminology.
---
7. Data and correctness guardrails
This is a presentation and interaction task, not permission to change the truth model.
Do not fabricate products, prices, stock, specifications, compatibility or missing-component requirements.
Do not convert unknown compatibility into compatible or incompatible.
Do not label a partial aggregate as a complete total.
Do not change calculation formulas or compatibility rules unless a genuine existing defect is discovered; if so, report it separately rather than silently broadening this task.
Keep provenance and evidence available.
Preserve demo labels on demo data.
Do not make the interface claim that a product is verified when its source is incomplete.
If a visual improvement requires data that the current backend does not provide, do not invent it. Present the available information honestly and report the missing field as an implementation dependency.
---
8. Implementation workflow
Inspect the existing Byggelab implementation and relevant documentation.
Identify the existing theme preference mechanism and design tokens.
Inspect and load the relevant installed animation skill.
Identify the right-panel sections, workspace selection model and existing responsive behaviour.
Make the improvements incrementally, reusing existing components and styles.
Verify that existing configuration, catalogue, compatibility, history, save/export and cart behaviour still works.
Test both light and dark modes.
Test representative desktop, tablet and mobile widths.
Test reduced-motion behaviour.
Run the existing project checks and relevant tests.
Do not stop after writing a plan. Implement the changes.
---
9. Acceptance criteria
The task is complete when all of the following are true:
Layout and hierarchy
[ ] Existing three-column configurator architecture is preserved on desktop.
[ ] The central isometric assembly remains the visual focus.
[ ] The right panel has a clear hierarchy and substantially less forced scrolling.
[ ] Important warnings and unresolved states are easy to find.
[ ] Detailed evidence and assumptions remain available through progressive disclosure.
[ ] Product catalogue remains searchable and functional.
[ ] Primary and secondary actions are visually distinguishable.
Correctness and data clarity
[ ] Compatibility states remain distinct: documented compatible, rule-based, unknown and documented incompatible.
[ ] Partial values are explicitly labelled as partial.
[ ] Missing data is never silently treated as zero.
[ ] No product facts or compatibility claims are fabricated.
[ ] Provenance remains accessible.
Appearance and motion
[ ] Light mode follows the user's light appearance setting.
[ ] Dark mode follows the user's dark appearance setting.
[ ] No hardcoded dark-only default remains.
[ ] Both themes have readable contrast and consistent status colours.
[ ] Motion improves feedback and spatial understanding.
[ ] Reduced-motion preferences are respected.
Responsive and accessibility
[ ] No horizontal overflow at tested viewport widths.
[ ] Catalogue, workspace, details and primary actions remain usable on smaller screens.
[ ] Keyboard focus is visible.
[ ] Icon-only controls have accessible names.
[ ] Essential information is not hover-only.
Regression checks
[ ] Add/remove product works.
[ ] Compatibility updates correctly.
[ ] Undo/redo and history work.
[ ] Save/load works.
[ ] Export works.
[ ] Test-run works.
[ ] Add-to-cart works.
[ ] Existing project tests/checks pass.
---
10. Final report required from Claude
When implementation is finished, report:
Files changed.
UX improvements implemented.
Animation skill used and the interactions where it was applied.
How the existing appearance setting is detected and applied.
Viewport widths and themes tested.
Accessibility and reduced-motion checks performed.
Tests/checks run and their results.
Any acceptance criteria not met, with a concrete reason.
Do not claim completion for anything that was not actually tested.
The goal is a calmer, clearer and more responsive Byggelab—not a new product. Preserve what already works, remove unnecessary cognitive effort, and make the existing configurator feel polished and trustworthy.
