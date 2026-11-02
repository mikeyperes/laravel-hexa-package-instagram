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
                // CRITICAL - see BUGLOG.md IG-2026-11-02-01. Public embeds may
                // omit dates; preserve the profile feed's own publication evidence.
                $data['posts'] = array_map(fn ($post) => ['url' => $post['url'] ?? '', 'code' => $post['shortcode'] ?? '',
                    'posted_at' => (string) ($post['posted_at'] ?? ''),
                    'pinned' => $post['pinned'] ?? null], $items);
                if (empty($raw['success'])) $raw['message'] = trim(($row['message'] ?? '').' '.($raw['message'] ?? '').' '.($raw['detail'] ?? ''));
                break;
            case 'read_inbox':
                $raw=$this->reader->readInbox($r->session,(int)($r->options['limit'] ?? 20));$data=array_intersect_key($raw,array_flip(['threads']));break;
            case 'read_thread':
                $raw=$this->reader->readThread($r->session,$target,(int)($r->options['limit'] ?? 20));$data=array_intersect_key($raw,array_flip(['messages','participants']));break;
            case 'read_following':
            case 'read_followers':
                $raw=$this->reader->networkFeed($r->session,$target,(int)($r->options['limit'] ?? 100),$action->key()==='read_followers'?'followers':'following');$data=(array)($raw['data'] ?? []);break;
            case 'read_stories':
                $raw=$this->reader->accountStories($r->session,$target,(int)($r->options['owner_lookups'] ?? 10));
                $data=['stories'=>array_map(function($story){if(isset($story['tagged'])){$story['tagged_accounts']=$story['tagged'];unset($story['tagged']);}return $story;},$raw['stories'] ?? [])];break;
            case 'read_highlights':
                if(($account=(string)($r->options['account'] ?? ''))!=='') {
                    $profile=$this->reader->accountProfiles($r->session,[$account]);$id=(string)($profile['data']['accounts'][$account]['user_id'] ?? '');
                    if($id===''){$raw=['success'=>false,'message'=>'The highlight source account could not be resolved.'];$data=['highlights'=>[]];break;}
                    $raw=$this->reader->highlightFeeds($r->session,$id,(int)($r->options['limit'] ?? 15));$data=['highlights'=>$raw['data']['highlights'] ?? []];
                }else{$raw=$this->reader->highlights($r->session,$target==='self'?'':$target);$data=['highlights'=>$raw['data'] ?? []];}
                break;
            case 'download_story':
                $known=\hexa_package_instagram\Actions\Models\StoredResult::query()->where('session',$r->session)->where('action','read_stories')->where('outcome','done')->latest('id')->limit(20)->get();
                $saved=null;
                foreach($known as $entry) foreach((array)($entry->record['evidence']['stories'] ?? []) as $story) if(($story['url'] ?? '')===$target)$saved=$story;
                $raw=$saved ? ['success'=>true,'data'=>['item'=>$saved]] : $this->reader->mediaByUrl($r->session,$target);
                if(!empty($raw['success'])) {
                    $item=$raw['data']['item'] ?? [];
                    $url=(string)(($item['video_url'] ?? '') ?: ($item['image_url'] ?? ''));
                    if(!preg_match('~^https://[^/]+/(.+)$~',$url) || !preg_match('/(?:^|\.)(?:cdninstagram\.com|fbcdn\.net)$/',(string)parse_url($url,PHP_URL_HOST))) throw new \InvalidArgumentException('Story media did not resolve to an Instagram CDN URL.');
                    $download=app(\hexa_package_browser_worker\Services\BrowserHttpService::class)->getBinary($url,['timeout'=>30,'route_profile'=>$r->session,'require_route'=>true]);
                    if(empty($download['success'])) { $raw=['success'=>false,'message'=>'The story media download failed.']; break; }
                    $bytes=(string)$download['body'];$mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                    if(!in_array($mime,['image/jpeg','image/png','image/webp','video/mp4'],true)) throw new \InvalidArgumentException('Unsupported story media.');
                    $ext=match($mime){'image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4',default=>'jpg'};
                    $path=storage_path('app/private/instagram/stories/'.hash('sha256',$r->session.':'.$target).'.'.$ext);
                    if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
                    if(!is_file($path))file_put_contents($path,$bytes,LOCK_EX);
                    $data=['download'=>['path'=>$path,'mime'=>$mime,'sha256'=>hash_file('sha256',$path),'source'=>['network'=>'instagram','account'=>$item['owner'] ?? '', 'kind'=>'story','taken_at'=>$item['taken_at'] ?? null,'url'=>$target]]];
                }
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
        $message = $stop === 'session_busy' ? 'The session is busy with another browser owner; retry after it is released.' : trim(($raw['message'] ?? '').' '.($raw['detail'] ?? ''));
        if ($r->only) $data = array_intersect_key($data, array_flip(array_merge($r->only, ['classifications'])));
        if (!$success) $data['message'] = $message;
        // Legacy readers do not expose observed navigation rows. Never fabricate route proof.
        $outcome = $success ? 'done' : (str_contains(strtolower($message), 'embed without its media') ? 'inconclusive' : 'failed');
        $record = new InstagramResult($r->targets[0], $outcome, $data, now('UTC')->toIso8601String(), $r->mode->value, $r->session, $routes);
        return new ActionResult($success, $a->key(), $r->mode->value, $r->session, [$record], $success ? [] : [$message], $stop, $routes);
    }
    private function stop(array $raw): ?string
    {
        if (($raw['status_code'] ?? 0) === 409 || str_contains(strtolower(json_encode($raw)), 'ownership conflict')) return 'session_busy';
        if (!empty($raw['browser_failed'])) return null;
        $message = strtolower(json_encode($raw));
        if (str_contains($message, 'ownership conflict') || str_contains($message, 'profile is busy')) return 'session_busy';
        foreach (['rate_limited' => ['rate limited','rate limit','status 429','"status":429'],
            'challenge' => ['challenge_required','challenge required','security check'], 'login_required' => ['login_required','redirected to the instagram login'],
            'action_blocked' => ['action_blocked','action blocked']] as $signal => $matches) {
            foreach ($matches as $match) if (str_contains($message,$match)) return $signal;
        }
        return null;
    }
}
