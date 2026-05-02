<?php

namespace hexa_package_instagram\Domains\Config\Http\Controllers;

use hexa_core\Models\ActivityLog;
use hexa_core\Models\Setting;
use hexa_core\Services\CredentialService;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_instagram\Services\InstagramAccountSessionService;
use hexa_package_instagram\Services\InstagramImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InstagramSettingsController extends Controller
{
    public function index(InstagramConfigRepository $config, CredentialService $credentials, InstagramAccountSessionService $sessions)
    {
        $settings = $config->all();
        $credentialKey = 'cred_instagram_meta_access_token';
        $credentialRow = Setting::query()->where('key', $credentialKey)->first();
        $activeAccount = $config->activeAccount();

        return view('instagram::settings.index', [
            'settings' => $settings,
            'status' => [
                'accounts' => array_map(fn (array $account) => $sessions->accountPresentation($account), $settings['accounts']),
                'active_profile' => $settings['session_profile'],
                'active_account' => $activeAccount ? $sessions->accountPresentation($activeAccount) : null,
                'has_meta_token' => $credentials->exists('instagram', 'meta_access_token'),
                'meta_token_masked' => $credentials->getMasked('instagram', 'meta_access_token'),
                'credential_key' => $credentialKey,
                'credential_updated_at' => $credentialRow?->updated_at?->toDateTimeString(),
                'package_version' => (string) config('instagram.version', '1.0.0'),
            ],
        ]);
    }

    public function save(Request $request, InstagramConfigRepository $config): JsonResponse
    {
        $validated = $request->validate([
            'default_profile_username' => ['nullable', 'string', 'max:255'],
            'default_story_username' => ['nullable', 'string', 'max:255'],
            'default_post_url' => ['nullable', 'url', 'max:2000'],
        ]);

        ActivityLog::log('instagram', 'settings_save', 'Saved Instagram settings.', $validated);

        return response()->json([
            'success' => true,
            'message' => 'Instagram settings saved.',
            'settings' => $config->update($validated),
        ]);
    }

    public function test(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $profile = $config->resolveProfile($validated['profile'] ?? null);
        $result = $sessions->integrityTest($profile);

        ActivityLog::log('instagram', 'connection_test', 'Ran Instagram connection test for profile: ' . $profile, [
            'profile' => $profile,
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'detail' => $result['detail'] ?? null,
        ]);

        return response()->json($result);
    }

    public function testMetaToken(InstagramImportService $instagram): JsonResponse
    {
        return response()->json($instagram->testToken());
    }
}
