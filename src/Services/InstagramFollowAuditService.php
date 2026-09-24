<?php

namespace hexa_package_instagram\Services;

use hexa_core\AI\Services\AiChatGateway;

/**
 * Finds accounts through another account (the accounts it follows, or the accounts tagged and
 * mentioned in its stories and Highlights), reads each candidate's latest posts in the
 * logged-in browser, and has an AI model answer the caller's yes/no criteria for each one.
 * It never follows, messages or adds anything; callers decide what to do with the matches.
 */
class InstagramFollowAuditService
{
    public function __construct(
        private InstagramScraperService $instagram,
        private AiChatGateway $ai,
    ) {
    }

    /**
     * @param array<string, string> $criteria key => yes/no question; an account matches when every answer is yes
     * `on_start(array $info)` is called once candidates are known and `on_result(array $row, int $n, int $total)`
     * after each account is checked, so callers can show progress live.
     *
     * @param array{source?: string, limit?: int, posts_per_account?: int, max_following?: int, exclude_usernames?: array<int, string>, exclude_ids?: array<int, string>, model?: string, context?: string, on_start?: callable, on_result?: callable} $options
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function audit(?string $profile, string $account, array $criteria, array $options = []): array
    {
        $account = strtolower(ltrim(trim($account), '@'));
        $criteria = array_filter(array_map('trim', $criteria), static fn (string $question): bool => $question !== '');
        if ($account === '' || $criteria === []) {
            return ['success' => false, 'message' => 'An account and at least one criterion are required.', 'data' => []];
        }
        $source = ($options['source'] ?? 'following') === 'stories' ? 'stories' : 'following';
        $limit = max(1, min((int) ($options['limit'] ?? config('instagram.follow_audit.limit', 60)), 300));

        $found = $source === 'stories'
            ? $this->storyCandidates($profile, $account)
            : $this->followingCandidates($profile, $account, (int) ($options['max_following'] ?? 500));
        if (! $found['success']) {
            return ['success' => false, 'message' => $found['message'], 'data' => ['account' => $account, 'source' => $source]];
        }

        $excludeNames = array_flip(array_map(static fn ($name): string => strtolower(ltrim((string) $name, '@')), (array) ($options['exclude_usernames'] ?? [])));
        $excludeIds = array_flip(array_map('strval', (array) ($options['exclude_ids'] ?? [])));
        $excludeNames[$account] = true;
        $candidates = [];
        $excludedNames = [];
        foreach ($found['candidates'] as $candidate) {
            if (isset($excludeNames[$candidate['username']]) || ($candidate['user_id'] !== '' && isset($excludeIds[$candidate['user_id']]))) {
                $excludedNames[] = $candidate['username'];
                continue;
            }
            $candidates[] = $candidate;
        }
        $remaining = max(0, count($candidates) - $limit);
        $candidates = array_slice($candidates, 0, $limit);

        // Private accounts are tried too: the session can read those whose follow request it has.
        $public = $candidates;
        if (is_callable($options['on_start'] ?? null)) {
            ($options['on_start'])([
                'account' => $account,
                'source' => $source,
                'found' => count($found['candidates']),
                'all_usernames' => array_column($found['candidates'], 'username'),
                'excluded_usernames' => $excludedNames,
                'to_check' => count($public),
                'private' => count(array_filter($candidates, static fn (array $candidate): bool => $candidate['is_private'])),
                'remaining' => $remaining,
            ]);
        }
        $screened = $this->screen($profile, $public, $criteria, $options);

        $matches = array_values(array_filter($screened, static fn (array $row): bool => $row['status'] === 'match'));
        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return [
            'success' => true,
            'message' => count($matches) . ' of ' . count($candidates) . ' accounts match.',
            'data' => [
                'account' => $account,
                'source' => $source,
                'found' => count($found['candidates']),
                'excluded' => count($excludedNames),
                'excluded_usernames' => $excludedNames,
                'checked' => count($candidates),
                'remaining' => $remaining,
                'matches' => $matches,
                'screened_out' => array_values(array_filter($screened, static fn (array $row): bool => $row['status'] === 'no_match')),
                'unreadable' => array_values(array_filter($screened, static fn (array $row): bool => $row['status'] === 'unreadable')),
                'private' => array_values(array_filter($screened, static fn (array $row): bool => $row['status'] === 'private')),
                'model' => $this->model($options),
            ],
        ];
    }

    /** @return array{success: bool, message: string, candidates: array<int, array<string, mixed>>} */
    private function followingCandidates(?string $profile, string $account, int $max): array
    {
        $result = $this->instagram->followingFeed($profile, $account, $max);
        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'] . ' ' . $result['detail'], 'candidates' => []];
        }

