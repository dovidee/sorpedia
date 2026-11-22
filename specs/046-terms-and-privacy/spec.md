# 046 Terms and privacy

## Goal

A customer can read the terms of use and the privacy notice before creating an account, and accepts the terms when registering.

## Scope

`GET /vilkar` renders `templates/legal.php` with two sections, «Vilkår for bruk» (`#vilkar`) and «Personvern» (`#personvern`), marked as demo text. The privacy section states only what the code does: the account fields stored, the password kept as a hash, orders kept five years, searches and clicks counted without the account, chat conversations stored with the session and deleted `ChatHistory::TTL_HOURS` after the last message, the session and language cookies, and a link to Innstillinger, Personvern for the per field table, the export and account deletion.

Links: the footer on every store page («Vilkår», «Personvern»), the register form, and the privacy line in Søren's chat intro.

Registration needs the box «Jeg godtar vilkårene og personvernerklæringen»; without it the account is not created. `users.terms_at` records when the terms were accepted (added by `Auth::ensure()` on older databases, null for accounts made before).

## Acceptance criteria

1. `/vilkar` shows both sections in Norwegian and English, with the demo label.
2. The footer links open the two sections.
3. Registering without the box returns to the form with «Du må godta vilkårene for å opprette konto.» and creates no user.
4. A new account has `terms_at` set.
5. Søren's privacy line links to `/vilkar#personvern`.
