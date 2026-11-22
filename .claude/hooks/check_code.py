#!/usr/bin/env python3
"""Checks the code against the project rules (CLAUDE.md, docs/governance/constitution.md) and the
quality gates of the development workflow.

Run by hand:  python3 .claude/hooks/check_code.py [file ...]   (no files = the whole repo)
Used by the hooks: after_edit.py (the edited file) and finish_gate.py (the whole repo).
"""
import glob
import json
import os
import re
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", ".."))

# Code that runs when the site is used (scripts/ only runs at build time)
RUNTIME = ["public/index.php", "src/*.php", "templates/*.php", "public/assets/*.js"]
# Text the customer sees
UI = ["templates/*.php", "src/I18nEn.php", "src/I18n.php", "public/index.php", "public/assets/*.js"]
# "Goes well with", product guide and catalog search: rules and the database only, never the language model
RULE_ENGINE = ["src/Catalog.php", "src/CatalogIntent.php", "src/Guide.php", "src/Search.php", "src/Extract.php",
               "scripts/import.php"]
# The one file allowed to talk to Ollama
LLM_CLIENT = "src/Chat.php"

# Real Sør-Tre product IDs that are allowed in code, per file. Each one is checked against data/raw/products.json,
# and name, price and stock are still read from the database. Byggelab example setups, and one format example
# in a comment.
REAL_IDS = {
    "src/Byggelab.php": {"291483", "291485", "291385", "291482", "291445", "268745", "313059", "268747", "291451",
                         "291857", "291858", "291487", "267356", "267355", "291480"},
    "src/ProductRef.php": {"291448"},
}

# Guardrails that must stay in the code: (file, pattern, what it protects)
GUARDRAILS = [
    ("public/index.php", r"\$blocked = Chat::blocked\(\$q\)", "every new chat message is checked before the model"),
    ("src/Chat.php", r"self::crisis\(\$q\) =>", "self harm gets a fixed answer, the model is not asked"),
    ("src/Chat.php", r"Violence::threat\(\$q\) =>", "violence gets a fixed answer, the model is not asked"),
    ("src/Chat.php", r"!Moderation::clean\(\$q\) =>", "profanity gets a fixed answer, the model is not asked"),
    ("src/Chat.php", r"Guard::chatInjection\(\$q\) =>", "attempts to override Søren get a fixed answer"),
    ("public/index.php", r"\$question = ChatHistory::input\(\$in\)", "the server owns the chat history, the browser sends only the new message"),
    ("src/Chat.php", r"ProductRef::clean\(\$piece, \$allowed\)", "the model can only reference products that were in its facts"),
    ("src/Chat.php", r"Guard::forPrompt\(", "user text reaches the model wrapped as data, never as instructions"),
    ("src/helpers.php", r"function is_verified\(", "only verified users (confirmed email and a paid purchase) edit or review"),
    ("public/index.php", r"function check_csrf\(|check_csrf\(\)", "CSRF check on every POST"),
    ("src/Auth.php", r"MAX_FAILS = \d+", "login lockout after repeated wrong passwords"),
]

DASH = re.compile("[–—]")
EXTERNAL = re.compile(r"""(?:file_get_contents|fopen|curl_init|fsockopen|stream_socket_client|fetch|import)\s*\(\s*["'`]https?://"""
                      r"""|<(?:script|link|img|iframe|source|video|audio)\b[^>]*\b(?:src|href)=["']https?://"""
                      r"""|url\(\s*["']?https?://""", re.I)
LLM = re.compile(r"\bChat(?:Job)?::|ollama_url|/api/(?:chat|generate|ps)\b|curl_init|fsockopen", re.I)
NEW_COLUMN = re.compile(r"ALTER\s+TABLE\s+`?(products|spec_keys|spec_values|spec_\w+)`?\s+ADD", re.I)


def files(patterns):
    out = []
    for p in patterns:
        out += glob.glob(p, root_dir=ROOT)
    return sorted(set(out))


def read(rel):
    try:
        return open(os.path.join(ROOT, rel), encoding="utf-8").read()
    except (OSError, UnicodeDecodeError):
        return ""


def line_of(text, pos):
    return text.count("\n", 0, pos) + 1


