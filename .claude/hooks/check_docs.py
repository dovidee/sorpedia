#!/usr/bin/env python3
"""Checks the docs (README.md, CLAUDE.md, docs/, specs/) against the code and data they describe.

Every number and name the docs state is compared with the file that decides it. A finding means the
docs say something the code or data does not. Run by hand:  python3 .claude/hooks/check_docs.py
Used by the hooks: PostToolUse after editing a .md file, and UserPromptSubmit every turn.
"""
import glob
import json
import os
import re
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", ".."))
DOCS = ["README.md", "CLAUDE.md"] + sorted(glob.glob("docs/**/*.md", root_dir=ROOT, recursive=True)) \
    + sorted(glob.glob("specs/**/*.md", root_dir=ROOT, recursive=True))

WORDS = {"one": 1, "two": 2, "three": 3, "four": 4, "five": 5, "six": 6, "seven": 7, "eight": 8,
         "nine": 9, "ten": 10, "eleven": 11, "twelve": 12}
NUM = r"(\d+|" + "|".join(WORDS) + r")"
TEMPLATES = {"spec.md", "verification.md"}
APPROX = re.compile(r"\b(?:about|approximately|roughly|around|circa|cirka|nearly|almost)\s+(?:\d|half|a few|one|two|"
                    r"three|four|five|six|seven|eight|nine|ten)|\bca\.\s*\d|~\s*\d", re.I)


def read(rel):
    try:
        return open(os.path.join(ROOT, rel), encoding="utf-8").read()
    except OSError:
        return ""


def num(s):
    s = s.lower()
    return WORDS[s] if s in WORDS else int(s)


def facts():
    """The values the code and data actually have."""
    f = {}
    try:
        f["products"] = len(json.load(open(os.path.join(ROOT, "data/raw/products.json"), encoding="utf-8")))
    except (OSError, ValueError):
        pass
    cat = read("src/Catalog.php")
    m = re.search(r"const THEMES = \[(.*?)\n    \];", cat, re.S)
    if m:
        f["themes"] = len(re.findall(r"^\s+'\w+' => \[", m.group(1), re.M))
    imp = read("scripts/import.php")
    m = re.search(r"\$warehouses = \[(.*?)\];", imp, re.S)
    if m:
        f["stores"] = len(re.findall(r"\d+ => \['", m.group(1)))
    f["users"] = {e: r for e, _, r in re.findall(r"\['([\w.]+@sorpedia\.test)', '([^']*)', '(\w+)'", imp)}
    f["manuals"] = len(glob.glob(os.path.join(ROOT, "public/media/manuals/*.pdf")))
    f["env"] = {}
    for src in sorted(glob.glob(os.path.join(ROOT, "src/*.php"))):
        f["env"].update(re.findall(r"env\('([A-Z_]+)', '([^']*)'\)", open(src, encoding="utf-8").read()))
    comp = read("docker-compose.yml")
    f["compose"] = {k: v.strip() for k, v in re.findall(r"^\s+([A-Z_]+): (.+)$", comp, re.M)}
    m = re.search(r'\$\{PORT:-(\d+)\}', comp)
    f["port"] = m.group(1) if m else None
    a = read("src/Auth.php")
    m1, m2 = re.search(r"MAX_FAILS = (\d+)", a), re.search(r"LOCK_MINUTES = (\d+)", a)
    f["lock"] = (int(m1.group(1)), int(m2.group(1))) if m1 and m2 else None
    m = re.search(r"AVATAR_MAX = (\d+) \* 1024 \* 1024", read("src/Privacy.php"))
    f["avatar_mb"] = int(m.group(1)) if m else None
    m = re.search(r"strlen\(\$pw\) < (\d+)", read("public/index.php"))
    f["pw_min"] = int(m.group(1)) if m else None
    m = re.search(r"\$picks = array_slice\(\$scored, 0, (\d+)\)", read("src/Guide.php"))
    f["guide_picks"] = int(m.group(1)) if m else None
    g = read("src/Guide.php")
    m = re.search(r"foreach \(\[([^\]]+)\] as \$k\)", g)
    # oppgave + type + the optional questions in next()
    f["guide_max_q"] = 2 + len(m.group(1).split(",")) if m else None
    m = re.search(r"explode\(',', \(string\) \(\$_GET\['ids'\] \?\? ''\)\)\)\), 0, (\d+)\)", read("public/index.php"))
    f["compare_max"] = int(m.group(1)) if m else None
    f["routes"] = re.findall(r"route\('(?:GET|POST)', '([^']+)'\)", read("public/index.php"))
    return f


def route_exists(path, routes):
    return any(re.fullmatch(p, path) for p in routes)


