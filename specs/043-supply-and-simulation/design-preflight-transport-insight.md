# Design pre-flight: Transport map motion and labels, Innsikt without long scrolling (feature 043)

## 1. Task and scope

Changes: the Transport map gets motion that explains what happens on the road (vehicles glide between simulation ticks, a vehicle on a ferry is drawn as a boat on the crossing, a closed road is shown as a blocked stretch and the trip is planned around it when the network has another way), label decluttering (vehicle, store and place labels never print over each other; the arrival box is shown only for the selected trip and is sized to its text), and a clean fallback where a wind value is missing. Innsikt keeps its data and charts but is split into tabs, so each tab fits a 1366 x 768 screen without scrolling the page, and the charts get a plain summary line and the latest value labelled on the chart.

Does not change: the Oversikt view, the floor, the data and calculations, routes and permissions, the map projection, the tower tokens and themes.

## 2. Skills selected and read

impeccable (`.claude/skills/impeccable/SKILL.md` and its animate, distill and craft floor references in `.claude/skills/impeccable/reference/`): mandatory on UI work in this project; the surface is Operate. animate is covered by impeccable's animate reference. Rejected: overdrive and delight (decorative motion does not fit a staff tool), cosmic-glass-dashboard and other style skills (they bring their own visual language).

## 3. Conflicts and decisions

The request asks for motion that looks impressive; impeccable's Operate mode allows motion only for state and continuity (rule 1 and rule 4 agree here): every animation shows a real change in the simulation (position, ferry crossing, detour), none is decoration, and all spatial motion stops under reduced motion. Innsikt could be shortened by removing panels; the spec lists every panel (rule 2), so nothing is removed: tabs give progressive disclosure instead.

## 4. Direction

Restrained product UI on the existing tower tokens (`--info`, `--accent`, `--amber`, `--red`, `--ok`, `--panel`, `--line`). Vehicle motion is a CSS transform transition on kept SVG nodes, linear over the tick interval. Ferry: a hull under the vehicle and a moving wake on the crossing line. Closure: a red dashed stretch with the existing closure icon. Labels: one greedy placement per draw in screen space, priority selected vehicle, stores, vehicles, places. Innsikt tabs use the existing segmented button style (`tw-seg`, `aria-pressed`) as a tablist.

## 5. Preserve

Keyboard access to vehicles and tabs, `prefers-reduced-motion`, both themes, English through the language toggle, DEMO markers and sources, the presenter's camera rules, drill links and the order actions in Innsikt.

## 6. Review focus

Whether labels still collide at country zoom and at Kristiansand zoom; whether the arrival box fits its text in both languages; whether each Innsikt tab fits a 1366 x 768 screen and the tab state is clear; reduced motion; contrast of the closure and ferry marks in both themes.
