<?php

namespace hexa_package_instagram\Services\Concerns;

/**
 * Creates, fills, reads and deletes story Highlights on the Instagram account logged in to a browser
 * profile, through the same web calls Instagram's own site makes from that logged-in page. Every change
 * is read back from the Highlight itself.
 */
trait ManagesInstagramHighlights
{
    /**
     * The account's Highlights (id, title, number of stories) and, for $highlightId, its story ids.
     *
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function highlights(?string $profile, string $highlightId = ''): array
    {
        return $this->runHighlightStep($profile, 'read', ['highlight' => $this->highlightReelId($highlightId)], 'Highlights read.');
    }

    /**
     * Create a Highlight named $title from the account's stories (their numeric ids), cover = the first.
     *
     * @param array<int, string> $storyPks
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function createHighlight(?string $profile, string $title, array $storyPks): array
    {
        $storyPks = $this->storyPkList($storyPks);
        if ($storyPks === [] || trim($title) === '') {
            return $this->failure('A title and at least one story are required.', 'Pass the stories\' ids as recorded when they were posted.');
        }

        return $this->runHighlightStep($profile, 'create', ['title' => $title, 'add' => $storyPks], 'Highlight created and confirmed.');
    }

    /**
     * Add and remove stories on an existing Highlight, set its title and, optionally, its cover story.
     *
     * @param array<int, string> $addPks
     * @param array<int, string> $removePks
     * @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>}
     */
    public function editHighlight(?string $profile, string $highlightId, string $title, array $addPks, array $removePks = [], string $coverPk = ''): array
    {
        return $this->runHighlightStep($profile, 'edit', [
            'highlight' => $this->highlightReelId($highlightId),
            'title' => $title,
            'add' => $this->storyPkList($addPks),
            'remove' => $this->storyPkList($removePks),
            'cover' => ctype_digit($coverPk) ? $coverPk : '',
        ], 'Highlight updated and confirmed.');
    }

    /** @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>} */
    public function deleteHighlight(?string $profile, string $highlightId): array
    {
        return $this->runHighlightStep($profile, 'delete', ['highlight' => $this->highlightReelId($highlightId)], 'Highlight deleted; no longer on the account.');
    }

    /** @return array{success: bool, message: string, detail: string, status_code: int, data: array<string, mixed>} */
    private function runHighlightStep(?string $profile, string $action, array $args, string $message): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $result = $this->browser->runAutomation($resolved, [
            ['type' => 'goto', 'label' => 'open_home', 'url' => 'https://www.instagram.com/', 'wait_until' => 'domcontentloaded', 'timeout_ms' => 30000, 'wait_ms' => 2000],
            ['type' => 'evaluate', 'label' => 'highlight', 'code' => self::restJs(self::highlightJs()), 'args' => ['action' => $action] + $args],
        ], ['transport_timeout_ms' => 90000]);
        $data = json_decode((string) ($this->resultByLabel($result, 'highlight')['text'] ?? ''), true);
        if ($this->isLoginRedirect($result, []) || ! is_array($data) || isset($data['fatal'])) {
            return $this->feedFailure($result, $resolved, is_array($data) ? (string) ($data['fatal'] ?? '') : '', 'The Highlight could not be ' . ($action === 'read' ? 'read' : 'changed') . '.');
        }
        if (($data['ok'] ?? false) !== true) {
            return ['success' => false, 'message' => (string) ($data['error'] ?? 'Instagram did not confirm the Highlight change.'), 'detail' => (string) ($data['detail'] ?? ''), 'status_code' => (int) ($data['status'] ?? 0), 'data' => ['profile' => $resolved] + $data];
        }
        if (isset($data['id'])) {
            $data['url'] = 'https://www.instagram.com/stories/highlights/' . preg_replace('/^highlight:/', '', (string) $data['id']) . '/';
        }

