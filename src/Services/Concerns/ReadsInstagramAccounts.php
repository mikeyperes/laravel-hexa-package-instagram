<?php

namespace hexa_package_instagram\Services\Concerns;

/**
 * Account profiles, single posts/reels/stories by link, and Highlights, read through Instagram's own
 * web data calls from inside the logged-in browser profile (no page scraping).
 */
trait ReadsInstagramAccounts
{
    /**
     * Numeric id, name and private/verified flags for several accounts, from Instagram's search.
     * Stops at the first rate limit; unread accounts have success false.
     *
     * @param array<int, string> $usernames
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function accountProfiles(?string $profile, array $usernames): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $usernames = array_values(array_unique(array_filter(array_map(
            fn ($username): string => ltrim($this->config->normalizeUsername((string) $username), '@'),
            $usernames,
        ))));
        if ($usernames === []) {
            return $this->failure('At least one Instagram username is required.', 'Provide the accounts to read.');
        }

        $result = $this->browser->runAutomation($resolved, [
            ['type' => 'goto', 'label' => 'open_home', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 2000],
            ['type' => 'evaluate', 'label' => 'read_profiles', 'code' => self::restJs(self::accountProfilesJs()), 'args' => ['usernames' => $usernames, 'min_gap_ms' => 1200, 'max_gap_ms' => 2800]],
        ], ['transport_timeout_ms' => 45000 + count($usernames) * 6000]);

        $feed = json_decode((string) ($this->resultByLabel($result, 'read_profiles')['text'] ?? ''), true);
        if ($this->isLoginRedirect($result, []) || ! is_array($feed) || isset($feed['fatal'])) {
            return $this->feedFailure($result, $resolved, is_array($feed) ? (string) ($feed['fatal'] ?? '') : '', 'Instagram profile read failed.');
        }
        $accounts = [];
        foreach ($usernames as $username) {
            $accounts[$username] = (array) ($feed['accounts'][$username] ?? ['success' => false, 'message' => 'Not read in this batch.']);
        }
        $read = count(array_filter($accounts, static fn (array $account): bool => (bool) ($account['success'] ?? false)));

        return [
            'success' => $read > 0,
            'message' => $read . ' of ' . count($usernames) . ' Instagram profiles read.',
            'detail' => 'Read through Instagram\'s profile data in the authenticated browser profile.',
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => ['profile' => $resolved, 'accounts' => $accounts],
        ];
    }

    /**
     * One post, reel or story by its link: owner, caption, full-size media, tagged/mentioned accounts,
     * link stickers, location, and for a story that shares a post, that post's id and short id.
     *
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function mediaByUrl(?string $profile, string $url): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $link = $this->parseInstagramLink($url);
        if ($link === null) {
            return $this->failure('Unrecognised Instagram link.', 'Send a post (/p/), reel (/reel/) or story (/stories/<user>/<id>/) link.');
        }

        $result = $this->browser->runAutomation($resolved, [
            ['type' => 'goto', 'label' => 'open_home', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 2000],
            ['type' => 'evaluate', 'label' => 'read_media', 'code' => self::restJs(self::mediaNormalizeJs() . self::mediaByUrlJs()), 'args' => $link],
        ], ['transport_timeout_ms' => 60000]);

        $feed = json_decode((string) ($this->resultByLabel($result, 'read_media')['text'] ?? ''), true);
        if ($this->isLoginRedirect($result, []) || ! is_array($feed) || isset($feed['fatal'])) {
            return $this->feedFailure($result, $resolved, is_array($feed) ? (string) ($feed['fatal'] ?? '') : '', 'Instagram item read failed.');
        }
        $item = (array) ($feed['item'] ?? []);
        if (($item['pk'] ?? '') === '') {
            return [
                'success' => false,
                'message' => $link['type'] === 'story' ? 'Story not found. It may have expired or be private.' : 'Post not found. It may be private or removed.',
                'detail' => 'Instagram returned status ' . (int) ($feed['status'] ?? 0) . '.',
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => ['profile' => $resolved, 'link' => $link],
            ];
        }
        $item['type'] = $link['type'];
        $item['url'] = $link['type'] === 'story'
            ? 'https://www.instagram.com/stories/' . ($item['owner'] ?: $link['username']) . '/' . $item['pk'] . '/'
            : 'https://www.instagram.com/' . ($link['type'] === 'reel' ? 'reel' : 'p') . '/' . $this->canonicalShortcode((string) $item['code'], (string) $item['pk']) . '/';

        return [
            'success' => true,
            'message' => ucfirst($link['type']) . ' by @' . $item['owner'] . ' read.',
            'detail' => 'Read through Instagram\'s media data in the authenticated browser profile.',
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => ['profile' => $resolved, 'link' => $link, 'item' => $item],
        ];
    }

    /**
     * An account's Highlights (stories it pinned to its profile) with their items.
     *
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function highlightFeeds(?string $profile, string $userId, int $maxHighlights = 10): array
    {
        $resolved = $this->config->resolveProfile($profile);
        if (! ctype_digit($userId)) {
            return $this->failure('A numeric Instagram account id is required.', 'accountProfiles() returns each account\'s id.');
        }

        $result = $this->browser->runAutomation($resolved, [
            ['type' => 'goto', 'label' => 'open_home', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 2000],
            ['type' => 'evaluate', 'label' => 'read_highlights', 'code' => self::restJs(self::mediaNormalizeJs() . self::highlightsJs()), 'args' => ['user_id' => $userId, 'max' => max(1, min($maxHighlights, 30)), 'min_gap_ms' => 1200, 'max_gap_ms' => 2800]],
        ], ['transport_timeout_ms' => 90000]);

        $feed = json_decode((string) ($this->resultByLabel($result, 'read_highlights')['text'] ?? ''), true);
        if ($this->isLoginRedirect($result, []) || ! is_array($feed) || isset($feed['fatal'])) {
            return $this->feedFailure($result, $resolved, is_array($feed) ? (string) ($feed['fatal'] ?? '') : '', 'Instagram highlights read failed.');
        }
        $highlights = array_values(array_filter((array) ($feed['highlights'] ?? []), 'is_array'));

        return [
            'success' => true,
            'message' => count($highlights) . ' highlights read.',
            'detail' => 'Read through Instagram\'s highlights data in the authenticated browser profile.',
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => ['profile' => $resolved, 'user_id' => $userId, 'highlights' => $highlights],
        ];
    }

    /**
     * Type and ids from an Instagram link: post/reel (shortcode and numeric id) or story (username and id).
     *
     * @return array{type: string, username: string, shortcode: string, pk: string}|null
     */
    public function parseInstagramLink(string $url): ?array
    {
        $path = (string) parse_url(trim($url), PHP_URL_PATH);
        if (preg_match('~^/stories/([A-Za-z0-9._]{1,30})/(\d{5,30})~', $path, $match)) {
            return ['type' => 'story', 'username' => strtolower($match[1]), 'shortcode' => '', 'pk' => $match[2]];
        }
        if (preg_match('~^/(?:[A-Za-z0-9._]{1,30}/)?(p|reel|reels|tv)/([A-Za-z0-9_-]{5,64})~', $path, $match)) {
            $shortcode = strlen($match[2]) > 20 ? substr($match[2], 0, 11) : $match[2];

            return ['type' => $match[1] === 'p' ? 'post' : 'reel', 'username' => '', 'shortcode' => $shortcode, 'pk' => $this->shortcodeToPk($shortcode)];
        }

        return null;
    }

