SØRPEDIA — CONNECTED PRODUCT INTELLIGENCE PLATFORM

Name: Southern Product Intelligence (SPI)

Control Tower × BuildLab × Søren Intelligence × Produktguiden



Project vision: Transform Sørpedia from a product catalogue with search, recommendations and chatbot functionality into an interconnected product intelligence platform.



The platform should help customers discover products, understand how products work together, build complete solutions, and get assistance throughout the process.



At the same time, customer interactions should continuously improve the understanding of what customers need, what products are missing, and where product information needs improvement.



This is not four independent features.



It is one connected ecosystem built around the existing Sørpedia product catalogue, product relationships, analytics events and recommendation infrastructure.



1\. IMPORTANT: EXISTING FUNCTIONALITY



Before implementing anything, inspect the existing repository.



Several important systems already exist and must be preserved and extended.



1.1 Søren Intelligence



Søren is the existing AI assistant.



He can already:



Access and understand current products.

Answer product-related questions.

Explain product specifications.

Help customers find products.

Discuss recommendations.

Explain existing product relationships.



Do not build a second chatbot.



Extend Søren so he understands the customer's current context.



For example:



Which products are currently selected in BuildLab.

Which products the customer has compared.

Which product guide answers the customer has given.

Which compatibility relationships have been identified.

Which configuration warnings are active.

Which products have already been recommended.



Søren should be able to discuss the actual configuration rather than starting every conversation from scratch.



1.2 Existing Produktguiden



The product guide already uses an Akinator-style question flow.



Example:



Question 1: Hva skal du gjøre?



Se bedre i mørket

Snekre eller feste noe

Male eller sparkle

Skjerme balkongen

Løfte, sikre eller fortøye

Sage eller hogge ved

Tur, båt eller grilling



Question 2: Hva slags?



Lyskilder

Lykt

Lykter og batterier

Arbeidslys

Husholdningsbatteri

Ladere og invertere

Batterier 12V



Question 3: Hvor skal det brukes?



Inne

Ved sjøen eller i båten

Litt av hvert



Question 4: Hva vil du bruke?



Under 200 kr

200 til 1 000 kr

Spiller ingen rolle



The guide narrows down the product selection after each answer.



It already supports:



Dynamic questions.

Product filtering.

Budget filtering.

Candidate counts.

Recommendation ranking.

Product suggestions.

Customer feedback.

Explanation of why products were selected.

Follow-up conversations with Søren.



The current recommendation logic is particularly important:



The ranking does not rely on the language model. It is deterministic, repeatable and cannot invent products.



Preserve this architecture.



Do not replace the recommendation engine with LLM-generated product recommendations.



Instead, connect its output to BuildLab and Søren.



1.3 Existing product intelligence



Inspect and reuse:



Current product catalogue.

Product specifications.

Verified specifications.

Derived specifications.

Buyer-contributed specifications.

passer\_sammen relationships.

passer\_ikke relationships.

Rule-derived compatibility.

Purchase-related relationships.

Existing product guide rules.

Product images and prices.

Existing shopping cart integration.



Preserve the distinction between verified, inferred and unverified information.



2\. THE FOUR CONNECTED SYSTEMS



The platform consists of four interconnected systems.



A. CONTROL TOWER — Customer intelligence



Purpose:



Understand what customers are searching for, what they are trying to accomplish, and where the current product catalogue or product information falls short.



The Control Tower should visualize:



Search activity.

Searches without results.

Product guide activity.

Product recommendation interactions.

BuildLab configurations.

Compatibility failures.

Questions to Søren.

Human escalations.

Product comparisons.

Cart additions.

Product relationships explored.

Missing product information.



Use the existing analytics event infrastructure.



Warehouse visualization



Create the interactive isometric warehouse as the centerpiece of the analytics experience.



Events move through different stations:



INNTAK — incoming analytics events.

SØK \& SORTERING — search and product discovery.

PRODUKTGUIDE — recommendation journeys.

BUILDLAB — product configurations and compatibility checks.

SØREN — customer questions and assistance.

KVALITETSKONTROLL — product data quality and verification.

AVVIK — unsuccessful searches, incompatible combinations and missing information.



Each parcel represents a real event or a clearly identified historical record.



Users should be able to click individual parcels and inspect their underlying events.



Support historical replay and time filters.



Do not manufacture live activity.



Clearly distinguish actual user activity from synthetic prototype seed data.



B. BUILDLAB — Product configuration



Purpose:



Let customers combine products into complete solutions.



BuildLab should feel like PCPartPicker combined with an industrial robot-cell configurator.



