<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Services\InstagramFollowAuditService;
use Illuminate\Console\Command;

class InstagramFollowAuditCommand extends Command
{
    protected $signature = 'instagram:follow-audit
        {account : Account whose network is checked (@name or name)}
        {--profile= : Logged-in browser profile to read with}
        {--source=following : following (accounts it follows) or stories (accounts tagged in its stories and Highlights)}
        {--criteria= : JSON object of key => yes/no question; a match needs every answer yes}
        {--context= : One sentence of background for the AI check}
        {--limit=60 : Most accounts to check in this run}
        {--posts=5 : Latest posts read per account}
        {--exclude= : Comma-separated usernames to skip}
        {--json : Print the full result as JSON}';

    protected $description = 'Check the accounts another Instagram account follows (or tags in stories) against yes/no criteria. Read-only.';

    /**
     * Run the audit and print a short report, or JSON.
     */
    public function handle(InstagramFollowAuditService $audits): int
    {
        $criteria = json_decode((string) $this->option('criteria'), true);
        if (! is_array($criteria) || $criteria === []) {
            $this->error('--criteria must be a JSON object, e.g. {"events":"Does it post events?"}');

            return self::INVALID;
        }
        $result = $audits->audit($this->option('profile') ?: null, (string) $this->argument('account'), $criteria, [
            'source' => (string) $this->option('source'),
            'limit' => (int) $this->option('limit'),
            'posts_per_account' => (int) $this->option('posts'),
            'exclude_usernames' => array_filter(array_map('trim', explode(',', (string) $this->option('exclude')))),
            'context' => (string) $this->option('context'),
        ]);
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }
        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }
        $data = $result['data'];
        $this->line('@' . $data['account'] . ' · ' . $data['source'] . ' · ' . $data['found'] . ' found · ' . $data['excluded'] . ' skipped · ' . $data['checked'] . ' checked · ' . count($data['matches']) . ' match' . ($data['remaining'] > 0 ? ' · ' . $data['remaining'] . ' left for the next run' : ''));
        foreach ($data['matches'] as $index => $row) {
            $this->line(($index + 1) . '. @' . $row['username'] . ($row['full_name'] !== '' ? ' — ' . $row['full_name'] : '') . ' (' . $row['score'] . ') ' . $row['reason']);
        }

        return self::SUCCESS;
    }
}
