<?php

namespace hexa_package_instagram\Domains\Accounts\Http\Controllers;

use hexa_core\Models\ActivityLog;
use hexa_package_browser_console\Services\BrowserConsoleRuntimeService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_proxy\Services\DecodoApiClient;
use hexa_package_proxy\Domains\Config\ProxyConfigRepository;
use hexa_package_browser_worker\Services\BrowserProxyConfigWriter;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_instagram\Services\InstagramAccountSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class InstagramAccountsController extends Controller
{
    public function index(InstagramConfigRepository $config, InstagramAccountSessionService $sessions, BrowserConsoleRuntimeService $browserConsole)
    {
        return view('instagram::accounts.index', [
            'settings' => $config->all(),
            'status' => $this->statusPayload($config, $sessions),
            'browserConsole' => $browserConsole->status(),
            'runtimeReports' => $this->runtimeReports($config),
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

    public function workerScreen(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        return response()->json($sessions->workerScreen($config->resolveProfile($validated['profile'] ?? null)));
    }

    public function workerClick(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'x' => ['required', 'numeric', 'min:0', 'max:5000'],
            'y' => ['required', 'numeric', 'min:0', 'max:5000'],
        ]);

        return response()->json($sessions->clickWorkerScreen(
            $config->resolveProfile($validated['profile'] ?? null),
            (float) $validated['x'],
            (float) $validated['y']
        ));
    }

    public function workerReload(Request $request, InstagramConfigRepository $config, InstagramAccountSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        return response()->json($sessions->reloadWorkerScreen($config->resolveProfile($validated['profile'] ?? null)));
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

    private function runtimeReports(InstagramConfigRepository $config): array
    {
        $settings = $config->all();
        $accounts = (array) ($settings["accounts"] ?? []);
        $activeProfile = (string) ($settings["session_profile"] ?? "");
        $reports = [];
        $workerProfiles = [];
        $proxyState = [];
        $profilesByKey = [];

        try {
            if (class_exists(BrowserProxyConfigWriter::class)) {
                $workerStatus = app(BrowserProxyConfigWriter::class)->status();
                foreach ((array) ($workerStatus["profiles"] ?? []) as $profile) {
                    $workerProfiles[(string) ($profile["browser_profile"] ?? "")] = (array) $profile;
                }
            }
        } catch (\Throwable) {
            $workerProfiles = [];
        }

        try {
            if (class_exists(ProxyConfigRepository::class)) {
                $proxyState = app(ProxyConfigRepository::class)->all();
                $profilesByKey = (array) ($proxyState["profiles_by_key"] ?? []);
            }
        } catch (\Throwable) {
            $proxyState = [];
            $profilesByKey = [];
        }

        $direct = Cache::remember("instagram_accounts_runtime_direct_ip", now()->addMinutes(3), function (): array {
            if (!class_exists(DecodoApiClient::class)) {
                return ["success" => false, "data" => ["ip" => ""]];
            }

            return app(DecodoApiClient::class)->directIp();
        });

        foreach ($accounts as $account) {
            $browserProfile = (string) ($account["profile"] ?? "");
            $worker = (array) ($workerProfiles[$browserProfile] ?? []);
            $profileKey = trim((string) ($worker["profile_key"] ?? ""));
            if ($profileKey === "") {
                $profileKey = (string) ($proxyState["active_profile_key"] ?? "");
            }
            $masked = $profileKey !== "" && isset($profilesByKey[$profileKey]) ? (array) $profilesByKey[$profileKey] : [];
            $server = (string) ($worker["server"] ?? "");
            if ($server === "" && $masked !== []) {
                $server = (string) (($masked["protocol"] ?? "http") . "://" . ($masked["proxy_host"] ?? "") . ":" . ($masked["proxy_port"] ?? ""));
            }

            $proxyResult = ["success" => false, "message" => "Proxy was not verified.", "data" => ["ip" => ""]];
            $identity = ["success" => false, "data" => []];
            if ($profileKey !== "" && class_exists(ProxyConfigRepository::class) && class_exists(DecodoApiClient::class)) {
                $cacheKey = "instagram_accounts_runtime_proxy_" . md5($profileKey . "|" . $server);
                $proxyBundle = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($profileKey): array {
                    try {
                        $repo = app(ProxyConfigRepository::class);
                        $decodo = app(DecodoApiClient::class);
                        $profile = $repo->profileWithSecrets($profileKey);
                        $proxy = $decodo->testProxy($profile);
                        $proxyIp = (string) data_get($proxy, "data.ip", "");
                        $identity = $proxyIp !== "" ? $decodo->ipIdentity($proxyIp) : ["success" => false, "data" => []];
                        return ["proxy" => $proxy, "identity" => $identity];
                    } catch (\Throwable $e) {
                        return ["proxy" => ["success" => false, "message" => $e->getMessage(), "data" => ["ip" => ""]], "identity" => ["success" => false, "data" => []]];
                    }
                });
                $proxyResult = (array) ($proxyBundle["proxy"] ?? $proxyResult);
                $identity = (array) ($proxyBundle["identity"] ?? $identity);
            }

            $reports[$browserProfile] = [
                "browser" => [
                    "profile" => $browserProfile,
                    "active" => $browserProfile === $activeProfile,
                    "account_label" => (string) ($account["label"] ?? $browserProfile),
                    "instagram_username" => (string) ($account["instagram_username"] ?? ""),
                ],
                "proxy" => [
                    "profile_key" => $profileKey,
                    "name" => (string) ($masked["name"] ?? ($profileKey ?: "No proxy profile selected")),
                    "server" => $server,
                    "auth_mode" => (string) ($worker["proxy_auth_mode"] ?? ($masked["proxy_auth_mode"] ?? "")),
                    "endpoint_mode" => (string) ($worker["endpoint_mode"] ?? ($masked["endpoint_mode"] ?? "")),
                    "country" => (string) ($worker["country"] ?? ($masked["country"] ?? "")),
                    "state" => (string) ($worker["state"] ?? ($masked["state"] ?? "")),
                    "city" => (string) ($worker["city"] ?? ($masked["city"] ?? "")),
                    "updated_at" => (string) ($worker["updated_at"] ?? ""),
                ],
                "direct_ip" => (string) data_get($direct, "data.ip", ""),
                "proxy_ip" => (string) data_get($proxyResult, "data.ip", ""),
                "proxy_ok" => (bool) ($proxyResult["success"] ?? false) && (string) data_get($proxyResult, "data.ip", "") !== "",
                "proxy_message" => (string) ($proxyResult["message"] ?? ""),
                "identity" => [
                    "country" => (string) data_get($identity, "data.country", ""),
                    "region" => (string) data_get($identity, "data.region", ""),
                    "city" => (string) data_get($identity, "data.city", ""),
                    "org" => (string) data_get($identity, "data.org", ""),
                    "isp" => (string) data_get($identity, "data.isp", ""),
                    "is_decodo" => (bool) data_get($identity, "data.is_decodo", false),
                ],
                "checked_at" => now()->toIso8601String(),
            ];
        }

        return $reports;
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
