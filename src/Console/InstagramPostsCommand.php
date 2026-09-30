<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Services\InstagramScraperService;
use Illuminate\Console\Command;

class InstagramPostsCommand extends Command
{
    protected $signature = 'instagram:posts
        {account : Instagram username (@name, name or profile link)}
        {--profile= : Logged-in browser profile to read with (required)}
        {--limit=5 : Newest posts to read (1-12)}
        {--allow-public-fallback : When the logged-in read of this account fails, read its public profile embed instead}
        {--json : Print the full result as JSON}';

    protected $description = 'Read one Instagram account\'s newest posts through one logged-in browser profile. Read-only; stops with the reason when the profile is paused, not activated, not logged in or has no protected route.';

    public function handle(InstagramScraperService $instagram): int
    {
        $profile = trim((string) $this->option('profile'));
        if ($profile === '') {
            $this->error('--profile is required: name the logged-in browser profile to read with.');

            return self::INVALID;
        }
        $result = $instagram->accountPosts($profile, (string) $this->argument('account'), (int) $this->option('limit'), (bool) $this->option('allow-public-fallback'));
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }
        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }
        $this->line('@'.$result['account'].' · '.$result['profile'].' · '.$result['posts_source'].' · '.$result['message']);
        foreach ($result['posts'] as $post) {
            $this->line($post['taken_at'].' '.$post['url'].($post['video'] ? ' (video)' : '').' — '.mb_substr(str_replace("\n", ' ', $post['caption']), 0, 100));
        }
        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
