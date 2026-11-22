# 028 Verification

Run against the dev server with curl, demo user `epost@sorpedia.test`: asking for a reset redirected to `/innboks`, where the mail with the link was shown to the logged-out session; the stored `reset_hash` matched SHA-256 of the token and `reset_until` was in the future; the form opened (200); a 3 character password was sent back to the form; a valid password redirected to `/logg-inn`; opening the link again redirected to `/glemt-passord`; login with the new password succeeded; an unknown email gave the same redirect and message. Afterwards the demo password was set back to `admin` and the test mail removed.

Backup: `php scripts/backup.php` ran in under a second and wrote a 0.2 MB file; it held `CREATE TABLE` for all 40 base tables in the database and ended with "Dump completed". `git check-ignore` confirms backup files are ignored.

Not verified: a restore from the file (no second database available to the application's database user); the Docker image build with `mariadb-client` and the nightly loop; the English text of the new pages in the browser; the Innsikt line in a screenshot.
