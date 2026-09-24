<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Models\InstagramPublication;
use hexa_package_instagram\Services\InstagramPublisherService;
use Illuminate\Console\Command;

class InstagramUnpublishCommand extends Command
{
    protected $signature = 'instagram:unpublish
        {publication : Publication id (from instagram:publish or instagram:publications)}
        {--json : Print the result as JSON}';

    protected $description = 'Delete a story or feed post that was published through instagram:publish, and confirm it is gone.';

    /**
     * Delete the item from Instagram and mark its record removed.
     */
    public function handle(InstagramPublisherService $publisher): int
    {
        $record = InstagramPublication::query()->find((int) $this->argument('publication'));
        if ($record === null) {
            $this->error('No publication with that id.');

            return self::FAILURE;
        }
        $result = $publisher->unpublish($record);
        $this->line($this->option('json')
            ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $result['outcome'] . ': ' . $result['message'] . ($result['detail'] !== '' && ! $result['success'] ? ' — ' . $result['detail'] : ''));

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