Create a visual three-panel workspace:



LEFT:



Product inventory.

Search.

Filters.

Categories.

Add product functionality.



CENTER:



Visual configuration workspace.

Selected products.

Product relationships.

Connections.

Visual assembly.

Drag and drop where appropriate.



RIGHT:



Configuration statistics.

Total price.

Total weight when known.

Compatibility status.

Missing components.

Technical specifications.

Warnings.



BOTTOM:



Save configuration.

Configuration history.

Undo and redo.

Add all to cart.

Export shopping list.

Ask Søren.

Seven specialized builders

Lys og lykter — Light Builder.

Bygg og feste — Construction Builder.

Maling og sparkel — Surface Planner.

Balkong og levegg — Outdoor Builder.

Kjetting og løft — Lifting Configurator.

Skog og ved — Forestry Kit Builder.

Tur og friluft — Adventure Kit Builder.



All seven should share a common configuration engine.



Each category must have its own validation and calculation logic.



Configuration statistics



Calculate:



Combined price.

Total weight when all required weights are known.

Quantities.

Relevant technical specifications.

Estimated material requirements.

Category-specific measurements.

Compatibility status.



Never silently treat missing specifications as zero.



Clearly identify incomplete calculations.



Compatibility system



Use the existing Sørpedia product relationships.



Four possible statuses:



DOCUMENTED: Explicitly documented compatible relationship.



RULE-INFERRED: Compatibility derived from known product specifications or rules.



UNKNOWN: Insufficient evidence to determine compatibility.



INCOMPATIBLE: Documented incompatibility.



Every relationship should explain why it received its status.



Provide references to the underlying product information.



Test-run



Add a TEST-RUN action.



The test-run should:



Inspect selected products.

Validate available specifications.

Check known compatibility relationships.

Identify missing information.

Calculate relevant statistics.

Visualize the configuration.

Generate a readable report.



A test-run is a data-based configuration check, not a claim of physical engineering validation.



For regulated or safety-critical categories, never imply that an unverified configuration is safe.



C. SØREN INTELLIGENCE — Context-aware assistance



Purpose:



Søren becomes the intelligent interface connecting the customer to the entire product intelligence platform.



Do not create another independent assistant.



Extend the existing Søren implementation.



Context awareness



Søren should understand:



Current page.

Current product.

Current product category.

Current guide session.

Previous guide answers.

Recommended products.

Products in the comparison list.

Current BuildLab configuration.

Compatibility findings.

Missing components.

Existing configuration warnings.

Example: Customer building a lighting system



Customer has selected:



Ledlenser H7R Core.

Ledlenser 21700 battery.

Ledlenser magnetic charging cable.



Customer asks:



"Will everything work together?"



Søren should:



Inspect the actual selected products.

Retrieve existing product specifications.

Retrieve documented compatibility relationships.

Check model-specific limitations.

Explain confirmed relationships.

Identify unknown relationships.

Suggest appropriate alternatives where evidence exists.



He must not invent compatibility.



Søren actions



Søren should be able to initiate contextual actions:



Add product to BuildLab.

Remove product from BuildLab.

Compare selected products.

Open product specifications.

Start a product guide.

Explain a compatibility relationship.

Suggest missing accessories.

Explain configuration warnings.

Save a configuration.

Help complete a shopping list.



Use existing application actions and APIs.



Do not allow the language model to bypass the application's validation rules.



D. PRODUKTGUIDEN — Deterministic discovery engine



Purpose:



Help customers find the correct products through short, adaptive questions.



Preserve the existing Akinator-style question flow.



Do not redesign it into a conventional chatbot.



Existing question experience



The guide should continue asking progressively narrower questions.



Example:



Question 1: What are you trying to accomplish?



Question 2: What type of product do you need?



Question 3: Where will you use it?



Question 4: What is your budget?



Each answer changes the available product candidates and determines relevant follow-up questions.



Extend the guide with configuration intent



Introduce a distinction between:



FIND A PRODUCT.

BUILD A COMPLETE SOLUTION.



Example:



Customer selects:



"Se bedre i mørket"



The guide asks what kind of lighting they need.



If the customer chooses "Arbeidslys", the guide can ask:



What environment?

Portable or fixed installation?

Rechargeable or wired?

Desired brightness?

Budget?



Once recommendations are generated, offer:



"Vil du bare sammenligne disse produktene, eller bygge et komplett lysoppsett?"



Possible actions:



Compare recommendations.

Add products to BuildLab.

Build a complete solution.

Ask Søren about the recommendations.

Recommendation logic



Preserve the deterministic recommendation ranking.



