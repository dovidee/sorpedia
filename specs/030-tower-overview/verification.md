# 030 Verification

Endpoint: `/api/tarn/oversikt` returned 200 for the admin. The figures were compared with SQL on the same database: 1 711 of 4 124 filter values present, 0 of 2 deliveries on time in the last 30 days (both demo), 1 case waiting, shelf coverage 383 of 496 in Kristiansand and 235 of 496 in Oslo, 2 shipments on the road from Kristiansand.

Screenshots at 1600 px: eight cards in four columns, activity and the warehouse table side by side, Søren docked with the opening line "God kveld. 1 kundesak venter på svar. 1 sending er forsinket." Switching to Gulv showed the floor at full width without the KPI column (canvas 1240 x 662), with Søren closed. In English at 390 px: one column, no sideways scrolling, Søren not docked.

Found and fixed while testing: the first switch to the overview waited for the floor data, so a click on another view during the first seconds was overridden (now the overview opens before the floor loads); the floor was resized while hidden (now resized when shown); "1 kundesaker" and "1 sendinger" (singular and plural forms); the coverage column was cut off in the warehouse table.

Non-admin: as `ansatt` the endpoint returned orders without `ore` and `refundOre`, and no amount in the activity rows.

Open case and keyboard: "Åpne saken" switched to Kundeservice with case #1 open in the list; Enter on the focused Produktdata card opened Innsikt; all 8 cards are focusable.

First-load motion, sampled in the browser: right after render the first gauge arc and its figure were 0 and the bars at scale 0; 450 ms later the arc was part way and the figure read 33 on the way to 41; the screenshot after the run showed every card, gauge, figure and bar at its real value.

Reduced motion, emulated in the browser: no animations ran and the first gauge showed its full arc and 41 at once.

Tour with `?omvisning`, clicking Next through all 16 steps: every step highlighted its element on the expected view (Oversikt for the first two and the last, then Gulv, Transport, Kundeservice and Innsikt), and the tour closed after the last step.
