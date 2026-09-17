<?php

namespace hexa_package_instagram\Domains\Accounts\Http\Controllers;

use hexa_core\Models\ActivityLog;
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
            'set_active' => ['nullable', 'boolean'],
        ]);

        $settings = $config->saveAccount(
            $validated['label'],
            $validated['profile'],
            $validated['instagram_username'],
            (bool) ($validated['set_active'] ?? false)
        );
        $profile = $config->resolveProfile($validated['profile']);

        ActivityLog::log('instagram', 'account_save', 'Saved Instagram account binding: '.$validated['label'], [
            'profile' => $profile,
            'instagram_username' => $config->normalizeUsername($validated['instagram_username']),
            'set_active' => (bool) ($validated['set_active'] ?? false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Instagram account binding saved.',
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

        return response()->json([
            'success' => true,
            'message' => 'Default Instagram profile updated.',
            'settings' => $settings,
            'status' => $this->statusPayload($config, $sessions),
        ]);
    }

    public function status(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        return response()->json($sessions->status($config->resolveProfile($validated['profile']), true));
    }

    public function destroy(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);
        $profile = $config->resolveProfile($validated['profile']);
        $settings = $config->deleteAccount($profile);

        ActivityLog::log('instagram', 'account_delete', 'Removed Instagram account binding: '.$profile, [
            'profile' => $profile,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Instagram account binding removed. The Browser Console profile was preserved.',
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
