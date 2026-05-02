<?php

namespace hexa_package_instagram\Services;

use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

class InstagramAccountSessionService
{
    public function __construct(
        private InstagramConfigRepository $config,
        private BrowserWorkerBridgeContract $browser,
        private CredentialService $credentials,
    ) {
    }

    public function credentialKey(string $profile): string
    {
        return 'account_password_' . $this->config->normalizeProfile($profile);
    }

    public function storePassword(string $profile, string $password): void
    {
        $password = trim($password);
        if ($password === '') {
            return;
        }

        $this->credentials->store('instagram', $this->credentialKey($profile), $password);
    }

    public function deletePassword(string $profile): void
    {
        $this->credentials->delete('instagram', $this->credentialKey($profile));
    }

    public function hasPassword(string $profile): bool
    {
        return $this->credentials->exists('instagram', $this->credentialKey($profile));
    }

    public function maskedPassword(string $profile): string
    {
        return $this->credentials->getMasked('instagram', $this->credentialKey($profile));
    }

    public function password(string $profile): ?string
    {
        return $this->credentials->get('instagram', $this->credentialKey($profile));
    }

    public function status(?string $profile = null): array
    {
        $resolved = $this->config->resolveProfile($profile);

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'goto',
                'label' => 'open_instagram_home',
                'url' => 'https://www.instagram.com/',
                'wait_until' => 'domcontentloaded',
                'timeout_ms' => 30000,
                'wait_ms' => 2500,
            ],
            [
                'type' => 'click_if_exists',
                'label' => 'allow_cookies',
                'selector' => 'button:has-text("Allow all cookies")',
                'timeout_ms' => 4000,
                'wait_ms' => 1000,
            ],
            [
                'type' => 'evaluate',
                'label' => 'detect_login_state',
                'code' => self::stateProbeJs(),
            ],
        ], [
            'final' => [
                'include_screenshot' => true,
            ],
        ]);

        return $this->normalizeStatusResult($resolved, $result);
    }

    public function login(?string $profile = null): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $account = $this->config->findAccount($resolved);

        $currentStatus = $this->status($resolved);
        if (!empty($currentStatus['data']['connected'])) {
            $currentStatus['message'] = 'Instagram account is already connected.';
            $currentStatus['detail'] = 'This browser profile already has an authenticated Instagram session.';
            return $currentStatus;
        }

        if (!$account) {
            return $this->failure('Instagram account profile not found.', 'Save the Instagram account profile before trying to log in.', [
                'profile' => $resolved,
            ]);
        }

        $username = trim((string) ($account['instagram_username'] ?? ''));
        $password = $this->password($resolved);

        if ($username === '') {
            return $this->failure('Instagram username is missing.', 'Save the Instagram username on the Accounts page first.', [
                'profile' => $resolved,
                'account' => $account,
            ]);
        }

        if (!$password) {
            return $this->failure('Instagram password is missing.', 'Save the Instagram password for this profile on the Accounts page first.', [
                'profile' => $resolved,
                'account' => $account,
            ]);
        }

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'goto',
                'label' => 'open_login_page',
                'url' => 'https://www.instagram.com/accounts/login/',
                'wait_until' => 'domcontentloaded',
                'timeout_ms' => 30000,
                'wait_ms' => 2500,
            ],
            [
                'type' => 'click_if_exists',
                'label' => 'allow_cookies',
                'selector' => 'button:has-text("Allow all cookies")',
                'timeout_ms' => 4000,
                'wait_ms' => 1000,
            ],
            [
                'type' => 'wait_for_selector',
                'label' => 'wait_username',
                'selector' => 'input[name="username"]',
                'state' => 'visible',
                'timeout_ms' => 15000,
            ],
            [
                'type' => 'fill',
                'label' => 'fill_username',
                'selector' => 'input[name="username"]',
                'value' => $username,
                'timeout_ms' => 15000,
            ],
            [
                'type' => 'fill',
                'label' => 'fill_password',
                'selector' => 'input[name="password"]',
                'value' => $password,
                'timeout_ms' => 15000,
            ],
            [
                'type' => 'click',
                'label' => 'submit_login',
                'selector' => 'button[type="submit"]',
                'timeout_ms' => 15000,
                'wait_ms' => 5000,
            ],
            [
                'type' => 'click_if_exists',
                'label' => 'dismiss_save_info',
                'selector' => 'button:has-text("Not now"), button:has-text("Not Now")',
                'timeout_ms' => 4000,
                'wait_ms' => 1500,
            ],
            [
                'type' => 'evaluate',
                'label' => 'detect_login_state',
                'code' => self::stateProbeJs(),
            ],
        ], [
            'final' => [
                'include_screenshot' => true,
            ],
        ]);

        return $this->normalizeStatusResult($resolved, $result, 'Instagram login flow finished.');
    }

    public function logout(?string $profile = null): array
    {
        return $this->browser->logoutProfile($this->config->resolveProfile($profile));
    }

    public function integrityTest(?string $profile = null): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $health = $this->browser->health();
        $status = $this->status($resolved);

        $workerReachable = (bool) ($health['success'] ?? false);
        $connected = (bool) ($status['data']['connected'] ?? false);

        return [
            'success' => $workerReachable && $connected,
            'message' => $workerReachable
                ? ($connected ? 'Browser worker is healthy and the Instagram account is connected.' : 'Browser worker is healthy, but the Instagram account is not connected yet.')
                : (string) ($health['message'] ?? 'Browser worker is not reachable.'),
            'detail' => $workerReachable
                ? ($connected
                    ? 'The active Instagram browser session is authenticated and usable.'
                    : (string) ($status['detail'] ?? 'Save the account credentials, log in on the Accounts page, then run the test again.'))
                : (string) ($health['detail'] ?? ''),
            'status_code' => (int) ($health['status_code'] ?? 0),
            'data' => [
                'profile' => $resolved,
                'worker_health' => $health,
                'instagram_status' => $status,
                'worker_reachable' => $workerReachable,
                'connected' => $connected,
                'active_account' => $this->config->findAccount($resolved),
            ],
        ];
    }

    public function accountPresentation(array $account): array
    {
        $profile = (string) ($account['profile'] ?? '');

        return array_merge($account, [
            'password_configured' => $profile !== '' ? $this->hasPassword($profile) : false,
            'password_masked' => $profile !== '' ? $this->maskedPassword($profile) : '',
        ]);
    }

    public static function stateProbeJs(): string
    {
        return <<<'JS'
const bodyText = (document.body?.innerText || '').trim();
const loginForm = Boolean(document.querySelector('input[name="username"], input[name="password"]'));
const challenge = /challenge|checkpoint|two_factor|suspended/i.test(`${location.pathname} ${bodyText}`);
const alerts = Array.from(document.querySelectorAll('[role="alert"]')).map((node) => (node.innerText || '').trim()).filter(Boolean);
const visibleButtons = Array.from(document.querySelectorAll('button')).map((node) => (node.innerText || '').trim()).filter(Boolean).slice(0, 20);
const avatarLinks = Array.from(document.querySelectorAll('a[href]')).map((node) => node.getAttribute('href') || '').filter(Boolean);
const authenticatedMarkers = avatarLinks.filter((href) => /^\/(accounts\/edit|direct\/inbox|explore\/|[A-Za-z0-9._]+\/?)$/.test(href));

return {
  url: location.href,
  path: location.pathname,
  title: document.title,
  login_form: loginForm,
  challenge,
  alerts,
  visible_buttons: visibleButtons,
  authenticated_markers: authenticatedMarkers.slice(0, 12),
  auth_indicator_count: authenticatedMarkers.length,
  body_excerpt: bodyText.slice(0, 1200),
  connected: authenticatedMarkers.length > 0 && !loginForm && !challenge && !/\/accounts\/login/i.test(location.pathname),
};
JS;
    }

    private function normalizeStatusResult(string $profile, array $result, string $successMessage = 'Instagram account status loaded.'): array
    {
        $probe = $this->resultByLabel($result, 'detect_login_state');

        $connected = (bool) ($probe['connected'] ?? false);
        $loginForm = (bool) ($probe['login_form'] ?? false);
        $challenge = (bool) ($probe['challenge'] ?? false);
        $alerts = $probe['alerts'] ?? [];
        $detail = $connected
            ? 'The browser session is authenticated and reached Instagram without a login form.'
            : ($challenge
                ? 'Instagram returned a challenge/checkpoint state for this browser profile.'
                : ($loginForm
                    ? 'Instagram still shows the login form for this browser profile.'
                    : 'Instagram did not confirm an authenticated session for this profile.'));

        if (!$connected && !empty($alerts)) {
            $detail .= ' Alerts: ' . implode(' | ', array_slice($alerts, 0, 3));
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $connected ? $successMessage : 'Instagram account is not connected yet.',
            'detail' => $detail,
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => [
                'profile' => $profile,
                'connected' => $connected,
                'login_form' => $loginForm,
                'challenge' => $challenge,
                'probe' => $probe,
                'worker' => $result['data'] ?? [],
                'account' => $this->config->findAccount($profile),
            ],
        ];
    }

    private function resultByLabel(array $result, string $label): array
    {
        foreach (($result['data']['results'] ?? []) as $step) {
            if (($step['label'] ?? null) === $label && is_array($step['result'] ?? null)) {
                return $step['result'];
            }
        }

        return [];
    }

    private function failure(string $message, string $detail, array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'detail' => $detail,
            'status_code' => 0,
            'data' => $data,
        ];
    }
}
