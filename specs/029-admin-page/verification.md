# 029 Verification

Run against the dev server with curl: six failed logins on an unknown address were logged as `login_fail` with target "ukjent e-post", no user and the same day hash; a guest on `/admin` was redirected to `/logg-inn?back=/admin`; the admin got 200; the backup button redirected to `/admin#kopier`, wrote a second file in `data/backups` and logged `backup_request` (admin) and `backup_ok` (drift); a customer got 403. A screenshot at 1440 px showed the counters, the hash under possible password guessing with 6 failures and 1 account, the security log with failures in red, the staff action and both backup files.

Lockout: five wrong passwords on a demo account logged five `login_fail` and one `lockout`, and the sixth attempt `login_blocked`; the account was unlocked afterwards.

Staff actions: an error report and a support case created only for the test were closed over HTTP as admin; `report_close` and `case_close` were logged with the admin as actor, and both rows were deleted afterwards. English at 390 px in a screenshot: the table keeps time, event and target (actor and place are hidden on phones), words no longer break inside, and stored targets such as "konto #7" show as "account #7".

Refund, search mapping and revision reject: run over HTTP as admin on a test order, case, revision and search event made for the purpose; `refund` (with case and amount), `search_mapping` and `revision_reject` were logged with the admin as actor. All test rows and the events they emitted were deleted afterwards.

Not verified: `revision_merge` (same code path as reject, other branch); the backup button under Apache in Docker, where the PHP CLI is found on PATH (the Docker daemon was not reachable from the test environment).
