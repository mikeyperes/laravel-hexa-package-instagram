<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Models\InstagramPublication;
use hexa_package_instagram\Services\InstagramPublisherService;
use Illuminate\Console\Command;

class InstagramPublicationsCommand extends Command
{
    protected $signature = 'instagram:publications
        {--kind=story : story or post}
        {--key=* : Only these keys; with keys, Instagram is checked for which are still live}
        {--profile= : Logged-in browser profile}
        {--limit=20 : Latest records to list when no key is given}
        {--json : Print the result as JSON}';

    protected $description = 'List published stories or posts, or check which keys are live on Instagram now.';

    /**
     * Print records, or the live check for the given keys.
     */
    public function handle(InstagramPublisherService $publisher): int
    {
        $kind = (string) $this->option('kind');
        $keys = array_values(array_filter((array) $this->option('key')));
        if ($keys !== []) {
            $result = $publisher->state($this->option('profile') ?: null, $kind, $keys);
            $rows = $result['data']['keys'] ?? [];
        } else {
            $query = InstagramPublication::query()->where('kind', $kind)->latest('id')->limit(max(1, (int) $this->option('limit')));
            if ($this->option('profile')) {
                $query->where('profile', (string) $this->option('profile'));
            }
            $result = ['success' => true, 'message' => ''];
            $rows = $query->get()->map(fn (InstagramPublication $record): array => $publisher->recordData($record))->all();
        }
        if ($this->option('json')) {
            $this->line(json_encode($result + ['rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            if (! $result['success']) {
                $this->error($result['message']);
            }
            foreach ($rows as $key => $row) {
                $this->line((is_string($key) ? $key . ': ' : '#' . ($row['publication_id'] ?? '') . ' ') . ($row['state'] ?? $row['status'] ?? '') . ' ' . ($row['source_key'] ?? '') . ' ' . ($row['url'] ?? ''));
            }
        }

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
