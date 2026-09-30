<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Services\InstagramScraperService;
use Illuminate\Console\Command;

class InstagramStoriesCommand extends Command
{
    protected $signature = 'instagram:stories
        {account : Instagram username (@name, name or profile link)}
        {--profile= : Logged-in browser profile to read with (required)}
        {--owner-lookups=10 : Most reshared posts whose owner is looked up when the share sticker does not name it}
        {--json : Print the full result as JSON}';

    protected $description = 'Read one Instagram account\'s current stories through one logged-in browser profile. Read-only, no fallback; stops with the reason when the profile is paused, not activated, not logged in or has no protected route.';

    public function handle(InstagramScraperService $instagram): int
    {
        $profile = trim((string) $this->option('profile'));
        if ($profile === '') {
            $this->error('--profile is required: name the logged-in browser profile to read with.');

            return self::INVALID;
        }
        $result = $instagram->accountStories($profile, (string) $this->argument('account'), (int) $this->option('owner-lookups'));
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }
        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }
        $this->line('@'.$result['account'].' · '.$result['profile'].' · '.$result['message']);
        foreach ($result['stories'] as $story) {
            $shared = $story['reshared_post'];
            $this->line($story['taken_at'].' '.$story['url'].($shared ? ' (reshares '.$shared['url'].($shared['owner'] !== '' ? ' by @'.$shared['owner'] : '').')' : ''));
        }
        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
