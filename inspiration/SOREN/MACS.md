MACS — A Hybrid Multi-Agent Framework for Reliable Conversational E-Commerce Recommendation

Juli Huang et al. · arXiv · 14 August 2026

The architecture separates two responsibilities:
- Shopping agent: understands natural-language requests and gathers preferences.
- Merchant agent: retrieves products, enforces hard constraints and applies business rules deterministically.
The paper uses SQL-enforced product filtering rather than trusting the LLM to obey instructions about product selection. 
arXiv+1


Reported results
Measurement	Result
Single-query pass rate	87.1%
Brand compliance	100%
Filter compliance	97%
Expanded benchmark pass rate	85.3%

We have researched the architecture. We are making a final architectural change to Søren.

Implement a constrained language-understanding layer with a deterministic PHP commerce engine.

This is NOT the previous proposal where Ollama selects product IDs.

Ollama must not select, rank or recommend products.

### 1. Ollama responsibilities

Use Ollama's local `/api/chat` structured-output support with a JSON Schema.

The model may extract:
- Customer intent
- Requested product category
- Explicit requirements
- Budget constraints
- Missing information
- Whether clarification is required

Use `think: false` and `stream: false` for this structured task. Never expose reasoning to customers.

Do not give Ollama authority to generate:
- Product IDs
- Product names
- Recommendations
- Prices
- Product specifications
- Compatibility claims
- Order status
- Customer-facing factual explanations

No free-text reasoning field.

Validate extracted constraints against the user's actual message and the application's allowed taxonomy. Ambiguous requirements must trigger clarification rather than guessing.

### 2. PHP responsibilities

PHP exclusively owns:
- Product retrieval
- Product-type filtering
- Product eligibility
- Compatibility
- Product ranking
- Specifications
- Pricing
- Stock
- Cart state
- Order state and confirmation
- Customer-facing factual responses

Use existing database structures, FactAnswer, Usage and other existing logic wherever possible.

The model must never choose which database records constitute the correct recommendation.

### 3. Customer-facing output

PHP constructs all product-related answers using documented database information and controlled response templates.

Do not send model-generated product advice or reasoning to the customer.

Ollama may retain unrestricted conversational capability for unrelated small talk, but product, shopping, compatibility, cart and order intents must follow the constrained pipeline.

### 4. Long-term maintainability

The taxonomy, product attributes and allowed filters should be data-driven wherever possible.

Adding a product or changing its documented specifications must not require new prompt instructions, product-specific hardcoding or another AnswerGuard regex.

Do not introduce a new model, external API, framework or unnecessary dependency.

Keep the existing PHP/plain-JavaScript architecture.

### 5. Implementation

Inspect the existing code before changing it.

Produce an implementation plan identifying:
- Existing components to reuse
- Changes to the request/response pipeline
- Database and order-state ownership
- Failure and clarification handling
- Files that must change
- Existing functionality that must remain intact

Then implement the architecture.

Do not start another cycle of individual hallucination patches. Do not treat JSON Schema compliance as proof of correct intent extraction.

The objective is to make unsupported product claims impossible in customer-visible product answers by removing the model's authority to generate them.

Document the architectural change and its verification in the relevant specifications.
