# Changelog

## 1.25.0 - 2026-11-01
- Read explicitly selected source-account highlight items through the shared efficient action; keep that account in human navigation.


## 1.24.2 - 2026-11-01
- Require distinct stable keys per target for publication lists; support per-target captions without bypassing receipt binding.


## 1.24.1 - 2026-11-01
- Correct optional JSON defaults in the generic action command; add single/list command coverage.
- Complete Instagram Feature suite: 41 tests, 290 assertions, no errors.

## 1.24.0 (2026-11-01)

- Complete the task-action entry point for single/list targets and optional synchronous readback. Wrap the existing Facebook official publisher with the same receipt and shared-limit runner. Import only toolbox-verified approval envelopes; reject legacy background publication.

## 1.23.0 (2026-11-01)

- Add read-only inbox/thread transport in efficient and human modes, and receipt-gated human sends in existing exact threads.

## 1.22.0 (2026-11-01)

- Add shared story reads and cached CDN downloads with source identity; add human-only highlight actions and reject legacy background edits.

## 1.21.1 (2026-11-01)

- Declare honest human account-post links/pinned coverage and owner-aware search/grid navigation.

## 1.21.0 (2026-11-01)

- Add human transport through a network-generic Social Automation bridge; verified receipt gates for follow, unfollow and like; local list add; remove unrestricted follow; explicit logged-in fallback for inconclusive public embeds and session-busy stop mapping.

## 1.20.0 (2026-11-01)

- Phase 1 action engine: explicit sessions, shared Human Behavior limits, persistent JSON results, post output parts, links-only account posts, profiles and classified tags. Legacy reader transport and Browser Worker failure distinctions preserved. Test doubles now receive BrowserProxyConfigWriter.

## 1.19.1 - 2026-11-01

- Account checks (`InstagramFollowAuditService`) hand the judge each post's full caption (up to Instagram's 2,200 characters, was 600) and its slide count (`media_count`).

## 1.19.0 - 2026-11-01

- New `instagram:post <link or id>`: one post or reel, every slide, the caption and the comments. Logged in through `--profile` (media and comment data, up to `--comments`, 20); `--logged-out` reads the public embed through the public reader profile, with only the comments the embed carries. Service method `InstagramScraperService::readPost()`.
- The public post read (`publicPostSlides()`) also returns the owner's name, location, like and comment counts, embed comments and each video slide's address.

## 1.18.1 - 2026-10-27

- Link each profile to its own saved Instagram scanner list, with its current name and account count.

## 1.18.0 - 2026-10-27

- Add named website profiles, a responsive dashboard, profile creation/notes, searchable locations and hosts, and saved complete website catalogs.
- Add a generic website-source contract. Website owners supply their canonical account, desktop, region and timezone; browser and proxy ownership stays with existing services.
- Preserve catalog data on refresh failure and hide a previous site's snapshot when the linked website changes.
- Add read-only JSON listing, explicit website refresh and profile-save commands. Existing Instagram reading and publication behavior is retained.
