#!/usr/bin/env python3
"""Stop hook: before Claude says a task is done, the whole repo must pass.

1. php -l on every changed or new PHP file (git status)
2. check_code.py on the whole repo (project rules and guardrails)
3. check_docs.py (docs agree with the code and data)
4. php tests/run.php (loads every class in src/, then the tests)
5. php evals/run.php (the golden dataset and the fixed safety replies)
6. PHPStan (phpstan.neon), when a PHP file changed. Not installed is reported, not skipped

A failure sends the findings back to Claude. The same findings are reported once per session."""
import json
import os
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, "..", ".."))
sys.dont_write_bytecode = True
sys.path.insert(0, HERE)


def changed_php():
    r = subprocess.run(["git", "status", "--porcelain", "--untracked-files=all"], cwd=ROOT,
                       capture_output=True, text=True)
    out = []
    for line in r.stdout.splitlines():
        path = line[3:].split(" -> ")[-1].strip('"')
        if line[:2].strip() != "D" and path.endswith(".php") and os.path.exists(os.path.join(ROOT, path)):
            out.append(path)
    return out


def re_safe(s):
    return "".join(c for c in str(s) if c.isalnum() or c == "-")[:64]


def main():
    try:
        data = json.load(sys.stdin)
    except ValueError:
        data = {}
    if data.get("stop_hook_active"):
        return 0
    found = []
    for f in changed_php():
        r = subprocess.run(["php", "-l", f], cwd=ROOT, capture_output=True, text=True)
        if r.returncode != 0:
            found.append(f"{f}: PHP syntax error: " + (r.stdout + r.stderr).strip())
    import check_code
    import check_docs
    found += check_code.check()
    found += check_docs.check()
    # Tests (with the type check that loads every class) and the golden dataset
    for script in ("tests/run.php", "evals/run.php"):
        r = subprocess.run(["php", script], cwd=ROOT, capture_output=True, text=True, timeout=90)
        if r.returncode != 0:
            fails = [l for l in r.stdout.splitlines() if l.startswith("FAIL")] or [(r.stdout + r.stderr).strip()[-500:]]
            found += [f"{script}: {l}" for l in fails]
    # Static analysis takes about half a minute, so only when PHP changed
    if changed_php():
        phar = os.path.join(ROOT, "tools", "phpstan.phar")
        if not os.path.exists(phar):
            found.append("PHPStan not run: tools/phpstan.phar is missing. Install it with scripts/tools.sh")
        else:
            r = subprocess.run(["php", "-d", "memory_limit=1G", phar, "analyse", "--no-progress", "--error-format=raw"],
                               cwd=ROOT, capture_output=True, text=True, timeout=150)
            if r.returncode != 0:
                found += ["phpstan: " + l.replace(ROOT + "/", "") for l in r.stdout.splitlines() if l.strip()][:30] \
                    or ["phpstan: " + (r.stdout + r.stderr).strip()[-500:]]
    # Report the same findings only once per session, so they don't repeat after every reply
    import hashlib
    import tempfile
    state = os.path.join(tempfile.gettempdir(), "sorpedia-finish-gate-" + re_safe(data.get("session_id") or "x"))
    digest = hashlib.sha256("\n".join(sorted(found)).encode()).hexdigest()
    try:
        last = open(state).read()
    except OSError:
        last = ""
    if not found:
        if last:
            os.remove(state)
        return 0
    if digest == last:
        return 0
    with open(state, "w") as fh:
        fh.write(digest)
    if found:
        print(json.dumps({"decision": "block", "reason":
              "NOT DONE. The repo fails the checks (php -l, check_code.py, check_docs.py, tests, evals, PHPStan). Fix these, or tell "
              "the user exactly which ones are left and why:\n" + "\n".join(found)}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
