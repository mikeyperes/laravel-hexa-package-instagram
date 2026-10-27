# laravel-hexa-package-instagram

Dedicated Instagram package for Hexa.

## Main flow

1. Open **Profiles** to create a named workspace linked to a registered website.
2. The website owner supplies its current Instagram username, browser desktop, region and timezone.
3. Click **Refresh from website** to save the complete current location and host lists.
4. Open the exact desktop in **Browser Console** to manage Instagram login and its network connection.
5. **Accounts**, **Settings** and **Raw Workspace** retain the existing account and diagnostic tools.

## Website profiles

- Dashboard: `/instagram/profiles`, with a selected profile at `?id=<id>`.
- A profile stores its own name, website source/key, notes, last successful catalog and refresh error in `instagram_profiles`.
- Website facts remain with the website owner. The Instagram package does not import JPN models or duplicate city settings.
- Each source implements `InstagramProfileSiteSource` and is tagged `instagram.profile-sources`. JPN registers its city adapter.
- Locations are the website's saved area/location registry. Hosts include the public author page, public website, Instagram handle and location when available.
- Refreshes read the complete website registry, including all host pages. Failure preserves the previous successful snapshot and its timestamp; empty successful lists remain explicitly empty.
- A website URL change hides the previous site's snapshot until a successful refresh.
- Browser assignments come from the website record and link through the trusted session registry. Profile names and desktop names are independent.
- Login and proxy/VPN providers remain owned by Browser Console and the shared transport packages. No provider-specific credentials or Decodo integration are added.
- Creating a profile saves a missing account binding, preserves the active account, and refuses a desktop already bound to a different Instagram username.
- This dashboard never follows an account, posts, launches a desktop, verifies a login or changes a network route.

### Commands

```bash
php artisan instagram:profile-save <profile-name> <website-source> <website-record-key> [--notes="..."]
php artisan instagram:profiles [--profile=<profile-name>]
php artisan instagram:profiles [--profile=<profile-name>] --refresh
```

The default listing is read-only and emits safe JSON including saved catalogs. `--refresh` reads the website and replaces its successful snapshot.

## Included surfaces

- `/instagram`
- `/instagram/profiles`
- `/instagram/accounts`
- `/settings/instagram`
- `/instagram/raw`

## Storage and dependencies

- `instagram_profiles`: unique name and website source/key, notes, catalog JSON, successful sync time and last error.
- `instagram_publications`: existing publication tracking.
- Account bindings and package defaults remain in Hexa Core settings.
- Browser Worker supplies the sanitized session registry; Browser Console owns sign-in and routing; website packages supply their own catalog adapters.
- Install only this package's profile migration when upgrading an existing app. The service provider loads the migration, views, routes, commands, documentation and sidebar entry.

## Reading recent posts fast

`InstagramScraperService::profileFeeds($profile, $usernames, $limit)` reads several accounts'
recent posts in one browser session: it opens the first account's profile once, then asks
Instagram's own web data query (`PolarisProfilePostsQuery`) for each account, about 1 second each
with a short random gap. The query id and request tokens are read from Instagram's page on every
run, so they follow Instagram's releases, and the login never leaves the browser. Each post comes
back in `postScan()`'s shape (full-size images of every photo, caption, date, video flag, pinned
flag). An account that can't be read is marked unsuccessful so callers fall back to `profileScan()`.
Each account also returns its numeric `user_id`.

`storyFeeds($profile, [user_id => username, ...])` reads the current stories of up to 20 accounts
per request: each story's own link (`/stories/<user>/<id>/`), full-size image or video, posting and
expiry times, and the accounts, links and hashtags on it. Stories expire after 24 hours and their
media links sooner, so download what you keep right away.

A story that shares a post carries `reshared_post` (`pk`, `code`, `url`, `owner`, `owner_id`); when the share
sticker does not name the owner it is looked up from the post, slowly, up to
`instagram.story_reshare_owner_lookups` (10) posts per call, or per call's `owner_lookups` option.

