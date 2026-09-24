# Instagram package bug log

## IG-2026-09-24-01 — Every story after the first failed once the account had a live story

- **Audit:** JPN story run to @miamijpn (#2067 and #2124 after #2109 posted).
- **Symptom:** `choose_image` timed out waiting for a file chooser; nothing was posted.
- **Impact:** Only one story per 24 hours could be posted to an account.
- **Root cause:** With a story live, the "Your story" tray item opens the viewer and the story camera input sits in the header, 9 levels from the "Your story" text; the input finder looked only 4 levels up and returned missing.
- **Patch:** When no input is near "Your story", use the header's story-creator input (the file input accepting PNG/AVIF), which opens Instagram's "Add to your story" editor with or without a live story (verified on the live page; the JPEG-only header input does not open it).
- **Guard:** `storyInputJs()` marked CRITICAL.

## IG-2026-09-23-02 — Posts with long ids were never collected from profile pages

- **Symptom:** @solfli always showed 0 posts found, although its profile shows posts.
- **Impact:** Every post whose link carries Instagram's long post id (posts from private accounts carry the short id plus a suffix) was silently dropped by profile scans and refused by post scans.
- **Root cause:** The profile link reader and `normalizePostUrl()` accepted post ids of 5–20 characters; long ids are 39.
- **Patch:** Post ids of 5–64 characters are accepted. The new `profileFeeds()` reader returns the canonical short id (the post's numeric id in base 64) for such posts.
- **Guard:** `test_profile_feeds_read_a_batch_into_post_scan_shape` (long id → `CB1vrXrJdZR`).

## IG-2026-09-23-01 — Schemeless profile URL was rejected as a username

- **Symptom:** `jpn-miami:add-instagram-account "instagram.com/cluballenby/"` failed with "Enter one valid Instagram username."
- **Impact:** Profile links copied without `https://`, or with a shared-link `?igsh=` query, could not be added to any saved list.
- **Root cause:** `normalizeUsername()` removed the Instagram host only when the URL began with `http://` or `https://`, and it kept query strings.
- **Patch:** The optional scheme, `www.` and `m.` host prefixes, and any query or fragment are removed before the username is validated.
- **Guard:** `test_username_normalization_accepts_schemeless_and_shared_profile_urls`.

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
