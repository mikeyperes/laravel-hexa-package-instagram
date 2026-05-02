<?php

namespace hexa_package_instagram\Domains\Workspace\Http\Controllers;

use hexa_core\Models\Setting;
use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_instagram\Services\InstagramAccountSessionService;
use hexa_package_instagram\Services\InstagramScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
        return response()->json($sessions->integrityTest($config->resolveProfile($request->input('profile') ?: $request->query('profile'))));
    }

    public function status(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        return response()->json($sessions->status($config->resolveProfile($request->input('profile') ?: $request->query('profile'))));
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

        return response()->json($scraper->profileScan(
            $config->resolveProfile($validated['profile'] ?? null),
            $validated['instagram_username'],
            (int) ($validated['limit'] ?? 12)
        ));
    }

    public function storyScan(Request $request, InstagramConfigRepository $config, InstagramScraperService $scraper): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'instagram_username' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($scraper->storyScan(
            $config->resolveProfile($validated['profile'] ?? null),
            $validated['instagram_username']
        ));
    }

    public function importPost(Request $request, InstagramScraperService $scraper): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'include_image_data' => ['nullable', 'boolean'],
        ]);

        return response()->json($scraper->importPost(
            $validated['url'],
            (bool) ($validated['include_image_data'] ?? true)
        ));
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
        ];
    }
}
