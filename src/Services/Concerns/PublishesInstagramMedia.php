<?php

namespace hexa_package_instagram\Services\Concerns;

use Illuminate\Support\Str;

/**
 * Posts and deletes stories and feed posts from the Instagram account logged in to a browser profile,
 * the way a person does: stories on Instagram's phone site ("Your story"), feed posts through the
 * desktop "New post" dialog. Every action is read back from the account's own stories or posts.
 * Images must already be composed (InstagramImageComposer); nothing here crops or resizes.
 */
trait PublishesInstagramMedia
{
    /** Instagram's visible "Delete" (a menu row, then the confirm button). */
    private const DELETE_BUTTON = 'text="Delete" >> visible=true';

    /**
     * The logged-in account's current stories and latest feed posts.
     *
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function ownMedia(?string $profile, int $posts = 12): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $result = $this->browser->runAutomation($resolved, [
            ['type' => 'goto', 'label' => 'open_home', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 2000],
            ['type' => 'evaluate', 'label' => 'own_media', 'code' => self::restJs(self::ownMediaJs() . 'return { text: JSON.stringify(await ownMedia(args.posts)) };'), 'args' => $this->ownMediaArgs($resolved, ['posts' => max(0, min($posts, 30))])],
        ], ['transport_timeout_ms' => 60000]);

        return $this->ownMediaResult($result, $resolved, 'own_media', 'Instagram account media read.');
    }

    /**
     * Post one composed 9:16 JPEG as a story, then find it in the account's stories.
     *
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function postStoryImage(?string $profile, string $jpeg): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $screen = (array) config('instagram.publishing.story_screen', []);

        return $this->withStagedUpload($jpeg, function (string $file) use ($resolved, $screen): array {
            $result = $this->browser->runAutomation($resolved, [
                ['type' => 'emulate_mobile', 'label' => 'phone', 'width' => (int) ($screen['width'] ?? 360), 'height' => (int) ($screen['height'] ?? 640)],
                ['type' => 'goto', 'label' => 'open_home', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 4000],
                ['type' => 'click_if_exists', 'label' => 'dismiss', 'selector' => 'button:text-is("Not now")', 'timeout_ms' => 1500, 'wait_ms' => 800],
                ['type' => 'evaluate', 'label' => 'before', 'code' => self::restJs(self::ownMediaJs() . self::rememberBeforeJs(0)), 'args' => $this->ownMediaArgs($resolved)],
                ['type' => 'evaluate', 'label' => 'find_input', 'code' => self::storyInputJs()],
                ['type' => 'choose_file', 'label' => 'choose_image', 'selector' => 'input[data-hexa-story-input]', 'file' => $file, 'wait_ms' => 2000, 'timeout_ms' => 15000],
                ['type' => 'wait_for_selector', 'label' => 'editor', 'selector' => 'text=Add to your story', 'timeout_ms' => 30000],
                ['type' => 'wait_ms', 'label' => 'render', 'ms' => 2500],
                ['type' => 'click', 'label' => 'share', 'selector' => 'text=Add to your story', 'timeout_ms' => 15000, 'wait_ms' => 3000],
                ['type' => 'evaluate', 'label' => 'confirm', 'code' => self::restJs(self::ownMediaJs() . self::awaitNewJs('stories')), 'args' => $this->ownMediaArgs($resolved, ['tries' => 20])],
            ], ['transport_timeout_ms' => 180000]);

            return $this->publishResult($result, $resolved, 'story');
        });
    }

    /**
     * Post one composed JPEG with a caption to the feed (crop "Original"), then find it in the account's posts.
     *
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function postFeedImage(?string $profile, string $jpeg, string $caption): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $caption = mb_substr(trim($caption), 0, (int) config('instagram.publishing.caption_max', 2200));
        $next = '[role=dialog] [role=button]:text-is("Next"), [role=dialog] button:text-is("Next")';

        return $this->withStagedUpload($jpeg, function (string $file) use ($resolved, $caption, $next): array {
            $steps = [
                ['type' => 'goto', 'label' => 'open_home', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 4000],
                ['type' => 'evaluate', 'label' => 'before', 'code' => self::restJs(self::ownMediaJs() . self::rememberBeforeJs(5)), 'args' => $this->ownMediaArgs($resolved)],
                ['type' => 'click', 'label' => 'new_post', 'selector' => 'svg[aria-label="New post"]', 'timeout_ms' => 15000, 'wait_ms' => 1500],
                ['type' => 'click_if_exists', 'label' => 'post_option', 'selector' => 'svg[aria-label="Post"]', 'timeout_ms' => 3000, 'wait_ms' => 1500],
                ['type' => 'choose_file', 'label' => 'choose_image', 'selector' => '[role=dialog] input[type=file]', 'file' => $file, 'wait_ms' => 3000, 'timeout_ms' => 15000],
                ['type' => 'click', 'label' => 'crop_menu', 'selector' => '[role=dialog] svg[aria-label="Select crop"]', 'timeout_ms' => 15000, 'wait_ms' => 800],
                ['type' => 'click', 'label' => 'crop_original', 'selector' => '[role=dialog] svg[aria-label="Photo outline icon"]', 'timeout_ms' => 10000, 'wait_ms' => 800],
                ['type' => 'click', 'label' => 'next_crop', 'selector' => $next, 'timeout_ms' => 15000, 'wait_ms' => 2000],
                ['type' => 'click', 'label' => 'next_edit', 'selector' => $next, 'timeout_ms' => 15000, 'wait_ms' => 2000],
            ];
            if ($caption !== '') {
                $steps[] = ['type' => 'click', 'label' => 'caption_box', 'selector' => '[role=dialog] div[contenteditable="true"]', 'timeout_ms' => 15000, 'wait_ms' => 500];
                $steps[] = ['type' => 'type_text', 'label' => 'caption', 'text' => $caption, 'delay_ms' => 5];
            }
            $steps[] = ['type' => 'click', 'label' => 'share', 'selector' => '[role=dialog] [role=button]:text-is("Share"), [role=dialog] button:text-is("Share")', 'timeout_ms' => 15000, 'wait_ms' => 4000];
            $steps[] = ['type' => 'evaluate', 'label' => 'confirm', 'code' => self::restJs(self::ownMediaJs() . self::awaitNewJs('posts')), 'args' => $this->ownMediaArgs($resolved, ['tries' => 25])];

            $result = $this->browser->runAutomation($resolved, $steps, ['transport_timeout_ms' => 180000 + mb_strlen($caption) * 20]);

            return $this->publishResult($result, $resolved, 'post');
        });
    }

    /**
     * Delete one of the account's stories (Menu → Delete on the story) and confirm it is gone.
     *
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function deleteStory(?string $profile, string $username, string $pk): array
    {
        $resolved = $this->config->resolveProfile($profile);
        if (! ctype_digit($pk) || ! preg_match('/^[A-Za-z0-9._]{1,30}$/', $username)) {
            return $this->failure('A story id and the account username are required.', 'Use the id recorded when the story was posted.');
        }
        $screen = (array) config('instagram.publishing.story_screen', []);
        $result = $this->browser->runAutomation($resolved, [
            ['type' => 'emulate_mobile', 'label' => 'phone', 'width' => (int) ($screen['width'] ?? 360), 'height' => (int) ($screen['height'] ?? 640)],
            ['type' => 'goto', 'label' => 'open_story', 'url' => 'https://www.instagram.com/stories/' . $username . '/' . $pk . '/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 4000],
            ['type' => 'click', 'label' => 'menu', 'selector' => '[aria-label="Menu"]', 'timeout_ms' => 15000, 'wait_ms' => 1200],
            ['type' => 'click', 'label' => 'delete', 'selector' => self::DELETE_BUTTON, 'timeout_ms' => 10000, 'wait_ms' => 1200],
            ['type' => 'click', 'label' => 'confirm_delete', 'selector' => self::DELETE_BUTTON, 'timeout_ms' => 10000, 'wait_ms' => 4000],
            ['type' => 'goto', 'label' => 'open_home', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 1500],
            ['type' => 'evaluate', 'label' => 'after', 'code' => self::restJs(self::ownMediaJs() . 'return { text: JSON.stringify(await ownMedia(0)) };'), 'args' => $this->ownMediaArgs($resolved)],
        ], ['transport_timeout_ms' => 120000]);

        return $this->deleteResult($result, $resolved, 'stories', $pk);
    }

    /**
     * Delete one of the account's feed posts (More options → Delete on the post) and confirm it is gone.
     *
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function deleteFeedPost(?string $profile, string $code, string $pk): array
    {
        $resolved = $this->config->resolveProfile($profile);
        if (! preg_match('/^[A-Za-z0-9_-]{5,40}$/', $code)) {
            return $this->failure('A post short id is required.', 'Use the code recorded when the post was published.');
        }
        $result = $this->browser->runAutomation($resolved, [
            ['type' => 'goto', 'label' => 'open_post', 'url' => 'https://www.instagram.com/p/' . $code . '/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 4000],
            ['type' => 'click', 'label' => 'menu', 'selector' => 'main svg[aria-label="More options"], [role=dialog] svg[aria-label="More options"]', 'timeout_ms' => 15000, 'wait_ms' => 1200],
            ['type' => 'click', 'label' => 'delete', 'selector' => self::DELETE_BUTTON, 'timeout_ms' => 10000, 'wait_ms' => 1200],
            ['type' => 'click', 'label' => 'confirm_delete', 'selector' => self::DELETE_BUTTON, 'timeout_ms' => 10000, 'wait_ms' => 4000],
            ['type' => 'goto', 'label' => 'open_home', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 1500],
            ['type' => 'evaluate', 'label' => 'after', 'code' => self::restJs(self::ownMediaJs() . 'return { text: JSON.stringify(await ownMedia(12)) };'), 'args' => $this->ownMediaArgs($resolved)],
        ], ['transport_timeout_ms' => 120000]);

        return $this->deleteResult($result, $resolved, 'posts', $pk);
    }

    /** Arguments for ownMedia(): the account's username (its posts are read by username). */
    private function ownMediaArgs(string $profile, array $extra = []): array
    {
        return ['username' => (string) ($this->config->findAccount($profile)['instagram_username'] ?? '')] + $extra;
    }

