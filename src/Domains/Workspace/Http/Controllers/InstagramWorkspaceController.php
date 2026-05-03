<?php

namespace hexa_package_instagram\Domains\Workspace\Http\Controllers;

use hexa_core\Models\ActivityLog;
use hexa_core\Models\Setting;
use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_instagram\Services\InstagramAccountSessionService;
use hexa_package_instagram\Services\InstagramScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class InstagramWorkspaceController extends Controller
{
    public function index(Request $request, InstagramConfigRepository $config, CredentialService $credentials, InstagramAccountSessionService $sessions)
    {
        return view('instagram::workspace.index', [
            'settings' => $config->all(),
            'status' => $this->statusData($request, $config, $credentials, $sessions),
        ]);
    }

    public function raw(Request $request, InstagramConfigRepository $config, CredentialService $credentials, InstagramAccountSessionService $sessions)
    {
        return view('instagram::workspace.raw', [
            'settings' => $config->all(),
            'status' => $this->statusData($request, $config, $credentials, $sessions),
        ]);
    }

    public function integrity(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $profile = $config->resolveProfile($request->input('profile') ?: $request->query('profile'));
        $result = $sessions->integrityTest($profile);
        $this->recordRawAction('raw_integrity', $result, [
            'profile' => $profile,
        ]);

        return response()->json($result);
    }

    public function status(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $profile = $config->resolveProfile($request->input('profile') ?: $request->query('profile'));
        $result = $sessions->status($profile);
        $this->recordRawAction('raw_status', $result, [
            'profile' => $profile,
        ]);

        return response()->json($result);
    }

    public function logs(Request $request, BrowserWorkerBridgeContract $browser): JsonResponse
    {
        return response()->json($browser->logs((int) $request->integer('limit', 100)));
    }

    public function profileScan(Request $request, InstagramConfigRepository $config, InstagramScraperService $scraper): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'instagram_username' => ['required', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $profile = $config->resolveProfile($validated['profile'] ?? null);
        $result = $scraper->profileScan(
            $profile,
            $validated['instagram_username'],
            (int) ($validated['limit'] ?? 12)
        );
        $this->recordRawAction('raw_profile_scan', $result, [
            'profile' => $profile,
            'instagram_username' => $validated['instagram_username'],
            'limit' => (int) ($validated['limit'] ?? 12),
        ]);

        return response()->json($result);
    }

    public function storyScan(Request $request, InstagramConfigRepository $config, InstagramScraperService $scraper): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'instagram_username' => ['required', 'string', 'max:255'],
        ]);

        $profile = $config->resolveProfile($validated['profile'] ?? null);
        $result = $scraper->storyScan(
            $profile,
            $validated['instagram_username']
        );
        $this->recordRawAction('raw_story_scan', $result, [
            'profile' => $profile,
            'instagram_username' => $validated['instagram_username'],
        ]);

        return response()->json($result);
    }

    public function postScan(Request $request, InstagramConfigRepository $config, InstagramScraperService $scraper): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'url' => ['required', 'url', 'max:2000'],
        ]);

        $profile = $config->resolveProfile($validated['profile'] ?? null);
        $result = $scraper->postScan(
            $profile,
            $validated['url']
        );
        $this->recordRawAction('raw_post_scan', $result, [
            'profile' => $profile,
            'url' => $validated['url'],
        ]);

        return response()->json($result);
    }

    public function importPost(Request $request, InstagramScraperService $scraper): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'include_image_data' => ['nullable', 'boolean'],
        ]);

        $result = $scraper->importPost(
            $validated['url'],
            (bool) ($validated['include_image_data'] ?? true)
        );
        $this->recordRawAction('raw_post_import', $result, [
            'url' => $validated['url'],
            'include_image_data' => (bool) ($validated['include_image_data'] ?? true),
        ]);

        return response()->json($result);
    }

    private function statusData(Request $request, InstagramConfigRepository $config, CredentialService $credentials, InstagramAccountSessionService $sessions): array
    {
        $settings = $config->all();
        $credentialKey = 'cred_instagram_meta_access_token';
        $credentialRow = Setting::query()->where('key', $credentialKey)->first();
        $activeProfile = $config->resolveProfile($request->query('profile') ?: $request->input('profile'));
        $activeAccount = $config->findAccount($activeProfile);

        return [
            'accounts' => array_map(fn (array $account) => $sessions->accountPresentation($account), $settings['accounts']),
            'active_profile' => $activeProfile,
            'active_account' => $activeAccount ? $sessions->accountPresentation($activeAccount) : null,
            'default_profile_username' => $settings['default_profile_username'],
            'default_story_username' => $settings['default_story_username'],
            'default_post_url' => $settings['default_post_url'],
            'has_meta_token' => $credentials->exists('instagram', 'meta_access_token'),
            'meta_token_masked' => $credentials->getMasked('instagram', 'meta_access_token'),
            'credential_key' => $credentialKey,
            'credential_updated_at' => $credentialRow?->updated_at?->toDateTimeString(),
            'package_version' => (string) config('instagram.version', '1.0.0'),
            'raw_history' => $this->recentRawHistory(),
        ];
    }

    private function recentRawHistory(): array
    {
        return ActivityLog::query()
            ->where('category', 'instagram')
            ->whereIn('action', ['raw_status', 'raw_integrity', 'raw_profile_scan', 'raw_story_scan', 'raw_post_scan', 'raw_post_import'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->map(function (ActivityLog $log): array {
                return [
                    'type' => $this->logTypeForAction($log),
                    'message' => $log->description,
                    'detail' => $log->context ?: null,
                    'time' => optional($log->created_at)->timezone(config('app.timezone', 'America/New_York'))->format('H:i:s'),
                ];
            })
            ->values()
            ->all();
    }

    private function logTypeForAction(ActivityLog $log): string
    {
        if (($log->context['success'] ?? false) === true) {
            return 'success';
        }

        return match ($log->action) {
            'raw_status', 'raw_integrity' => 'info',
            default => 'warning',
        };
    }

    private function recordRawAction(string $action, array $result, array $context = []): void
    {
        $payload = array_merge($context, [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'detail' => $result['detail'] ?? null,
            'summary' => $this->summarizeResult($action, $result),
        ]);

        ActivityLog::log('instagram', $action, $this->actionDescription($action, $payload), $payload);
    }

    private function actionDescription(string $action, array $payload): string
    {
        return match ($action) {
            'raw_status' => 'Checked raw Instagram status for profile: ' . ($payload['profile'] ?? 'unknown'),
            'raw_integrity' => 'Ran raw Instagram integrity test for profile: ' . ($payload['profile'] ?? 'unknown'),
            'raw_profile_scan' => 'Ran raw Instagram profile scan for @' . ($payload['instagram_username'] ?? 'unknown'),
            'raw_story_scan' => 'Ran raw Instagram story pull for @' . ($payload['instagram_username'] ?? 'unknown'),
            'raw_post_scan' => 'Ran raw Instagram post scan for ' . Str::limit((string) ($payload['url'] ?? ''), 90),
            'raw_post_import' => 'Ran raw Instagram post import for ' . Str::limit((string) ($payload['url'] ?? ''), 90),
            default => 'Ran Instagram raw action.',
        };
    }

    private function summarizeResult(string $action, array $result): array
    {
        return match ($action) {
            'raw_profile_scan' => [
                'post_count' => count((array) data_get($result, 'data.scan.post_links', [])),
                'title' => data_get($result, 'data.scan.title'),
            ],
            'raw_story_scan' => [
                'image_count' => count((array) data_get($result, 'data.scan.image_urls', [])),
                'video_count' => count((array) data_get($result, 'data.scan.video_urls', [])),
                'title' => data_get($result, 'data.scan.title'),
            ],
            'raw_post_scan' => [
                'posted_at' => data_get($result, 'data.scan.posted_at'),
                'image_count' => count((array) data_get($result, 'data.scan.image_urls', [])),
                'video_count' => count((array) data_get($result, 'data.scan.video_urls', [])),
                'title' => data_get($result, 'data.scan.title'),
            ],
            'raw_post_import' => [
                'method_used' => data_get($result, 'data.method_used'),
                'caption_length' => strlen((string) data_get($result, 'data.caption', '')),
                'image_url' => data_get($result, 'data.image_url'),
            ],
            default => [
                'connected' => data_get($result, 'data.connected'),
                'verification_required' => data_get($result, 'data.verification_required'),
                'challenge' => data_get($result, 'data.challenge'),
                'current_url' => data_get($result, 'data.probe.url'),
            ],
        };
    }
}
