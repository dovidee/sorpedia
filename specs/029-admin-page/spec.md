# 029 Admin page: security log, staff actions, backups

## Goal

The admin role gets one page, `/admin`, to see what happens around the shop: signs of password guessing, logins and lockouts, what staff did, backups and errors. It also takes a backup on request.

## Audit log

1. Table `audit_log` (`src/Audit.php`), one row per event, never changed afterwards. Kinds: `sikkerhet`, `admin`, `drift`.
2. Security events from `Auth`: login, failed login, attempt on a locked account, lockout, reset requested, reset done, and password changed in settings.
3. Staff actions: refund, search term mapping, revision merged or rejected, error report closed or value removed, case closed, backup requested.
4. Operations: backup taken or failed (`scripts/backup.php`).
5. Data minimisation: no IP address and no typed email is stored. A place is a 12 character SHA-256 hash of the address, the date and the app secret, so it groups attempts within one day and cannot be followed across days. Accounts appear as `konto #ID`, and actors as `#ID (role)`.

## Page

1. Only the admin role. A guest is sent to log in; other roles get 403.
2. Security, last 24 hours: failed logins, lockouts, accounts locked now, reset requests. "Possible password guessing" lists places with at least `Auth::MAX_FAILS` failures in 24 hours, with failures, accounts tried and last time.
3. The newest 40 security events and 40 staff actions; failures are marked in text and colour.
4. Backups: the files in `data/backups` with time and size, a "Ta sikkerhetskopi nå" button that runs `scripts/backup.php` on the server, and the operations log.
5. Error log: the last 20 lines of `data/logs/errors.log`, newest first, with the reference the customer was shown.
6. Link "Admin" in the account menu for the admin role.

## Acceptance

1. Failed logins show up in the security log and, from `Auth::MAX_FAILS` failures, under possible password guessing.
2. The backup button adds a file to the list and a row in the operations log.
3. A customer gets 403 and a guest is sent to log in.
