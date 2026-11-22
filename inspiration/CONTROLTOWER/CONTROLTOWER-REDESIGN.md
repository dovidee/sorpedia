Control Tower --- UX, KPI & Visual Design Redesign
Scope: Control Tower `Innsikt` and `Oversikt`  
Type: UX / information architecture / data visualization / visual
design / interaction  
Status: Ready for implementation  
Reference material: Six supplied screenshots. Screenshots 1--4 show
`Innsikt`; screenshots 5--6 show `Oversikt`.  
Primary objective: Make the Control Tower understandable at a
glance, reduce cognitive load and unnecessary scrolling, improve KPI and
chart clarity, add purposeful motion, and implement correct light/dark
theme behaviour.
---
1. The problem
The current Control Tower contains useful operational and analytical
data, but the presentation makes users work too hard to understand it.
Innsikt --- screenshots 1--4
Observed issues:
The page is extremely long and requires substantial vertical
scrolling.
Too many panels compete for attention; there is little distinction
between primary, secondary and diagnostic information.
Amber/yellow is used for too many unrelated bars, chart series,
highlights and controls. It has lost its meaning as an accent.
Many visualizations are bars even when a bar chart is not the
clearest way to communicate the information.
Some metrics are presented without enough context, denominator,
comparison period or explanation.
Several chart labels and annotations are too small or visually
quiet.
Forecasting is shown despite very little data (for example,
`Nå: 2`). This risks implying confidence the data does not support.
The "Muligheter" section contains large repeated product cards and
raw search terms. It consumes substantial space and mixes different
kinds of operational work.
The page makes the user scan many equal-weight boxes to find what
matters.
Oversikt --- screenshots 5--6
The Overview layout is a better starting point than the current Insights
layout, but it also needs refinement.
Observed issues:
Too many cards have similar visual weight.
The persistent "Spør Søren" side panel takes significant horizontal
space and competes with the operational overview.
Some numbers lack context or a clear explanation of what they mean.
Strong status colours are used frequently and can make the page feel
more alarming than the underlying situation warrants.
Warehouse and activity information is useful, but the hierarchy
between urgent actions, current status and background information
needs improvement.
Shared design problems
High cognitive load and weak information hierarchy.
Excessive card/panel repetition.
Too much visual noise from borders, bars, badges and accent colours.
Inconsistent distinction between real operational data, demo data,
estimates and unknown values.
Important actions and exceptions do not consistently stand out from
routine information.
Dark mode is currently effectively the default, rather than being
driven by the user's theme preference.
Motion is limited, making state changes and updates feel static;
however, animation must communicate meaning rather than add
decoration.
---
2. Desired outcome
Redesign both pages as a coherent operational dashboard.
A user should be able to answer these questions quickly:
Is anything wrong right now?
What needs attention first?
How is the operation performing?
What has changed compared with the previous period?
Where can I drill down for detail?
The interface should feel calm, deliberate, legible and professional. It
should have the information density of a serious operations tool without
feeling crowded.
Do not solve this by merely shrinking everything. Reduce, group,
prioritise and progressively disclose information.
---
3. Before implementation: inspect the project and available skills
First inspect the existing Control Tower implementation, design system,
CSS tokens, theme handling, chart code, data contracts and
documentation.
Also inspect the skills available to the coding agent. Load and use
relevant skills if available, especially:
UX / interaction design
Dashboard and information architecture
Data visualization
Visual design / design systems
Animation and motion design (including the available animation
skill)
Accessibility
Do not assume a skill exists or invent its name. Discover the available
skills and read their instructions before using them.
Use the animation skill to improve the interface where motion helps
explain state, hierarchy, transitions or feedback. Do not add animation
simply to make the dashboard look busy.
Before changing code, report briefly: - Which relevant skills were found
and loaded. - Which existing components and theme mechanisms will be
reused. - Any important implementation constraints discovered.
Then proceed with implementation. Do not stop after producing a design
proposal.
---
4. Information architecture
4.1 Innsikt: from a long report into a concise analytical workspace
Reorganise Insights into clear levels of importance.
Level 1 --- At-a-glance summary
At the top, show a small set of the most useful KPIs. Each KPI must
have: - A clear human-readable label. - A prominent value. - A
denominator or supporting count where relevant. - A comparison or trend
only when valid data exists. - A short explanation or tooltip for
non-obvious metrics.
Avoid presenting every available metric as a top-level KPI. Choose the
metrics that help an operator understand the business and decide what to
do.
Examples of potentially useful metrics already visible in the current
page: - Search volume. - Searches without results. - Questions sent to
Søren. - Questions escalated to a human. - Product guide
starts/completions. - Add-to-cart and purchase events. - Orders with
compatible accessories.
Do not assume all these deserve equal prominence. Group related funnel
metrics and make the most important outcomes visually dominant.
Level 2 --- Trends and conversion
Group related analytics into a smaller number of coherent sections: -
Search activity and no-result rate. - Search-to-cart-to-purchase
funnel. - Order volume and order value. - Customer questions and
escalation. - Product guide usage.
Use chart types that fit the question: - Time series for changes over
time. - Funnel or clearly labelled sequential steps for conversion. -
Distribution plots for order values where they add value. - Ranked lists
for top products, searches or combinations. - Compact tables when exact
values matter more than shape.
Do not turn every metric into a horizontal amber bar.
Level 3 --- Diagnostics and opportunities
Move detailed or lower-priority material into compact sections,
expandable panels, tabs or focused drill-downs: - Data quality. -
Specification source distribution. - Compatibility confidence levels. -
Missing catalogue information. - Unanswered searches. - Product
opportunities. - Frequently used product combinations. - Guide details.
The user should not need to scroll through every diagnostic panel to
understand the main picture.
4.2 Oversikt: operational status first
Use the current Overview as the starting point, not as a design to copy
unchanged.
Prioritise: 1. Critical exceptions and actions requiring attention. 2.
Current operational status. 3. Orders and deliveries. 4. Warehouse/store
status. 5. Recent activity. 6. Secondary summaries.
Group related information. Reduce the number of equally prominent cards.
The "Spør Søren" assistant should not permanently consume a large part
of the dashboard by default. Prefer a compact entry point or a
collapsible drawer/panel. Opening it should be an intentional action,
and closing it should restore the available dashboard width.
Keep navigation and core operational actions easy to find.
---
5. Reduce cognitive load
Apply established UX principles deliberately:
Visual hierarchy: size, weight, spacing and position should
communicate importance.
Gestalt proximity: related values, labels and explanations
should be grouped together.
Progressive disclosure: show essential information first; reveal
detail on demand.
Recognition over recall: use clear labels and familiar chart
conventions rather than requiring users to remember definitions.
Consistency: same metric, status, interaction and colour should
mean the same thing throughout the Control Tower.
Signal over noise: remove decorative elements that do not help
interpretation or action.
Hick's law: avoid presenting too many equally weighted choices
at once.
Information scent: labels and actions should make it obvious
what users will see or do next.
Fitts's law: important actions must have practical hit areas and
clear affordances.
Accessibility: readable contrast, keyboard access, visible focus
and non-colour status cues.
Avoid: - Repeated cards with identical styling and importance. -
Unexplained abbreviations. - Tiny chart labels. - Excessive badges. -
Red or amber for ordinary, healthy states. - Requiring users to
interpret a chart when a clear number and sentence would be better. -
Hiding important meaning in hover-only interactions.
Use concise Norwegian UI copy consistent with the existing application.
---
6. KPI and data visualization rules
The current page overuses amber bars. Establish a restrained, semantic
visualization system.
Colour
Use the theme's neutral surfaces and typography as the foundation.
Reserve the primary accent for selected navigation, key highlights
and important interactive elements.
Use semantic colours only when their meaning is clear:
Red: actual errors, critical risk or urgent attention.
Amber: warning, pending review or caution.
Green: healthy, completed or positive status.
Blue or another restrained secondary hue: informational or
neutral series.
Do not use amber for every chart, bar, badge and metric.
Do not rely on colour alone to communicate meaning.
Chart selection
Choose the simplest chart that answers the question. Do not force all
visualizations into bars.
Every chart must have: - A descriptive title. - A clear time range or
scope. - Legible axes and units. - A legend only when multiple series
need one. - Useful hover/focus details where applicable. - A readable
empty state. - A short interpretation when the chart is not
self-explanatory.
Avoid unnecessary chart decoration, excessive gridlines, gradients and
visual effects.
Metric integrity and context
Every percentage must make its denominator understandable.
For example, do not show only `50%` when the meaningful context is
`2 of 4 searches`. Show both.
For rates and conversions: - Show numerator and denominator. - State the
period. - Make the calculation consistent. - Do not compare different
populations or periods without explanation.
For monetary metrics: - Display NOK consistently. - Explain mean versus
median. - Use sensible number formatting and units. - Do not imply that
a distribution or average represents a typical order when it does not.
Forecasts and small samples
Do not present forecasts as authoritative when the historical sample is
too small.
The current Insights page shows a forecast with only two data points.
That should not look like a reliable prediction.
Requirements: - Check sample size and data sufficiency before rendering
forecasts or trend claims. - If the sample is insufficient, show a clear
"not enough data yet" state. - Do not fabricate confidence intervals or
trends. - Distinguish actual values, estimates, projections and demo
data. - Make the underlying sample size visible where it materially
affects interpretation.
Data quality and provenance
The existing specification-source and compatibility-level panels contain
useful information, but need clearer labels and explanations.
Make it easy to distinguish: - Documented data. - Rule-based
conclusions. - Inferred/assumed values. - Missing or uncontrolled
values. - Demo data.
Keep the original database values and calculations. Improve
presentation, not the underlying truth.
Never invent data to make a chart look populated.
---
7. Layout and scrolling
The current Insights page is too tall.
Target: - A useful at-a-glance summary should fit within the initial
viewport on a typical desktop. - The most important trends should be
visible shortly below it. - Lower-priority diagnostics should be grouped
and collapsible or placed in a focused detail view. - Avoid a single
uninterrupted wall of cards. - Use a consistent responsive grid with
deliberate column widths and spacing. - Avoid excessively wide charts
with large unused areas. - Avoid cards so tall that their content
appears sparse. - Do not create horizontal scrolling at normal desktop
widths.
Do not force every section above the fold at the expense of legibility.
The objective is to remove unnecessary scrolling and prioritise
information, not to compress the whole application into one screen.
Responsive behaviour
Test at: - 1920px desktop. - 1440px desktop. - 1024px tablet/small
laptop. - 768px tablet. - 390px mobile.
At smaller widths: - Stack sections in priority order. - Keep primary
KPIs readable. - Make charts adapt or simplify. - Avoid clipped labels
and overlapping controls. - Ensure drawers and navigation do not obscure
essential content.
---
8. Light mode and dark mode --- mandatory
The Control Tower currently appears dark by default. This must be
corrected.
The Control Tower must follow the user's existing application theme
setting.
Requirements: 1. Inspect and reuse the existing user theme preference
mechanism. 2. If the user has explicitly selected light mode, render
light mode. 3. If the user has explicitly selected dark mode, render
dark mode. 4. If no explicit theme preference exists, use light mode as
the default. 5. Do not hardcode dark backgrounds or dark-only chart
colours. 6. Do not create a separate Control Tower theme preference if a
shared application preference already exists. 7. Do not change the
user's saved preference as a side effect. 8. Ensure charts, tooltips,
drawers, tables, inputs, borders, badges, focus states and overlays all
adapt to the active theme. 9. Check contrast and readability in both
themes. 10. Switching themes must not require a page redesign or manual
refresh if the existing application supports live theme changes.
Create or reuse semantic theme tokens rather than scattering hardcoded
colours throughout the Control Tower.
Acceptance test: With the application set to light mode, every
Control Tower route must genuinely render in light mode. Repeat in dark
mode. An unset preference must not force dark mode.
---
9. Animation and interaction
Use the available animation skill and the project's existing animation
capabilities where appropriate.
Add purposeful motion to improve comprehension and perceived quality:
Smooth transitions when changing between dashboard sections.
Subtle entrance/reveal for important content where it improves
orientation.
Animated KPI value changes when live data updates, without
distracting counting effects.
Chart transitions when the selected date range changes.
Clear loading/skeleton states.
Feedback for expanding/collapsing diagnostic sections.
Drawer opening and closing.
Clear state transitions for alerts, tasks and status changes.
Animation rules: - Motion must communicate a state change or improve
orientation. - Keep durations restrained and consistent. - Avoid
constantly moving elements, pulsing everything or animating every card
on every refresh. - Avoid parallax and decorative motion that competes
with data. - Respect `prefers-reduced-motion`. - Reduced-motion mode
must preserve all functionality and information. - Do not add a large
animation dependency if the project already has suitable capabilities. -
Do not introduce React or a new build system for animation.
The result should feel alive and responsive, not like a marketing
landing page.
---
10. Preserve data and architecture
This is a presentation and UX task, not permission to rewrite the data
model.
Project constraints: - The application uses PHP templates and plain
JavaScript. - Do not introduce React or React Three Fiber. - Do not
introduce an unnecessary package/build step. - No external runtime calls
or CDN dependencies. - Data must continue to come from the database. -
Do not invent operational or analytical data. - Demo data must remain
explicitly marked as demo. - Preserve existing permissions,
authentication and API boundaries. - Preserve existing KPI calculations
unless a demonstrable calculation bug is found. - If a calculation is
wrong, document the evidence and fix it separately and transparently. -
Do not remove useful metrics merely because they are difficult to
display. - Do not break existing routes, live updates or Control Tower
interactions.
Keep SQL/data retrieval separate from presentation. Reuse existing
readers and endpoints where appropriate.
---
11. Suggested implementation sequence
Work through the complete redesign in one coherent implementation
effort:
Inspect current implementation, theme system, chart components and
available skills.
Identify the highest-value information and group the existing
metrics.
Establish the visual hierarchy and semantic theme tokens.
Redesign `Innsikt`.
Refine `Oversikt` and make the Søren panel less intrusive.
Replace misleading or repetitive visualizations with appropriate
alternatives.
Implement purposeful animation and interaction.
Implement and verify light and dark modes.
Test responsive layouts and accessibility.
Run existing tests and relevant repository checks.
Update documentation if the design or behaviour has changed.
Do not stop after producing a mockup, plan or design proposal. Implement
the working application.
---
12. Acceptance criteria
Information architecture and usability
[ ] Both `Innsikt` and `Oversikt` have clear, intentional
information hierarchy.
[ ] Users can identify important exceptions and key performance
information quickly.
[ ] Insights no longer requires excessive scrolling through equally
weighted panels.
[ ] Lower-priority details are progressively disclosed.
[ ] The Søren panel does not permanently consume unnecessary
dashboard space.
[ ] Labels, values and actions are understandable without guesswork.
[ ] Repeated or redundant visual elements have been reduced.
Visualization
[ ] Amber/yellow is no longer the default colour for every bar and
chart.
[ ] Chart types are chosen based on the data and question.
[ ] Percentages include meaningful denominators.
[ ] Units, periods and labels are clear.
[ ] Small-sample forecasts are suppressed or clearly qualified.
[ ] Actual, demo, estimated and unknown data are distinguishable.
[ ] No data has been invented to improve the visual appearance.
Theme
[ ] Light mode works across all Control Tower routes.
[ ] Dark mode works across all Control Tower routes.
[ ] Existing user preference is respected.
[ ] Unset preference defaults to light mode.
[ ] Charts and interactive elements work in both themes.
Motion and accessibility
[ ] Relevant animations improve transitions or feedback.
[ ] Reduced-motion preferences are respected.
[ ] Keyboard focus and interaction remain usable.
[ ] Contrast and non-colour status indicators are adequate.
Technical verification
[ ] Existing routes and functionality remain intact.
[ ] PHP and JavaScript checks pass.
[ ] Existing tests pass.
[ ] Desktop, tablet and mobile layouts have been checked.
[ ] No console errors or broken chart rendering.
[ ] No external runtime dependencies have been introduced.
[ ] Documentation reflects the implemented behaviour.
---
13. Final reporting
Report: 1. Skills discovered and loaded. 2. Main UX problems addressed.
3. Pages and components changed. 4. Theme implementation and
verification. 5. Visualization changes and rationale. 6. Animation and
accessibility work. 7. Responsive verification results. 8. Tests and
checks executed. 9. Any known remaining limitations.
Include before/after screenshots of `Innsikt` and `Oversikt` in both
light and dark modes if the browser/testing environment supports them.
Do not claim a theme, responsive layout or interaction is verified
unless it was actually tested.
Definition of done: Both pages are visibly and functionally
redesigned, the dashboard is substantially easier to understand,
light/dark mode follows user settings, purposeful motion is implemented,
and all existing application behaviour remains intact.
