<?php

namespace hexa_package_instagram\Domains\Accounts\Http\Controllers;

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

        return response()->json($sessions->login($config->resolveProfile($validated['profile'] ?? null)));
    }

    public function logout(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        return response()->json($sessions->logout($config->resolveProfile($validated['profile'] ?? null)));
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
