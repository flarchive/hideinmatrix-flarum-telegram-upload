"""Extract the exact, nonempty changelog section for an existing release tag."""

import pathlib
import re
import sys

tag = sys.argv[1]
if not re.fullmatch(r"v\d+\.\d+\.\d+", tag):
    raise SystemExit("Expected a stable version tag such as v0.1.1")
text = (pathlib.Path(__file__).resolve().parents[1] / "CHANGELOG.md").read_text()
match = re.search(r"^## " + re.escape(tag[1:]) + r" - \d{4}-\d{2}-\d{2}\s*\n(.*?)(?=^## |\Z)", text, re.M | re.S)
if not match or not match.group(1).strip():
    raise SystemExit("No nonempty changelog section found for " + tag)
print(match.group(1).strip())
