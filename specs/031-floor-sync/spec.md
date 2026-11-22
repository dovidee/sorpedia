# 031 Floor synchronisation

## Goal

The first time staff open Gulv, the warehouse floor builds itself before it goes live, like connecting to a digital twin. The wait for the first data request becomes something to watch, and nothing in it pretends to be data.

## Behaviour

1. First visit to Gulv after the page loads: a perspective grid lights up, the structure rises from the ground as a glowing wireframe, turns solid, gets its materials, light and shadows, and the floor goes live. The staged part takes 5.2 seconds of animation time (`public/assets/tower-sync.js`).
2. A status panel lists the real steps (grid and coordinates, building and conveyors, materials and light, live data, activating the floor) with a bar and a percentage. The data step finishes only when the floor's first request has answered; until then the bar stops at 92 %.
3. Only the fixed structure is staged. Products, shipments, counts and labels are the application's data and appear when the request has answered.
4. Live updates (the poll every 5 seconds) never restart the sequence.
5. Coming back to Gulv from another view runs a short resync: one edge scan up the structure that fades in 900 ms. The full panel is not shown again.
6. Other views get a short scan line and a soft rise of the content when opened (420 ms): down a page, across the map.
7. With reduced motion, or without WebGL, nothing is staged: the floor goes live as soon as the data has answered.

## Acceptance

1. The panel appears on the first visit to Gulv and disappears after the floor is live.
2. Polls and later visits do not show the full panel again.
3. With `prefers-reduced-motion: reduce` the panel never appears.
