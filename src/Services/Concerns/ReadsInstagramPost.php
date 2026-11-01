<?php

namespace hexa_package_instagram\Services\Concerns;

use hexa_package_instagram\Services\InstagramConnectionService;

/**
 * One post or reel by its link or short id: every slide, the caption and the comments (instagram:post).
 * Logged in, it reads Instagram's media and comment data through one explicit logged-in browser profile
 * (after InstagramConnectionService::readGate()). Logged out, it reads the post's public embed through the
 * public reader profile, which carries only the comments Instagram puts in the embed (often none), so
 * `comments_complete` is false there. Read-only; callers own what the post means.
 */
trait ReadsInstagramPost
{
    /**
     * @return array{success: bool, message: string, mode: string, profile: string, post: array<string, mixed>|null, errors: array<int, string>}
     */
    public function readPost(string $post, ?string $profile, bool $loggedOut = false, int $comments = 20): array
    {
        $mode = $loggedOut ? 'logged_out' : 'logged_in';
        $profile = $this->config->normalizeProfile((string) ($profile ?: ($loggedOut ? config('instagram.public_reader_profile', 'instagram-public') : '')));
        $result = ['success' => false, 'message' => '', 'mode' => $mode, 'profile' => $profile, 'post' => null, 'errors' => []];
        $link = $this->postLink($post);
        if ($link === null) {
            return array_merge($result, ['message' => 'Give one Instagram post or reel link, or its short id (the part after /p/ or /reel/).']);
        }
        if ($profile === '') {
            return array_merge($result, ['message' => 'Name the logged-in browser profile to read with (--profile), or read logged out (--logged-out).']);
        }

        return $loggedOut
            ? $this->readPostLoggedOut($result, $link)
            : $this->readPostLoggedIn($result, $link, max(0, min($comments, 50)));
    }

    /** A post link or bare short id as ['type', 'shortcode', 'pk'], or null. */
    private function postLink(string $post): ?array
    {
        $post = trim($post);
        if (preg_match('/^[A-Za-z0-9_-]{5,64}$/', $post)) {
            $post = 'https://www.instagram.com/p/'.$post.'/';
        }
        $link = $this->parseInstagramLink($post);

        return is_array($link) && $link['type'] !== 'story' && ($link['shortcode'] ?? '') !== '' ? $link : null;
    }

    private function readPostLoggedIn(array $result, array $link, int $comments): array
    {
        $gate = app(InstagramConnectionService::class)->readGate($result['profile']);
        if (! $gate['ready']) {
            return array_merge($result, ['message' => $gate['reason'], 'errors' => [$gate['reason']]]);
        }
        if (($link['pk'] ?? '') === '') {
            return array_merge($result, ['message' => 'The post id could not be worked out from '.$link['shortcode'].'.']);
        }
        $run = $this->browser->runAutomation($result['profile'], [
            ['type' => 'goto', 'label' => 'open_home', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => random_int(2000, 4000)],
            ['type' => 'evaluate', 'label' => 'read_post', 'code' => self::restJs(self::postWithCommentsJs()), 'args' => ['pk' => $link['pk'], 'comments' => $comments] + $this->accountReadGaps()],
        ], ['transport_timeout_ms' => 90000]);
        $read = json_decode((string) ($this->resultByLabel($run, 'read_post')['text'] ?? ''), true);
        if ($this->isLoginRedirect($run, []) || ! is_array($read)) {
            $failure = $this->feedFailure($run, $result['profile'], '', 'Instagram post read failed.');

            return array_merge($result, ['message' => trim($failure['message'].' '.$failure['detail']), 'errors' => [$failure['detail']]]);
        }
        if (empty($read['item'])) {
            return array_merge($result, ['message' => 'Post not found. It may be private or removed (Instagram returned status '.(int) ($read['status'] ?? 0).').']);
        }
        $item = (array) $read['item'];
        if (($read['comments_status'] ?? 200) !== 200) {
            $result['errors'][] = 'Comments: Instagram returned status '.(int) $read['comments_status'].'.';
        }
        $result['post'] = $this->postRow($link, $item, (array) ($read['comments'] ?? []), $comments === 0 || ! empty($read['comments_complete']));

        return array_merge($result, ['success' => true, 'message' => $this->postMessage($result['post'], 'logged in')]);
    }