Continue using:



User answers.

Product category.

Budget.

Product specifications.

Customer ratings where available.

Store availability where available.

Documented product relationships.

Existing recommendation rules.



Never allow an LLM to invent product records or silently override the ranking.



Feedback loop



Preserve existing feedback:



"Fant du det du lette etter?"



Yes, product A.

Yes, product B.

No, I was looking for something else.



Extend the negative response so the customer can describe the missing product or need.



Record the feedback as structured data.



Do not automatically create a new product or compatibility relationship from customer feedback.



3\. THE CONNECTION BETWEEN ALL FOUR SYSTEMS



This is the most important architectural requirement.



All systems should share the same underlying product data, relationship graph and event infrastructure.



Connection 1: Produktguiden → BuildLab



The product guide recommends products.



Customers can select several recommendations and add them to BuildLab.



Example:



The guide recommends:



Ledlenser H7R Core.

Ledlenser battery.

Compatible charging accessory.



The customer selects:



"Build this setup."



BuildLab opens with those products already loaded.



The customer can then inspect compatibility, remove products, add accessories and calculate the total.



Connection 2: BuildLab → Søren



The customer can ask Søren about their current configuration.



Example:



"Am I missing anything?"



Søren inspects the configuration and available product relationships.



He can suggest accessories and explain why they may be useful.



Any suggested product must exist in the actual product catalogue.



Connection 3: Søren → Produktguiden



When Søren cannot confidently recommend a product, he can offer to start the structured product guide.



The guide receives relevant context where appropriate.



The customer should not need to repeat information already supplied.



Connection 4: BuildLab → Control Tower



Record important BuildLab events:



Configuration created.

Product added.

Product removed.

Compatibility checked.

Compatibility warning displayed.

Test-run completed.

Configuration saved.

Shopping list generated.

Configuration added to cart.



Control Tower should show:



Most frequently configured products.

Common product combinations.

Frequently missing components.

Most common compatibility issues.

Abandoned configurations.

Popular configuration categories.



Do not expose personally identifiable customer information in aggregate analytics.



Connection 5: Produktguiden → Control Tower



Record:



Guide starts.

Question answers.

Question abandonment.

Candidate counts.

Recommendation impressions.

Recommendation selections.

Positive feedback.

Negative feedback.

Customer descriptions of missing products.



Control Tower should identify:



Questions where customers frequently abandon the guide.

Categories with poor recommendation coverage.

Products frequently selected.

Common unsuccessful searches.

Repeated unmet customer needs.

Connection 6: Control Tower → Product improvement



Create a PRODUCT OPPORTUNITIES section.



This is where the platform becomes an actual product intelligence system.



Example:



Control Tower detects that customers repeatedly search for a particular accessory.



It can create an opportunity:



"Potential missing product: compatible charging accessory."



Show:



Number of unsuccessful searches.

Related search terms.

Relevant product categories.

Related products.

Existing compatibility information.

Product guide feedback.

Customer comments.



An employee can review the opportunity and:



Mark it as investigated.

Link an existing product.

Suggest a new catalogue entry.

Request missing specifications.

Add a verified product relationship.

Dismiss the opportunity with a reason.



These are suggestions for human review, not automatic catalogue changes.



4\. SHARED DATA ARCHITECTURE



Avoid creating separate databases for each feature.



Inspect the existing schema and extend it.



The platform should have shared access to:



Products.

Product specifications.

Product relationships.

Product guide sessions.

Guide answers.

Recommendations.

BuildLab configurations.

Configuration items.

Compatibility evaluations.

Søren conversations.

Analytics events.

Product opportunities.

Customer feedback.



Use stable product IDs across every feature.



A product added from a guide must be the same product record used in BuildLab, product comparison, Søren and the shopping cart.



Suggested event structure



Events should contain:



Event ID.

Event type.

Timestamp.

Session ID.

Relevant product IDs.

Relevant configuration ID.

Relevant guide session ID.

Relevant category.

Event metadata.



Use existing analytics conventions if they already provide equivalent fields.



Do not duplicate events or create multiple conflicting sources of truth.



Event integrity



Clearly distinguish:



Real user-generated activity.

Synthetic seed data.

Historical imported data.

System-generated events.



Never present synthetic demo orders or reviews as real customer activity.



5\. PRODUCT OPPORTUNITIES — THE INTELLIGENCE LOOP



Build a shared opportunity engine.



It should detect patterns across:



Search without results.

Product guide abandonment.

Negative guide feedback.

Frequently missing accessories.

Repeated compatibility failures.

Unresolved questions to Søren.

Frequently assembled product combinations.



