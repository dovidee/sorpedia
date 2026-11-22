# TASK: Build a Sørpedia Presentation Mode / Demo Hub

We need to prepare Sørpedia for a five-minute presentation to a jury.

**The problem:** We have built so many features that we will almost certainly forget to mention important functionality during the five-minute demo. We cannot demonstrate every feature individually, and we don't want to spend the presentation jumping between the website, PowerPoint slides, technical documentation and admin panels.

We need a coherent presentation strategy that demonstrates both the actual working product and the full scope of what we have developed.

I want you to investigate the existing project and help us implement a dedicated **Sørpedia Presentation Mode / Demo Hub** directly inside our website.

Read all relevant project documentation, inspect the actual codebase, database schema, routes, APIs, existing functionality and integrations before making assumptions.

Do not invent features, statistics, integrations, architecture, KPIs or capabilities. Everything presented to the jury must be grounded in the actual implementation. Clearly distinguish implemented, verified, demonstrable, demo-only, in-progress and planned functionality.

---

# 1. THE CORE IDEA: ONE WEBSITE, TWO TABS, ONE COHERENT PRESENTATION

I would NOT make a separate PowerPoint presentation.

I would build a dedicated presentation/overview page directly into Sørpedia.

**One website. Two tabs. One coherent presentation.**

We have too many features for a five-minute demo. The biggest risk isn't that the jury won't like the site. It's that they'll only see 30% of what we've actually built because we run out of time.

We need a way to show them the entire scope of the project without having to demonstrate every feature individually.

## TAB 1 — Live Sørpedia

This is where we demonstrate the actual product:

1. Homepage and product discovery.
2. BuildLab and product configuration.
3. Søren AI assistant.
4. Control Tower and logistics visualization.
5. A couple of particularly impressive interactions.

This tab should be strictly for the live user journey.

## TAB 2 — Sørpedia Demo Hub

This is our presentation overview.

It should visually demonstrate everything we've implemented:

- Complete feature inventory.
- System architecture.
- Technical implementation.
- Business value.
- Security and reliability.
- KPIs and measurable results.
- Implemented versus remaining work.

The second page isn't another website. It's a presentation layer for the website we already have.

It should be polished, presentation-friendly and accessible without navigating through the admin panel.

---

# 2. WHAT THE DEMO HUB SHOULD CONTAIN

Build the Demo Hub around six sections.

## SECTION 1 — Project overview

At the top:

**Sørpedia — Digital commerce, product intelligence and logistics platform.**

A concise overview of:

- What problem Sørpedia solves.
- Who the platform is designed for.
- What makes it different.
- What we've actually developed.

Include verified project statistics, such as product count, categories, implemented modules and integrations.

Do not invent numbers.

## SECTION 2 — Feature inventory

This is probably the most important section.

Organize every feature into functional categories rather than presenting a massive wall of bullet points.

Starting categories:

| Area | Features to investigate and showcase |
|---|---|
| E-commerce | Product catalogue, search, categories, filters |
| Product intelligence | Specifications, compatibility, product data |
| BuildLab | Seven specialized configuration builders |
| AI | Søren assistant, local Ollama, RAG |
| Logistics | Control Tower, delivery tracking, transport data |
| Integrations | WooCommerce, APIs, external data sources |
| Administration | Catalogue management, support, analytics |
| Security | Authentication, Argon2id, password reset, backups |
| Internationalization | Norwegian and English |

These are starting categories based on the project scope, not a declaration that every listed integration or capability is production-ready.

**Every feature must have a status:**

- Implemented
- Demonstrable
- In progress
- Planned

Where appropriate, distinguish between implemented and independently verified functionality.

The objective is to create a COMPLETE feature inventory so we never forget to mention something important.

Include smaller and less visible technical achievements too. Do not limit the inventory to the features that happen to look impressive on the homepage.

## SECTION 3 — System architecture

We need a visual architecture diagram showing how the actual Sørpedia system works.

For example, a diagram showing:

```text
             SØRPEDIA
                 |
       ┌─────────┴─────────┐
       |                   |
   FRONTEND            BACKEND
       |                   |
       |              PHP APPLICATION
       |                   |
       |              ┌────┴────┐
       |              |         |
       |           MariaDB    REST API
       |              |
       └──────────────┤
                      |
           ┌──────────┼──────────┐
           |          |          |
        BuildLab    Søren    Control Tower
           |          |          |
        Products     RAG      Logistics
```

