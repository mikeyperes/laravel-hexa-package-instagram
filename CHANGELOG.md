# Changelog

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