        return ['success' => true, 'message' => $message, 'detail' => 'Read back from the Highlight on the account.', 'status_code' => (int) ($result['status_code'] ?? 0), 'data' => ['profile' => $resolved] + $data];
    }

    private function highlightReelId(string $id): string
    {
        $id = trim($id);

        return $id === '' ? '' : (str_starts_with($id, 'highlight:') ? $id : 'highlight:' . preg_replace('/\D/', '', $id));
    }

    /** @param array<int, mixed> $pks @return array<int, string> */
    private function storyPkList(array $pks): array
    {
        return array_values(array_unique(array_filter(array_map(static fn ($pk): string => ctype_digit((string) $pk) ? (string) $pk : '', $pks))));
    }

    /**
     * In-page script: read (tray + one Highlight's stories), create, edit or delete, then read the
     * Highlight back. Story ids are sent as Instagram's full media ids (<story id>_<account id>).
     */
    private static function highlightJs(): string
    {
        return <<<'JS'
  const owner = (document.cookie.match(/(?:^|; )ds_user_id=(\d+)/) || [])[1] || '';
  if (!owner) return { text: JSON.stringify({ fatal: 'No Instagram account is logged in.' }) };
  const media = (pks) => JSON.stringify((pks || []).map((pk) => pk + '_' + owner));
  const post = async (path, fields) => {
    const response = await fetch(path, { method: 'POST', credentials: 'include', body: new URLSearchParams(fields), headers: { ...headers, 'Content-Type': 'application/x-www-form-urlencoded' } });
    let json = null; try { json = JSON.parse(await response.text()); } catch (error) { json = null; }
    return { status: response.status, json };
  };
  const readOne = async (id) => {
    const { status, json } = await getJson('/api/v1/feed/reels_media/?reel_ids=' + encodeURIComponent(id));
    const reel = json?.reels?.[id];
    return { status, exists: !!reel, title: reel?.title || '', cover: String(reel?.cover_media?.media_id || '').split('_')[0],
      stories: (reel?.items || []).map((item) => String(item.pk).split('_')[0]) };
  };
  const tray = async () => {
    const { status, json } = await getJson('/api/v1/highlights/' + owner + '/highlights_tray/');
    return { status, list: (json?.tray || []).map((item) => ({ id: item.id, title: item.title || '', count: item.media_count || 0 })) };
  };
  const crop = [0.0, 0.21830457, 1.0, 0.78094524];
  let out = {};
  if (args.action === 'read') {
    const all = await tray();
    out = { ok: all.status === 200, status: all.status, highlights: all.list };
    if (args.highlight) Object.assign(out, { id: args.highlight }, await readOne(args.highlight));
  } else if (args.action === 'create') {
    const cover = JSON.stringify({ media_id: args.add[0] + '_' + owner, crop_rect: JSON.stringify(crop) });
    const made = await post('/api/v1/highlights/create_reel/', { source: 'self_profile', creation_id: String(Math.floor(Date.now() / 1000)), title: args.title, media_ids: media(args.add), cover });
    const id = made.json?.reel?.id || '';
    if (!id) return { text: JSON.stringify({ ok: false, status: made.status, error: 'Instagram did not create the Highlight.', detail: JSON.stringify(made.json || {}).slice(0, 300) }) };
    const back = await readOne(id);
    out = { ok: back.exists, status: made.status, id, ...back };
  } else if (args.action === 'edit') {
    const fields = { source: 'story_viewer', added_media_ids: media(args.add), removed_media_ids: media(args.remove) };
    if (args.title) fields.title = args.title;
    if (args.cover) fields.cover = JSON.stringify({ media_id: args.cover + '_' + owner, crop_rect: JSON.stringify(crop) });
    const changed = await post('/api/v1/highlights/' + args.highlight + '/edit_reel/', fields);
    if (!changed.json?.reel) return { text: JSON.stringify({ ok: false, status: changed.status, error: 'Instagram did not update the Highlight.', detail: JSON.stringify(changed.json || {}).slice(0, 300) }) };
    const back = await readOne(args.highlight);
    const missing = (args.add || []).filter((pk) => !back.stories.includes(pk));
    const kept = (args.remove || []).filter((pk) => back.stories.includes(pk));
    out = { ok: back.exists && missing.length === 0 && kept.length === 0, status: changed.status, id: args.highlight, missing, kept,
      error: missing.length || kept.length ? 'The Highlight did not change as asked.' : '', ...back };
  } else if (args.action === 'delete') {
    const gone = await post('/api/v1/highlights/' + args.highlight + '/delete_reel/', {});
    const back = await readOne(args.highlight);
    out = { ok: !back.exists, status: gone.status, id: args.highlight, error: back.exists ? 'The Highlight is still on the account.' : '' };
  }
  return { text: JSON.stringify(out) };
JS;
    }
}
