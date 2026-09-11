#!/usr/bin/env bash
# Scan library code with Codex Security. Maintainer tooling: runs locally, not
# in CI, and needs the codex-security CLI installed. Results go to the CLI's
# state dir; view them with: codex-security scans list
# Uses gpt-6-astra with the CLI's default reasoning effort and scan limits.
# Additional CLI flags can be passed as arguments. Run scans one repo at a time:
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
# as an encoding bypass. It also lists the documented limits of encoding so
# the scanner reports regressions and new paths instead of re-reporting them.
prompt_file=$(mktemp)
trap 'rm -f "$prompt_file"' EXIT
cat > "$prompt_file" <<'PROMPT'
This is a whole-library pre-release scan of SmartArray, a PHP 8.1+ collection
library. tests/ and vendor/ are excluded on purpose; everything else is the
shipping code. Review runtime source, documentation examples, and maintainer
tooling in their actual contexts. Assess the current checkout for release
readiness. Calibrate severity to the evidence; not every finding is a
release blocker.

Core security promise to verify: when created with SmartStrings enabled,
elements come back as SmartString objects, so element output is HTML-encoded
by default. Array keys are never encoded.

Intended API, not findings: raw element access for logic is documented
behavior. SmartArray and SmartArrayRaw return plain values by design; only
SmartArrayHtml wraps elements as SmartStrings. toArray(), map() callbacks,
and jsonSerialize() expose raw values per the documented contract. Only flag
these if a concrete in-repo path renders their result into HTML without
encoding.

Known limitations and finding criteria:

- Keys are never encoded, and docs/outputting-html.md says so: foreach hands
  keys back as plain values, and keys() returns them as fields that encode.
  Do not re-report that contract. Report a shipped helper or documentation
  example that writes a key into HTML without going through keys().
- Encoding covers HTML text and quoted attribute values. Link URL schemes,
  script blocks, and query strings need scheme checks, jsonEncode(), and
  urlEncode(), as the docs state. The absence of a context-specific encoder
  is not a defect. Report a method that claims a context and fails it, or a
  documentation example that puts a value into one of those contexts
  through plain encoding.

Use prior findings as leads, not proof against the current checkout. Verify
the current implementation and consolidate repeated manifestations of one
root cause. Separate known documented risks, hardening suggestions, and
confirmed current defects. Documentation is counterevidence, not an exemption:
report contradictions, unsafe recommended usage, and new attack paths with
concrete inputs, source-to-sink evidence, prerequisites, and validation limits.

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
    --model gpt-6-astra \
    --knowledge-base docs/ai-reference.md \
    --knowledge-base docs/outputting-html.md \
    --scan-prompt-file "$prompt_file" \
    "$@"
