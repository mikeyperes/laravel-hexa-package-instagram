<?php

namespace hexa_package_instagram\Services;

use hexa_package_instagram\Models\InstagramPublication;
use hexa_package_instagram\Support\InstagramImageComposer;
use Illuminate\Support\Facades\Http;

/**
 * Publishes stories and feed posts for any caller and remembers them by the caller's key (for example
 * "jpn-event:1979"). Before publishing a key again it checks Instagram: a story still showing or a post
 * still on the account is left alone; an expired story or a deleted post is published again.
 */
class InstagramPublisherService
{
    public const KINDS = ['story', 'post'];

    public function __construct(
        private InstagramScraperService $instagram,
        private InstagramImageComposer $composer,
    ) {
    }

    /**
     * Whether each key is on Instagram now: live, expired, gone (removed outside the package) or none.
     * One read of the account covers every key. Records found to be expired or gone are updated.
     *
     * @param array<int, string> $sourceKeys
     * @return array{success: bool, message: string, detail: string, data: array<string, mixed>}
     */
    public function state(?string $profile, string $kind, array $sourceKeys): array
    {
        $profile = $this->profile($profile);
        $records = [];
        foreach (array_values(array_unique(array_filter($sourceKeys))) as $key) {
            $records[$key] = InstagramPublication::query()->where('profile', $profile)->where('kind', $kind)
                ->where('source_key', $key)->latest('id')->first();
        }
        $open = array_filter($records, fn (?InstagramPublication $record): bool => $record !== null && $record->status === 'live');
        $account = null;
        if ($open !== []) {
            $account = $this->instagram->ownMedia($profile, $kind === 'post' ? 30 : 0);
            if (! $account['success']) {
                return ['success' => false, 'message' => $account['message'], 'detail' => $account['detail'], 'data' => []];
            }
        }

        $keys = [];
        foreach ($records as $key => $record) {
            $keys[$key] = $record === null ? ['state' => 'none'] : $this->describe($record, $account['data'] ?? null);
        }

        return ['success' => true, 'message' => count(array_filter($keys, fn ($row) => $row['state'] === 'live')) . ' of ' . count($keys) . ' already live.', 'detail' => '', 'data' => ['profile' => $profile, 'kind' => $kind, 'keys' => $keys]];
    }

    /**
     * Publish one image (a file path, an https URL or raw bytes) as a story or feed post. With a key and
     * without $force, a key that is already live is skipped (outcome "already_live"); with $updateCaption
     * a live post whose caption differs is edited instead (outcome "edited" or "unchanged").
     *
     * @param array<string, mixed> $meta stored with the record (for example the caller's item title)
     * @return array{success: bool, outcome: string, message: string, detail: string, data: array<string, mixed>}
     */
    public function publish(?string $profile, string $kind, string $image, string $caption = '', ?string $sourceKey = null, bool $force = false, array $meta = [], bool $updateCaption = false): array
    {
        if (! in_array($kind, self::KINDS, true)) {
            return $this->outcome(false, 'failed', 'Kind must be story or post.');
        }
        $profile = $this->profile($profile);
        $sourceKey = $sourceKey !== null && trim($sourceKey) !== '' ? trim($sourceKey) : null;

        if ($sourceKey !== null && ! $force) {
            $state = $this->state($profile, $kind, [$sourceKey]);
            if (! $state['success']) {
                return $this->outcome(false, 'failed', 'Could not check whether it is already live: ' . $state['message'], $state['detail']);
            }
            $current = $state['data']['keys'][$sourceKey];
            if ($current['state'] === 'live') {
                if ($updateCaption && $kind === 'post' && ($record = InstagramPublication::query()->find($current['publication_id'] ?? 0))) {
                    return $this->editCaption($record, $caption);
                }

                return $this->outcome(true, 'already_live', ($kind === 'story' ? 'Story' : 'Post') . ' already live; not posted again.', '', ['source_key' => $sourceKey] + $current);
            }
        }

        try {
            $bytes = $this->imageBytes($image);
            $jpeg = $this->composer->compose($bytes, $kind);
        } catch (\Throwable $exception) {
            return $this->outcome(false, 'failed', 'Image not usable.', $exception->getMessage());
        }

        $result = $kind === 'story'
            ? $this->instagram->postStoryImage($profile, $jpeg)
            : $this->instagram->postFeedImage($profile, $jpeg, $caption);
        if (! $result['success']) {
            return $this->outcome(false, 'failed', $result['message'], $result['detail'], ['source_key' => $sourceKey]);
        }

        $item = (array) $result['data']['item'];
        $record = InstagramPublication::create([
            'profile' => $profile,
            'account' => (string) ($result['data']['username'] ?? ''),
            'kind' => $kind,
            'source_key' => $sourceKey,
            'media_pk' => (string) $item['pk'],
            'media_code' => (string) ($item['code'] ?? '') ?: null,
            'url' => (string) $item['url'],
            'status' => 'live',
            'caption' => $kind === 'post' ? $caption : null,
            'image_sha1' => sha1($jpeg),
            'meta' => ['owner_id' => (string) ($result['data']['owner_id'] ?? '')] + $meta,
            'posted_at' => ! empty($item['taken_at']) ? now()->setTimestamp((int) $item['taken_at']) : now(),
            'expires_at' => $kind === 'story' ? (! empty($item['expiring_at']) ? now()->setTimestamp((int) $item['expiring_at']) : now()->addDay()) : null,
        ]);

        return $this->outcome(true, 'published', $result['message'], $result['detail'], $this->recordData($record));
    }

