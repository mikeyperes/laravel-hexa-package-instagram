<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Services\InstagramScraperService;
use Illuminate\Console\Command;

class InstagramPostCommand extends Command
{
    protected $signature = 'instagram:post
        {post : Post or reel link, or its short id (the part after /p/ or /reel/)}
        {--profile= : Logged-in browser profile to read with (logged out: defaults to the public reader)}
        {--logged-out : Read the public embed instead, without any account (comments are partial or none)}
        {--comments=20 : Comments to read when logged in (0-50)}
        {--json : Print the full result as JSON}';

    protected $description = 'Read one Instagram post or reel by its link or short id: every slide, the caption and the comments, logged in or logged out. Read-only.';

    public function handle(InstagramScraperService $instagram): int
    {
        $result = $instagram->readPost((string) $this->argument('post'), $this->option('profile'), (bool) $this->option('logged-out'), (int) $this->option('comments'));
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }
        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }
        $post = $result['post'];
        $this->line($post['url'].' · '.$result['profile'].' · '.$result['message']);
        $this->line($post['taken_at'].' — '.mb_substr(str_replace("\n", ' ', $post['caption']), 0, 200));
        foreach ($post['slides'] as $slide) {
            $this->line('  slide '.$slide['index'].($slide['is_video'] ? ' (video)' : '').': '.$slide['image_url']);
        }
        foreach ($post['comments'] as $comment) {
            $this->line('  @'.$comment['owner'].': '.mb_substr(str_replace("\n", ' ', $comment['text']), 0, 140));
        }
        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
