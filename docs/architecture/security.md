# Security

Each point refers to its category in the OWASP Top 10:2025. Diagram: section 7 in `docs/architecture/diagrams.md`.

**CSRF.** Every form has a token (`csrf_field()`). All POST requests are checked in one place in `public/index.php` with `check_csrf()`, which compares using `hash_equals()`. The chat sends the token in the `X-CSRF` header. Without a valid token the server answers 400. A01 Broken Access Control.

**Access control.** The staff, customer service and admin pages check the role with `has_role()` on every request. Editing and reviews require `is_verified()`. Orders and profile pictures are always fetched together with the logged in user, and a support case only by the session that created it, so nobody can see someone else's by changing a number in the URL. A01 Broken Access Control.

**SQL injection.** Prepared statements with PDO and emulation turned off (`PDO::ATTR_EMULATE_PREPARES => false`). The few queries built as text only use numbers cast with `(int)` or values passed through `quote()`. A05 Injection.

**XSS.** All output is escaped with `h()`. Text from users goes through `hc()`, which also hides profanity with asterisks (f***). Data passed to JavaScript is encoded with `JSON_HEX_TAG`. A05 Injection.

**Prompt injection.** Protected in several layers (`src/Guard.php`). A specification from a buyer must be a value, not an instruction: text with words like "ignore", "system" or "you are", links, code characters or talk about price and stock is rejected before it is saved. Invisible characters, Cyrillic look-alikes, `ign0re` and s p a c e d letters are normalized before the check. Before reaching the model, everything from buyers is checked again, shortened and quoted so the model sees it as data. Chat messages that try to override the mascot, or that are about self harm, violence or profanity, get a fixed answer without asking the model. The server owns the chat history: the browser sends only the new message (`src/ChatHistory.php`). The model only gets public product info from a fixed list of pages, never passwords, accounts, carts or orders, and never exact prices or stock (`src/ProductRef.php`). Product cards are built from the database, not from the model's text. A05 Injection and LLM01:2025 Prompt Injection.

**Passwords.** Argon2id (`password_hash()` with `PASSWORD_ARGON2ID`) and at least 8 characters. A04 Cryptographic Failures.

**Login.** `session_regenerate_id(true)` on login, logout, password change and account deletion, against session fixation. The session cookie is `HttpOnly` and `SameSite=Lax`, `Secure` under HTTPS, and the server only accepts session IDs it created itself (`use_strict_mode`). Five wrong passwords in a row lock the account for 15 minutes (`src/Auth.php`). Unknown email, wrong password and locked account all give the same message. A07 Authentication Failures.

**Redirects.** After a POST, `redirect()` sends 303 See Other. Addresses from forms (`back`) go through `safe_back()`, which only accepts paths on this site, so `//evil.no` can't send the customer elsewhere. A01 Broken Access Control.

**Configuration.** Every page sends `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` and `Referrer-Policy: same-origin`. Apache and PHP hide their version numbers (`docker/Dockerfile`). The app only listens on `127.0.0.1`, and the database is not reachable outside Docker. A02 Security Misconfiguration.

**Uploads and privacy.** Profile pictures must be JPEG or PNG under 3 MB, and location and other metadata are removed. They are stored in `data/avatars` outside `public/`, kept out of git, and only shown to the user themselves. Full names are never shown publicly, and analytics are stored without user IDs.

**Errors.** Warnings become exceptions, and the customer only sees a friendly page with a short reference that also appears in the log (`docker compose logs app`). Arrays where text is expected (`?q[]=`) give 400 instead of a crash. A10 Mishandling of Exceptional Conditions.

**Deliberate demo choices.** Registration says when an email is already in use, all test users have the password `admin`, and the chat has no limit on the number of questions.
