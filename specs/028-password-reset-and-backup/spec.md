# 028 Password reset and database backup

## Goal

Two things a shop needs before real customers: a customer who forgot the password gets back in without staff, and the database can be restored after a failure.

## Password reset

1. "Glemt passordet?" on the login page opens `/glemt-passord`. The customer enters an email; the answer is the same whether an account exists or not.
2. For an active account a link `/nytt-passord/{token}` goes to the demo inbox. The token is 32 random bytes; only its SHA-256 hash is stored (`users.reset_hash`), with `reset_until` 30 minutes ahead. A new request replaces the old token.
3. The link shows a form for the new password (at least 8 characters, typed twice). Saving stores the Argon2id hash, removes the token, clears failed logins and a lock, logs the session out and sends the customer to the login page.
4. A used or expired link sends the customer back to ask for a new one.
5. The prototype sends no real email: the session that asked for the reset may read that one message in `/innboks` without logging in.

## Backup

1. `php scripts/backup.php` dumps the whole database with `mariadb-dump --single-transaction` (a consistent snapshot without locking the shop), gzip compressed, to `data/backups/sorpedia-YYYYMMDD-HHMMSS.sql.gz`, mode 0600, and keeps the newest 14.
2. The database password goes in a private options file, never on the command line. A failed dump leaves no file and exits with an error.
3. Docker runs it at start and every 24 hours (`docker/entrypoint.sh`) as `www-data`, the user Apache runs as, which owns `data/backups` and `data/logs`; so the admin page can list the files and take a backup itself. The image has `mariadb-client`.
4. `data/backups/` is ignored by git: backups hold customer data.
5. Control Tower Innsikt shows when the newest backup was made, its size and how many are kept.
6. Restore is one command, in `README.md`.

## Acceptance

1. A reset link works once, within 30 minutes, and the new password logs in.
2. An unknown email gives the same message as a known one.
3. A backup run produces a file with every table and the dump's completion line.
