<?php

namespace hexa_package_instagram\Services\Concerns;

/**
 * Reads accounts' newest posts without an Instagram account. A logged-out browser session loads each
 * account's public profile embed (the widget Instagram serves to other websites), which carries the
 * account's six newest posts with caption, date and full-size image. No login and no private web
 * queries; the read stops at the first refusal so the caller can change route and clear the session
 * before trying the remaining accounts again. Instagram's "profile may be broken or removed" page, an
 * empty page and a timeout are uncertain, not proof the account is gone: the account is tried once more
 * at the end of the batch, and soft_limit uncertain accounts in a row count as a refusal.
 */
trait ReadsPublicInstagramFeeds
{
    /**
     * Newest public posts for several accounts, read logged out in one browser session.
     *
     * Accounts come back in profileFeeds()'s shape (`post_links`, `posts` keyed by shortcode in
     * postScan()'s `data.scan` shape). Carousels carry their first image only and videos their cover
     * (marked as video). `data.blocked` is true when Instagram refused the reader; the accounts after
     * that point are not read. A refusal of soft_limit uncertain accounts in a row leaves those accounts
     * unread too (listed in `data.blocked_uncertain`) so they are read again on the new route; accounts in
     * `settled` already had that second chance and are returned as unreadable instead.
     * Unreadable accounts carry `uncertain` = true unless Instagram answered 404.
     *
     * @param array<int, string> $usernames
     * @param array{min_gap_ms?: int, max_gap_ms?: int, fresh?: bool, soft_limit?: int, settled?: array<int, string>, fetch_timeout_ms?: int} $options fresh: forget Instagram's cookies and storage first
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function publicProfileFeeds(?string $profile, array $usernames, int $limit = 6, array $options = []): array
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
        $minGap = max(800, (int) ($options['min_gap_ms'] ?? 1500));
        $maxGap = max($minGap, (int) ($options['max_gap_ms'] ?? 4000));

        $steps = [];
        if (! empty($options['fresh'])) {
            $steps[] = ['type' => 'clear_site_data', 'label' => 'forget_instagram', 'origins' => ['https://www.instagram.com', 'https://instagram.com']];
        }
        $steps[] = ['type' => 'goto', 'label' => 'open_instagram', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => random_int(2000, 4000)];
        $steps[] = [
            'type' => 'evaluate',
            'label' => 'read_public_feeds',
            'code' => self::publicProfileFeedJs(),
            'args' => [
                'usernames' => $usernames,
                'limit' => $limit,
                'min_gap_ms' => $minGap,
                'max_gap_ms' => $maxGap,
                'soft_limit' => max(0, (int) ($options['soft_limit'] ?? 2)),
                'settled' => array_values(array_map('strtolower', (array) ($options['settled'] ?? []))),
                'fetch_timeout_ms' => max(5000, (int) ($options['fetch_timeout_ms'] ?? 20000)),
            ],
        ];

        $result = $this->browser->runAutomation($resolved, $steps, [
            // Room for one retry of every account (fetch timeout plus gap) on top of the normal read.
            'transport_timeout_ms' => 45000 + (count($usernames) * 2 * ($maxGap + 8000)),
            'public_read_only' => true,
        ]);

        $feed = json_decode((string) ($this->resultByLabel($result, 'read_public_feeds')['text'] ?? ''), true);
        if (! is_array($feed)) {
            // Instagram's own page did not load: treat it as a refusal of this route.
            return [
                'success' => false,
                'message' => 'Instagram public read failed.',
                'detail' => (string) ($result['message'] ?? 'The browser returned no data.'),
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => ['profile' => $resolved, 'accounts' => [], 'blocked' => true, 'blocked_at' => $usernames[0], 'blocked_status' => 0],
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
                    $scan = $this->feedPostAsScan($post + ['source' => 'instagram_public_embed']);
                    $posts[$scan['shortcode']] = $scan;
                }
            }
            $ok = (bool) ($account['ok'] ?? false);
            $accounts[$username] = [
                'success' => $ok,
                'user_id' => (string) ($account['user_id'] ?? ''),
                'status' => (int) ($account['status'] ?? 0),
                'message' => $ok ? 'Recent public posts read.' : (string) ($account['error'] ?? 'Recent posts could not be read.'),
                'uncertain' => ! $ok && (bool) ($account['uncertain'] ?? false),
                'elapsed_ms' => (int) ($account['ms'] ?? 0),
                'post_links' => array_values(array_map(static fn (array $scan): string => (string) $scan['url'], $posts)),
                'posts' => $posts,
            ];
        }
        foreach ($usernames as $username) {
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
        $blocked = is_array($feed['blocked'] ?? null);

        return [
            'success' => $read > 0,
            'message' => $read.' of '.count($usernames).' Instagram accounts read logged out.'
                .($blocked ? (! empty($feed['blocked']['uncertain']) && (int) $feed['blocked']['status'] < 400
                    ? ' Instagram gave no profile for '.count((array) $feed['blocked']['uncertain']).' accounts in a row from @'.$feed['blocked']['username'].'; treated as a refusal.'
                    : ' Instagram refused the reader at @'.$feed['blocked']['username'].' (HTTP '.(int) $feed['blocked']['status'].').') : ''),
            'detail' => 'Read from each account\'s public profile embed in the logged-out browser profile '.$resolved.'.',
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => [
                'profile' => $resolved,
                'accounts' => $accounts,
                'blocked' => $blocked,
                'blocked_at' => $blocked ? (string) $feed['blocked']['username'] : '',
                'blocked_status' => $blocked ? (int) $feed['blocked']['status'] : 0,
                'blocked_uncertain' => $blocked ? array_values((array) ($feed['blocked']['uncertain'] ?? [])) : [],
            ],
        ];
    }

    public static function publicProfileFeedJs(): string
    {
        return <<<'JS'
return (async () => {
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const largest = (resources) => (resources || []).slice().sort((a, b) => (b.config_width || 0) - (a.config_width || 0))[0]?.src || '';
  const settled = new Set(args.settled || []);
  const gap = () => sleep(args.min_gap_ms + Math.random() * (args.max_gap_ms - args.min_gap_ms));
  // One account: { ok, ... } when read, { refused } when Instagram refuses the reader, or { uncertain } when
  // Instagram gave no profile (its "may be broken" page, an empty page, a timeout): not proof the account is gone.
  const read = async (username) => {
    const started = Date.now();
    let status = 0;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), args.fetch_timeout_ms);
    try {
      const response = await fetch('/' + encodeURIComponent(username) + '/embed/', { credentials: 'include', signal: controller.signal });
      status = response.status;
      const html = response.ok ? await response.text() : '';
      const match = html.match(/"contextJSON":("(?:[^"\\]|\\.)*")/);
      if ([401, 403, 429].includes(status) || status >= 500 || /\/accounts\/login/.test(response.url)) {
        return { refused: true, status };
      }
      if (!match) {
        if (status === 404) return { username, ok: false, status, ms: Date.now() - started, error: 'Instagram account not found (HTTP 404).' };
        return { username, ok: false, uncertain: true, status, ms: Date.now() - started,
          error: /may be broken|may have been removed/i.test(html)
            ? 'Instagram showed the logged-out reader its "profile may be broken or removed" page; often a temporary refusal, not proof the account is gone.'
            : 'Instagram returned the page without any posts.' };
      }
      const context = JSON.parse(JSON.parse(match[1])).context || {};
      const owner = String(context.username || username).toLowerCase();
      const posts = (context.graphql_media || []).map((item) => item?.shortcode_media).filter((media) => media?.shortcode)
        .slice(0, args.limit).map((media) => {
          const video = !!media.is_video;
          const image = largest(media.display_resources) || media.display_url || '';
          return {
            code: media.shortcode,
            pk: String(media.id || '').split('_')[0],
            posted_at: media.taken_at_timestamp ? new Date(media.taken_at_timestamp * 1000).toISOString() : '',
            taken_at: media.taken_at_timestamp || 0,
            owner: media.owner?.username || owner,
            owner_name: context.full_name || '',
            coauthors: (media.coauthor_producers || []).map((user) => user?.username).filter(Boolean),
            caption: media.edge_media_to_caption?.edges?.[0]?.node?.text || '',
            accessibility_caption: media.accessibility_caption || '',
            product_type: video ? 'clips' : '',
            pinned: (media.pinned_for_users || []).length > 0,
            primary_tag: video ? 'video' : 'img',
            primary_media_url: image,
            cover_url: image,
            image_urls: video || !image ? [] : [image],
            video_urls: [],
            media_count: 1,
            location: media.location?.name || '',
          };
        });
      return { username, ok: true, status, ms: Date.now() - started, user_id: String(context.owner_id || ''), posts };
    } catch (error) {
      const timedOut = error?.name === 'AbortError';
      return { username, ok: false, uncertain: true, status, ms: Date.now() - started,
        error: timedOut ? 'Instagram did not answer within ' + Math.round(args.fetch_timeout_ms / 1000) + ' seconds.' : String(error).slice(0, 200) };
    } finally {
      clearTimeout(timer);
    }
  };
  const accounts = [];
  const retry = [];
  let streak = [];
  let blocked = null;
  for (let index = 0; index < args.usernames.length && !blocked; index += 1) {
    const username = args.usernames[index];
    if (index > 0) await gap();
    const result = await read(username);
    if (result.refused) {
      blocked = { username, status: result.status };
    } else if (result.uncertain && !settled.has(username.toLowerCase())) {
      streak.push(result);
      if (args.soft_limit > 0 && streak.length >= args.soft_limit) {
        // Several accounts in a row without a profile: the route is being refused. Leave them unread.
        blocked = { username: streak[0].username, status: result.status, uncertain: streak.map((item) => item.username) };
      }
    } else {
      retry.push(...streak);
      streak = [];
      accounts.push(result.uncertain ? { ...result, error: 'Instagram does not show this profile to a logged-out reader, on a second NordVPN server too (embedding may be turned off, or the account is age-restricted); it needs a logged-in read. Last answer: ' + result.error } : result);
    }
  }
  if (!blocked) retry.push(...streak);
  // Uncertain accounts get a second try at the end of the batch, after a longer wait.
  for (const first of blocked ? [] : retry) {
    await sleep(5000 + Math.random() * 5000);
    const result = await read(first.username);
    if (result.refused) {
      blocked = { username: first.username, status: result.status };
      break;
    }
    accounts.push(result.ok || !result.uncertain ? result : { ...result, error: result.error + ' Same answer when tried again.' });
  }
  if (blocked) {
    // Accounts still waiting for their second try are read again on the next route, with the refused ones.
    const waiting = retry.map((item) => item.username).filter((name) => !accounts.some((done) => done.username === name));
    blocked.uncertain = [...new Set([...(blocked.uncertain || []), ...waiting])];
  }
  return { text: JSON.stringify({ accounts, blocked }) };
})();
JS;
    }

    /**
     * Every slide of public posts, read logged out from each post's embed (the widget Instagram serves
     * to other websites), in the given browser profile. A carousel returns all its slides in order; a
     * single photo or video returns one. Nothing is logged in; the profile's own route (for JPN a NordVPN
     * server) carries the reads. `data.posts[<code>]` holds owner, caption, posted_at and `slides`
     * (`index`, `is_video`, `image_url`: the largest rendition, a video's cover).
     *
     * @param array<int, string> $shortcodes Post codes (the part after /p/ or /reel/)
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function publicPostSlides(?string $profile, array $shortcodes): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $codes = array_values(array_unique(array_filter(array_map(
            static fn ($code): string => preg_replace('/[^A-Za-z0-9_-]/', '', (string) $code) ?? '',
            $shortcodes,
        ))));
        if ($codes === []) {
            return $this->failure('At least one Instagram post code is required.', 'Provide the post codes whose slides should be read.');
        }

        $result = $this->browser->runAutomation($resolved, [
            ['type' => 'goto', 'label' => 'open_instagram', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => random_int(2000, 4000)],
            ['type' => 'evaluate', 'label' => 'read_post_slides', 'code' => self::publicPostSlidesJs(), 'args' => ['codes' => $codes, 'min_gap_ms' => 1500, 'max_gap_ms' => 4000]],
        ], [
            'transport_timeout_ms' => 45000 + count($codes) * 25000,
            'public_read_only' => true,
        ]);

        $read = json_decode((string) ($this->resultByLabel($result, 'read_post_slides')['text'] ?? ''), true);
        if (! is_array($read)) {
            return [
                'success' => false,
                'message' => 'Instagram public read failed.',
                'detail' => (string) ($result['message'] ?? 'The browser returned no data.'),
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => ['profile' => $resolved, 'posts' => [], 'blocked' => true],
            ];
        }
        $posts = [];
        foreach ((array) ($read['posts'] ?? []) as $post) {
            if (is_array($post) && ($post['code'] ?? '') !== '') {
                $posts[(string) $post['code']] = $post;
            }
        }
        $read_ok = count(array_filter($posts, static fn (array $post): bool => ! empty($post['ok'])));

        return [
            'success' => $read_ok > 0 && empty($read['blocked']),
            'message' => $read_ok.' of '.count($codes).' posts read logged out.',
            'detail' => 'Read from each post\'s public embed in the logged-out browser profile '.$resolved.'.',
            'status_code' => 200,
            'data' => ['profile' => $resolved, 'posts' => $posts, 'blocked' => ! empty($read['blocked']), 'blocked_status' => (int) ($read['blocked_status'] ?? 0)],
        ];
    }

    public static function publicPostSlidesJs(): string
    {
        return <<<'JS'
return (async () => {
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const largest = (resources) => (resources || []).slice().sort((a, b) => (b.config_width || 0) - (a.config_width || 0))[0]?.src || '';
  const posts = [];
  let blocked = false, blockedStatus = 0;
  for (const [n, code] of (args.codes || []).entries()) {
    if (n > 0) await sleep(args.min_gap_ms + Math.random() * (args.max_gap_ms - args.min_gap_ms));
    let status = 0;
    try {
      const response = await fetch('/p/' + encodeURIComponent(code) + '/embed/captioned/', { credentials: 'include' });
      status = response.status;
      if ([401, 403, 429].includes(status) || status >= 500 || /\/accounts\/login/.test(response.url)) {
        blocked = true; blockedStatus = status; break;
      }
      const html = response.ok ? await response.text() : '';
      const match = html.match(/"contextJSON":("(?:[^"\\]|\\.)*")/);
      const context = match ? JSON.parse(JSON.parse(match[1])) : {};
      const media = context.gql_data?.shortcode_media || context.context?.media || null;
      if (!media) { posts.push({ code, ok: false, status, error: 'Instagram returned the post embed without its media.' }); continue; }
      const edges = media.edge_sidecar_to_children?.edges || [];
      const nodes = edges.length ? edges.map((edge) => edge?.node || {}) : [media];
      posts.push({
        code, ok: true, status,
        owner: media.owner?.username || '',
        caption: media.edge_media_to_caption?.edges?.[0]?.node?.text || '',
        posted_at: media.taken_at_timestamp ? new Date(media.taken_at_timestamp * 1000).toISOString() : '',
        is_carousel: edges.length > 1,
        slides: nodes.map((node, index) => ({ index: index + 1, is_video: !!node.is_video, image_url: largest(node.display_resources) || node.display_url || '' })),
      });
    } catch (error) {
      posts.push({ code, ok: false, status, error: String(error).slice(0, 200) });
    }
  }
  return { text: JSON.stringify({ posts, blocked, blocked_status: blockedStatus }) };
})();
JS;
    }
}
