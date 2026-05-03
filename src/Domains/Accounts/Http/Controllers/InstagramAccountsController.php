<?php

namespace hexa_package_instagram\Domains\Accounts\Http\Controllers;

use hexa_core\Models\ActivityLog;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_instagram\Services\InstagramAccountSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InstagramAccountsController extends Controller
{
    public function index(InstagramConfigRepository $config, InstagramAccountSessionService $sessions)
    {
        return view('instagram::accounts.index', [
            'settings' => $config->all(),
            'status' => $this->statusPayload($config, $sessions),
        ]);
    }

    public function store(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'profile' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'instagram_username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'set_active' => ['nullable', 'boolean'],
        ]);

        $settings = $config->saveAccount(
            $validated['label'],
            $validated['profile'],
            $validated['instagram_username'],
            (bool) ($validated['set_active'] ?? false)
        );

        if (isset($validated['password']) && trim((string) $validated['password']) !== '') {
            $sessions->storePassword($validated['profile'], $validated['password']);
        }

        ActivityLog::log('instagram', 'account_save', 'Saved Instagram account profile: ' . $validated['label'], [
            'profile' => $config->resolveProfile($validated['profile']),
            'instagram_username' => $validated['instagram_username'],
            'set_active' => (bool) ($validated['set_active'] ?? false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Instagram account profile saved.',
            'settings' => $settings,
            'status' => $this->statusPayload($config, $sessions),
        ]);
    }

    public function activate(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $settings = $config->setActiveProfile($validated['profile']);

        ActivityLog::log('instagram', 'account_activate', 'Activated Instagram account profile: ' . $validated['profile'], [
            'profile' => $config->resolveProfile($validated['profile']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Active Instagram account updated.',
            'settings' => $settings,
            'status' => $this->statusPayload($config, $sessions),
        ]);
    }

    public function status(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        return response()->json($sessions->status($config->resolveProfile($validated['profile'] ?? null)));
    }

    public function login(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $profile = $config->resolveProfile($validated['profile'] ?? null);
        $result = $sessions->login($profile);

        ActivityLog::log('instagram', 'account_login', ((bool) ($result['success'] ?? false) ? 'Ran' : 'Failed') . ' Instagram login for profile: ' . $profile, [
            'profile' => $profile,
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'detail' => $result['detail'] ?? null,
        ]);

        return response()->json($result);
    }

    public function logout(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $profile = $config->resolveProfile($validated['profile'] ?? null);
        $result = $sessions->logout($profile);

        ActivityLog::log('instagram', 'account_logout', 'Logged out Instagram browser profile: ' . $profile, [
            'profile' => $profile,
            'success' => (bool) ($result['success'] ?? false),
        ]);

        return response()->json($result);
    }

    public function submitVerificationCode(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $profile = $config->resolveProfile($validated['profile'] ?? null);
        $result = $sessions->submitVerificationCode($profile, $validated['code']);

        ActivityLog::log('instagram', 'account_submit_code', ((bool) ($result['success'] ?? false) ? 'Submitted' : 'Failed to submit') . ' Instagram verification code for profile: ' . $profile, [
            'profile' => $profile,
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'detail' => $result['detail'] ?? null,
            'verification_channel' => $result['data']['verification_channel'] ?? null,
        ]);

        return response()->json($result);
    }

    public function destroy(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions, BrowserWorkerBridgeContract $browser): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $profile = $config->resolveProfile($validated['profile']);
        $browser->deleteProfile($profile);
        $sessions->deletePassword($profile);
        $settings = $config->deleteAccount($profile);

        ActivityLog::log('instagram', 'account_delete', 'Removed Instagram account profile: ' . $profile, [
            'profile' => $profile,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Instagram account profile removed.',
            'settings' => $settings,
            'status' => $this->statusPayload($config, $sessions),
        ]);
    }

    private function statusPayload(InstagramConfigRepository $config, InstagramAccountSessionService $sessions): array
    {
        $settings = $config->all();
        $activeAccount = $config->activeAccount();

        return [
            'accounts' => array_map(fn (array $account) => $sessions->accountPresentation($account), $settings['accounts']),
            'active_profile' => $settings['session_profile'],
            'active_account' => $activeAccount ? $sessions->accountPresentation($activeAccount) : null,
        ];
    }
}
