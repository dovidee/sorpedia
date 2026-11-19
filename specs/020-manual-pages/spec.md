# 020 Assembly guides as editorial manuals

## Goal

The assembly guides (`/veiledning/*`, feature 011) read like a designed manual instead of a plain text box: a cover with a large title, a clearly separated equipment list and numbered steps with large illustrations.

## Scope

Presentation only: `templates/manual.php`, the manual rules in `public/assets/app.css` and new UI strings in `scripts/i18n_en.py`. The category palettes from spec 018 are now declared on `[data-tema]` so the manual cover can reuse them.

Not in scope: the `manuals` and `manual_steps` tables, the seed texts in `scripts/manuals.php`, the PDF export, the route and the product guide (`/guide`).

## Acceptance criteria

1. Cover: title, source line, intro, actions (go to the steps, PDF when one exists, print) and the first step illustration on a grid. A rail in the category accent shows the step count and the equipment count, both counted from the data.
2. Each guide gets the palette of its category, so guides in different categories look different. The cover palette does not change with light or dark mode.
3. The demo line is shown only when `manuals.is_demo` is set. Nothing is attributed to a manufacturer.
4. «Du trenger» lists the items from `manuals.tools` as given, split on commas. The section is left out when the field is empty. Nothing is added.
5. Steps are numbered from `manual_steps.step_no`, any count. Each step has number, heading, text and the step illustration when one exists; a step without one uses the full width.
6. No closing section, safety note or tips are generated.
7. No horizontal overflow at 390, 820 and 1366 px. One column on phones. Print gives plain black text without the cover decoration.
8. All new text has an English translation through the language toggle.
9. The steps section has an index of the steps (number and heading, linked to each step) that stays in view on wide screens. On tablet and phone the index list is hidden and the steps follow in one column.
