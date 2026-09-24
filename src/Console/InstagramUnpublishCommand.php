<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Models\InstagramPublication;
use hexa_package_instagram\Services\InstagramPublisherService;
use Illuminate\Console\Command;

class InstagramUnpublishCommand extends Command
{
    protected $signature = 'instagram:unpublish
        {publication? : Publication id, or the post/story link}
        {--key=* : Instead: delete what is live under these keys}
        {--kind=post : story or post (with --key)}
        {--profile= : Logged-in browser profile (with --key)}
        {--json : Print the result as JSON}';

    protected $description = 'Delete stories or feed posts published through instagram:publish (by id, link or key), and confirm each is gone.';

    /**
     * Delete the item from Instagram and mark its record removed.
     */
    public function handle(InstagramPublisherService $publisher): int
    {
        $keys = array_values(array_filter((array) $this->option('key')));
        if ($keys !== []) {
            $results = $publisher->unpublishKeys($this->option('profile') ?: null, (string) $this->option('kind'), $keys, function (array $result): void {
                if (! $this->option('json')) {
                    $this->line($result['source_key'] . ': ' . $result['outcome'] . ' — ' . $result['message']);
                }
            });
            if ($this->option('json')) {
                $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }

            return collect($results)->every(fn (array $result): bool => $result['success']) ? self::SUCCESS : self::FAILURE;
        }
        $target = (string) $this->argument('publication');
        $record = ctype_digit($target) ? InstagramPublication::query()->find((int) $target) : $publisher->findByUrl($target);
        if ($record === null) {
            $this->error('No publication with that id or link.');

            return self::FAILURE;
        }
        $result = $publisher->unpublish($record);
        $this->line($this->option('json')
            ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $result['outcome'] . ': ' . $result['message'] . ($result['detail'] !== '' && ! $result['success'] ? ' — ' . $result['detail'] : ''));

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
