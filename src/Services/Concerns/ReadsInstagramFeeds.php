<?php

namespace hexa_package_instagram\Services\Concerns;

/**
 * Reads accounts' recent posts through Instagram's own web data queries, from inside the logged-in
 * browser profile: one page load per batch, then one request per account. The query id and request
 * tokens are read from Instagram's page each time, so they follow Instagram's releases.
 */
trait ReadsInstagramFeeds
{
    /**
     * Recent posts for several accounts in one browser session.
     *
     * Each account comes back as `post_links` (in profile order, pinned first) and `posts` keyed by
     * shortcode, every post already in the same shape as postScan()'s `data.scan`. An account that
     * fails (private, missing, rate limited, not reached) has `success` false so callers can fall back
     * to the page scan for it.
     *
     * @param array<int, string> $usernames
     * @param array{min_gap_ms?: int, max_gap_ms?: int, max_media?: int} $options
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function profileFeeds(?string $profile, array $usernames, int $limit = 12, array $options = []): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $usernames = array_values(array_unique(array_filter(array_map(
            fn ($username): string => ltrim($this->config->normalizeUsername((string) $username), '@'),
            $usernames,
        ))));
        if ($usernames === []) {
            return $this->failure('At least one Instagram username is required.', 'Provide the accounts whose recent posts should be read.');
        }

        $limit = max(1, min($limit, 12));
        $minGap = max(500, (int) ($options['min_gap_ms'] ?? 1200));
        $maxGap = max($minGap, (int) ($options['max_gap_ms'] ?? 2800));

        $result = $this->browser->runAutomation($resolved, [
            [
                // The posts query module loads with a profile page, so the batch opens on its first account.
                'type' => 'goto',
                'label' => 'open_profile',
                'url' => 'https://www.instagram.com/' . $usernames[0] . '/',
                'wait_until' => 'domcontentloaded',
                'timeout_ms' => 30000,
                'wait_ms' => 2500,
            ],
            [
                'type' => 'evaluate',
                'label' => 'read_feeds',
                'code' => self::profileFeedJs(),
                'args' => [
                    'usernames' => $usernames,
                    'limit' => $limit,
                    'min_gap_ms' => $minGap,
                    'max_gap_ms' => $maxGap,
                    'max_media' => max(1, min((int) ($options['max_media'] ?? 10), 20)),
                ],
            ],
        ], [
            'transport_timeout_ms' => 45000 + (count($usernames) * ($maxGap + 6000)),
        ]);

        // The feed is returned as JSON text: the worker keeps text results whole but cuts nested objects short.
        $feed = json_decode((string) ($this->resultByLabel($result, 'read_feeds')['text'] ?? ''), true);
        if ($this->isLoginRedirect($result, [])) {
            return [
                'success' => false,
                'message' => 'Instagram feed read requires a connected account.',
                'detail' => 'The browser worker was redirected to the Instagram login flow.',
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => ['profile' => $resolved, 'accounts' => []],
            ];
        }
        if (! is_array($feed) || isset($feed['fatal'])) {
            return [
                'success' => false,
                'message' => 'Instagram feed read failed.',
                'detail' => is_array($feed) ? (string) $feed['fatal'] : (string) ($result['message'] ?? 'The browser returned no feed data.'),
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => ['profile' => $resolved, 'accounts' => []],
            ];
        }

        $accounts = [];
        foreach ((array) ($feed['accounts'] ?? []) as $account) {
            $username = strtolower((string) ($account['username'] ?? ''));
            if ($username === '') {
                continue;
            }
            $posts = [];
            foreach ((array) ($account['posts'] ?? []) as $post) {
                if (is_array($post) && ($post['code'] ?? '') !== '') {
                    $scan = $this->feedPostAsScan($post);
                    $posts[$scan['shortcode']] = $scan;
                }
            }
            $accounts[$username] = [
                'success' => (bool) ($account['ok'] ?? false),
                'user_id' => (string) ($account['user_id'] ?? ''),
                'status' => (int) ($account['status'] ?? 0),
                'message' => (bool) ($account['ok'] ?? false) ? 'Recent posts read.' : (string) ($account['error'] ?? 'Recent posts could not be read.'),
                'elapsed_ms' => (int) ($account['ms'] ?? 0),
                'post_links' => array_values(array_map(static fn (array $scan): string => (string) $scan['url'], $posts)),
                'posts' => $posts,
            ];
        }
        foreach ($usernames as $username) {
            // Accounts after a rate limit are not attempted in this batch.
            $accounts[$username] ??= [
                'success' => false,
                'user_id' => '',
                'status' => 0,
                'message' => 'Not read in this batch.',
                'elapsed_ms' => 0,
                'post_links' => [],
                'posts' => [],
            ];
        }

        $read = count(array_filter($accounts, static fn (array $account): bool => $account['success']));

        return [
            'success' => $read > 0,
            'message' => $read . ' of ' . count($usernames) . ' Instagram feeds read.',
            'detail' => 'Read through Instagram\'s web data query ' . (string) ($feed['doc_id'] ?? '') . ' in the authenticated browser profile.',
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => ['profile' => $resolved, 'accounts' => $accounts],
        ];
    }

    /**
     * One feed post in postScan()'s `data.scan` shape, plus the fields only the feed knows.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function feedPostAsScan(array $post): array
    {
        $caption = trim((string) ($post['caption'] ?? ''));
        $ownerName = trim((string) ($post['owner_name'] ?? '')) ?: trim((string) ($post['owner'] ?? ''));
        $imageUrls = array_values(array_filter(array_map('strval', (array) ($post['image_urls'] ?? []))));
        $videoUrls = array_values(array_filter(array_map('strval', (array) ($post['video_urls'] ?? []))));
        $primary = (string) ($post['primary_media_url'] ?? '');
        $shortcode = $this->canonicalShortcode((string) $post['code'], (string) ($post['pk'] ?? ''));
        $url = 'https://www.instagram.com/' . (($post['product_type'] ?? '') === 'clips' ? 'reel' : 'p') . '/' . $shortcode . '/';

        return [
            'url' => $url,
            'canonical_url' => $url,
            'title' => $ownerName !== '' ? $ownerName . ' on Instagram' . ($caption !== '' ? ': "' . mb_substr($caption, 0, 120) . '"' : '') : '',
            'posted_at' => (string) ($post['posted_at'] ?? ''),
            'time_text' => '',
            'image_urls' => $imageUrls,
            'video_urls' => $videoUrls,
            'caption_blocks' => $caption !== '' ? [$caption] : [],
            'meta_description' => '',
            'body_excerpt' => '',
            'primary_media_url' => $primary,
            'primary_media_box' => ['tag' => (string) ($post['primary_tag'] ?? 'img'), 'source_url' => $primary],
            'cover_url' => (string) ($post['cover_url'] ?? ''),
            'post_media_urls' => array_values(array_unique(array_merge($imageUrls, $videoUrls))),
            'post_media_count' => max(1, (int) ($post['media_count'] ?? 1)),
            'source' => 'instagram_feed',
            'shortcode' => $shortcode,
            'instagram_code' => (string) $post['code'],
            'taken_at' => (int) ($post['taken_at'] ?? 0),
            'owner' => (string) ($post['owner'] ?? ''),
            'coauthors' => array_values(array_map('strval', (array) ($post['coauthors'] ?? []))),
            'pinned' => (bool) ($post['pinned'] ?? false),
            'product_type' => (string) ($post['product_type'] ?? ''),
            'accessibility_caption' => (string) ($post['accessibility_caption'] ?? ''),
            'location' => (string) ($post['location'] ?? ''),
        ];
    }

    /**
     * Current stories of several accounts, 20 accounts per request, from inside the logged-in browser.
     *
     * Each story has its own link (`/stories/<user>/<id>/`), its full-size image or video, when it
     * was posted and expires, and the accounts, links and hashtags on it. Stories disappear after
     * 24 hours and their media links expire, so callers download what they keep right away.
     *
     * @param array<string, string> $userIds numeric account id => username
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function storyFeeds(?string $profile, array $userIds): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $ids = array_values(array_filter(array_map('strval', array_keys($userIds)), 'ctype_digit'));
        if ($ids === []) {
            return $this->failure('At least one numeric Instagram account id is required.', 'profileFeeds() returns each account\'s id as user_id.');
        }

        $result = $this->browser->runAutomation($resolved, [
            ['type' => 'goto', 'label' => 'open_home', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 2000],
            ['type' => 'evaluate', 'label' => 'read_stories', 'code' => self::restJs(self::storyFeedJs()), 'args' => ['ids' => $ids, 'chunk' => 20, 'min_gap_ms' => 1200, 'max_gap_ms' => 2800]],
        ], ['transport_timeout_ms' => 60000 + (int) ceil(count($ids) / 20) * 10000]);

        $feed = json_decode((string) ($this->resultByLabel($result, 'read_stories')['text'] ?? ''), true);
        if ($this->isLoginRedirect($result, []) || ! is_array($feed) || isset($feed['fatal'])) {
            return $this->feedFailure($result, $resolved, is_array($feed) ? (string) ($feed['fatal'] ?? '') : '', 'Instagram story read failed.');
        }

        $reels = [];
        foreach ((array) ($feed['reels'] ?? []) as $id => $reel) {
            $username = strtolower((string) ($reel['username'] ?? ($userIds[$id] ?? '')));
            $reels[$username] = [
                'user_id' => (string) $id,
                'stories' => array_values(array_map(static fn (array $item): array => $item + [
                    'url' => 'https://www.instagram.com/stories/' . $username . '/' . $item['pk'] . '/',
                ], array_filter((array) ($reel['items'] ?? []), 'is_array'))),
            ];
        }
        $errors = (array) ($feed['errors'] ?? []);

        return [
            'success' => $errors === [] || $reels !== [],
            'message' => count($reels) . ' of ' . count($ids) . ' accounts have current stories.',
            'detail' => $errors === [] ? 'Read through Instagram\'s stories feed in the authenticated browser profile.' : 'Some requests failed: ' . json_encode($errors),
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => ['profile' => $resolved, 'reels' => $reels],
        ];
    }

    /**
     * The accounts one account follows, page by page, from inside the logged-in browser.
     *
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function followingFeed(?string $profile, string $username, int $max = 500): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $username = ltrim($this->config->normalizeUsername($username), '@');
        if ($username === '') {
            return $this->failure('Instagram username is required.', 'Provide the account whose following list should be read.');
        }
        $max = max(1, min($max, 2000));

        $result = $this->browser->runAutomation($resolved, [
            ['type' => 'goto', 'label' => 'open_profile', 'url' => 'https://www.instagram.com/' . $username . '/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 2500],
            ['type' => 'evaluate', 'label' => 'read_following', 'code' => self::restJs(self::followingFeedJs()), 'args' => ['max' => $max, 'page_size' => 50, 'min_gap_ms' => 1500, 'max_gap_ms' => 3500]],
        ], ['transport_timeout_ms' => 60000 + (int) ceil($max / 50) * 8000]);

        $feed = json_decode((string) ($this->resultByLabel($result, 'read_following')['text'] ?? ''), true);
        if ($this->isLoginRedirect($result, []) || ! is_array($feed) || isset($feed['fatal'])) {
            return $this->feedFailure($result, $resolved, is_array($feed) ? (string) ($feed['fatal'] ?? '') : '', 'Instagram following read failed.');
        }
        $users = array_values(array_filter((array) ($feed['users'] ?? []), 'is_array'));

        return [
            'success' => ($feed['error'] ?? null) === null || $users !== [],
            'message' => '@' . $username . ' follows ' . count($users) . (! empty($feed['complete']) ? '' : '+') . ' accounts.',
            'detail' => (string) ($feed['error'] ?? 'Read through Instagram\'s following list in the authenticated browser profile.'),
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => ['profile' => $resolved, 'username' => $username, 'user_id' => (string) ($feed['user_id'] ?? ''), 'complete' => (bool) ($feed['complete'] ?? false), 'users' => $users],
        ];
    }

    /** @return array{success: false, message: string, detail: string, status_code: int, data: array<string, mixed>} */
    private function feedFailure(array $result, string $profile, string $fatal, string $message): array
    {
        return [
            'success' => false,
            'message' => $this->isLoginRedirect($result, []) ? 'Instagram requires a connected account.' : $message,
            'detail' => $fatal !== '' ? $fatal : (string) ($result['message'] ?? 'The browser returned no data.'),
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => ['profile' => $profile],
        ];
    }

