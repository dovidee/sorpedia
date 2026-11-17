#!/usr/bin/env python3
"""PostToolUse hook (Write, Edit): checks the file that was just written.

.php: php -l. A syntax error is sent back to Claude, which must fix it before going on.
.php, .js, .css, .sql, .yml, Dockerfile: check_code.py on that file (project rules and guardrails).
.md:  check_docs.py. A doc that disagrees with the code or data is sent back the same way.
"""
import json
import os
import subprocess
import sys

sys.dont_write_bytecode = True
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))


def block(reason):
    print(json.dumps({"decision": "block", "reason": reason}))
    return 0


def main():
    try:
        data = json.load(sys.stdin)
    except ValueError:
        return 0
    path = str((data.get("tool_input") or {}).get("file_path") or "")
    if path.endswith(".php"):
        r = subprocess.run(["php", "-l", path], capture_output=True, text=True)
        if r.returncode != 0:
            return block("PHP SYNTAX ERROR, fix it before going on:\n" + (r.stdout + r.stderr).strip())
    if path.endswith((".php", ".js", ".css", ".sql", ".yml")) or os.path.basename(path) == "Dockerfile":
        import check_code
        found = check_code.check({os.path.relpath(os.path.abspath(path), check_code.ROOT)})
        if found:
            return block("THE EDIT BREAKS A PROJECT RULE (check_code.py). Undo or fix it before going on:\n"
                         + "\n".join(found))
    elif path.endswith(".md"):
        import check_docs
        found = check_docs.check()
        if found:
            return block("DOCS DISAGREE WITH THE CODE OR DATA (check_docs.py). Open the source for each, "
                         "fix the doc, and claim nothing about it until this passes:\n" + "\n".join(found))
    return 0


if __name__ == "__main__":
    sys.exit(main())