    /** Instagram's short post id is the numeric media id in base 64. */
    private function shortcodeToPk(string $shortcode): string
    {
        if (! function_exists('bcmul')) {
            return '';
        }
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $pk = '0';
        foreach (str_split($shortcode) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                return '';
            }
            $pk = bcadd(bcmul($pk, '64'), (string) $position);
        }

        return $pk;
    }

    private static function accountProfilesJs(): string
    {
        // Instagram's profile-info endpoint is rate limited for this use; its search returns the id,
        // name and private/verified flags. Bios are not available this way.
        return <<<'JS'
  const accounts = {};
  for (let index = 0; index < args.usernames.length; index += 1) {
    const username = args.usernames[index];
    if (index > 0) await gap();
    const { status, json } = await getJson('/web/search/topsearch/?query=' + encodeURIComponent(username));
    const user = (json?.users || []).map((row) => row.user).find((row) => (row?.username || '').toLowerCase() === username);
    if (!user) { accounts[username] = { success: false, status, message: status === 429 ? 'Rate limited by Instagram.' : 'Account not found in Instagram search.' }; if (status === 429) break; continue; }
    accounts[username] = { success: true, status, user_id: String(user.pk || user.pk_id || user.id || ''), username: user.username, full_name: user.full_name || '',
      is_private: !!user.is_private, is_verified: !!user.is_verified, biography: '', category: '', external_url: '', followers: null };
  }
  return { text: JSON.stringify({ accounts }) };
JS;
    }

    /** Shared JS: one Instagram media item (post, reel, story or highlight item) in a flat shape. */
    private static function mediaNormalizeJs(): string
    {
        return <<<'JS'
  const isVideo = (media) => media?.media_type === 2 || (media?.video_versions || []).length > 0;
  const stickerUrl = (sticker) => { const url = sticker.story_link?.url || ''; try { return new URL(url).searchParams.get('u') || url; } catch (error) { return url; } };
  const norm = (node) => {
    const children = node.carousel_media || [];
    const primary = children.length ? children[0] : node;
    const cover = largest(primary.image_versions2?.candidates);
    const shared = (node.story_feed_media || [])[0] || null;
    return {
      pk: String(node.pk || node.id || '').split('_')[0], code: node.code || '',
      owner: node.user?.username || '', owner_name: node.user?.full_name || '', owner_id: String(node.user?.pk || node.user?.id || ''),
      taken_at: node.taken_at || 0, expiring_at: node.expiring_at || 0,
      caption: node.caption?.text || '', accessibility_caption: node.accessibility_caption || primary.accessibility_caption || '',
      product_type: node.product_type || '', media_type: isVideo(primary) ? 'video' : 'image',
      cover_url: cover, video_url: isVideo(primary) ? ((primary.video_versions || [])[0]?.url || '') : '',
      image_urls: (children.length ? children : [node]).filter((media) => !isVideo(media)).map((media) => largest(media.image_versions2?.candidates)).filter(Boolean).slice(0, 10),
      media_count: children.length || 1,
      location: node.location?.name || node.story_locations?.[0]?.location?.name || '',
      coauthors: (node.coauthor_producers || []).map((user) => user?.username).filter(Boolean),
      tagged: (node.usertags?.in || []).map((tag) => tag.user?.username).filter(Boolean),
      mentions: (node.reel_mentions || []).map((mention) => mention.user?.username).filter(Boolean),
      links: (node.story_link_stickers || []).map(stickerUrl).filter(Boolean),
      hashtags: (node.story_hashtags || []).map((tag) => tag.hashtag?.name).filter(Boolean),
      reshared_post: shared ? { pk: String(shared.media_id || '').split('_')[0], code: shared.media_code || '', owner: shared.media?.user?.username || shared.product_type || '' } : null,
    };
  };
JS;
    }

    private static function mediaByUrlJs(): string
    {
        // Stories are read from the owner's stories feed, which (unlike the single-item lookup)
        // includes the post a story shares; posts and reels use the single-item lookup.
        return <<<'JS'
  let status = 0;
  let node = null;
  if (args.type === 'story' && args.username) {
    const search = await getJson('/web/search/topsearch/?query=' + encodeURIComponent(args.username));
    const owner = (search.json?.users || []).map((row) => row.user).find((row) => (row?.username || '').toLowerCase() === args.username);
    const userId = String(owner?.pk || owner?.pk_id || '');
    if (userId) {
      const reels = await getJson('/api/v1/feed/reels_media/?reel_ids=' + userId);
      status = reels.status;
      node = (reels.json?.reels?.[userId]?.items || []).find((item) => String(item.pk) === String(args.pk)) || null;
    }
  }
  if (!node) {
    const info = await getJson('/api/v1/media/' + args.pk + '/info/');
    status = info.status;
    node = (info.json?.items || [])[0] || null;
  }
  return { text: JSON.stringify({ status, item: node ? norm(node) : null }) };
JS;
    }

    private static function highlightsJs(): string
    {
        return <<<'JS'
  const tray = await getJson('/api/v1/highlights/' + args.user_id + '/highlights_tray/');
  if (!tray.json) return { text: JSON.stringify({ fatal: 'Instagram returned status ' + tray.status + ' for highlights.' }) };
  const list = (tray.json.tray || []).slice(0, args.max).map((item) => ({ id: String(item.id || ''), title: item.title || '', count: item.media_count || 0 }));
  const highlights = [];
  for (let index = 0; index < list.length; index += 10) {
    if (index > 0) await gap();
    const chunk = list.slice(index, index + 10);
    const { json } = await getJson('/api/v1/feed/reels_media/?' + chunk.map((item) => 'reel_ids=' + encodeURIComponent(item.id)).join('&'));
    for (const item of chunk) highlights.push({ ...item, items: ((json?.reels || {})[item.id]?.items || []).map(norm) });
  }
  return { text: JSON.stringify({ highlights }) };
JS;
    }
}