def product_ids():
    try:
        data = json.load(open(os.path.join(ROOT, "data/raw/products.json"), encoding="utf-8"))
    except (OSError, ValueError):
        return set(), set()
    ids = {str(p["id"]) for p in data if p.get("id")}
    slugs = {p["slug"] for p in data if len(p.get("slug") or "") > 8}
    return ids, slugs


def check(only=None):
    """only: repo-relative paths to check (None = everything). Guardrails are always checked."""
    out = []
    runtime, ui, engine = set(files(RUNTIME)), set(files(UI)), set(RULE_ENGINE)
    every = sorted(runtime | ui | engine | set(files(["scripts/*.php", "db/*.sql", "public/assets/*.css",
                                                         "docker/*", "docker-compose.yml", "*.md", "docs/**/*.md",
                                                         "tests/*.php", "evals/*.php", "evals/*.json", ".claude/hooks/*.py",
                                                         "phpstan.neon", "scripts/*.sh"])))
    targets = [f for f in every if only is None or f in only]
    ids, slugs = product_ids()
    for f in targets:
        t = read(f)
        if f in runtime:
            # CLAUDE.md rule 1: product numbers are never hardcoded
            for m in re.finditer(r"(?<![\w.#/-])(\d{5,7})(?![\w.])", t):
                if m.group(1) in ids and m.group(1) not in REAL_IDS.get(f, set()):
                    out.append(f"{f}:{line_of(t, m.start())}: product ID {m.group(1)} is hardcoded. It must come from the database")
            for s in slugs:
                i = t.find(s)
                if i >= 0:
                    out.append(f"{f}:{line_of(t, i)}: product slug '{s}' is hardcoded. It must come from the database")
            # CLAUDE.md rule 8: no external calls at runtime except Ollama, images stored locally
            for m in EXTERNAL.finditer(t):
                out.append(f"{f}:{line_of(t, m.start())}: external call at runtime ({m.group(0)[:60]}). Only Ollama is allowed, assets are local")
            # Only Chat.php talks to Ollama
            if f not in (LLM_CLIENT, "src/bootstrap.php"):
                for m in re.finditer(r"ollama_url|/api/(?:chat|generate|ps)['\"]?\s*,\s*(?:false|'r')", t):
                    out.append(f"{f}:{line_of(t, m.start())}: talks to Ollama directly. Only {LLM_CLIENT} may")
        # Gate 5: the compatibility ("goes well with") module never calls the language model
        if f in engine:
            for m in LLM.finditer(t):
                out.append(f"{f}:{line_of(t, m.start())}: '{m.group(0)}' in the rule engine. \"Goes well with\", the guide and search use rules and the database, never the language model")
        # CLAUDE.md rule 6: no em or en dashes in UI text
        if f in ui:
            for m in DASH.finditer(t):
                out.append(f"{f}:{line_of(t, m.start())}: em or en dash in UI text")
        # Formatting: the same in every editor, so diffs show only real changes
        for i, line in enumerate(t.split("\n"), 1):
            if line.rstrip(" \t\r") != line:
                out.append(f"{f}:{i}: trailing whitespace or CRLF line ending")
            if line.startswith("\t"):
                out.append(f"{f}:{i}: tab indentation, use spaces")
        if t and not t.endswith("\n"):
            out.append(f"{f}: no newline at the end of the file")
        if f.startswith(("src/", "tests/", "evals/")) and f.endswith(".php") and "declare(strict_types=1);" not in t[:200]:
            out.append(f"{f}: declare(strict_types=1) missing at the top")
        # CLAUDE.md rule 4: new specification types are rows in spec_keys, never new columns
        for m in NEW_COLUMN.finditer(t):
            out.append(f"{f}:{line_of(t, m.start())}: new column on {m.group(1)}. New specifications are rows in spec_keys")
    # Constitution 9: critical rules are enforced in code. They must never be removed.
    for f, pat, why in GUARDRAILS:
        if not re.search(pat, read(f)):
            out.append(f"{f}: guardrail missing ({why}). Put it back")
    return out


def main(argv):
    only = None
    if argv:
        only = {os.path.relpath(os.path.abspath(a), ROOT) for a in argv}
    found = check(only)
    for x in found:
        print(x)
    print(f"{len(found)} findings")
    return 1 if found else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