    /**
     * Replace the caption of a published feed post and keep the record's caption in step.
     *
     * @return array{success: bool, outcome: string, message: string, detail: string, data: array<string, mixed>}
     */
    public function editCaption(InstagramPublication $record, string $caption): array
    {
        if ($record->kind !== 'post' || $record->status !== 'live') {
            return $this->outcome(false, 'failed', 'Only a live feed post can be edited.', '', $this->recordData($record));
        }
        if (trim((string) $record->caption) === trim($caption)) {
            return $this->outcome(true, 'unchanged', 'Caption already up to date.', '', $this->recordData($record));
        }
        $result = $this->instagram->editFeedCaption($record->profile, (string) $record->media_code, $caption);
        if (! $result['success']) {
            return $this->outcome(false, 'failed', $result['message'], $result['detail'], $this->recordData($record));
        }
        $record->forceFill(['caption' => $caption])->save();

        return $this->outcome(true, 'edited', $result['message'], $result['detail'], $this->recordData($record));
    }

    /**
     * Keep one Highlight (the caller's key, for example "jpn-highlight:2026-09-24") named $title and
     * holding the stories published under $storyKeys: created the first time, then only the stories it
     * lacks are added. A Highlight with the same title already on the account (made by hand) is taken
     * over instead of creating a second one. Outcomes: created, updated, unchanged, no_stories, failed.
     *
     * @param array<int, string> $storyKeys
     * @return array{success: bool, outcome: string, message: string, detail: string, data: array<string, mixed>}
     */
    public function syncHighlight(?string $profile, string $key, string $title, array $storyKeys): array
    {
        $profile = $this->profile($profile);
        $key = trim($key);
        $title = trim($title);
        if ($key === '' || $title === '') {
            return $this->outcome(false, 'failed', 'A Highlight key and title are required.');
        }
        // The latest posted story of each key; an expired story stays in the archive and can be highlighted.
        $pks = InstagramPublication::query()->where('profile', $profile)->where('kind', 'story')
            ->whereIn('source_key', array_values(array_unique(array_filter($storyKeys))))
            ->whereIn('status', ['live', 'expired'])->orderBy('id')->get(['source_key', 'media_pk'])
            ->groupBy('source_key')->map(fn ($rows): string => (string) $rows->last()->media_pk)
            ->filter()->values()->all();
        if ($pks === []) {
            return $this->outcome(true, 'no_stories', 'None of these items has a posted story yet; nothing to highlight.', '', ['source_key' => $key]);
        }

        $record = InstagramPublication::query()->where('profile', $profile)->where('kind', 'highlight')
            ->where('source_key', $key)->where('status', 'live')->latest('id')->first();
        $current = null;
        if ($record !== null) {
            $current = $this->instagram->highlights($profile, (string) $record->media_pk);
            if (! $current['success']) {
                return $this->outcome(false, 'failed', $current['message'], $current['detail'], $this->recordData($record));
            }
            if (! ($current['data']['exists'] ?? false)) {
                $record->forceFill(['status' => 'gone'])->save();
                $record = null;
            }
        }
        if ($record === null) {
            $tray = $this->instagram->highlights($profile);
            if (! $tray['success']) {
                return $this->outcome(false, 'failed', $tray['message'], $tray['detail']);
            }
            $same = collect((array) ($tray['data']['highlights'] ?? []))->first(fn (array $row): bool => trim((string) $row['title']) === $title);
            if ($same === null) {
                $made = $this->instagram->createHighlight($profile, $title, $pks);
                if (! $made['success']) {
                    return $this->outcome(false, 'failed', $made['message'], $made['detail'], ['source_key' => $key]);
                }
                $record = $this->highlightRecord($profile, $key, $title, $made['data']);

                return $this->outcome(true, 'created', 'Highlight "' . $title . '" created with ' . count((array) $made['data']['stories']) . ' stories.', $made['detail'], $this->recordData($record) + ['stories' => count((array) $made['data']['stories']), 'added' => count($pks)]);
            }
            $current = $this->instagram->highlights($profile, (string) $same['id']);
            if (! $current['success'] || ! ($current['data']['exists'] ?? false)) {
                return $this->outcome(false, 'failed', 'The existing Highlight "' . $title . '" could not be read.', (string) ($current['detail'] ?? ''), ['source_key' => $key]);
            }
            $record = $this->highlightRecord($profile, $key, $title, $current['data']);
        }

        $add = array_values(array_diff($pks, (array) ($current['data']['stories'] ?? [])));
        if ($add === [] && trim((string) ($current['data']['title'] ?? '')) === $title) {
            return $this->outcome(true, 'unchanged', 'Highlight "' . $title . '" already holds every story.', '', $this->recordData($record) + ['stories' => count((array) $current['data']['stories']), 'added' => 0]);
        }
        $edit = $this->instagram->editHighlight($profile, (string) $record->media_pk, $title, $add);
        if (! $edit['success']) {
            return $this->outcome(false, 'failed', $edit['message'], $edit['detail'], $this->recordData($record));
        }
        $record->forceFill(['caption' => $title, 'meta' => array_merge((array) $record->meta, ['stories' => (array) $edit['data']['stories']])])->save();

        return $this->outcome(true, 'updated', 'Highlight "' . $title . '": ' . count($add) . ' stories added.', $edit['detail'], $this->recordData($record) + ['stories' => count((array) $edit['data']['stories']), 'added' => count($add)]);
    }