This is an illustrative starting point, NOT an authoritative architecture specification.

Inspect the actual implementation and produce an accurate architecture diagram.

Show the real integrations, database relationships, data flows, AI infrastructure, APIs and external services.

The important thing is that the diagram reflects the real implementation, not an aspirational architecture.

The jury should be able to understand how the different parts of Sørpedia connect without having to read source code.

## SECTION 4 — The three jury criteria

This is where we should be strategic.

Make the page mirror the evaluation criteria.

### Ønskelighet — Desirability

Why would customers want Sørpedia?

- Problems solved.
- Target customers.
- Improved product discovery.
- Intelligent product configuration.
- AI-powered assistance.
- Better logistics visibility.

Use actual project functionality and explain its customer value.

### Levedyktighet — Viability

Why can this become a sustainable business?

- Business model.
- Potential revenue streams.
- Customer value.
- Operational efficiency.
- Measurable KPIs.
- Growth opportunities.

Clearly distinguish actual measured results from projections and business opportunities.

### Gjennomførbarhet — Feasibility

Can this actually be built and operated?

- Architecture.
- Database.
- Backend and APIs.
- AI infrastructure.
- Security.
- Integrations.
- Deployment and backups.

This means we don't have to remember which features belong to which evaluation category. They're already organized.

## SECTION 5 — Technical achievements

A dedicated section for the things the jury might otherwise never discover.

Investigate and document:

- Database architecture.
- API endpoints.
- Authentication and authorization.
- AI and RAG implementation.
- Data synchronization.
- Event-driven functionality.
- Backup and recovery.
- Performance and testing.
- Responsive design.
- Infrastructure and deployment.

Include expandable technical details so non-technical jurors aren't overwhelmed.

We want to demonstrate engineering depth without forcing ourselves to navigate through code or admin panels during the presentation.

## SECTION 6 — Roadmap and production readiness

This is extremely important.

Show that we understand the difference between a working prototype, an implemented system and a production-ready commercial service.

Use clear statuses:

| Status | Meaning |
|---|---|
| Implemented | Functionality exists |
| Verified | Functionality has been tested |
| Demo data | Demonstration functionality/data |
| Remaining | Work required before production |

This actually makes us look more credible, not less.

Never claim that something is production-ready simply because it works in a local demonstration.

---

# 3. HOW WE SHOULD RUN THE ACTUAL FIVE-MINUTE PRESENTATION

I would use ONE person driving the browser and ONE person presenting/narrating, if we have two presenters.

We should NOT have one person operating PowerPoint while another operates Sørpedia.

Use ONE browser with two prepared tabs.

## Five-minute demo structure

1. **0:00–0:40 — The problem**

   Introduce Sørpedia, the problem and the intended customers.

2. **0:40–2:45 — Live website**

   Demonstrate the strongest features. Don't try to show every menu.

3. **2:45–3:30 — Technical depth**

   Switch to the architecture diagram. Explain how the different systems work together.

4. **3:30–4:30 — Business value**

   Show the three evaluation criteria, customer value and measurable results.

5. **4:30–5:00 — Complete feature overview**

   Switch to the feature inventory. Show the breadth of the platform and finish with your strongest message.

Afterwards, leave the Demo Hub open during questions.

If someone asks about AI, open the AI section. If they ask about security, open security. If they ask about logistics, open Control Tower.

We have our answers ready without navigating through the application.

---

# 4. PRESENTATION INTERFACE DESIGN

I would build this as a proper presentation interface, not just another admin page.

Requirements:

- Polished, presentation-friendly full-screen layout.
- Persistent navigation sidebar.
- Large readable typography for projector screens.
- Interactive architecture diagrams.
- Feature cards with implementation statuses.
- Expandable technical explanations.
- Dedicated Q&A section.
- Keyboard shortcuts to navigate between sections.
- Direct link back to the live application.
- Clear visual hierarchy.
- Responsive layout.
- No unnecessary clutter or excessive animations.

It must look like a professional product presentation, not an ordinary administrative dashboard.

The visual design should be consistent with Sørpedia's existing design language.

