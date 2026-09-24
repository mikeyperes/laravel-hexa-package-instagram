<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Services\InstagramPublisherService;
use Illuminate\Console\Command;

class InstagramPublishCommand extends Command
{
    protected $signature = 'instagram:publish
        {kind : story or post}
        {image : Image file path or https URL (fitted whole, never cropped)}
        {--caption= : Post caption}
        {--caption-file= : Read the post caption from this file}
        {--key= : Your id for this item (e.g. jpn-event:1979); an item already live under this key is not posted again}
        {--force : Post even if the key is already live}
        {--profile= : Logged-in browser profile (the Instagram account that posts)}
        {--json : Print the result as JSON}';

    protected $description = 'Post one image as a story or feed post from the account logged in to a browser profile, skipping it when already live.';

    /**
     * Publish and print the outcome (published, already_live or failed).
     */
    public function handle(InstagramPublisherService $publisher): int
    {
        $caption = (string) $this->option('caption');
        if ($this->option('caption-file')) {
            $caption = (string) @file_get_contents((string) $this->option('caption-file'));
        }
        $result = $publisher->publish($this->option('profile') ?: null, (string) $this->argument('kind'), (string) $this->argument('image'), $caption, $this->option('key') ?: null, (bool) $this->option('force'));
        $this->line($this->option('json')
            ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $result['outcome'] . ': ' . $result['message'] . (($result['data']['url'] ?? '') !== '' ? ' ' . $result['data']['url'] : '') . ($result['detail'] !== '' && ! $result['success'] ? ' — ' . $result['detail'] : ''));

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
