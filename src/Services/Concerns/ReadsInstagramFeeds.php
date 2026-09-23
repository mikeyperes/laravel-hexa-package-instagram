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
            fn ($username): string => $this->config->normalizeUsername((string) $username),
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
      accounts.push({ username, ok: true, status: response.status, ms: Date.now() - started,
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