    /** @param array<string, mixed> $highlight the read-back Highlight (id, url, stories) */
    private function highlightRecord(string $profile, string $key, string $title, array $highlight): InstagramPublication
    {
        return InstagramPublication::create([
            'profile' => $profile,
            'kind' => 'highlight',
            'source_key' => $key,
            'media_pk' => preg_replace('/^highlight:/', '', (string) $highlight['id']),
            'url' => (string) ($highlight['url'] ?? ''),
            'status' => 'live',
            'caption' => $title,
            'meta' => ['stories' => (array) ($highlight['stories'] ?? [])],
            'posted_at' => now(),
        ]);
    }

    /**
     * Delete a published story or post from Instagram and mark its record removed.
     *
     * @return array{success: bool, outcome: string, message: string, detail: string, data: array<string, mixed>}
     */
    public function unpublish(InstagramPublication $record): array
    {
        if ($record->status !== 'live') {
            return $this->outcome(true, 'not_live', 'Already ' . $record->status . '; nothing to delete.', '', $this->recordData($record));
        }
        $result = match ($record->kind) {
            'story' => $this->instagram->deleteStory($record->profile, (string) $record->account, (string) $record->media_pk),
            'highlight' => $this->instagram->deleteHighlight($record->profile, (string) $record->media_pk),
            default => $this->instagram->deleteFeedPost($record->profile, (string) $record->media_code, (string) $record->media_pk),
        };
        if (! $result['success']) {
            return $this->outcome(false, 'failed', $result['message'], $result['detail'], $this->recordData($record));
        }
        $record->forceFill(['status' => 'removed', 'removed_at' => now()])->save();

        return $this->outcome(true, 'removed', $result['message'], $result['detail'], $this->recordData($record));
    }

