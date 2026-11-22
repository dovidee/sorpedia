# 046 Verification

Implemented in `templates/legal.php`, `public/index.php` (route and register check), `templates/register.php`, `templates/layout.php` (footer links), `src/Auth.php` and `db/schema.sql` (`terms_at`), `public/assets/app.js` (chat link) and `src/I18nEn.php`.

Run in the app: `/vilkar` renders both headings; the footer on `/` has the two links; `/registrer` has the required box; `Auth::ensure()` added `terms_at` to the running database. Viewed `/vilkar` in the dark view at 1366 x 768 in headless Chromium.

Not verified: a registration without the box through the browser (the server check was inspected, not run), the English page, and the chat link in a live chat panel.