    /** Write the image to the worker's upload folder for one run, and always remove it afterwards. */
    private function withStagedUpload(string $jpeg, callable $run): array
    {
        if ($jpeg === '') {
            return $this->failure('No image to publish.', 'Compose the image first.');
        }
        $directory = (string) config('browser-worker.profile_provisioning.upload_base_path', storage_path('app/private/workers/browser-worker/uploads'));
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return $this->failure('The browser upload folder is missing.', $directory);
        }
        $file = 'ig-' . Str::lower(Str::random(20)) . '.jpg';
        $path = $directory . DIRECTORY_SEPARATOR . $file;
        file_put_contents($path, $jpeg);
        @chmod($path, 0644);
        try {
            return $run($file);
        } finally {
            @unlink($path);
        }
    }

    private function publishResult(array $result, string $profile, string $kind): array
    {
        $confirm = json_decode((string) ($this->resultByLabel($result, 'confirm')['text'] ?? ''), true);
        $before = json_decode((string) ($this->resultByLabel($result, 'before')['text'] ?? ''), true);
        if ($this->isLoginRedirect($result, []) || (is_array($before) && isset($before['fatal']))) {
            return $this->feedFailure($result, $profile, (string) ($before['fatal'] ?? ''), 'Instagram requires a connected account.');
        }
        $item = is_array($confirm) ? ($confirm['new'] ?? null) : null;
        if (! is_array($item)) {
            $failedStep = collect((array) ($result['data']['results'] ?? []))->first(fn ($step): bool => ($step['success'] ?? true) === false);

            return [
                'success' => false,
                'message' => $kind === 'story' ? 'The story was not confirmed on the account.' : 'The post was not confirmed on the account.',
                'detail' => $failedStep ? ('Step "' . ($failedStep['label'] ?? '?') . '" failed: ' . ($failedStep['error'] ?? '')) : (string) ($result['message'] ?? 'Instagram did not list a new item.'),
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => ['profile' => $profile, 'kind' => $kind],
            ];
        }
        $username = (string) ($confirm['username'] ?? '');
        $item['url'] = $kind === 'story'
            ? 'https://www.instagram.com/stories/' . $username . '/' . $item['pk'] . '/'
            : 'https://www.instagram.com/p/' . $item['code'] . '/';

        return [
            'success' => true,
            'message' => $kind === 'story' ? 'Story posted and confirmed.' : 'Post published and confirmed.',
            'detail' => 'Read back from the account\'s own ' . ($kind === 'story' ? 'stories' : 'posts') . '.',
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => ['profile' => $profile, 'kind' => $kind, 'owner_id' => (string) ($confirm['owner_id'] ?? ''), 'username' => $username, 'item' => $item],
        ];
    }

    private function deleteResult(array $result, string $profile, string $list, string $pk): array
    {
        $after = json_decode((string) ($this->resultByLabel($result, 'after')['text'] ?? ''), true);
        if (! is_array($after) || isset($after['fatal'])) {
            return $this->feedFailure($result, $profile, is_array($after) ? (string) ($after['fatal'] ?? '') : '', 'Delete could not be confirmed.');
        }
        $stillThere = collect((array) ($after[$list] ?? []))->contains(fn ($item): bool => (string) ($item['pk'] ?? '') === $pk);

        return [
            'success' => ! $stillThere,
            'message' => $stillThere ? 'Still on the account after Delete.' : 'Deleted; no longer on the account.',
            'detail' => $stillThere ? (string) ($result['message'] ?? '') : 'Read back from the account\'s own ' . $list . '.',
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => ['profile' => $profile, 'pk' => $pk],
        ];
    }

    private function ownMediaResult(array $result, string $profile, string $label, string $message): array
    {
        $media = json_decode((string) ($this->resultByLabel($result, $label)['text'] ?? ''), true);
        if ($this->isLoginRedirect($result, []) || ! is_array($media) || isset($media['fatal'])) {
            return $this->feedFailure($result, $profile, is_array($media) ? (string) ($media['fatal'] ?? '') : '', 'Instagram account media read failed.');
        }

        return [
            'success' => true,
            'message' => $message,
            'detail' => count((array) $media['stories']) . ' live stories, ' . count((array) $media['posts']) . ' recent posts.',
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => ['profile' => $profile] + $media,
        ];
    }

    /**
     * Shared JS: ownMedia(postCount) reads the logged-in account's stories (stories feed) and latest
     * posts (Instagram's profile posts query, by username; the REST feed returns a web page here).
     */
    private static function ownMediaJs(): string
    {
        return <<<'JS'
  const ownPosts = async (username, count) => {
    const load = (name) => { try { return window.require(name); } catch (error) { return null; } };
    const params = load('PolarisProfilePostsQuery.graphql')?.params || {};
    const docId = params.id || load('PolarisProfilePostsQuery_instagramRelayOperation') || '';
    const dtsg = load('DTSGInitialData')?.token || '';
    const lsd = load('LSD')?.token || '';
    if (!docId || !dtsg) return { fatal: 'Instagram posts query or request tokens were not found on the page.' };
    const provided = {};
    for (const [key, provider] of Object.entries(params.providedVariables || {})) { try { provided[key] = provider.get(); } catch (error) { provided[key] = null; } }
    const variables = { data: { count, include_reel_banner: false, include_relationship_info: false, latest_besties_reel_media: false, latest_reel_media: false }, username, ...provided };
    const body = new URLSearchParams({ fb_dtsg: dtsg, lsd, fb_api_caller_class: 'RelayModern', fb_api_req_friendly_name: 'PolarisProfilePostsQuery', variables: JSON.stringify(variables), server_timestamps: 'true', doc_id: docId });
    const response = await fetch('/graphql/query', { method: 'POST', credentials: 'include', body,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-FB-LSD': lsd, 'X-CSRFToken': csrf, 'X-IG-App-ID': headers['X-IG-App-ID'], 'X-FB-Friendly-Name': 'PolarisProfilePostsQuery' } });
    let json = null;
    try { json = JSON.parse(await response.text()); } catch (error) { json = null; }
    const connection = json?.data?.xdt_api__v1__feed__user_timeline_graphql_connection;
    if (!connection) return { fatal: 'Instagram returned status ' + response.status + ' for the account posts.' };
    return { posts: (connection.edges || []).map((edge) => edge.node).filter((node) => (node?.user?.username || username) === username)
      .map((node) => ({ pk: String(node.pk || node.id || '').split('_')[0], code: node.code || '', taken_at: node.taken_at || 0, caption: (node.caption?.text || '').slice(0, 200) })) };
  };
  const ownMedia = async (postCount) => {
    const id = (document.cookie.match(/(?:^|; )ds_user_id=(\d+)/) || [])[1] || '';
    if (!id) return { fatal: 'No Instagram account is logged in.' };
    const reels = await getJson('/api/v1/feed/reels_media/?reel_ids=' + id);
    if (!reels.json) return { fatal: 'Instagram returned status ' + reels.status + ' for the account stories.' };
    const reel = reels.json.reels?.[id] || {};
    const username = args.username || reel.user?.username || '';
    let posts = [];
    if (postCount > 0) {
      if (!username) return { fatal: 'The account username is unknown; save it on the Instagram account.' };
      const read = await ownPosts(username, postCount);
      if (read.fatal) return read;
      posts = read.posts;
    }
    return { owner_id: id, username,
      stories: (reel.items || []).map((item) => ({ pk: String(item.pk).split('_')[0], taken_at: item.taken_at || 0, expiring_at: item.expiring_at || 0 })),
      posts };
  };
JS;
    }

    /** Shared JS: read the account before publishing and keep its item ids in this tab for the confirm step. */
    private static function rememberBeforeJs(int $postCount): string
    {
        return <<<JS
  const media = await ownMedia({$postCount});
  // Stop the run here: without this read the new item could not be told apart, so nothing is posted.
  if (media.fatal) throw new Error(media.fatal);
  sessionStorage.setItem('hexa-publish-before', JSON.stringify([...media.stories, ...media.posts].map((item) => item.pk)));
  return { text: JSON.stringify(media) };
JS;
    }

    /** Shared JS: wait until a story or post that was not there before publishing appears (Instagram needs a few seconds). */
    private static function awaitNewJs(string $list): string
    {
        $postCount = $list === 'posts' ? 5 : 0;

        return <<<JS
  const stored = sessionStorage.getItem('hexa-publish-before');
  if (stored === null) return { text: JSON.stringify({ fatal: 'The account was not read before publishing.' }) };
  const known = new Set(JSON.parse(stored));
  for (let attempt = 0; attempt < args.tries; attempt += 1) {
    if (attempt > 0) await sleep(3000);
    const now = await ownMedia({$postCount});
    if (now.fatal) continue;
    const fresh = (now.{$list} || []).filter((item) => !known.has(item.pk)).sort((a, b) => b.taken_at - a.taken_at)[0];
    if (fresh) { sessionStorage.removeItem('hexa-publish-before'); return { text: JSON.stringify({ owner_id: now.owner_id, username: now.username, new: fresh }) }; }
  }
  return { text: JSON.stringify({ fatal: 'No new item appeared on the account.' }) };
JS;
    }

    /** Marks the file input that belongs to "Your story" in the stories tray (the page has several). */
    private static function storyInputJs(): string
    {
        return <<<'JS'
for (const input of document.querySelectorAll('input[type=file]')) {
  let node = input;
  for (let depth = 0; depth < 4 && node; depth += 1) {
    node = node.parentElement;
    if (node && /Your story/.test(node.innerText || '')) { input.setAttribute('data-hexa-story-input', '1'); return { text: 'found' }; }
  }
}
return { text: 'missing' };
JS;
    }
}