    private function readPostLoggedOut(array $result, array $link): array
    {
        $read = $this->publicPostSlides($result['profile'], [$link['shortcode']]);
        $item = (array) ($read['data']['posts'][$link['shortcode']] ?? []);
        if (empty($item['ok'])) {
            $reason = (string) (($item['error'] ?? '') ?: trim(($read['message'] ?? '').' '.($read['detail'] ?? '')));

            return array_merge($result, ['message' => 'The public read of the post failed: '.$reason, 'errors' => [$reason]]);
        }
        $slides = array_map(static fn (array $slide): array => [
            'index' => (int) $slide['index'],
            'is_video' => (bool) $slide['is_video'],
            'image_url' => (string) $slide['image_url'],
            'video_url' => (string) ($slide['video_url'] ?? ''),
        ], (array) ($item['slides'] ?? []));
        $result['post'] = $this->postRow($link, [
            'owner' => (string) ($item['owner'] ?? ''),
            'owner_name' => (string) ($item['owner_name'] ?? ''),
            'taken_at' => ($item['posted_at'] ?? '') !== '' ? strtotime((string) $item['posted_at']) : 0,
            'caption' => (string) ($item['caption'] ?? ''),
            'location' => (string) ($item['location'] ?? ''),
            'like_count' => $item['like_count'] ?? null,
            'comment_count' => $item['comment_count'] ?? null,
            'slides' => $slides,
        ], (array) ($item['comments'] ?? []), false);

        return array_merge($result, ['success' => true, 'message' => $this->postMessage($result['post'], 'logged out')]);
    }

    /** @return array<string, mixed> */
    private function postRow(array $link, array $item, array $comments, bool $complete): array
    {
        $code = (string) $link['shortcode'];

        return [
            'code' => $code,
            'pk' => (string) ($link['pk'] ?? ''),
            'url' => 'https://www.instagram.com/'.($link['type'] === 'reel' ? 'reel' : 'p').'/'.$code.'/',
            'owner' => strtolower((string) ($item['owner'] ?? '')),
            'owner_name' => (string) ($item['owner_name'] ?? ''),
            'taken_at' => (int) ($item['taken_at'] ?? 0) > 0 ? date(DATE_ATOM, (int) $item['taken_at']) : '',
            'caption' => (string) ($item['caption'] ?? ''),
            'location' => (string) ($item['location'] ?? ''),
            'like_count' => isset($item['like_count']) ? (int) $item['like_count'] : null,
            'comment_count' => isset($item['comment_count']) ? (int) $item['comment_count'] : null,
            'slides' => array_values((array) ($item['slides'] ?? [])),
            'comments' => array_values(array_map(static fn (array $comment): array => [
                'owner' => strtolower((string) ($comment['owner'] ?? '')),
                'text' => (string) ($comment['text'] ?? ''),
                'created_at' => (int) ($comment['created_at'] ?? 0) > 0 ? date(DATE_ATOM, (int) $comment['created_at']) : '',
                'likes' => (int) ($comment['likes'] ?? 0),
            ], array_filter($comments, 'is_array'))),
            'comments_complete' => $complete,
            'tagged' => array_values(array_unique((array) ($item['tagged'] ?? []))),
            'mentions' => array_values(array_unique((array) ($item['mentions'] ?? []))),
            'coauthors' => array_values(array_unique((array) ($item['coauthors'] ?? []))),
        ];
    }

    private function postMessage(array $post, string $how): string
    {
        return 'Post by @'.$post['owner'].' read '.$how.': '.count($post['slides']).' slide(s), '.count($post['comments']).' comment(s)'
            .($post['comment_count'] !== null ? ' of '.$post['comment_count'] : '').'.';
    }

    private static function postWithCommentsJs(): string
    {
        return <<<'JS'
  const info = await getJson('/api/v1/media/' + args.pk + '/info/');
  const node = (info.json?.items || [])[0] || null;
  if (!node) return { text: JSON.stringify({ status: info.status, item: null }) };
  const isVideo = (media) => media?.media_type === 2 || (media?.video_versions || []).length > 0;
  const children = node.carousel_media || [];
  const item = {
    owner: node.user?.username || '', owner_name: node.user?.full_name || '',
    taken_at: node.taken_at || 0, caption: node.caption?.text || '', location: node.location?.name || '',
    like_count: node.like_count ?? null, comment_count: node.comment_count ?? null,
    tagged: [...new Set([node, ...children].flatMap(m => (m.usertags?.in || []).map(t => t.user?.username).filter(Boolean)))],
    mentions: [...new Set([...(node.caption?.text || '').matchAll(/@([A-Za-z0-9._]{1,30})/g)].map(m => m[1].toLowerCase()))],
    coauthors: (node.coauthor_producers || []).map(u => u.username).filter(Boolean),
    slides: (children.length ? children : [node]).map((media, index) => ({ index: index + 1, is_video: isVideo(media),
      image_url: largest(media.image_versions2?.candidates), video_url: isVideo(media) ? ((media.video_versions || [])[0]?.url || '') : '' })),
  };
  let comments = [], commentsStatus = 200, complete = true;
  if (args.comments > 0 && (node.comment_count || 0) > 0) {
    await gap();
    const page = await getJson('/api/v1/media/' + args.pk + '/comments/?can_support_threading=true&permalink_enabled=false');
    commentsStatus = page.status;
    comments = (page.json?.comments || []).slice(0, args.comments).map((c) => ({ owner: c.user?.username || '', text: c.text || '', created_at: c.created_at || 0, likes: c.comment_like_count || 0 }));
    complete = page.status === 200 && comments.length >= Math.min(args.comments, node.comment_count || 0);
  }
  return { text: JSON.stringify({ status: info.status, item, comments, comments_status: commentsStatus, comments_complete: complete }) };
JS;
    }
}
