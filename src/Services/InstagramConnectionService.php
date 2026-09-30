<?php

namespace hexa_package_instagram\Services;

use hexa_package_browser_console\Services\BrowserConsoleRuntimeService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_browser_worker\Services\BrowserHttpService;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

class InstagramConnectionService
{
    public function __construct(
        private readonly InstagramConfigRepository $config,
        private readonly BrowserWorkerBridgeContract $worker,
        private readonly BrowserConsoleRuntimeService $console,
        private readonly BrowserHttpService $http,
    ) {}

    /**
     * Whether a logged-in browser profile can read Instagram now, with the exact reason when it cannot:
     * automation paused, Browser Console runtime not activated, Instagram login not verified, or (when
     * $requireRoute) no protected route (the profile is on a direct connection). Nothing is launched or
     * activated here; the login is re-verified once only when the runtime is up and the last check lapsed.
     *
     * @return array{ready: bool, reason: string, profile: string, paused: bool, runtime_ready: bool, authenticated: bool, transport: string, console_url: string}
     */
    public function readGate(string $profile, bool $requireRoute = true): array
    {
        $profile = $this->config->normalizeProfile($profile);
        $gate = [
            'ready' => false,
            'reason' => '',
            'profile' => $profile,
            'paused' => false,
            'runtime_ready' => false,
            'authenticated' => false,
            'transport' => 'unknown',
            'console_url' => $this->console->url($profile),
        ];

        if (method_exists($this->worker, 'pausedProfiles') && in_array($profile, (array) $this->worker->pausedProfiles(), true)) {
            return ['paused' => true, 'reason' => 'Automation is paused for browser profile "'.$profile.'" (setting browser_worker_paused_profiles).'] + $gate;
        }

        $status = $this->status($profile, null, false);
        $data = (array) ($status['data'] ?? []);
        $gate['runtime_ready'] = (bool) ($data['runtime_ready'] ?? false);
        $gate['authenticated'] = (bool) ($data['authenticated'] ?? false);
        $gate['transport'] = (string) ($data['transport'] ?? 'unknown');
        $gate['console_url'] = (string) (($data['console_url'] ?? '') ?: $gate['console_url']);
        if (! $gate['runtime_ready']) {
            $detail = (string) ($status['detail'] ?? '');

            return ['reason' => 'The '.$profile.' Browser Console runtime needs activation (not running).'.($detail !== '' && ! str_contains($detail, 'runtime is not ready') ? ' '.$detail : '')] + $gate;
        }
        if (! ($status['success'] ?? false)) {
            // The last login verification lapsed or never happened: verify it once now.
            $status = $this->status($profile, null, true);
            $data = (array) ($status['data'] ?? []);
            $gate['authenticated'] = (bool) ($data['authenticated'] ?? false);
            $gate['runtime_ready'] = (bool) ($data['runtime_ready'] ?? $gate['runtime_ready']);
            if (! ($status['success'] ?? false)) {
                return ['reason' => $gate['authenticated']
                    ? 'The '.$profile.' session is not ready: '.(string) ($status['detail'] ?? 'unknown reason.')
                    : 'Instagram is not logged in (login not verified) in the '.$profile.' session: '.(string) ($status['detail'] ?? '')] + $gate;
            }
        }
        if ($requireRoute && $this->http->routeProxyUrl($profile) === null) {
            return ['reason' => 'The '.$profile.' session has no protected route (direct connection). Assign its VPN route first.'] + $gate;
        }

        return ['ready' => true] + $gate;
    }