`followingFeed($profile, $username, $max)` lists the accounts one account follows (username, name,
id, private/verified), 50 per page.

## One account's posts and stories (read-only commands)

Any caller reads one account through one explicit logged-in browser profile; the caller owns what the results mean.

```bash
php artisan instagram:posts <account> --profile=<profile> [--limit=5] [--allow-public-fallback] [--json]
php artisan instagram:stories <account> --profile=<profile> [--owner-lookups=10] [--json]
```

- Both first pass `InstagramConnectionService::readGate($profile)` and stop with its exact reason when the profile is
  paused (`browser_worker_paused_profiles`), its Browser Console runtime needs activation, Instagram is not logged in,
  or it has no protected route (direct connection). Nothing is launched, activated or logged in by them.
- `instagram:posts` → `{success, message, account, profile, posts_source (logged_in|public_embed), posts: [{url,
  shortcode, taken_at, caption, image_urls, video ({url, cover_url}|null), mentions, tagged, coauthors}], errors}`
  through `profileFeeds()`. Only with `--allow-public-fallback`, a failed logged-in read of the account falls back to
  its public profile embed through `instagram.public_reader_profile` (`instagram-public`).
- `instagram:stories` → `{success, message, account, profile, stories: [{id, url, taken_at, image_url, video_url,
  reshared_post ({url, code, owner, owner_id}|null), mentions, tagged, links}], errors}` through `accountProfiles()`
  (account id) and `storyFeeds()`. No fallback.
- Reads are paced with a random pause in `instagram.account_reads` (4-9 s) between the reads of one account.
  Service methods: `InstagramScraperService::accountPosts()` and `accountStories()`.

## Publishing stories and posts

`InstagramPublisherService` posts from the account logged in to a browser profile, for any caller:

- **Story:** Instagram's phone site ("Your story") on a 9:16 screen. The image is fitted whole on a 1080x1920 canvas over a blurred copy of itself, never cropped.
- **Feed post:** the desktop "New post" dialog with the "Original" crop, on a 1080x1350 (4:5) canvas, with a caption.
- **Already live check:** pass your own key (for example `jpn-event:1979`). A key whose story is still showing, or whose post is still on the account, is skipped. An expired story, or one deleted elsewhere, is posted again. `--force` posts anyway.
- Every post and delete is read back from the account's own stories or posts. Records live in `instagram_publications`.

```bash
php artisan instagram:publish story /path/or/https/image.jpg --profile=jpn-<city> --key=jpn-event:1979
php artisan instagram:publish post image.jpg --caption="..." --profile=jpn-<city> --key=jpn-event:1979
php artisan instagram:publications --kind=story --key=jpn-event:1979   # live check
php artisan instagram:unpublish <publication id>                      # delete + confirm
php artisan instagram:edit <publication id> --caption="..."            # replace a post caption + confirm
```

`publish(..., updateCaption: true)` edits a live post's caption instead of skipping it when the caption changed.

Browser steps used: `emulate_mobile` and `choose_file` (Browser Worker 1.12.18+). Staged images go to `browser-worker.profile_provisioning.upload_base_path` and are deleted after each run.

## Read-only profiles

Profiles listed in `instagram.read_only_profiles` (env `INSTAGRAM_READ_ONLY_PROFILES`, comma-separated; other
packages may add theirs at boot, as JPN does for its scout reader) may only read. Follow, story and post
publishing, caption edits, deletes and Highlight changes return `success: false` with status 423 and never
reach the browser.

## Package ownership

This package owns:

- attached Instagram browser accounts
- optional Meta oEmbed token
- Instagram connection testing
- raw profile/story/post debugging
- the shared Instagram post import service used by other packages

It builds on `laravel-hexa-package-browser-worker` for the generic browser runtime and persistent session handling.