        return ['success' => true, 'message' => $result['message'], 'candidates' => array_map(static fn (array $user): array => [
            'username' => strtolower((string) $user['username']),
            'full_name' => (string) ($user['full_name'] ?? ''),
            'user_id' => (string) ($user['user_id'] ?? ''),
            'is_private' => (bool) ($user['is_private'] ?? false),
            'found_via' => [$account],
        ], (array) $result['data']['users'])];
    }

    /**
     * Accounts tagged, mentioned or reposted in the account's current stories and Highlights, most
     * frequent first.
     *
     * @return array{success: bool, message: string, candidates: array<int, array<string, mixed>>}
     */
    private function storyCandidates(?string $profile, string $account): array
    {
        $info = $this->instagram->accountProfiles($profile, [$account]);
        $userId = (string) ($info['data']['accounts'][$account]['user_id'] ?? '');
        if ($userId === '') {
            return ['success' => false, 'message' => '@' . $account . '\'s profile could not be read. ' . (string) ($info['data']['accounts'][$account]['message'] ?? ''), 'candidates' => []];
        }
        $items = [];
        $stories = $this->instagram->storyFeeds($profile, [$userId => $account]);
        foreach ((array) ($stories['data']['reels'] ?? []) as $reel) {
            array_push($items, ...(array) ($reel['stories'] ?? []));
        }
        $highlights = $this->instagram->highlightFeeds($profile, $userId, (int) config('instagram.follow_audit.max_highlights', 15));
        foreach ((array) ($highlights['data']['highlights'] ?? []) as $highlight) {
            array_push($items, ...(array) ($highlight['items'] ?? []));
        }

        $counts = [];
        foreach ($items as $item) {
            $names = array_merge((array) ($item['mentions'] ?? []), (array) ($item['tagged'] ?? []), (array) ($item['coauthors'] ?? []));
            foreach (array_unique(array_map('strtolower', $names)) as $name) {
                if ($name !== '' && $name !== $account) {
                    $counts[$name] = ($counts[$name] ?? 0) + 1;
                }
            }
        }
        arsort($counts);

        return ['success' => true, 'message' => count($items) . ' stories and highlight items read.', 'candidates' => array_map(static fn (string $name, int $count): array => [
            'username' => $name,
            'full_name' => '',
            'user_id' => '',
            'is_private' => false,
            'found_via' => [$account],
            'mentions' => $count,
        ], array_keys($counts), array_values($counts))];
    }

    /**
     * Latest posts for each candidate (with Instagram's own image descriptions), then one AI answer per candidate.
     *
     * @param array<int, array<string, mixed>> $candidates
     * @param array<string, string> $criteria
     * @return array<int, array<string, mixed>>
     */
    private function screen(?string $profile, array $candidates, array $criteria, array $options): array
    {
        $postsPer = max(1, min((int) ($options['posts_per_account'] ?? 5), 12));
        $rows = [];
        $total = count($candidates);
        $report = static function (array $row) use (&$rows, $options, $total): void {
            $rows[] = $row;
            if (is_callable($options['on_result'] ?? null)) {
                ($options['on_result'])($row, count($rows), $total);
            }
        };
        foreach (array_chunk($candidates, (int) config('instagram.follow_audit.batch_size', 8)) as $batch) {
            $names = array_column($batch, 'username');
            $feeds = $this->instagram->profileFeeds($profile, $names, $postsPer)['data']['accounts'] ?? [];
            foreach ($batch as $candidate) {
                $name = $candidate['username'];
                $feed = (array) ($feeds[$name] ?? []);
                if (! ($feed['success'] ?? false) || ($candidate['is_private'] && ($feed['posts'] ?? []) === [])) {
                    $report($candidate + [
                        'status' => $candidate['is_private'] ? 'private' : 'unreadable',
                        'score' => 0,
                        'answers' => [],
                        'reason' => $candidate['is_private'] ? 'Private account; its posts are not visible to this session.' : (string) ($feed['message'] ?? 'Not readable.'),
                    ]);
                    continue;
                }
                $posts = array_slice(array_values((array) ($feed['posts'] ?? [])), 0, $postsPer);
                $candidate['user_id'] = $candidate['user_id'] ?: (string) ($feed['user_id'] ?? '');
                if ($candidate['full_name'] === '') {
                    $candidate['full_name'] = (string) (preg_match('/^(.*) on Instagram/u', (string) (($posts[0] ?? [])['title'] ?? ''), $m) ? $m[1] : '');
                }
                // Instagram's bio endpoint is rate limited for this use, so the evidence is the name and latest posts.
                $evidence = [
                    'posts' => array_map(static fn (array $post): array => [
                        'url' => (string) ($post['url'] ?? ''),
                        'posted_at' => (string) ($post['posted_at'] ?? ''),
                        'caption' => mb_substr((string) (($post['caption_blocks'] ?? [])[0] ?? ''), 0, 600),
                        'image_text' => mb_substr((string) ($post['accessibility_caption'] ?? ''), 0, 300),
                        'location' => (string) ($post['location'] ?? ''),
                    ], $posts),
                ];
                $report($candidate + $this->judge($candidate, $evidence, $criteria, $options) + ['evidence' => $evidence]);
            }
        }

        return $rows;
    }

    /**
     * @param array<string, string> $criteria
     * @return array{status: string, score: int, answers: array<string, bool>, reason: string}
     */
    private function judge(array $candidate, array $evidence, array $criteria, array $options): array
    {
        $questions = implode("\n", array_map(static fn (string $key, string $question): string => '- ' . $key . ': ' . $question, array_keys($criteria), $criteria));
        $system = 'You screen Instagram accounts for a human reviewer. Answer each yes/no question from the evidence only; when the evidence does not support yes, answer no. '
            . trim((string) ($options['context'] ?? ''))
            . ' Reply with JSON only: {"answers": {"<key>": true|false, ...}, "score": 0-100 (confidence the account fits overall), "reason": "one short sentence naming the evidence"}.';
        $user = "Questions:\n" . $questions . "\n\nAccount: @" . $candidate['username'] . ($candidate['full_name'] !== '' ? ' (' . $candidate['full_name'] . ')' : '')
            . "\nEvidence:\n" . json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        try {
            $result = $this->ai->chat($system, $user, $this->model($options), 0.1, 600);
        } catch (\Throwable $exception) {
            return ['status' => 'unreadable', 'score' => 0, 'answers' => [], 'reason' => 'AI check failed: ' . mb_substr($exception->getMessage(), 0, 160)];
        }
        $content = (string) ($result['data']['content'] ?? $result['content'] ?? '');
        $json = preg_match('/\{[\s\S]*\}/', $content, $match) ? json_decode($match[0], true) : null;
        if (! ($result['success'] ?? false) || ! is_array($json)) {
            return ['status' => 'unreadable', 'score' => 0, 'answers' => [], 'reason' => 'AI check returned no usable answer.'];
        }
        $answers = [];
        foreach (array_keys($criteria) as $key) {
            $answers[$key] = ($json['answers'][$key] ?? false) === true;
        }

        return [
            'status' => ! in_array(false, $answers, true) ? 'match' : 'no_match',
            'score' => max(0, min(100, (int) ($json['score'] ?? 0))),
            'answers' => $answers,
            'reason' => mb_substr(trim((string) ($json['reason'] ?? '')), 0, 300),
        ];
    }

    private function model(array $options): string
    {
        return (string) ($options['model'] ?? config('instagram.follow_audit.model', 'claude-haiku-4-5-20251001'));
    }
}
