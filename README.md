# laravel-hexa-package-instagram

Dedicated Instagram package for Hexa.

## Main flow

1. Open **Accounts** and save one or more Instagram browser profiles with usernames and passwords.
2. Click **Log in with saved credentials** on the account you want to attach.
3. Open **Settings** and save the default usernames plus the optional Meta oEmbed token.
4. Run the **Full Connection Test** to confirm the active browser profile is really authenticated.
5. Use **Raw Workspace** to test profile scans, story pulls, public post import, and worker logs.

## Included surfaces

- `/instagram`
- `/instagram/accounts`
- `/settings/instagram`
- `/instagram/raw`

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

`followingFeed($profile, $username, $max)` lists the accounts one account follows (username, name,
id, private/verified), 50 per page.

## Publishing stories and posts

`InstagramPublisherService` posts from the account logged in to a browser profile, for any caller:

- **Story:** Instagram's phone site ("Your story") on a 9:16 screen. The image is fitted whole on a 1080x1920 canvas over a blurred copy of itself, never cropped.
- **Feed post:** the desktop "New post" dialog with the "Original" crop, on a 1080x1350 (4:5) canvas, with a caption.
- **Already live check:** pass your own key (for example `jpn-event:1979`). A key whose story is still showing, or whose post is still on the account, is skipped. An expired story, or one deleted elsewhere, is posted again. `--force` posts anyway.
- Every post and delete is read back from the account's own stories or posts. Records live in `instagram_publications`.

```bash
php artisan instagram:publish story /path/or/https/image.jpg --profile=jpn-miami --key=jpn-event:1979
php artisan instagram:publish post image.jpg --caption="..." --profile=jpn-miami --key=jpn-event:1979
php artisan instagram:publications --kind=story --key=jpn-event:1979   # live check
php artisan instagram:unpublish <publication id>                      # delete + confirm
```

Browser steps used: `emulate_mobile` and `choose_file` (Browser Worker 1.12.18+). Staged images go to `browser-worker.profile_provisioning.upload_base_path` and are deleted after each run.

## Package ownership

This package owns:

- attached Instagram browser accounts
- optional Meta oEmbed token
- Instagram connection testing
- raw profile/story/post debugging
- the shared Instagram post import service used by other packages

It builds on `laravel-hexa-package-browser-worker` for the generic browser runtime and persistent session handling.
