<?php
namespace hexa_package_instagram\Actions;
use hexa_package_browser_worker\Contracts\BrowserSessionRegistryContract;
use hexa_package_human_behavior\Limits\Limiter;
use hexa_package_human_behavior\Settings\HumanBehaviorSettings;
use hexa_package_instagram\Actions\Models\StoredResult;
use hexa_package_instagram\Actions\Support\SessionActor;
use hexa_package_social_automation\Automation\Devices\Models\Device;
use Illuminate\Support\Facades\Cache;
use Throwable;
final class ActionRunner
{
    public function __construct(private readonly ActionRegistry $registry, private readonly BrowserSessionRegistryContract $sessions,
        private readonly Limiter $limiter, private readonly HumanBehaviorSettings $settings) {}
    public function run(string $key, ActionRequest $request): ActionResult
    {
        $result = new ActionResult(false, $key, $request->mode->value, $request->session);
        try {
            $action = $this->registry->get($key);
            if (!in_array($request->session, array_column($this->sessions->all(), 'profile_id'), true)) throw new \InvalidArgumentException('Unknown exact browser session: '.$request->session);
            if (!in_array($request->mode, $action->modes(), true)) throw new \InvalidArgumentException('Mode is not supported for '.$key.'.');
            if (in_array($key,['publish_story','publish_post'],true) && count(array_unique($request->targets))>1) {
                $keys=(array)($request->options['keys'] ?? []);$selected=array_map(fn($target)=>(string)($keys[$target] ?? ''),array_unique($request->targets));
                if(in_array('',$selected,true) || count(array_unique($selected))!==count($selected)) throw new \InvalidArgumentException('A publication list requires a distinct stable key for every target in options.keys.');
            }
            if ($action->risk() !== Risk::Read) {
                if ((!($request->mode === Mode::Api && in_array($key,['publish_story','publish_post'],true)) && $request->mode !== Mode::Human) || !$request->approval) throw new \InvalidArgumentException('Writes require a supported write transport and a verified approval receipt.');
                app(\hexa_package_instagram\Actions\Support\ApprovalVerifier::class)->verify($key,$request);
                if(in_array($request->session,config('instagram.read_only_profiles',[]),true)) throw new \InvalidArgumentException('This session is read-only.');
            }
            if ($request->targets === [] || array_filter($request->targets, fn ($t) => !is_string($t) || trim($t) === '')) throw new \InvalidArgumentException('At least one nonempty target is required.');
            if (array_diff($request->only, $action->parts())) throw new \InvalidArgumentException('Unknown output part.');
            if ($request->mode === Mode::Human) return app(\hexa_package_instagram\Transport\HumanTransport::class)->run($key,$request);
            $lock = Cache::lock('instagram:action:'.$request->session, 600);
            if (!$lock->get()) { $result->stop = 'session_busy'; $result->errors[] = 'Another action owns this session.'; return $result; }
            try {
                $actor = new SessionActor($request->session);
                foreach (array_unique($request->targets) as $target) {
                    $repeatKey = $request->campaignId === null ? null : hash('sha256', json_encode([$key, $request->session, $target, $request->campaignId, $request->only, $request->options]));
                    $previous = $repeatKey ? StoredResult::query()->where('repeat_key', $repeatKey)->whereIn('outcome', ['done','already'])->first() : null;
                    if (in_array($target, $request->skip, true) || $previous) {
                        $result->records[] = new InstagramResult($target, 'skipped', $previous ? ['previous_result_id' => $previous->id] : [], now('UTC')->toIso8601String(), $request->mode->value, $request->session);
                        continue;
                    }
                    $device = Device::query()->where('profile', $request->session)->first();
                    if ($device?->inCooldown()) { $result->stop = 'cooldown'; $result->errors[] = ['wait_seconds' => max(1, (int) now()->diffInSeconds($device->cooldown_until, false)), 'reason' => $device->cooldown_reason]; break; }
                    $wait = $action->limitKey() === '' ? 0 : $this->limiter->waitFor($actor, $action->limitKey());
                    if ($wait > 0) { $result->stop = 'limit_exhausted'; $result->errors[] = ['wait_seconds' => $wait, 'limit' => $action->limitKey()]; break; }
                    $last = StoredResult::query()->where('session', $request->session)->latest('performed_at')->first();
                    if ($last) {
                        [$min, $max] = $this->settings->timing()['between_tasks_seconds'];
                        $remaining = random_int((int) ceil($min), (int) ceil($max)) - (int) $last->performed_at->diffInSeconds(now(), true);
                        if ($remaining > 0) sleep($remaining);
                    }
                    $one = $action->run($request->forTarget($target));
                    // Conservatively count every dispatched attempt, including uncertain reads.
                    if ($action->limitKey() !== '') $this->limiter->record($actor, $action->limitKey());
                    foreach ($one->records as $record) {
                        StoredResult::query()->create(['action' => $key, 'session' => $request->session, 'target' => $target, 'mode' => $request->mode->value,
                            'outcome' => $record->outcome, 'repeat_key' => $repeatKey, 'record' => $record->jsonSerialize(), 'performed_at' => $record->at]);
                        $result->records[] = $record;
                    }
                    $result->errors = array_merge($result->errors, $one->errors);
                    $result->routes = array_merge($result->routes, $one->routes);
                    if ($one->stop) {
                        $result->stop = $one->stop;
                        if (in_array($one->stop, config('social-automation.stop_signals', []), true)) {
                            app(\hexa_package_social_automation\Automation\Devices\DeviceService::class)->cooldown($request->session,$one->stop);
                        }
                        break;
                    }
                }
                $result->success = !$result->stop && $result->errors === [] && $result->records !== [];
            } finally { $lock->release(); }
        } catch (Throwable $e) { $result->errors[] = $e->getMessage(); }
        if (!$result->success && !$result->stop && ($request->options['fallback_session'] ?? '') !== '' &&
            $request->session === config('instagram.public_reader_profile', 'instagram-public') &&
            count(array_filter($result->records, fn ($row) => $row->outcome === 'inconclusive')) > 0) {
            $options = $request->options; $fallback = $options['fallback_session']; unset($options['fallback_session']);
            $next = $this->run($key, new ActionRequest($fallback, $request->targets, $request->mode, $request->only, $request->skip,
                $request->approval, $request->campaignId, $request->context, $options));
            return $next;
        }
        return $result;
    }
}
