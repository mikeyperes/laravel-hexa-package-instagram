<?php

namespace hexa_package_instagram\Services\Concerns;

use hexa_package_instagram\Services\InstagramConnectionService;

/**
 * One account's newest posts or current stories, read-only, through one explicit logged-in browser
 * profile (instagram:posts, instagram:stories). Each read first passes InstagramConnectionService::readGate()
 * and stops with its exact reason; reads are paced with random pauses (instagram.account_reads).
 * Callers own what the results mean; nothing is followed, saved or posted here.
 */
trait ReadsInstagramAccountActivity
{
    /**
     * @return array{success: bool, message: string, account: string, profile: string, posts_source: string|null, posts: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function accountPosts(string $profile, string $account, int $limit = 5, bool $allowPublicFallback = false): array
    {
        $account = ltrim($this->config->normalizeUsername($account), '@');
        $profile = $this->config->normalizeProfile($profile);
        $result = ['success' => false, 'message' => '', 'account' => $account, 'profile' => $profile, 'posts_source' => null, 'posts' => [], 'errors' => []];
        if ($refused = $this->accountReadRefusal($profile, $account)) {
            return array_merge($result, $refused);
        }
        $limit = max(1, min($limit, 12));

        $feed = $this->profileFeeds($profile, [$account], $limit, $this->accountReadGaps());
        $row = (array) ($feed['data']['accounts'][$account] ?? []);
        if (! empty($row['success'])) {
            $result['posts_source'] = 'logged_in';
            $items = array_values((array) ($row['posts'] ?? []));
        } else {
            $reason = trim((string) (($row['message'] ?? '') ?: ($feed['message'] ?? '')).' '.(string) ($feed['detail'] ?? ''));
            $result['errors'][] = 'Posts: the '.$profile.' read failed ('.$reason.').';
            if (! $allowPublicFallback) {
                return array_merge($result, ['message' => 'The '.$profile.' read of @'.$account.'\'s posts failed: '.$reason]);
            }
            $this->accountReadPause();
            $publicProfile = $this->config->normalizeProfile((string) config('instagram.public_reader_profile', 'instagram-public'));
            $public = $this->publicProfileFeeds($publicProfile, [$account], $limit, $this->accountReadGaps());
            $publicRow = (array) ($public['data']['accounts'][$account] ?? []);
            if (empty($publicRow['success'])) {
                $publicReason = trim((string) (($publicRow['message'] ?? '') ?: ($public['message'] ?? '')).' '.(string) ($public['detail'] ?? ''));
                $result['errors'][] = 'Posts: the public embed ('.$publicProfile.') was not read either ('.$publicReason.').';

                return array_merge($result, ['message' => 'Neither the '.$profile.' read nor the public embed returned @'.$account.'\'s posts.']);
            }
            $result['posts_source'] = 'public_embed';
            $items = array_values((array) ($publicRow['posts'] ?? []));
        }

        foreach (array_slice($items, 0, $limit) as $post) {
            $result['posts'][] = $this->accountPostRow((array) $post);
        }

        return array_merge($result, [
            'success' => true,
            'message' => count($result['posts']).' posts read from @'.$account.' ('.$result['posts_source'].').',
        ]);
    }

    /**
     * @return array{success: bool, message: string, account: string, profile: string, stories: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function accountStories(string $profile, string $account, int $ownerLookups = 10): array
    {
        $account = ltrim($this->config->normalizeUsername($account), '@');
        $profile = $this->config->normalizeProfile($profile);
        $result = ['success' => false, 'message' => '', 'account' => $account, 'profile' => $profile, 'stories' => [], 'errors' => []];
        if ($refused = $this->accountReadRefusal($profile, $account)) {
            return array_merge($result, $refused);
        }

        $info = $this->accountProfiles($profile, [$account]);
        $userId = (string) ($info['data']['accounts'][$account]['user_id'] ?? '');
        if ($userId === '') {
            $reason = trim((string) (($info['data']['accounts'][$account]['message'] ?? '') ?: ($info['message'] ?? '')).' '.(string) ($info['detail'] ?? ''));
            $result['errors'][] = 'Stories: @'.$account.'\'s account id could not be read ('.$reason.').';

            return array_merge($result, ['message' => '@'.$account.'\'s account id could not be read: '.$reason]);
        }

        $this->accountReadPause();
        $read = $this->storyFeeds($profile, [$userId => $account], ['owner_lookups' => max(0, $ownerLookups)]);
        if (! ($read['success'] ?? false)) {
            $reason = trim((string) ($read['message'] ?? '').' '.(string) ($read['detail'] ?? ''));
            $result['errors'][] = 'Stories: '.$reason;

            return array_merge($result, ['message' => 'The '.$profile.' story read of @'.$account.' failed: '.$reason]);
        }
        if (str_starts_with((string) ($read['detail'] ?? ''), 'Some requests failed')) {
            $result['errors'][] = 'Stories: '.(string) $read['detail'];
        }
        foreach ((array) ($read['data']['reels'] ?? []) as $reel) {
            foreach ((array) ($reel['stories'] ?? []) as $story) {
                $result['stories'][] = $this->accountStoryRow((array) $story);
            }
        }

        return array_merge($result, [
            'success' => true,
            'message' => count($result['stories']).' current stories read from @'.$account.'.',
        ]);
    }

    /** The fail-fast refusal (invalid account or a profile that cannot read now), or null. */
    private function accountReadRefusal(string $profile, string $account): ?array
    {
        if (! preg_match('/^[a-z0-9._]{1,30}$/', $account)) {
            return ['message' => 'Give one Instagram username.', 'errors' => ['Invalid Instagram username: '.$account]];
        }
        $gate = app(InstagramConnectionService::class)->readGate($profile);
        if (! $gate['ready']) {
            return ['message' => $gate['reason'], 'errors' => [$gate['reason']]];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function accountPostRow(array $post): array
    {
        $caption = (string) (($post['caption_blocks'] ?? [])[0] ?? '');
        $videoUrls = array_values(array_filter(array_map('strval', (array) ($post['video_urls'] ?? []))));
        $isVideo = $videoUrls !== [] || (string) ($post['primary_media_box']['tag'] ?? '') === 'video' || (string) ($post['product_type'] ?? '') === 'clips';
        preg_match_all('/(?<![\w.])@([A-Za-z0-9._]{1,30})/u', $caption, $found);

        return [
            'url' => (string) ($post['url'] ?? ''),
            'shortcode' => (string) ($post['shortcode'] ?? ''),
            'taken_at' => (int) ($post['taken_at'] ?? 0) > 0 ? date(DATE_ATOM, (int) $post['taken_at']) : (string) ($post['posted_at'] ?? ''),
            'caption' => $caption,
            'image_urls' => array_values(array_filter(array_map('strval', (array) ($post['image_urls'] ?? [])))),
            // null for a photo post; the public embed gives a video's cover only, so its url may be ''.
            'video' => $isVideo ? ['url' => $videoUrls[0] ?? '', 'cover_url' => (string) ($post['cover_url'] ?? '')] : null,
            'mentions' => array_values(array_unique(array_filter(array_map(static fn ($name): string => strtolower(rtrim((string) $name, '.')), $found[1] ?? [])))),
            'tagged' => array_values(array_unique(array_map('strtolower', (array) ($post['tagged'] ?? [])))),
            'coauthors' => array_values(array_unique(array_map('strtolower', (array) ($post['coauthors'] ?? [])))),
        ];
    }

    /** @return array<string, mixed> */
    private function accountStoryRow(array $story): array
    {
        $shared = is_array($story['reshared_post'] ?? null) ? $story['reshared_post'] : null;

        return [
            'id' => (string) ($story['pk'] ?? ''),
            'url' => (string) ($story['url'] ?? ''),
            'taken_at' => (int) ($story['taken_at'] ?? 0) > 0 ? date(DATE_ATOM, (int) $story['taken_at']) : '',
            'image_url' => (string) ($story['image_url'] ?? ''),
            'video_url' => (string) ($story['video_url'] ?? ''),
            'reshared_post' => $shared === null ? null : [
                'url' => (string) ($shared['url'] ?? ''),
                'code' => (string) ($shared['code'] ?? ''),
                'owner' => (string) ($shared['owner'] ?? ''),
                'owner_id' => (string) ($shared['owner_id'] ?? ''),
            ],
            'mentions' => array_values(array_unique(array_map('strtolower', array_filter((array) ($story['mentions'] ?? []))))),
            'tagged' => array_values(array_unique(array_map('strtolower', array_filter((array) ($story['tagged'] ?? []))))),
            'links' => array_values(array_map('strval', (array) ($story['links'] ?? []))),
        ];
    }

    /** @return array{min_gap_ms: int, max_gap_ms: int} */
    private function accountReadGaps(): array
    {
        $min = max(1500, (int) config('instagram.account_reads.min_gap_ms', 4000));

        return ['min_gap_ms' => $min, 'max_gap_ms' => max($min, (int) config('instagram.account_reads.max_gap_ms', 9000))];
    }

    private function accountReadPause(): void
    {
        $gaps = $this->accountReadGaps();
        usleep(random_int($gaps['min_gap_ms'], $gaps['max_gap_ms']) * 1000);
    }
}