    /** Wraps a reader in the request headers Instagram's web app sends with its own data calls. */
    private static function restJs(string $body): string
    {
        return <<<'JS'
return (async () => {
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const gap = () => sleep(args.min_gap_ms + Math.random() * (args.max_gap_ms - args.min_gap_ms));
  const csrf = (document.cookie.match(/(?:^|; )csrftoken=([^;]+)/) || [])[1] || '';
  const headers = { 'X-IG-App-ID': (document.documentElement.innerHTML.match(/"X-IG-App-ID":"(\d+)"/) || [])[1] || '936619743392459',
    'X-ASBD-ID': '129477', 'X-IG-WWW-Claim': sessionStorage.getItem('www-claim-v2') || '0', 'X-CSRFToken': csrf, 'X-Requested-With': 'XMLHttpRequest' };
  const getJson = async (path) => { const response = await fetch(path, { headers, credentials: 'include' }); let json = null; try { json = JSON.parse(await response.text()); } catch (error) { json = null; } return { status: response.status, json }; };
  const largest = (candidates) => (candidates || []).slice().sort((a, b) => (b.width || 0) - (a.width || 0))[0]?.url || '';
JS . "
" . $body . "
})();";
    }

    private static function storyFeedJs(): string
    {
        return <<<'JS'
  const reels = {};
  const errors = [];
  for (let index = 0; index < args.ids.length; index += args.chunk) {
    if (index > 0) await gap();
    const chunk = args.ids.slice(index, index + args.chunk);
    const { status, json } = await getJson('/api/v1/feed/reels_media/?' + chunk.map((id) => 'reel_ids=' + encodeURIComponent(id)).join('&'));
    if (!json) { errors.push({ status, accounts: chunk.length }); if (status === 429) break; continue; }
    for (const [id, reel] of Object.entries(json.reels || {})) {
      reels[id] = { username: reel.user?.username || '', items: (reel.items || []).map((item) => ({
        pk: String(item.pk || ''), taken_at: item.taken_at || 0, expiring_at: item.expiring_at || 0,
        media_type: item.media_type === 2 ? 'video' : 'image',
        image_url: largest(item.image_versions2?.candidates), video_url: (item.video_versions || [])[0]?.url || '',
        accessibility_caption: item.accessibility_caption || '',
        mentions: (item.reel_mentions || []).map((mention) => mention.user?.username).filter(Boolean),
        links: (item.story_link_stickers || []).map((sticker) => { const url = sticker.story_link?.url || ''; try { return new URL(url).searchParams.get('u') || url; } catch (error) { return url; } }).filter(Boolean),
        hashtags: (item.story_hashtags || []).map((tag) => tag.hashtag?.name).filter(Boolean),
        location: item.story_locations?.[0]?.location?.name || '',
        // A story that shares a post points at that post, so the post can be used (and deduplicated) instead.
        reshared_post: (item.story_feed_media || [])[0] ? { pk: String(item.story_feed_media[0].media_id || '').split('_')[0], code: item.story_feed_media[0].media_code || '' } : null,
      })).filter((item) => item.pk) };
    }
  }
  return { text: JSON.stringify({ reels, errors }) };
JS;
    }

