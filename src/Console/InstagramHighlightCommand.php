<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Models\InstagramPublication;
use hexa_package_instagram\Services\InstagramPublisherService;
use hexa_package_instagram\Services\InstagramScraperService;
use Illuminate\Console\Command;

class InstagramHighlightCommand extends Command
{
    protected $signature = 'instagram:highlight
        {action=show : show (the account\'s Highlights), sync (create or fill one) or delete}
        {--key= : The caller\'s key for this Highlight (sync, delete)}
        {--title= : The Highlight\'s name (sync)}
        {--story-key=* : Keys of the published stories it should hold (sync)}
        {--profile= : Logged-in browser profile}
        {--json : Print the result as JSON}';

    protected $description = 'Show, create or fill a story Highlight from stories published through instagram:publish, or delete one; every change is read back.';

    public function handle(InstagramPublisherService $publisher, InstagramScraperService $instagram): int
    {
        $profile = $this->option('profile') ?: null;
        $result = match ((string) $this->argument('action')) {
            'sync' => $publisher->syncHighlight($profile, (string) $this->option('key'), (string) $this->option('title'), (array) $this->option('story-key')),
            'delete' => $this->delete($publisher, $profile, (string) $this->option('key')),
            'show' => $instagram->highlights($profile) + ['outcome' => 'read'],
            default => ['success' => false, 'outcome' => 'failed', 'message' => 'Action must be show, sync or delete.', 'detail' => '', 'data' => []],
        };
        $this->line($this->option('json')
            ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $result['outcome'] . ': ' . $result['message'] . ((string) ($result['data']['url'] ?? '') !== '' ? ' ' . $result['data']['url'] : ''));

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }

    private function delete(InstagramPublisherService $publisher, ?string $profile, string $key): array
    {
        $record = InstagramPublication::query()->where('kind', 'highlight')->where('source_key', $key)->where('status', 'live')
            ->when($profile !== null, fn ($query) => $query->where('profile', $profile))->latest('id')->first();

        return $record === null
            ? ['success' => false, 'outcome' => 'failed', 'message' => 'No live Highlight under that key.', 'detail' => '', 'data' => []]
            : $publisher->unpublish($record);
    }
}