    /**
     * Delete the live stories or posts published under these keys (all accounts of the profile).
     *
     * @param array<int, string> $sourceKeys
     * @param callable(array<string, mixed>): void|null $onResult called after each item
     * @return array<int, array<string, mixed>> one outcome per key
     */
    public function unpublishKeys(?string $profile, string $kind, array $sourceKeys, ?callable $onResult = null): array
    {
        $profile = $this->profile($profile);
        $results = [];
        foreach (array_values(array_unique(array_filter($sourceKeys))) as $key) {
            $records = InstagramPublication::query()->where('profile', $profile)->where('kind', $kind)
                ->where('source_key', $key)->where('status', 'live')->orderBy('id')->get();
            $outcome = $records->isEmpty()
                ? $this->outcome(true, 'not_live', 'Nothing live under this key.', '', ['source_key' => $key])
                : null;
            foreach ($records as $record) {
                $outcome = $this->unpublish($record);
                if (! $outcome['success']) {
                    break;
                }
            }
            $results[$key] = $outcome + ['source_key' => $key];
            if ($onResult) {
                $onResult($results[$key]);
            }
        }

        return $results;
    }

    /** The recorded publication for an Instagram post or story link, if it was published through the package. */
    public function findByUrl(string $url): ?InstagramPublication
    {
        $link = $this->instagram->parseInstagramLink($url);
        if ($link === null) {
            return null;
        }

        return InstagramPublication::query()
            ->where(function ($query) use ($link): void {
                if (($link['shortcode'] ?? '') !== '') {
                    $query->orWhere('media_code', $link['shortcode']);
                }
                if (($link['pk'] ?? '') !== '') {
                    $query->orWhere('media_pk', $link['pk']);
                }
            })
            ->latest('id')
            ->first();
    }

    /** Seconds to pause between two items of one batch (random within the configured range). */
    public function gapSeconds(): int
    {
        [$min, $max] = array_values((array) config('instagram.publishing.gap_seconds', [20, 45])) + [20, 45];

        return random_int((int) $min, max((int) $min, (int) $max));
    }

    /** @return array<string, mixed> */
    public function recordData(InstagramPublication $record): array
    {
        return [
            'publication_id' => $record->id,
            'kind' => $record->kind,
            'source_key' => $record->source_key,
            'account' => $record->account,
            'status' => $record->status,
            'url' => $record->url,
            'media_pk' => $record->media_pk,
            'posted_at' => $record->posted_at?->toIso8601String(),
            'expires_at' => $record->expires_at?->toIso8601String(),
        ];
    }

    /** @param array<string, mixed>|null $account the ownMedia() data, or null when no live record needed checking */
    private function describe(InstagramPublication $record, ?array $account): array
    {
        if ($record->status === 'live' && $account !== null) {
            $list = $record->kind === 'story' ? (array) ($account['stories'] ?? []) : (array) ($account['posts'] ?? []);
            $onAccount = collect($list)->contains(fn ($item): bool => (string) ($item['pk'] ?? '') === (string) $record->media_pk);
            if (! $onAccount) {
                $expired = $record->kind === 'story' && $record->expires_at !== null && $record->expires_at->isPast();
                if ($record->kind === 'post' && count($list) >= 30) {
                    // Older than the 30 posts read; still counted as live.
                    return ['state' => 'live'] + $this->recordData($record);
                }
                $record->forceFill(['status' => $expired ? 'expired' : 'gone'])->save();
            }
        }

        return ['state' => $record->status === 'live' ? 'live' : $record->status] + $this->recordData($record);
    }

    private function imageBytes(string $image): string
    {
        if (preg_match('#^https?://#i', $image)) {
            $response = Http::timeout(45)->get($image);
            if (! $response->successful()) {
                throw new \RuntimeException('Image download failed with status ' . $response->status() . '.');
            }

            return (string) $response->body();
        }
        if (strlen($image) < 4096 && is_file($image)) {
            return (string) file_get_contents($image);
        }

        return $image;
    }

    private function profile(?string $profile): string
    {
        return app(\hexa_package_instagram\Domains\Config\InstagramConfigRepository::class)->resolveProfile($profile);
    }

    /** @return array{success: bool, outcome: string, message: string, detail: string, data: array<string, mixed>} */
    private function outcome(bool $success, string $outcome, string $message, string $detail = '', array $data = []): array
    {
        return ['success' => $success, 'outcome' => $outcome, 'message' => $message, 'detail' => $detail, 'data' => $data];
    }
}