Example:



CUSTOMER NEED: Customers want to connect a specific lighting product to an accessory.



OBSERVATION: Multiple searches and BuildLab configurations show the same missing component.



RELATED PRODUCTS: Show the relevant products already in the catalogue.



EXISTING EVIDENCE: Show verified and inferred compatibility relationships.



SUGGESTED ACTION: Investigate whether an existing accessory should be linked or whether additional product information is needed.



EMPLOYEE DECISION: Approve, investigate, link an existing product or dismiss.



The system should never automatically invent products, specifications or documented relationships.



6\. UNIFIED USER EXPERIENCE



The customer should experience one connected platform.



Suggested navigation:



Forsiden

Varehus

Produktkategorier

Produktguide

BuildLab

Sammenligninger

Spør Søren



The experience should preserve Sørpedia's existing branding and Søren mascot.



Example complete customer journey

Customer searches for a suitable work light.

The search engine finds relevant products.

The customer opens Produktguiden.

The guide asks about intended use, environment and budget.

The deterministic recommendation engine suggests suitable products.

The customer adds two recommendations to BuildLab.

BuildLab calculates combined price and available specifications.

The customer adds a battery or accessory.

BuildLab checks compatibility.

The customer asks Søren whether the configuration is complete.

Søren explains verified relationships and identifies unknown information.

The customer saves the configuration.

The customer adds the selected products to the cart.

The relevant events become available to Control Tower.

Aggregated insights can eventually identify recurring customer needs.



The customer should not have to restart the journey when switching features.



7\. DESIGN SYSTEM



Visual direction:



Nordic Industrial Luxury.



Combine:



Fieldwork robot-cell configuration interfaces.

Isometric logistics control towers.

Premium Scandinavian e-commerce.

Industrial software dashboards.

Restrained glassmorphism.



Use:



Deep pine-charcoal.

Warm birch.

Sør-Tre red.

Off-white editorial typography.

Subtle lighting and depth.

Clear technical data visualization.



Each feature should feel like part of the same product.



The warehouse should feel like an operational control room.



BuildLab should feel like professional engineering configuration software.



Produktguiden should remain approachable and conversational.



Søren should provide personality and intelligence without overwhelming the interface.



8\. DEVELOPMENT PRIORITIES



Do not attempt to implement everything simultaneously.



Phase 1 — Shared foundation

Inspect existing repository.

Document existing schemas.

Map existing APIs and actions.

Identify reusable product and compatibility services.

Preserve existing guide and Søren functionality.

Establish shared product context.

Phase 2 — BuildLab



Start with Lys og lykter.



Implement:



Product selection.

Multi-product configurations.

Compatibility checks.

Combined price.

Available technical statistics.

Test-run.

Søren integration.

Phase 3 — Produktguiden integration

Add the option to transfer recommendations into BuildLab.

Preserve deterministic ranking.

Share guide context with Søren.

Record structured feedback and events.

Phase 4 — Control Tower integration

Add BuildLab and guide analytics.

Visualize the event flow.

Add configuration insights.

Add product opportunities.

Phase 5 — Full ecosystem

Expand BuildLab to all seven categories.

Add advanced simulations where supported by reliable data.

Add employee review workflows.

Improve recommendation intelligence using aggregated insights.

9\. NON-NEGOTIABLE REQUIREMENTS

Preserve existing working functionality.

Do not create a second Søren chatbot.

Do not replace the deterministic product guide with LLM recommendations.

Reuse existing product IDs and product relationships.

Never invent products or technical specifications.

Distinguish verified data from inferred data.

Distinguish synthetic prototype data from real activity.

Keep customer information private in aggregate analytics.

Require human approval for catalogue and relationship changes.

Do not claim physical or engineering safety based on visual simulations.

Preserve existing cart, authentication and navigation functionality.

Build shared reusable services rather than duplicating business logic.

10\. DEFINITION OF SUCCESS



A customer should be able to:



Find a product through search.

Find a product through the existing guide.

Understand why it was recommended.

Compare multiple products.

Combine products in BuildLab.

Check documented compatibility.

Calculate a meaningful configuration total.

Ask Søren about the actual configuration.

Save the result.

Add the products to their cart.



An employee should be able to:



Understand customer activity through Control Tower.

Identify missing products and information.

Inspect unsuccessful searches.

See popular product combinations.

Identify compatibility gaps.

Review product opportunities.

Improve the catalogue through controlled human review.



The ultimate goal: Sørpedia should not merely help customers find products. It should help them understand what they need, assemble the right solution, and help the business learn where its product knowledge can improve.

