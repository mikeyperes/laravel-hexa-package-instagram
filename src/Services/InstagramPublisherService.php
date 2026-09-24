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
     * without $force, a key that is already live is skipped (outcome "already_live").
     *
     * @param array<string, mixed> $meta stored with the record (for example the caller's item title)
     * @return array{success: bool, outcome: string, message: string, detail: string, data: array<string, mixed>}
     */
    public function publish(?string $profile, string $kind, string $image, string $caption = '', ?string $sourceKey = null, bool $force = false, array $meta = []): array
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
     * Delete a published story or post from Instagram and mark its record removed.
     *
     * @return array{success: bool, outcome: string, message: string, detail: string, data: array<string, mixed>}
     */
    public function unpublish(InstagramPublication $record): array
    {
        if ($record->status !== 'live') {
            return $this->outcome(true, 'not_live', 'Already ' . $record->status . '; nothing to delete.', '', $this->recordData($record));
        }
        $result = $record->kind === 'story'
            ? $this->instagram->deleteStory($record->profile, (string) $record->account, (string) $record->media_pk)
            : $this->instagram->deleteFeedPost($record->profile, (string) $record->media_code, (string) $record->media_pk);
        if (! $result['success']) {
            return $this->outcome(false, 'failed', $result['message'], $result['detail'], $this->recordData($record));
        }
        $record->forceFill(['status' => 'removed', 'removed_at' => now()])->save();

        return $this->outcome(true, 'removed', $result['message'], $result['detail'], $this->recordData($record));
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
