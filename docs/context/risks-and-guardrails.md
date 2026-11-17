# Risks and guardrails

| Risk | Guardrail |
|---|---|
| Misleading specifications (illegal) | Source and level on every value. Derived and user data are marked. Only staff can merge into Verified |
| Trolling and spam in the open version | Only verified buyers can edit. Full history with rollback. Staff can lock fields |
| The model makes up prices, stock or products | The model only gets product candidates from the database (ID, name, price). The answer is rendered with price and stock from the database, not from the model's text. Product IDs that weren't in the context are discarded |
| Prompt injection through user text in the open version | User text is sent to the model wrapped and marked as data, never as instructions |
| The reasoning looks more reliable than it is | DeepSeek-R1's "thinking" is generated text and is not guaranteed to be the actual reason for the answer. The panel is labeled "How the mascot reasoned (may contain errors)" |
| Ollama is unavailable during the demo | Rule-based fallback with a visible decision trail (which rules and filters were used) |
| The customer gets stuck | A "Talk to a human" button creates a case for Support, updated live |
| Fake reviews | Only verified buyers of that exact product can review it. Demo reviews are marked "Demo" |
| Wrong stock status | Stock per store is synthetic and marked as demo |
| Privacy | Synthetic users. No email is sent out, all email goes to the demo inbox |
