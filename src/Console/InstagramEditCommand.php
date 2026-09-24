<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Models\InstagramPublication;
use hexa_package_instagram\Services\InstagramPublisherService;
use Illuminate\Console\Command;

class InstagramEditCommand extends Command
{
    protected $signature = 'instagram:edit
        {publication : Publication id of a live feed post}
        {--caption= : New caption}
        {--caption-file= : Read the new caption from this file}
        {--json : Print the result as JSON}';

    protected $description = 'Replace the caption of a feed post published through instagram:publish, and confirm it on Instagram.';

    /**
     * Edit the caption and print the outcome (edited, unchanged or failed).
     */
    public function handle(InstagramPublisherService $publisher): int
    {
        $record = InstagramPublication::query()->find((int) $this->argument('publication'));
        if ($record === null) {
            $this->error('No publication with that id.');

            return self::FAILURE;
        }
        $caption = $this->option('caption-file') ? (string) @file_get_contents((string) $this->option('caption-file')) : (string) $this->option('caption');
        $result = $publisher->editCaption($record, $caption);
        $this->line($this->option('json')
            ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $result['outcome'] . ': ' . $result['message'] . ($result['detail'] !== '' && ! $result['success'] ? ' — ' . $result['detail'] : ''));

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
