# Constitution (project principles)

All plans and implementations must follow these principles. If a specification conflicts with this document: stop and report the conflict.

1. Structured data (the database) is the source of truth for product ID, name, price and stock. The language model is only used for dialogue, interpretation and explanation.
2. Giving misleading product information is illegal (the Norwegian Marketing Control Act). That is why every specification has a source and a level, and unknown is a valid and necessary result.
3. Two versions of every product description: Verified (Sør-Tre data and staff approved data) and Open (verified buyers can edit, like Wikipedia). Staff can merge into Verified or reject.
4. Vandalism must cost something: only customers who have confirmed their email and bought at least one item can edit. The price of posting false information becomes the price of an item.
5. Every recommendation must be explainable, with evidence (product text, rule or purchase data) and a level (documented, rule based, assumed).
6. The mascot shows the model's full reasoning to the customer (white box). It is marked that the reasoning is the model's own text and may contain errors.
7. The customer can always move on from the chat to a human (Support).
8. New types of specifications require no database migration.
9. Critical rules (verified user, source level, the language model never setting prices) are enforced in code, not only in instructions.
10. Personal data is minimized. Demo users are synthetic.