    private static function followingFeedJs(): string
    {
        return <<<'JS'
  const html = document.documentElement.innerHTML;
  const userId = (html.match(/"profile_id":"(\d+)"/) || html.match(/"page_id":"profilePage_(\d+)"/) || [])[1] || '';
  if (!userId) return { text: JSON.stringify({ fatal: 'The account id was not found on its profile page.' }) };
  const users = [];
  let maxId = '';
  let error = null;
  while (users.length < args.max) {
    const { status, json } = await getJson('/api/v1/friendships/' + userId + '/following/?count=' + args.page_size + (maxId ? '&max_id=' + encodeURIComponent(maxId) : ''));
    if (!json || !Array.isArray(json.users)) { error = 'Instagram returned status ' + status + '.'; break; }
    for (const user of json.users) users.push({ username: user.username, full_name: user.full_name || '', user_id: String(user.pk || user.id || ''), is_private: !!user.is_private, is_verified: !!user.is_verified });
    maxId = json.next_max_id || '';
    if (!maxId) break;
    await gap();
  }
  return { text: JSON.stringify({ user_id: userId, users: users.slice(0, args.max), complete: !maxId && !error, error }) };
JS;
    }

    /**
     * Instagram's short post id is the post's numeric id in base 64. Posts from private accounts carry
     * a longer id (that short id plus a suffix); the short id is the canonical one.
     */
    private function canonicalShortcode(string $code, string $pk): string
    {
        if (ctype_digit($pk) && function_exists('bcmod')) {
            $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
            $short = '';
            for ($id = $pk; bccomp($id, '0') > 0; $id = bcdiv($id, '64', 0)) {
                $short = $alphabet[(int) bcmod($id, '64')] . $short;
            }
            if ($short !== '' && str_starts_with($code, $short)) {
                return $short;
            }
        }

        return strlen($code) > 20 ? substr($code, 0, 11) : $code;
    }

