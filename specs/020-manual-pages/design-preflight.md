# Design pre-flight: animated step illustrations

## Task and scope

Each step illustration on `/veiledning/{slug}` acts out its step: a part is lifted, slid in, dropped into place, drawn or swapped. A step plays when it reaches the middle of the screen or when its link in the step index is clicked. Steps above the current one jump to their end state. Text, layout, the step index, the cover art and the PDF do not change.

## Skills selected and read

`impeccable` (SKILL.md and reference/animate.md), mandatory for UI work in this project, with docs/PRODUCT.md loaded (register: product). `animate` (SKILL.md), for the motion plan. Considered and rejected: `overdrive` and `gpt-taste` (scroll scrubbing, pinning and GSAP do not fit a calm product register and would add a dependency), `transitions-dev` (covers UI component transitions, not illustration).

## Conflicts and decisions

The product register asks for 150 to 250 ms transitions, while a step that demonstrates an action needs time to be read. Decision (user requirement first): each part moves in 700 ms with an ease out curve, staggered by up to 0.8 s, played once. Scroll triggered motion against reduced motion: with `prefers-reduced-motion`, without JavaScript and in print, the illustration is shown in its end state only.

## Direction

Plain CSS keyframes in `app.css` on classes inside the SVG markup (`m-a` plus one motion such as `m-drop`, `m-in-l`, `m-in-r`, `m-lift`, `m-grow`, `m-draw`, `m-pop`, `m-out-r`, `m-file`), with a per part delay in `--d`. Only transform, opacity and stroke-dashoffset animate. Timing uses `--ease-out`; colour stays `currentColor` and the theme tokens (`--ok` for the charging light). A small observer in `app.js` adds `play` and `done` to the step items. The end state is the static drawing, so the PDF printer keeps working unchanged.

## Preserve

The step index anchors and sticky index, the cover art, light and dark themes, the English toggle, print and the PDF downloads, the `no-media` layout, and keyboard focus on the index links.

## Review focus

Motion respects reduced motion and print; nothing animates layout properties; the end state equals the drawing in the PDF; a step above the current one is never caught half way; motion explains the step rather than decorates it.