def check():
    f = facts()
    out = []
    for rel in DOCS:
        text = read(rel)
        for i, line in enumerate(text.splitlines(), 1):
            w = f"{rel}:{i}"
            if APPROX.search(line):
                out.append(f"{w}: approximation ('{APPROX.search(line).group(0)}'). State the exact value from the code or data, or remove the claim")
            for m in re.finditer(r"(?<!to )" + NUM + r" products\b", line, re.I):
                if "products" in f and num(m.group(1)) != f["products"]:
                    out.append(f"{w}: says {m.group(1)} products; data/raw/products.json has {f['products']}")
            for m in re.finditer(NUM + r" (?:stores|warehouses)\b", line, re.I):
                if "stores" in f and num(m.group(1)) != f["stores"]:
                    out.append(f"{w}: says {m.group(1)} stores; scripts/import.php has {f['stores']}")
            for m in re.finditer(NUM + r" themes\b", line, re.I):
                if "themes" in f and num(m.group(1)) != f["themes"]:
                    out.append(f"{w}: says {m.group(1)} themes; Catalog::THEMES has {f['themes']}")
            for m in re.finditer(NUM + r" (?:assembly guides|manuals)\b", line, re.I):
                if num(m.group(1)) != f["manuals"]:
                    out.append(f"{w}: says {m.group(1)} assembly guides; public/media/manuals has {f['manuals']} PDFs")
            # backticked repo paths must exist
            for p in re.findall(r"`([\w./-]+\.(?:php|md|py|sql|yml|js|css|json|sh)|[\w-]+/(?:[\w-]+/)*)`", line):
                # spec.md and verification.md name the files each feature folder specs/NNN-name/ gets
                if "NNN" in p or p.startswith(("http", "/")) or "*" in p or p in TEMPLATES:
                    continue
                if not os.path.exists(os.path.join(ROOT, p)) and not p.startswith(("specs/", "src/Foo")):
                    out.append(f"{w}: `{p}` does not exist in the repo")
            # backticked site routes must exist in public/index.php
            for p in re.findall(r"`(/[a-z][\w/-]*)`", line):
                if p.startswith(("/var", "/etc", "/usr")) or "." in p:
                    continue
                if not route_exists(p.rstrip("/") or "/", f["routes"]):
                    out.append(f"{w}: route {p} is not in public/index.php")
        if rel in ("README.md", "docs/architecture/security.md"):
            out += check_readme(text, f)
    return out


def check_readme(t, f):
    out = []
    for e, role in re.findall(r"\| `([\w.]+@sorpedia\.test)` \| ([^|]+)\|", t):
        if e not in f["users"]:
            out.append(f"README.md: test user {e} is not created by scripts/import.php")
            continue
        want = {"admin": "Admin", "ansatt": "Staff", "support": "Customer service", "bruker": ""}[f["users"][e]]
        if want and not role.strip().startswith(want):
            out.append(f"README.md: {e} is described as '{role.strip()}', but its role is {f['users'][e]}")
    for var, default in re.findall(r"^\| `([A-Z_]+)` \| `?([^`|]*?)`? \|", t, re.M):
        code = f["env"].get(var)
        comp = f["compose"].get(var)
        if var == "PORT":
            if default != f["port"]:
                out.append(f"README.md: PORT default {default}; docker-compose.yml has {f['port']}")
            continue
        m = re.match(r"\$\{\w+:-(.+)\}", comp or "")
        effective = m.group(1) if m else comp if comp is not None else code
        if default in ("empty", "") and not effective:
            continue
        if effective is not None and default != effective:
            out.append(f"README.md: {var} default '{default}'; the app gets '{effective}' (docker-compose.yml / src/bootstrap.php)")
    m = re.search(r"^\| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` \| ([^|]+)\|", t, re.M)
    if m:
        said = re.findall(r"`([^`]*)`", m.group(1))
        real = [f["compose"].get(k) or f["env"].get(k) for k in ("DB_HOST", "DB_PORT", "DB_NAME", "DB_USER", "DB_PASS")]
        if said != real:
            out.append(f"README.md: DB defaults {said}; the app gets {real} (docker-compose.yml / src/bootstrap.php)")
    m = re.search(r"(\w+) wrong passwords in a row lock the account for (\d+) minutes", t)
    if m and f["lock"] and (num(m.group(1)), int(m.group(2))) != f["lock"]:
        out.append(f"README.md: lockout {m.group(1)}/{m.group(2)} min; src/Auth.php has {f['lock'][0]}/{f['lock'][1]} min")
    m = re.search(r"at least (\d+) characters", t)
    if m and f["pw_min"] and int(m.group(1)) != f["pw_min"]:
        out.append(f"README.md: password minimum {m.group(1)}; public/index.php has {f['pw_min']}")
    m = re.search(r"under (\d+) MB", t)
    if m and f["avatar_mb"] and int(m.group(1)) != f["avatar_mb"]:
        out.append(f"README.md: picture limit {m.group(1)} MB; src/Privacy.php has {f['avatar_mb']} MB")
    m = re.search(r"Up to (\w+) questions give at most (\w+) suggestions", t)
    if m and (num(m.group(1)), num(m.group(2))) != (f["guide_max_q"], f["guide_picks"]):
        out.append(f"README.md: guide {m.group(1)} questions / {m.group(2)} suggestions; src/Guide.php has {f['guide_max_q']} / {f['guide_picks']}")
    m = re.search(r"Up to (\w+) products in a table", t)
    if m and f["compare_max"] and num(m.group(1)) != f["compare_max"]:
        out.append(f"README.md: compare up to {m.group(1)}; public/index.php has {f['compare_max']}")
    return out


def main():
    found = check()
    for x in found:
        print(x)
    print(f"{len(found)} findings")
    return 1 if found else 0


if __name__ == "__main__":
    sys.exit(main())