The Demo Hub should be accessible without logging into the admin panel, but protected from public access if the information is confidential.

Consider whether it should be a dedicated presentation route with appropriate access control.

---

# 5. VERY IMPORTANT: COMPLETE FEATURE AUDIT BEFORE WRITING THE PRESENTATION

Before implementing the Demo Hub or writing the final presentation script, conduct a comprehensive audit of the existing Sørpedia project.

Inspect:

1. All relevant project documentation.
2. Database schema and actual database structure.
3. PHP source files and application architecture.
4. All public routes and pages.
5. All API endpoints.
6. JavaScript functionality.
7. Admin functionality.
8. BuildLab and all seven builders.
9. Søren AI and its actual capabilities.
10. Control Tower and logistics functionality.
11. External integrations and data sources.
12. Authentication, permissions and security.
13. Backup and recovery functionality.
14. Tests, evaluations and verification status.
15. Existing KPIs and analytics.
16. Anything else that represents meaningful work or functionality.

We have invested a significant amount of work in this project. I do not want the presentation to overlook features simply because they are hidden in a submenu, backend service, database table or technical implementation.

Create a complete internal feature inventory first.

For each feature, document:

- Feature name.
- What it does.
- Where it exists in the application.
- Technical implementation.
- Whether it is working.
- Whether it has been tested.
- Whether it can be demonstrated live.
- Its relevance to the jury's evaluation criteria.

Do not confuse code existence with verified functionality.

Do not invent missing information.

If something is unclear, investigate it or explicitly mark it as unverified.

---

# 6. THE MOST IMPORTANT FINAL DELIVERABLE: A VERY SHORT DEMO SCRIPT

This is essential.

The Demo Hub is useful, but we ALSO need a concise document that tells us EXACTLY what to say and do during the five-minute presentation.

I do not want the final speaking document to be another massive technical document.

After the audit and presentation planning are complete, produce a separate, extremely concise document called:

**SØRPEDIA — 5-MINUTE DEMO SCRIPT**

It should contain:

### A. What to say

Actual sentences that we can speak to the jury.

Not vague instructions like "explain the AI functionality".

Write the actual words we should say.

### B. What to show

For every speaking segment, specify:

- Which browser tab to use.
- Which page or feature to open.
- What interaction to perform.
- What the jury should notice.

### C. Who does what

If two people present, specify:

- Presenter 1: speaks.
- Presenter 2: controls the browser.

Keep the responsibilities simple.

### D. Exact timing

Follow this structure:

| Time | What we say | What we show |
|---|---|---|
| 0:00–0:40 | Problem and introduction | Sørpedia homepage |
| 0:40–2:45 | Product and strongest features | Live Sørpedia |
| 2:45–3:30 | Technical implementation | Architecture diagram |
| 3:30–4:30 | Business value | Jury criteria |
| 4:30–5:00 | Overall scope and conclusion | Feature inventory |

The actual spoken script must fit within five minutes at a natural speaking pace.

Prioritize the most impressive, differentiating and relevant features.

Do not attempt to mention every single feature verbally. The Demo Hub's purpose is to show everything else visually.

### E. Q&A cheat sheet

Create a very short reference section with likely jury questions and where to find the answers in the Demo Hub.

Examples:

- How does the data synchronize?
- How does the AI work?
- What happens if the AI goes down?
- How do you prevent data loss?
- How is the system secured?
- What is actually implemented versus planned?
- How could Sørpedia generate revenue?
- How does the logistics system work?

Only provide answers supported by the actual project.

---

# 7. MY FINAL RECOMMENDATION

Don't create two separate presentations. Don't make one person operate PowerPoint while another operates Sørpedia.

Build one cohesive presentation experience inside Sørpedia.

The live application proves that the product works. The Demo Hub proves how much we've built, how it works, why it matters and how it can become a real business.

Those are two different jobs, and we need both.

**The first thing to do is create a complete feature audit of the existing codebase, database, routes, APIs and documentation. That becomes the source of truth for the Demo Hub, ensuring we don't forget the less visible achievements that might earn us points with the jury.**

---

# EXECUTION ORDER — FOLLOW THIS

Please work in this order:

