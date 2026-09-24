<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Services\InstagramScraperService;
use Illuminate\Console\Command;

class InstagramFollowCommand extends Command
{
    protected $signature = 'instagram:follow
        {usernames* : Accounts to follow (@name or name)}
        {--profile= : Logged-in browser profile (the Instagram account that follows)}
        {--json : Print the result as JSON}';

    protected $description = 'Follow Instagram accounts from the account logged in to a browser profile, by clicking Follow on each profile.';

    /**
     * Follow the accounts and print one line per account, or JSON.
     */
    public function handle(InstagramScraperService $instagram): int
    {
        $result = $instagram->followAccounts($this->option('profile') ?: null, (array) $this->argument('usernames'));
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line($result['message']);
            foreach ((array) ($result['data']['accounts'] ?? []) as $username => $row) {
                $this->line('@'.$username.': '.($row['state'] ?? 'failed').(! empty($row['already']) ? ' (already)' : '').(($row['message'] ?? '') !== '' ? ' — '.$row['message'] : ''));
            }
        }

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
