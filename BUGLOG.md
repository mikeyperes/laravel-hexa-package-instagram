# Instagram package bug log

## IG-2026-09-17-01 — Connection ownership was duplicated and account identity was optional

- **Symptom:** Instagram login, verification-code entry, screenshots, clicks, logout, proxy diagnostics, and session checks were implemented again inside this package.
- **Impact:** Browser Console and Instagram could disagree about login and route state, while callers could use a valid session for the wrong account.
- **Root cause:** The package inferred connection state with page automation instead of consuming Browser Worker's exact-profile readiness evidence.
- **Patch:** Replace the duplicate controls with one explicit-profile connection service that requires runtime, authentication, account, and actual transport evidence. Human login stays in Browser Console.
- **Guard:** Connection tests cover correct account evidence, a valid session bound to a different expected account, missing account evidence, and actual route reporting.

## IG-2026-09-17-02 — Account bindings could lose concurrent updates

- **Symptom:** Multiple account changes rewrote one JSON setting without serialization.
- **Impact:** Concurrent saves could discard another saved account.
- **Root cause:** Read-modify-write mutations had no lock.
- **Patch:** Serialize account mutations with one bounded application lock.
- **Guard:** Repository tests retain multiple accounts and active-profile changes.

## IG-2026-09-17-03 — Workspaces advertised connection controls that no longer existed

- **Symptom:** The overview and raw workspace still displayed saved-password login, verification-code recovery, screenshots, and a second integrity endpoint after those controls were removed.
- **Impact:** Operators were directed to dead workflows and the overview parsed the new connection response with the old schema.
- **Root cause:** The account page was rebuilt without updating the two diagnostic surfaces.
- **Patch:** Both workspaces now consume the single status endpoint and send human login and route changes to Browser Console.
- **Guard:** View and route tests reject saved-password UI and the duplicate integrity route.

## IG-2026-09-17-04 — Empty default profile caused recursive normalization

- **Symptom:** Resolving the Instagram profile could recurse indefinitely when the configured default profile was empty or normalized to an empty string.
- **Impact:** A malformed or blank deployment setting could prevent every Instagram connection and scan request from resolving a profile.
- **Root cause:** `defaultProfile()` called `normalizeProfile()`, whose empty-value fallback called `defaultProfile()` again.
- **Patch:** Normalize the configured default directly and fall back to `instagram-main` without re-entering the public normalizer.
- **Guard:** Repository tests cover an empty configured default through both normalization and profile resolution.
