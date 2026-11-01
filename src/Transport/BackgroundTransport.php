<?php
namespace hexa_package_instagram\Transport;
use hexa_package_instagram\Actions\{ActionRequest, ActionResult, InstagramResult};
use hexa_package_instagram\Actions\Contracts\InstagramAction;
use hexa_package_instagram\Actions\Support\{AccountTypeClassifier, SessionActor};
use hexa_package_instagram\Services\{InstagramScraperService, InstagramConnectionService};
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_human_behavior\Limits\Limiter;
use hexa_package_human_behavior\Settings\HumanBehaviorSettings;
final class BackgroundTransport
{
    public function __construct(private readonly InstagramScraperService $reader, private readonly InstagramConfigRepository $config,
        private readonly Limiter $limiter, private readonly HumanBehaviorSettings $settings, private readonly AccountTypeClassifier $classifier) {}
    public function run(InstagramAction $action, ActionRequest $r): ActionResult
    {
        $target = $r->targets[0]; $data = []; $errors = []; $routes = []; $stop = null;
        $public = $r->session === config('instagram.public_reader_profile', 'instagram-public');
        if (!$public) {
            $gate = app(InstagramConnectionService::class)->readGate($r->session);
            if (!$gate['ready']) return $this->mapped($action, $r, ['success' => false, 'message' => $gate['reason']], [], []);
        }
        switch ($action->key()) {
            case 'read_post':
            case 'read_tagged_accounts':
                $raw = $this->reader->readPost($target, $r->session, $public, (int) ($r->options['comments'] ?? 20));
                $data = (array) ($raw['post'] ?? []);
                if (array_key_exists('tagged', $data)) { $data['tagged_accounts'] = $data['tagged']; unset($data['tagged']); }
                if ($action->key() === 'read_tagged_accounts' && !empty($raw['success'])) {
                    $data = array_intersect_key($data, array_flip($action->parts()));
                    if ($r->options['classify'] ?? true) {
                        $classified = [];
                        foreach (array_unique(array_merge(...array_values($data ?: [[]]))) as $account) {
                            $actor = new SessionActor($r->session);
                            $wait = $this->limiter->waitFor($actor, 'open_profile');
                            if ($wait > 0) { $stop = 'limit_exhausted'; $errors[] = ['limit' => 'open_profile', 'wait_seconds' => $wait]; break; }
                            [$min, $max] = $this->settings->timing()['between_tasks_seconds']; sleep(random_int((int) ceil($min), (int) ceil($max)));
                            $profile = $this->profile($r, $account, $public);
                            $this->limiter->record($actor, 'open_profile');
                            $classified[$account] = $this->classifier->classify((array) ($profile['profile'] ?? []));
                            if (empty($profile['success'])) { $errors[] = $profile['message'] ?? 'Profile not read.'; $stop = $this->stop($profile); if ($stop) break; }
                        }
                        $data['classifications'] = $classified;
                    }
                }
                break;
            case 'read_profile':
                $raw = $this->profile($r, $target, $public); $data = ['profile' => $raw['profile'] ?? []]; break;
            case 'read_account_posts':
                $account = $this->config->normalizeUsername($target);
                if ($public) {
                    $raw = $this->reader->publicProfileFeeds($r->session, [$account], max(1,min(12,(int) ($r->options['limit'] ?? 5))));
                    $row = $raw['data']['accounts'][$account] ?? []; $items = array_values($row['posts'] ?? []);
                    $raw['success'] = !empty($row['success']);
                } else {
                    $raw = $this->reader->profileFeeds($r->session, [$account], max(1,min(12,(int) ($r->options['limit'] ?? 5))), (array) config('instagram.account_reads', []));
                    $row = $raw['data']['accounts'][$account] ?? []; $items = array_values($row['posts'] ?? []);
                    $raw['success'] = !empty($row['success']);
                }
                $data['posts'] = array_map(fn ($post) => ['url' => $post['url'] ?? '', 'code' => $post['shortcode'] ?? '',
                    'taken_at' => $post['taken_at'] ?? $post['posted_at'] ?? null, 'pinned' => $post['pinned'] ?? null], $items);
                if (empty($raw['success'])) $raw['message'] = trim(($row['message'] ?? '').' '.($raw['message'] ?? '').' '.($raw['detail'] ?? ''));
                break;
            default: throw new \InvalidArgumentException('No background reader for '.$action->key());
        }
        $result = $this->mapped($action, $r, $raw, $data, $routes);
        $result->errors = array_merge($result->errors, $errors); $result->stop = $stop ?? $result->stop;
        if ($result->stop || $result->errors) $result->success = false;
        return $result;
    }
    private function profile(ActionRequest $r, string $target, bool $public): array
    {
        $account = $this->config->normalizeUsername($target);
        if ($public) {
            $raw = $this->reader->publicProfileFeeds($r->session, [$account], 1);
            $row = (array) ($raw['data']['accounts'][$account] ?? []);
            $raw['success'] = !empty($row['success']);
            $raw['profile'] = ['username' => $account, 'coverage' => 'public_embed', 'full_name' => null, 'category' => null,
                'biography' => null, 'is_business' => null, 'is_professional' => null, 'external_url' => null];
        } else {
            $raw = $this->reader->accountProfiles($r->session, [$account]);
            $raw['profile'] = $raw['data']['accounts'][$account] ?? [];
            $raw['success'] = !empty($raw['profile']['success']);
            if (!$raw['success']) $raw['message'] = trim(($raw['profile']['message'] ?? '').' '.($raw['message'] ?? '').' '.($raw['detail'] ?? ''));
        }
        return $raw;
    }
    private function mapped(InstagramAction $a, ActionRequest $r, array $raw, array $data, array $routes): ActionResult
    {
        $success = !empty($raw['success']); $stop = $this->stop($raw);
        $message = trim(($raw['message'] ?? '').' '.($raw['detail'] ?? ''));
        if ($r->only) $data = array_intersect_key($data, array_flip(array_merge($r->only, ['classifications'])));
        if (!$success) $data['message'] = $message;
        // Legacy readers do not expose observed navigation rows. Never fabricate route proof.
        $record = new InstagramResult($r->targets[0], $success ? 'done' : 'failed', $data, now('UTC')->toIso8601String(), $r->mode->value, $r->session, $routes);
        return new ActionResult($success, $a->key(), $r->mode->value, $r->session, [$record], $success ? [] : [$message], $stop, $routes);
    }
    private function stop(array $raw): ?string
    {
        if (!empty($raw['browser_failed']) || ($raw['status_code'] ?? 0) === 409) return null;
        $message = strtolower(json_encode($raw));
        foreach (['rate_limited' => ['rate limited','rate limit','status 429','"status":429'],
            'challenge' => ['challenge_required','challenge required','security check'], 'login_required' => ['login_required','redirected to the instagram login'],
            'action_blocked' => ['action_blocked','action blocked']] as $signal => $matches) {
            foreach ($matches as $match) if (str_contains($message,$match)) return $signal;
        }
        return null;
    }
}