1. **Audit:** Investigate the complete existing Sørpedia implementation.
2. **Inventory:** Create a comprehensive feature inventory with accurate statuses.
3. **Presentation strategy:** Identify the strongest features and map them to the three jury criteria.
4. **Architecture:** Produce an accurate diagram based on the actual codebase.
5. **Demo Hub:** Implement the presentation interface inside Sørpedia.
6. **Five-minute script:** Write the short, practical speaking and demonstration document.
7. **Q&A:** Prepare a concise jury-question reference.
8. **Verification:** Test the Demo Hub, links, navigation, responsive layout and every claim made in the presentation.

Do not rush straight into coding before understanding the existing project.

Do not introduce unnecessary frameworks or dependencies. Respect the existing Sørpedia architecture, project conventions and technical constraints.

Do not break existing functionality.

**THE ULTIMATE GOAL:**

When we stand in front of the jury, we should only need two browser tabs and one very short speaking document.

We should be able to demonstrate the most impressive parts of Sørpedia in five minutes, while the Demo Hub makes it immediately obvious that there is substantially more functionality, engineering and work behind the product than we could possibly demonstrate live.

We should never finish the presentation thinking: "Shit, we forgot to mention that feature."

Make sure we don't.

## Levedyktighet (Forretningsverdi og målbare gevinster)

Løsningen skaper direkte forretningsverdi for Sør-Tre ved å løse to kostbare flaskehalser uten å kreve ekstra manuelt arbeid fra ansatte:

- **Økt salg gjennom verifiserte kjøper-spesifikasjoner (To-lagsmodellen):** Sør-Tres egne produktdata ligger låst øverst, mens verifiserte kjøpere (koblet mot faktiske ordrenummer) fyller inn manglende tekniske spesifikasjoner og praktisk erfaring over tid. Mer komplette produktsider øker kjøpstryggheten og konverteringen uten at Sør-Tre må skrive spesifikasjoner manuelt for hundrevis av kategorier.
- **Færre returer og mindre kundesupport med Byggelab og Søren:** Kunder sjekker kompatibilitet *før* betaling i Byggelab og får presise svar fra Søren basert på Sør-Tres egne data. Dette stopper feilkjøp før pakken sendes, reduserer kostbare returer og frigjør tid for kundeservice.
- **Direkte målbart i eksisterende tall:** Gevinsten krever ingen nye analysesystemer og måles rett i WooCommerce-data Sør-Tre allerede har: (1) antall produkter beriket av verifiserte kjøpere, (2) salgsvekst på berikede produkter, og (3) nedgang i kompatibilitetsrelaterte returer på pakkekjøp.

**ADDITION TO THE SØRPEDIA PRESENTATION MODE TASK — MAKE THE INTEGRATION OBVIOUS**
! ABSOLUTELY IMPORTANT !

A central part of Sørpedia's identity is that its components are interconnected. The presentation must communicate this clearly.

Do not present Sørpedia, Søren AI, BuildLab and Control Tower as four independent features or products. Explain and demonstrate them as parts of one integrated platform.

Add the following:

1. **One end-to-end workflow**
   - Demonstrate a real product/customer scenario across as many connected components as the implementation supports.
   - Reuse actual database records and identifiers wherever possible.
   - Explain how information moves from one component to another.
   - Do not invent connections or fabricate data to make the demonstration appear more integrated.

2. **Interactive architecture and data-flow diagram**
   - Visualize the actual relationships between Sørpedia, Søren AI, BuildLab, Control Tower, the database, APIs and event systems.
   - Label connections with the actual data being exchanged and the mechanism used.
   - Allow the presenter to select a connection and reveal its technical explanation.
   - Ground every connection in the existing codebase.

3. **Integration evidence**
   - Identify which integrations are fully implemented, partially implemented or planned.
   - Show the relevant API, database relationship, event mechanism or service responsible.
   - Do not describe a planned connection as an existing integration.

4. **Presentation narrative**
   - Introduce Sørpedia as one connected platform.
   - Demonstrate the customer journey rather than switching between disconnected feature demonstrations.
   - Use transitions that explain why each component is relevant to the next.
   - Finish by showing how the components collectively solve the larger business problem.

The jury should leave understanding not only what each component does, but why the components belong together.

Audit the existing implementation before deciding which connections can be demonstrated. Preserve all existing project rules and architecture.