    public function status(string $profile, ?string $expectedUsername = null, bool $refresh = true): array
    {
        $profile = $this->config->normalizeProfile($profile);
        $account = $this->config->findAccount($profile);
        $expectedUsername = $this->config->normalizeUsername(
            $expectedUsername ?: (string) ($account['instagram_username'] ?? '')
        );

        try {
            $worker = $refresh
                ? $this->worker->readyProfile($profile, true, $expectedUsername !== '' ? $expectedUsername : null)
                : $this->worker->status($profile);
        } catch (\Throwable $error) {
            $worker = [
                'success' => false,
                'message' => 'Browser Worker could not be reached.',
                'detail' => $error->getMessage(),
                'data' => [],
            ];
        }

        $state = is_array($worker['data'] ?? null) ? $worker['data'] : [];
        $workerProfile = trim((string) ($state['profile'] ?? ''));
        $authConfigured = ($state['auth_evidence_configured'] ?? false) === true;
        $authValid = ($state['auth_evidence_valid'] ?? true) === true;
        $authVerified = ($state['auth_verified'] ?? false) === true
            || ($state['auth_evidence_verified'] ?? false) === true;
        $accountConfigured = ($state['account_evidence_configured'] ?? false) === true;
        $accountVerified = ($state['account_evidence_verified'] ?? false) === true;
        $identityRequired = $expectedUsername !== '';
        $expectedAccountVerified = ! $identityRequired
            || ($state['expected_account_binding_verified'] ?? false) === true;
        $runtimeReady = $this->runtimeReady((array) ($state['runtime_activation'] ?? []), $profile);
        $connected = ($worker['success'] ?? false) === true
            && $workerProfile === $profile
            && $runtimeReady
            && $authConfigured
            && $authValid
            && $authVerified
            && (! $identityRequired || ($accountConfigured && $accountVerified && $expectedAccountVerified));

        $transport = strtolower(trim((string) data_get($state, 'proxy.transport_mode', 'unknown')));
        if (! in_array($transport, ['direct', 'proxy', 'server85', 'extension'], true)) {
            $transport = 'unknown';
        }

        $detail = $this->detail(
            $connected,
            $worker,
            $runtimeReady,
            $authConfigured,
            $authValid,
            $authVerified,
            $identityRequired,
            $accountConfigured,
            $accountVerified,
            $expectedAccountVerified,
        );

        return [
            'success' => $connected,
            'message' => $connected
                ? 'Instagram connection is ready.'
                : 'Instagram connection needs attention.',
            'detail' => $detail,
            'data' => [
                'profile' => $profile,
                'expected_username' => $expectedUsername,
                'connected' => $connected,
                'runtime_ready' => $runtimeReady,
                'authenticated' => $authVerified,
                'account_evidence_configured' => $accountConfigured,
                'account_verified' => $accountVerified,
                'expected_account_verified' => $expectedAccountVerified,
                'transport' => $transport,
                'console_url' => $this->console->url($profile),
                'current_url' => (string) ($state['current_url'] ?? ''),
                'checked_at' => now()->toIso8601String(),
                'worker' => $state,
                'account' => $account,
            ],
        ];
    }

    private function runtimeReady(array $activation, string $profile): bool
    {
        if (($activation['schema_version'] ?? null) !== 3
            || ($activation['profile'] ?? null) !== $profile
            || ($activation['configured'] ?? null) !== true
            || ($activation['active'] ?? null) !== true) {
            return false;
        }

        foreach ([
            'desktop_transport_ready', 'viewer_ready', 'window_manager_ready',
            'readiness_recorded', 'rfb_transport_ready', 'service_account_owned',
            'x_authority_private', 'x_access_controlled', 'loopback_only',
        ] as $key) {
            if (($activation[$key] ?? null) !== true) {
                return false;
            }
        }

        return true;
    }

    private function detail(
        bool $connected,
        array $worker,
        bool $runtimeReady,
        bool $authConfigured,
        bool $authValid,
        bool $authVerified,
        bool $identityRequired,
        bool $accountConfigured,
        bool $accountVerified,
        bool $expectedAccountVerified,
    ): string {
        if ($connected) {
            return 'Worker runtime, Instagram authentication, account identity, and actual route are verified.';
        }
        if (($worker['success'] ?? false) !== true) {
            return trim((string) (($worker['detail'] ?? '') ?: ($worker['message'] ?? 'Browser Worker is unavailable.')));
        }
        if (! $runtimeReady) {
            return 'The Browser Console runtime is not ready for this profile.';
        }
        if (! $authConfigured || ! $authValid) {
            return 'Instagram authentication evidence is not configured correctly for this profile.';
        }
        if (! $authVerified) {
            return 'Open this profile in Browser Console, sign in to Instagram, then check status again.';
        }
        if ($identityRequired && ! $accountConfigured) {
            return 'The profile can prove an Instagram login, but account-bound evidence is not configured.';
        }
        if ($identityRequired && ! $accountVerified) {
            return 'Instagram is signed in, but the expected account identity was not verified.';
        }
        if ($identityRequired && ! $expectedAccountVerified) {
            return 'Browser Worker account evidence is valid, but it is bound to a different expected account.';
        }

        return 'Instagram connection evidence is incomplete.';
    }
}
