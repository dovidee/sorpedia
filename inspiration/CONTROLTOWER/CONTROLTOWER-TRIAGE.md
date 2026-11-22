CONTROL TOWER: INSIGHT TRIAGE, BYGGELAB WARNINGS AND TASKS
Purpose: keep customer activity, configuration warnings, review findings and improvement work apart.
Precedence: this brief governs how observations, reviews and tasks behave in Innsikt, Byggelab details and Gulv. It refines CONTROLTOWER.md sections 5.4 and 8.

---

1. AN OBSERVATION IS NOT A TASK

Observation: something a customer did or the system detected (a search without a result, a Byggelab session, a missing component, a compatibility warning).
Reviewed finding: a person looked at the observation and decided whether it shows a real, actionable issue.
Improvement task: a specific piece of work a person deliberately created to correct a confirmed issue.

An observation can be reviewed and closed without a task. Nothing creates a task automatically.

---

2. WHERE THINGS LIVE

Innsikt: observations and their evidence (searches, sessions, opportunities).
Byggelab details (drill-down): one configuration with its products, missing parts and compatibility results.
Gulv, Forbedringsoppgaver: every real task and its lifecycle. There is no other task page.

An observation linked to a task shows «Oppgave #N · status», and that link opens the task in Gulv.

---

3. REVIEW STATE IS SEPARATE FROM TASK STATUS

Review state of an observation:
Ikke vurdert: nobody has reviewed it.
Vurdert: reviewed; no task was needed.
Ignorert: noise, a typo, a duplicate or test traffic.
Oppgave opprettet: a task was deliberately created and linked.

Task status, only once a task exists: Åpen, Under behandling, Klar for kontroll (only when a separate check is genuinely needed), Fullført, Avvist, Arkivert.

The review state is stored per event (`event_reviews.outcome`); a linked task decides Oppgave opprettet.

---

4. ACTIONS PER RECORD

Raw observation: Marker vurdert, Ignorer, details.
Reviewed observation: Opprett forbedringsoppgave, offered only after Marker vurdert and with a line that says when a task is right (a concrete product, synonym, category or rule to change).
Observation with a task: Åpne oppgave #N.
Byggelab session: Se oppsett.

If a relevant task exists, link to it instead of creating another. Several observations may support one task. A task is never created to make an item leave a list.

---

5. SEARCHES WITHOUT A RESULT

The exact term, its count, sessions, first and last search and any product mapping are shown. A search without a result is a clue, not a fault. One-off noise such as a random string is ignored. A task is created only after a person confirms a concrete catalogue, synonym, category or mapping change. Frequency helps prioritise; it never proves a defect, and no threshold is invented.

Identity: anonymous sessions are called økter or anonyme økter, never customers. Test and development traffic is not presented as customer demand.

---

6. BYGGELAB WARNINGS

A session describes one customer's configuration. Its warnings belong to that session.

Missing component: guidance for completing the configuration, not a catalogue defect.
Incompatible selected components: the parts, their values and the source of each value are shown. The parts differ in this setup; neither catalogue value is called wrong without evidence.
Missing or wrong catalogue or compatibility data: may justify a task after review, with the supporting sessions linked.

Example: «Delene har ulike dimensjoner (10, 11 mm)» names each selected part with its dimension and source and says to check that shackle and hook fit the chain. No task is created for the shackle, hook or chain.

The drill-down says that a missing part concerns the customer's setup and does not mean the product data is wrong.

---

7. «SE OPPSETT» OPENS THE EXACT SETUP

Se oppsett opens the session's configuration in Byggelab through its address (`/byggelab/{builder}?c=id:qty`), with the same products and quantities. A session without stored products says that its setup cannot be opened; it never falls back to a generic view or the search list.

---

8. ACCEPTANCE

1. A search observation never creates a task by itself.
2. Noise such as «zzqxv» can be ignored without a task.
3. A Byggelab compatibility warning does not create a product task.
4. A 10 mm and 11 mm mismatch names the parts and the values with their sources.
5. A missing part in Byggelab is not treated as proof of wrong catalogue data.
6. Opprett forbedringsoppgave appears only after review, where a concrete correction can be made.
7. Innsikt shows observations and evidence; tasks are managed in Gulv, Forbedringsoppgaver.
8. Review state and task status are separate and both visible.
9. Klar for kontroll is used only when a real check is required.
10. Se oppsett opens the exact Byggelab session.
11. Anonymous sessions are not called customers.
12. Existing records are neither deleted nor converted into tasks.
