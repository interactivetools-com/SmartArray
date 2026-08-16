#!/usr/bin/env bash
# Scan library code with Codex Security. Maintainer tooling: runs locally, not
# in CI, and needs the codex-security CLI installed. Results go to the CLI's
# state dir; view them with: codex-security scans list
# The defaults (gpt-5.6-sol, xhigh effort, stop-after-no-new 4) are already the
# strongest settings; extra flags only lower them. Run scans one repo at a time:
# concurrent scans share a sandbox dir in /tmp and kill each other's workers.
# Full pre-release scan:
#   .github/scripts/codex-security-scan.sh --mode deep
set -euo pipefail
cd "$(dirname "$0")/../.."

# There's no exclude flag, so build the path list here: everything except tests,
# gitignored files (vendor, caches, .idea) and __* scratch notes. Skipping those
# keeps the scan on shipped code; the scratch notes also get quoted back as
# evidence, which we don't want steering the results.
shopt -s dotglob
paths=()
for entry in *; do
    if [[ $entry == .git || $entry == tests || $entry == __* ]] || git check-ignore -q "$entry"; then
        continue
    fi
    paths+=(--path "$entry")
done

# The scan prompt lives here (written to a temp file at runtime) so the repo
# needs no scratch file. It names the documented raw-access API as intended
# behavior; without that split, every documented raw accessor gets reported
# as an encoding bypass.
prompt_file=$(mktemp)
trap 'rm -f "$prompt_file"' EXIT
cat > "$prompt_file" <<'PROMPT'
This is a whole-library pre-release scan of SmartArray, a PHP 8.1+ collection
library. tests/ and vendor/ are excluded on purpose; everything else is the
shipping code. Treat findings as release blockers, not incremental review notes.

Core security promise to verify: when created with SmartStrings enabled,
elements come back as SmartString objects, so element output is HTML-encoded
by default. Array keys are never encoded.

Intended API, not findings: raw element access for logic is documented
behavior. SmartArray and SmartArrayRaw return plain values by design; only
SmartArrayHtml wraps elements as SmartStrings. toArray(), map() callbacks,
and jsonSerialize() expose raw values per the documented contract. Only flag
these if a concrete in-repo path renders their result into HTML without
encoding.

Prioritize, in order:

1. Paths that return raw values where the caller expects SmartString-wrapped
   ones: pluck, map, filter, group and similar collection methods, implode or
   join style output helpers, and SmartNull fallbacks.
2. Unencoded keys or raw element values reaching HTML output through the row
   and layout helpers.
3. Type juggling and offset handling on mixed integer and string keys.
4. Unbounded work on attacker-controlled input: loops or recursion whose
   depth depends on input structure.
PROMPT

codex-security scan . "${paths[@]}" \
    --knowledge-base docs/outputting-html.md \
    --scan-prompt-file "$prompt_file" \
    "$@"