    public static function profileFeedJs(): string
    {
        return <<<'JS'
return (async () => {
  const req = window.require;
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const load = (name) => { try { return req(name); } catch (error) { return null; } };
  if (typeof req !== 'function') return { text: JSON.stringify({ fatal: 'Instagram page modules are unavailable.' }) };
  const operation = load('PolarisProfilePostsQuery.graphql');
  const params = operation?.params || {};
  const docId = params.id || load('PolarisProfilePostsQuery_instagramRelayOperation') || '';
  const dtsg = load('DTSGInitialData')?.token || '';
  const lsd = load('LSD')?.token || '';
  if (!docId || !dtsg) return { text: JSON.stringify({ fatal: 'Instagram posts query or request tokens were not found on the page.' }) };
  const provided = {};
  for (const [key, provider] of Object.entries(params.providedVariables || {})) {
    try { provided[key] = provider.get(); } catch (error) { provided[key] = null; }
  }
  const csrf = (document.cookie.match(/(?:^|; )csrftoken=([^;]+)/) || [])[1] || '';
  const appId = (document.documentElement.innerHTML.match(/"X-IG-App-ID":"(\d+)"/) || [])[1] || '936619743392459';
  const largest = (candidates) => (candidates || []).slice().sort((a, b) => (b.width || 0) - (a.width || 0))[0]?.url || '';
  const isVideo = (media) => media?.media_type === 2 || (media?.video_versions || []).length > 0;
  const normalize = (node) => {
    const children = node.carousel_media || [];
    const primary = children.length ? children[0] : node;
    const images = (children.length ? children : [node]).filter((media) => !isVideo(media))
      .map((media) => largest(media.image_versions2?.candidates)).filter(Boolean).slice(0, args.max_media);
    const videos = isVideo(primary) ? [(primary.video_versions || [])[0]?.url].filter(Boolean) : [];
    const cover = largest(primary.image_versions2?.candidates);
    return {
      code: node.code,
      pk: String(node.pk || node.id || '').split('_')[0],
      posted_at: node.taken_at ? new Date(node.taken_at * 1000).toISOString() : '',
      taken_at: node.taken_at || 0,
      owner: node.user?.username || '',
      owner_name: node.user?.full_name || '',
      coauthors: (node.coauthor_producers || []).map((user) => user?.username).filter(Boolean),
      caption: node.caption?.text || '',
      accessibility_caption: node.accessibility_caption || primary.accessibility_caption || '',
      product_type: node.product_type || '',
      pinned: (node.timeline_pinned_user_ids || []).length > 0,
      primary_tag: isVideo(primary) ? 'video' : 'img',
      primary_media_url: isVideo(primary) ? (videos[0] || cover) : cover,
      cover_url: cover,
      image_urls: images,
      video_urls: videos,
      media_count: children.length || 1,
      location: node.location?.name || '',
    };
  };
  const accounts = [];
  for (let index = 0; index < args.usernames.length; index += 1) {
    const username = args.usernames[index];
    if (index > 0) await sleep(args.min_gap_ms + Math.random() * (args.max_gap_ms - args.min_gap_ms));
    const started = Date.now();
    try {
      const variables = { data: { count: args.limit, include_reel_banner: true, include_relationship_info: true, latest_besties_reel_media: true, latest_reel_media: true }, username, ...provided };
      const body = new URLSearchParams({ fb_dtsg: dtsg, lsd, fb_api_caller_class: 'RelayModern', fb_api_req_friendly_name: 'PolarisProfilePostsQuery', variables: JSON.stringify(variables), server_timestamps: 'true', doc_id: docId });
      const response = await fetch('/graphql/query', { method: 'POST', credentials: 'include', body,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-FB-LSD': lsd, 'X-CSRFToken': csrf, 'X-IG-App-ID': appId, 'X-FB-Friendly-Name': 'PolarisProfilePostsQuery' } });
      const raw = await response.text();
      let json = null;
      try { json = JSON.parse(raw); } catch (error) { json = null; }
      const connection = json?.data?.xdt_api__v1__feed__user_timeline_graphql_connection;
      if (!connection) {
        accounts.push({ username, ok: false, status: response.status, ms: Date.now() - started,
          error: String(json?.errors?.[0]?.message || (json ? 'No posts were returned for this account.' : 'Instagram did not return data.')).slice(0, 200) });
        if (response.status === 429) break;
        continue;
      }
      const own = (connection.edges || []).find((edge) => (edge.node?.user?.username || '').toLowerCase() === username);
      accounts.push({ username, ok: true, status: response.status, ms: Date.now() - started, user_id: String(own?.node?.user?.pk || own?.node?.user?.id || ''),
        posts: (connection.edges || []).map((edge) => normalize(edge.node)).filter((post) => post.code) });
    } catch (error) {
      accounts.push({ username, ok: false, status: 0, ms: Date.now() - started, error: String(error).slice(0, 200) });
    }
  }
  return { text: JSON.stringify({ doc_id: docId, accounts }) };
})();
JS;
    }
}
