#!/usr/bin/env python3
"""PreToolUse hook (Bash): every deletion is its own request, "Delete X", approved by the user.

One target per command: asks the user "Delete X?".
More than one target, a wildcard, or find -delete / -exec rm: denied. Delete one file per request."""
import json
import re
import shlex
import sys

DELETERS = {"rm", "unlink", "rmdir", "shred"}
SPLIT = re.compile(r"&&|\|\||[;|\n&]|\$\(|`")


def targets(seg):
    """The paths a command segment deletes, or None if it deletes nothing. 'MANY' if it cannot be one path."""
    try:
        tok = shlex.split(seg, posix=True)
    except ValueError:
        tok = seg.split()
    while tok and (re.fullmatch(r"\w+=.*", tok[0]) or tok[0] in ("sudo", "command", "exec", "xargs", "nohup")):
        tok = tok[1:]
    if not tok:
        return None
    cmd = tok[0].rsplit("/", 1)[-1]
    if cmd == "find" and ("-delete" in tok or any(t in DELETERS for t in tok)):
        return "MANY"
    if cmd == "git":
        rest = tok[1:]
        while rest and rest[0].startswith("-"):
            rest = rest[2:] if rest[0] in ("-C", "-c") else rest[1:]
        if not rest or rest[0] != "rm":
            return None
        args = rest[1:]
    elif cmd in DELETERS:
        args = tok[1:]
    else:
        return None
    paths = [a for a in args if not a.startswith("-")]
    if len(paths) != 1 or re.search(r"[*?\[\]{}]", seg):
        return "MANY"
    return paths


def decide(command):
    found = []
    for seg in SPLIT.split(command):
        t = targets(seg.strip())
        if t == "MANY":
            return "deny", "Delete one file per request. Ask the user: \"Delete X?\" for each file, one at a time."
        if t:
            found += t
    if not found:
        return None, None
    if len(found) > 1:
        return "deny", "Delete one file per request. Ask the user: \"Delete X?\" for each file, one at a time."
    return "ask", f"Delete {found[0]}?"


def main():
    try:
        data = json.load(sys.stdin)
    except ValueError:
        return 0
    decision, reason = decide(str((data.get("tool_input") or {}).get("command") or ""))
    if decision:
        print(json.dumps({"hookSpecificOutput": {"hookEventName": "PreToolUse",
                                                 "permissionDecision": decision,
                                                 "permissionDecisionReason": reason}}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
