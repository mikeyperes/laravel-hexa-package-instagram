<?php

namespace hexa_package_instagram\Services\Concerns;

/**
 * Reads accounts' newest posts without an Instagram account. A logged-out browser session loads each
 * account's public profile embed (the widget Instagram serves to other websites), which carries the
 * account's six newest posts with caption, date and full-size image. No login and no private web
 * queries; the read stops at the first refusal so the caller can change route and clear the session
 * before trying the remaining accounts again.
 */
trait ReadsPublicInstagramFeeds
{
    /**
     * Newest public posts for several accounts, read logged out in one browser session.
     *
     * Accounts come back in profileFeeds()'s shape (`post_links`, `posts` keyed by shortcode in
     * postScan()'s `data.scan` shape). Carousels carry their first image only and videos their cover
     * (marked as video). `data.blocked` is true when Instagram refused the reader; the accounts after
     * that point are not read.
     *
     * @param array<int, string> $usernames
     * @param array{min_gap_ms?: int, max_gap_ms?: int, fresh?: bool} $options fresh: forget Instagram's cookies and storage first
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
            'args' => ['usernames' => $usernames, 'limit' => $limit, 'min_gap_ms' => $minGap, 'max_gap_ms' => $maxGap],
        ];

        $result = $this->browser->runAutomation($resolved, $steps, [
            'transport_timeout_ms' => 45000 + (count($usernames) * ($maxGap + 8000)),
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
                .($blocked ? ' Instagram refused the reader at @'.$feed['blocked']['username'].' (HTTP '.(int) $feed['blocked']['status'].').' : ''),
            'detail' => 'Read from each account\'s public profile embed in the logged-out browser profile '.$resolved.'.',
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => [
                'profile' => $resolved,
                'accounts' => $accounts,
                'blocked' => $blocked,
                'blocked_at' => $blocked ? (string) $feed['blocked']['username'] : '',
                'blocked_status' => $blocked ? (int) $feed['blocked']['status'] : 0,
            ],
        ];
    }

    public static function publicProfileFeedJs(): string
    {
        return <<<'JS'
return (async () => {
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const largest = (resources) => (resources || []).slice().sort((a, b) => (b.config_width || 0) - (a.config_width || 0))[0]?.src || '';
  const accounts = [];
  let blocked = null;
  for (let index = 0; index < args.usernames.length; index += 1) {
    const username = args.usernames[index];
    if (index > 0) await sleep(args.min_gap_ms + Math.random() * (args.max_gap_ms - args.min_gap_ms));
    const started = Date.now();
    let status = 0;
    try {
      const response = await fetch('/' + encodeURIComponent(username) + '/embed/', { credentials: 'include' });
      status = response.status;
      const html = response.ok ? await response.text() : '';
      const match = html.match(/"contextJSON":("(?:[^"\\]|\\.)*")/);
      if ([401, 403, 429].includes(status) || status >= 500 || /\/accounts\/login/.test(response.url)) {
        // Instagram is refusing this reader: stop so the caller can change route and clear the session.
        blocked = { username, status };
        break;
      }
      if (!match) {
        accounts.push({ username, ok: false, status, ms: Date.now() - started,
          error: status === 404 ? 'Instagram account not found.' : 'No public posts (private or unavailable account).' });
        continue;
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
      accounts.push({ username, ok: true, status, ms: Date.now() - started, user_id: String(context.owner_id || ''), posts });
    } catch (error) {
      accounts.push({ username, ok: false, status, ms: Date.now() - started, error: String(error).slice(0, 200) });
    }
  }
  return { text: JSON.stringify({ accounts, blocked }) };
})();
JS;
    }
}
