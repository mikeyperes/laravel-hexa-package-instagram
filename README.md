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

## Package ownership

This package owns:

- attached Instagram browser accounts
- optional Meta oEmbed token
- Instagram connection testing
- raw profile/story/post debugging
- the shared Instagram post import service used by other packages

It builds on `laravel-hexa-package-browser-worker` for the generic browser runtime and persistent session handling.
