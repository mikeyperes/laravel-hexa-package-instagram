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
        $account = $this->config->findAccount($resolved);
        $username = trim((string) ($account["instagram_username"] ?? ""));

        $activeResult = $this->browser->runAutomation($resolved, [
            [
                "type" => "wait_ms",
                "label" => "settle_current_worker_page",
                "ms" => 800,
            ],
            [
                "type" => "evaluate",
                "label" => "detect_login_state",
                "code" => self::stateProbeJs(),
            ],
        ], [
            "use_active_page" => true,
            "final" => [
                "include_screenshot" => false,
            ],
        ]);

        $activeStatus = $this->normalizeStatusResult($resolved, $activeResult);
        if ($this->statusHasUsefulWorkerPage($activeStatus)) {
            return $activeStatus;
        }
        $result = $this->browser->runAutomation($resolved, [
            [
                "type" => "goto",
                "label" => "open_instagram_home",
                "url" => "https://www.instagram.com/",
                "wait_until" => "domcontentloaded",
                "timeout_ms" => 30000,
                "wait_ms" => 2500,
            ],
            [
                "type" => "evaluate",
                "label" => "dismiss_cookies",
                "code" => self::dismissCookieBannerJs(),
            ],
            [
                "type" => "wait_ms",
                "label" => "settle_after_cookies",
                "ms" => 1000,
            ],
            [
                "type" => "evaluate",
                "label" => "continue_saved_account_chooser",
                "code" => self::continueSavedAccountChooserJs(),
                "args" => [
                    "username" => $username,
                ],
            ],
            [
                "type" => "click_if_exists",
                "label" => "click_continue_saved_account",
                "selector" => "text=Continue",
                "timeout_ms" => 7000,
                "wait_ms" => 4000,
            ],
            [
                "type" => "wait_ms",
                "label" => "settle_after_continue_saved_account",
                "ms" => 3500,
            ],
            [
                "type" => "evaluate",
                "label" => "dismiss_post_login_prompts",
                "code" => self::dismissPostLoginPromptsJs(),
            ],
            [
                "type" => "wait_ms",
                "label" => "settle_after_prompts",
                "ms" => 1200,
            ],
            [
                "type" => "evaluate",
                "label" => "detect_login_state",
                "code" => self::stateProbeJs(),
            ],
        ], [
            "final" => [
                "include_screenshot" => false,
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

        $profileRestart = $this->restartBrowserProfile($resolved);

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
                'type' => 'evaluate',
                'label' => 'dismiss_cookies',
                'code' => self::dismissCookieBannerJs(),
            ],
            [
                'type' => 'wait_ms',
                'label' => 'settle_after_cookies',
                'ms' => 1200,
            ],
            [
                'type' => 'evaluate',
                'label' => 'open_explicit_login_form',
                'code' => self::openExplicitLoginFormJs(),
            ],
            [
                'type' => 'click_if_exists',
                'label' => 'click_use_another_profile',
                'selector' => 'text=Use another profile',
                'timeout_ms' => 7000,
                'wait_ms' => 2500,
            ],
            [
                'type' => 'wait_ms',
                'label' => 'settle_after_explicit_login_form',
                'ms' => 1800,
            ],
            [
                "type" => "evaluate",
                "label" => "submit_login_form",
                "code" => self::submitLoginFormJs(),
                "args" => [
                    "username" => $username,
                    "password" => $password,
                ],
            ],
            [
                "type" => "wait_ms",
                "label" => "settle_after_submit",
                "ms" => 6500,
            ],
            [
                "type" => "evaluate",
                "label" => "submit_login_form_retry",
                "code" => self::submitLoginFormJs(),
                "args" => [
                    "username" => $username,
                    "password" => $password,
                ],
            ],
            [
                "type" => "wait_ms",
                "label" => "settle_after_submit_retry",
                "ms" => 3500,
            ],
            [
                'type' => 'evaluate',
                'label' => 'dismiss_post_login_prompts',
                'code' => self::dismissPostLoginPromptsJs(),
            ],
            [
                'type' => 'wait_ms',
                'label' => 'settle_after_prompts',
                'ms' => 1500,
            ],
            [
                'type' => 'evaluate',
                'label' => 'detect_login_state',
                'code' => self::stateProbeJs(),
            ],
        ], [
            'use_active_page' => true,
            'transport_timeout_ms' => 90000,
            'final' => [
                'include_screenshot' => false,
            ],
        ]);

        $response = $this->normalizeStatusResult($resolved, $result, 'Instagram login flow finished.');
        $response['data']['profile_restart'] = $profileRestart;

        return $response;
    }

    public function submitVerificationCode(?string $profile, string $code): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $code = trim($code);

        if ($code === '') {
            return $this->failure('Verification code is missing.', 'Enter the verification code from Instagram before submitting it.', [
                'profile' => $resolved,
            ]);
        }

        $currentStatus = $this->status($resolved);
        if (!empty($currentStatus['data']['connected'])) {
            $currentStatus['message'] = 'Instagram account is already connected.';
            $currentStatus['detail'] = 'No verification code is needed because this browser profile is already authenticated.';
            return $currentStatus;
        }

        if (empty($currentStatus['data']['verification_required'])) {
            return $this->failure(
                'Instagram is not waiting for a verification code.',
                'Run “Log in with saved credentials” first, then submit the verification code while the browser profile is on the code-entry screen.',
                [
                    'profile' => $resolved,
                    'status' => $currentStatus,
                ]
            );
        }

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'evaluate',
                'label' => 'submit_verification_code',
                'code' => self::submitVerificationCodeJs(),
                'args' => [
                    'code' => $code,
                ],
            ],
            [
                'type' => 'wait_ms',
                'label' => 'settle_after_code_submit',
                'ms' => 5000,
            ],
            [
                'type' => 'evaluate',
                'label' => 'dismiss_post_login_prompts',
                'code' => self::dismissPostLoginPromptsJs(),
            ],
            [
                'type' => 'wait_ms',
                'label' => 'settle_after_prompts',
                'ms' => 1200,
            ],
            [
                'type' => 'evaluate',
                'label' => 'detect_login_state',
                'code' => self::stateProbeJs(),
            ],
        ], [
            'final' => [
                'include_screenshot' => false,
            ],
        ]);

        return $this->normalizeStatusResult($resolved, $result, 'Instagram verification code submitted.');
    }

    public function logout(?string $profile = null): array
    {
        return $this->browser->logoutProfile($this->config->resolveProfile($profile));
    }


    public function workerScreen(?string $profile = null): array
    {
        return $this->workerScreenWithSteps($profile, [], 'Instagram worker screen loaded.');
    }

    public function clickWorkerScreen(?string $profile, float $x, float $y): array
    {
        return $this->workerScreenWithSteps($profile, [
            [
                'type' => 'mouse_click',
                'label' => 'click_worker_screen',
                'x' => $x,
                'y' => $y,
                'wait_ms' => 1800,
            ],
        ], 'Clicked inside the server worker browser.');
    }

    public function reloadWorkerScreen(?string $profile = null): array
    {
        return $this->workerScreenWithSteps($profile, [
            [
                'type' => 'reload',
                'label' => 'reload_worker_screen',
                'timeout_ms' => 30000,
                'wait_ms' => 1800,
            ],
        ], 'Reloaded the server worker browser.');
    }

    private function workerScreenWithSteps(?string $profile, array $prefixSteps, string $message): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $steps = array_merge($prefixSteps, [
            [
                'type' => 'wait_ms',
                'label' => 'settle_worker_screen',
                'ms' => 700,
            ],
            [
                'type' => 'screenshot',
                'label' => 'worker_screen',
            ],
            [
                'type' => 'evaluate',
                'label' => 'detect_login_state',
                'code' => self::stateProbeJs(),
            ],
        ]);

        $result = $this->browser->runAutomation($resolved, $steps, [
            'use_active_page' => true,
            'transport_timeout_ms' => 45000,
            'final' => [
                'include_screenshot' => false,
            ],
        ]);

        $status = $this->normalizeStatusResult($resolved, $result, $message);
        $screen = $this->stepByLabel($result, 'worker_screen');
        $worker = is_array($result['data'] ?? null) ? $result['data'] : [];
        $final = is_array($worker['final'] ?? null) ? $worker['final'] : [];

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $message,
            'detail' => (string) ($status['detail'] ?? ($result['detail'] ?? '')),
            'status_code' => (int) ($result['status_code'] ?? 200),
            'status' => $status,
            'data' => [
                'profile' => $resolved,
                'current_url' => (string) (($final['final_url'] ?? '') ?: ($worker['current_url'] ?? '')),
                'title' => (string) (($final['title'] ?? '') ?: ($worker['last_title'] ?? '')),
                'screenshot_data_url' => $screen['screenshot_data_url'] ?? null,
                'screenshot_error' => $screen['screenshot_error'] ?? null,
                'worker' => $worker,
            ],
        ];
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
const bodyLower = bodyText.toLowerCase();
const isVisible = (node) => {
  if (!node) return false;
  const style = window.getComputedStyle(node);
  const rect = node.getBoundingClientRect();
  return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && rect.width > 0 && rect.height > 0;
};
const inputs = Array.from(document.querySelectorAll('input')).filter(isVisible);
const visibleTextInputs = inputs.filter((node) => ['text', 'email', 'tel', 'search', ''].includes((node.type || '').toLowerCase()));
const visiblePasswordInputs = inputs.filter((node) => (node.type || '').toLowerCase() === 'password');
const visibleLoginIdentityInputs = visibleTextInputs.filter((node) => /username|email|phone|mobile|login/i.test([node.name, node.id, node.getAttribute('aria-label'), node.getAttribute('placeholder')].filter(Boolean).join(' ')));
const loginForm = visiblePasswordInputs.length > 0 && (visibleLoginIdentityInputs.length > 0 || /log into instagram|log in to instagram|forgot password/i.test(bodyLower));
const accountChooserDetected = /see everyday moments from your close friends|use another profile/i.test(bodyLower) && /\bcontinue\b/i.test(bodyText) && !loginForm;
const verificationRequired = /\/auth_platform\/codeentry/i.test(location.pathname)
  || /check your whatsapp messages|enter the code we sent to your whatsapp account|check your email|check your inbox|enter the code we sent to|confirmation code|security code|try another way/i.test(bodyLower);
const verificationChannel = verificationRequired
  ? (/whatsapp/i.test(bodyText)
      ? 'whatsapp'
      : (/check your email|check your inbox|email/i.test(bodyLower)
          ? 'email'
          : (/text message|sms/i.test(bodyLower) ? 'sms' : 'code')))
  : '';
const captchaRequired = /i.?m not a robot|recaptcha|security check|combat harmful conduct|maintain the integrity of our products|google.?s privacy policy|google.?s terms of use/i.test(bodyText) || Boolean(document.querySelector("iframe[src*=\"recaptcha\"], iframe[title*=\"recaptcha\" i]"));
const challenge = verificationRequired || captchaRequired || /challenge|checkpoint|two_factor|suspended/i.test(location.pathname + " " + bodyText);
const alerts = Array.from(document.querySelectorAll('[role="alert"]')).filter(isVisible).map((node) => (node.innerText || '').trim()).filter(Boolean);
const visibleButtons = Array.from(document.querySelectorAll('button')).filter(isVisible).map((node) => (node.innerText || '').trim()).filter(Boolean).slice(0, 20);
const avatarLinks = Array.from(document.querySelectorAll('a[href]')).map((node) => node.getAttribute('href') || '').filter(Boolean);
const authenticatedMarkers = avatarLinks.filter((href) => /^\/(accounts\/edit|direct\/inbox|explore\/|[A-Za-z0-9._]+\/?)$/.test(href));
const strongNavSelectors = [
  'a[href="/direct/inbox/"]',
  'a[href="/accounts/activity/"]',
  'a[href="/accounts/edit/"]',
  'svg[aria-label="Home"]',
  'svg[aria-label="Search"]',
  'svg[aria-label="Explore"]',
  'svg[aria-label="Reels"]',
  'svg[aria-label="Messenger"]',
  'svg[aria-label="Notifications"]',
  'svg[aria-label="Profile"]',
];
const strongNavMatches = strongNavSelectors.filter((selector) => document.querySelector(selector));
const loginCopyDetected = !accountChooserDetected && /log into instagram|log in to instagram|mobile number, username or email|forgot password|log in with facebook|create new account|sign up/i.test(bodyLower);
const storyViewerDetected = /^\/stories\//i.test(location.pathname) && /instagram/i.test(document.title) && !loginCopyDetected && !loginForm && !challenge;
const navTextLabels = ['home', 'reels', 'messages', 'search', 'explore', 'notifications', 'create', 'dashboard', 'profile'];
const navTextMatches = navTextLabels.filter((label) => new RegExp('\\b' + label + '\\b', 'i').test(bodyText));
const profileOwnerControls = /edit profile|view archive|share photos|professional dashboard|followers|following/i.test(bodyText) && /instagram/i.test(document.title + ' ' + bodyText);
const connected = (strongNavMatches.length > 0 || authenticatedMarkers.length >= 3 || storyViewerDetected || navTextMatches.length >= 4 || profileOwnerControls) && !loginForm && !challenge && !/\/accounts\/login/i.test(location.pathname);

return {
  url: location.href,
  path: location.pathname,
  title: document.title,
  login_form: loginForm,
  verification_required: verificationRequired,
  verification_channel: verificationChannel,
  captcha_required: captchaRequired,
  challenge,
  login_copy_detected: loginCopyDetected,
  account_chooser_detected: accountChooserDetected,
  alerts,
  visible_buttons: visibleButtons,
  authenticated_markers: authenticatedMarkers.slice(0, 12),
  auth_indicator_count: authenticatedMarkers.length,
  strong_nav_count: strongNavMatches.length,
  strong_nav_matches: strongNavMatches,
  story_viewer_detected: storyViewerDetected,
  nav_text_count: navTextMatches.length,
  nav_text_matches: navTextMatches,
  profile_owner_controls: profileOwnerControls,
  visible_text_inputs: visibleTextInputs.map((node) => node.getAttribute('aria-label') || node.getAttribute('placeholder') || node.name || 'text-input').slice(0, 8),
  visible_password_inputs: visiblePasswordInputs.length,
  body_excerpt: bodyText.slice(0, 1200),
  connected,
};
JS;
    }

    public static function dismissCookieBannerJs(): string
    {
        return <<<'JS'
(() => {
  const matches = [
    'Allow all cookies',
    'Allow all',
    'Accept all',
    'Allow essential and optional cookies',
    'Accept cookies',
  ];
  const buttons = Array.from(document.querySelectorAll('button'));
  const target = buttons.find((button) => {
    const text = (button.innerText || '').trim().toLowerCase();
    return matches.some((candidate) => text.includes(candidate.toLowerCase()));
  });
  if (target) {
    target.click();
    return { clicked: true, text: (target.innerText || '').trim() };
  }
  return { clicked: false };
})()
JS;
    }

    public static function dismissPostLoginPromptsJs(): string
    {
        return <<<'JS'
(() => {
  const matches = [
    'Not now',
    'Not Now',
    'Cancel',
    'Skip',
  ];
  const buttons = Array.from(document.querySelectorAll('button'));
  const clicked = [];
  for (const button of buttons) {
    const text = (button.innerText || '').trim();
    if (!text) continue;
    if (matches.some((candidate) => text.toLowerCase() === candidate.toLowerCase())) {
      button.click();
      clicked.push(text);
    }
  }
  return { clicked };
})()
JS;
    }


    public static function openExplicitLoginFormJs(): string
    {
        return <<<'JS'
return (() => {
  const isVisible = (node) => {
    if (!node) return false;
    const style = window.getComputedStyle(node);
    const rect = node.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && rect.width > 0 && rect.height > 0;
  };

  const bodyText = (document.body?.innerText || '').replace(/\s+/g, ' ').trim();
  const wanted = [
    'log in to another account',
    'login to another account',
    'use another account',
    'use another profile',
    'switch accounts',
    'not you',
  ];
  const blocked = ['continue', 'continue as'];
  const candidates = Array.from(document.querySelectorAll('button, [role=button], a, div, span'))
    .filter(isVisible)
    .map((node) => ({ node, text: (node.innerText || node.textContent || '').replace(/\s+/g, ' ').trim() }))
    .filter((row) => row.text);

  const target = candidates
    .filter((row) => {
      const text = row.text.toLowerCase();
      if (blocked.some((blockedText) => text === blockedText || text.startsWith(blockedText + ' '))) {
        return false;
      }
      return wanted.some((wantedText) => text.includes(wantedText));
    })
    .sort((left, right) => left.text.length - right.text.length)[0] || null;

  const clickTarget = target?.node?.closest('button, [role=button], a') || target?.node || null;
  const summary = {
    clicked: false,
    button_text: target ? target.text : '',
    click_target: clickTarget ? (clickTarget.tagName + (clickTarget.getAttribute('role') ? '[role=' + clickTarget.getAttribute('role') + ']' : '')) : '',
    visible_actions: candidates.map((row) => row.text).filter((text) => text.length <= 90).slice(0, 24),
    body_excerpt: bodyText.slice(0, 600),
  };

  if (clickTarget) {
    clickTarget.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
    clickTarget.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
    clickTarget.click();
    summary.clicked = true;
  }

  return summary;
})()
JS;
    }

    public static function continueSavedAccountChooserJs(): string
    {
        return <<<'JS'
return ((args) => {
  const isVisible = (node) => {
    if (!node) return false;
    const style = window.getComputedStyle(node);
    const rect = node.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && rect.width > 0 && rect.height > 0;
  };

  const bodyText = (document.body?.innerText || '').trim();
  const bodyLower = bodyText.toLowerCase();
  const username = String(args?.username || '').replace(/^@+/, '').trim().toLowerCase();
  const clickableSelector = 'button, [role=button], a, div, span';
  const candidates = Array.from(document.querySelectorAll(clickableSelector))
    .filter(isVisible)
    .map((node) => ({ node, text: (node.innerText || node.textContent || '').replace(/\s+/g, ' ').trim() }))
    .filter((row) => row.text);
  const continueCandidate = candidates.find((row) => row.text.toLowerCase() === 'continue')
    || candidates.find((row) => /^continue$/i.test(row.text))
    || candidates.find((row) => /continue/i.test(row.text) && row.text.length <= 40);
  const chooserDetected = Boolean(continueCandidate) && /use another profile|create new account|see everyday moments from your close friends/i.test(bodyText);
  const profileDetected = username === '' || bodyLower.includes(username);
  const clickTarget = continueCandidate?.node?.closest('button, [role=button], a') || continueCandidate?.node || null;
  const summary = {
    detected: chooserDetected,
    profile_detected: profileDetected,
    clicked: false,
    button_text: continueCandidate ? continueCandidate.text : '',
    click_target: clickTarget ? (clickTarget.tagName + (clickTarget.getAttribute('role') ? '[role=' + clickTarget.getAttribute('role') + ']' : '')) : '',
    visible_actions: candidates.map((row) => row.text).filter((text) => text.length <= 80).slice(0, 18),
    body_excerpt: bodyText.slice(0, 500),
  };

  if (chooserDetected && profileDetected && clickTarget) {
    clickTarget.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
    clickTarget.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
    clickTarget.click();
    summary.clicked = true;
  }

  return summary;
})(args)
JS;
    }

    public static function submitLoginFormJs(): string
    {
        return <<<'JS'
return ((args) => {
  const isVisible = (node) => {
    if (!node) return false;
    const style = window.getComputedStyle(node);
    const rect = node.getBoundingClientRect();
  return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && rect.width > 0 && rect.height > 0;
  };

  const setNativeValue = (node, value) => {
    try {
      node.focus({ preventScroll: true });
    } catch (error) {
      node.focus();
    }
    const prototype = Object.getPrototypeOf(node);
    const descriptor = Object.getOwnPropertyDescriptor(prototype, 'value');
    if (descriptor?.set) {
      descriptor.set.call(node, value);
    } else {
      node.value = value;
    }
    for (const eventName of ['input', 'change', 'keyup', 'blur']) {
      node.dispatchEvent(new Event(eventName, { bubbles: true }));
    }
  };

  const allInputs = Array.from(document.querySelectorAll('input')).filter(isVisible).filter((node) => !node.disabled);
  const usernameInput = document.querySelector('input[name="username"]')
    || allInputs.find((node) => ['text', 'email', 'tel', 'search', ''].includes((node.type || '').toLowerCase()));
  const passwordInput = document.querySelector('input[name="password"]')
    || allInputs.find((node) => (node.type || '').toLowerCase() === 'password');

  const summary = {
    found_username: Boolean(usernameInput),
    found_password: Boolean(passwordInput),
    visible_inputs: allInputs.map((node) => ({
      type: node.type || '',
      name: node.name || '',
      aria: node.getAttribute('aria-label') || '',
      placeholder: node.getAttribute('placeholder') || '',
    })).slice(0, 10),
    submitted: false,
  };

  if (!passwordInput) {
    summary.reason = 'Could not find a visible Instagram password input.';
    return summary;
  }

  if (usernameInput) {
    setNativeValue(usernameInput, String(args?.username || ''));
  }
  setNativeValue(passwordInput, String(args?.password || ''));

  const buttons = Array.from(document.querySelectorAll('button')).filter(isVisible).filter((node) => !node.disabled);
  const submitButton = document.querySelector('button[type="submit"], input[type="submit"]')
    || buttons.find((node) => /log in|login|sign in/i.test((node.innerText || '').trim()))
    || allInputs.find((node) => (node.type || '').toLowerCase() === 'submit' && /log in|login|sign in/i.test((node.value || '').trim()));

  if (submitButton) {
    submitButton.click();
    summary.submitted = true;
    summary.submit_text = (submitButton.innerText || '').trim();
  } else if (usernameInput.form) {
    if (typeof usernameInput.form.requestSubmit === 'function') {
      usernameInput.form.requestSubmit();
    } else {
      usernameInput.form.submit();
    }
    summary.submitted = true;
    summary.submit_text = 'form_submit';
  } else {
    summary.reason = 'Login inputs were found, but no submit button or form was available.';
  }

  return summary;
})(args)
JS;
    }

    public static function submitVerificationCodeJs(): string
    {
        return <<<'JS'
((args) => {
  const isVisible = (node) => {
    if (!node) return false;
    const style = window.getComputedStyle(node);
    const rect = node.getBoundingClientRect();
  return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && rect.width > 0 && rect.height > 0;
  };

  const setNativeValue = (node, value) => {
    const prototype = Object.getPrototypeOf(node);
    const descriptor = Object.getOwnPropertyDescriptor(prototype, 'value');
    if (descriptor?.set) {
      descriptor.set.call(node, value);
    } else {
      node.value = value;
    }
    for (const eventName of ['input', 'change', 'keyup']) {
      node.dispatchEvent(new Event(eventName, { bubbles: true }));
    }
  };

  const code = String(args?.code || '').trim();
  const visibleInputs = Array.from(document.querySelectorAll('input')).filter(isVisible).filter((node) => !node.disabled);
  const codeInput = document.querySelector('input[name="code"]')
    || visibleInputs.find((node) => /code/i.test((node.name || '') + ' ' + (node.getAttribute('aria-label') || '') + ' ' + (node.getAttribute('placeholder') || '')))
    || visibleInputs.find((node) => ['text', 'tel', 'number', ''].includes((node.type || '').toLowerCase()));

  const summary = {
    found_code_input: Boolean(codeInput),
    submitted: false,
    visible_inputs: visibleInputs.map((node) => ({
      type: node.type || '',
      name: node.name || '',
      aria: node.getAttribute('aria-label') || '',
      placeholder: node.getAttribute('placeholder') || '',
    })).slice(0, 10),
  };

  if (!codeInput) {
    summary.reason = 'Could not find a visible Instagram verification code input.';
    return summary;
  }

  setNativeValue(codeInput, code);

  const buttons = Array.from(document.querySelectorAll('button')).filter(isVisible).filter((node) => !node.disabled);
  const submitButton = document.querySelector('button[type="submit"]')
    || buttons.find((node) => /continue|confirm|submit|next/i.test((node.innerText || '').trim()));

  if (submitButton) {
    submitButton.click();
    summary.submitted = true;
    summary.submit_text = (submitButton.innerText || '').trim();
  } else if (codeInput.form) {
    if (typeof codeInput.form.requestSubmit === 'function') {
      codeInput.form.requestSubmit();
    } else {
      codeInput.form.submit();
    }
    summary.submitted = true;
    summary.submit_text = 'form_submit';
  } else {
    summary.reason = 'Verification code input was found, but no continue button or form was available.';
  }

  return summary;
})(args)
JS;
    }

    private function statusHasUsefulWorkerPage(array $status): bool
    {
        $probe = $status["data"]["probe"] ?? [];
        if (!is_array($probe)) {
            return false;
        }

        $worker = $status["data"]["worker"] ?? [];
        $workerFinal = is_array($worker["final"] ?? null) ? $worker["final"] : [];
        $workerUrl = is_array($worker) ? strtolower(trim((string) (($workerFinal["final_url"] ?? "") ?: ($worker["current_url"] ?? "")))) : "";
        $url = strtolower(trim((string) ($probe["url"] ?? "")));
        if (($url === "" || str_starts_with($url, "about:")) && ($workerUrl === "" || str_starts_with($workerUrl, "about:"))) {
            return false;
        }

        if (str_contains($workerUrl, "instagram.com")) {
            return true;
        }

        return str_contains($url, "instagram.com")
            || !empty($probe["captcha_required"])
            || !empty($probe["challenge"])
            || !empty($probe["verification_required"])
            || !empty($probe["profile_owner_controls"])
            || !empty($probe["login_form"]);
    }

    private function normalizeStatusResult(string $profile, array $result, string $successMessage = 'Instagram account status loaded.'): array
    {
        $probe = $this->resultByLabel($result, 'detect_login_state');

        $probeConnected = (bool) ($probe['connected'] ?? false);
        $loginForm = (bool) ($probe['login_form'] ?? false);
        $verificationRequired = (bool) ($probe['verification_required'] ?? false);
        $verificationChannel = (string) ($probe["verification_channel"] ?? "");
        $captchaRequired = (bool) ($probe["captcha_required"] ?? false);
        $challenge = (bool) ($probe['challenge'] ?? false);
        $loginCopyDetected = (bool) ($probe['login_copy_detected'] ?? false);
        $accountChooserDetected = (bool) ($probe["account_chooser_detected"] ?? false);
        $workerData = $result['data'] ?? [];
        $finalUrl = '';
        if (is_array($workerData)) {
            $finalUrl = strtolower((string) (($workerData['final']['final_url'] ?? '') ?: ($workerData['current_url'] ?? '')));
        }
        if (str_contains($finalUrl, '/recaptcha')) {
            $captchaRequired = true;
        }
        if (str_contains($finalUrl, '/challenge') || str_contains($finalUrl, '/checkpoint') || str_contains($finalUrl, '/auth_platform/')) {
            $challenge = true;
        }
        $strongNavCount = (int) ($probe['strong_nav_count'] ?? 0);
        $authIndicatorCount = (int) ($probe['auth_indicator_count'] ?? 0);
        $storyViewerDetected = (bool) ($probe['story_viewer_detected'] ?? false);
        $navTextCount = (int) ($probe["nav_text_count"] ?? 0);
        $profileOwnerControls = (bool) ($probe["profile_owner_controls"] ?? false);
        $alerts = $probe['alerts'] ?? [];
        $connected = $probeConnected && !$loginForm && !$challenge && ($strongNavCount > 0 || $storyViewerDetected || $authIndicatorCount >= 3 || $navTextCount >= 4 || $profileOwnerControls);
        $detail = $connected
            ? 'The browser session is authenticated and reached Instagram without a login form.'
            : ($verificationRequired
                ? match ($verificationChannel) {
                    'email' => 'Instagram accepted the saved credentials and is waiting for the verification code it sent by email.',
                    'sms' => 'Instagram accepted the saved credentials and is waiting for the verification code it sent by text message.',
                    'whatsapp' => 'Instagram accepted the saved credentials and is waiting for the verification code it sent to the linked WhatsApp number.',
                    default => 'Instagram accepted the saved credentials and is waiting for a verification code before it can finish the login.',
                }
                : ($challenge
                ? 'Instagram returned a challenge/checkpoint state for this browser profile.'
                : (($loginForm || $loginCopyDetected)
                    ? 'Instagram still shows the login form for this browser profile.'
                    : 'Instagram did not confirm an authenticated session for this profile.')));

        if (!$connected && $captchaRequired) {
            $detail = "Meta/Instagram returned a reCAPTCHA security check for this worker profile.";
        }

        if (!$connected && !empty($alerts)) {
            $detail .= ' Alerts: ' . implode(' | ', array_slice($alerts, 0, 3));
        }

        $workerDetail = trim((string) ($result['detail'] ?? $result['message'] ?? ''));

        if (!$connected && !$challenge && !$verificationRequired && $workerDetail !== "" && !str_contains($detail, $workerDetail)) {
            $detail .= ' Worker detail: ' . $workerDetail;
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
                "verification_required" => $verificationRequired,
                "captcha_required" => $captchaRequired,
                'verification_channel' => $verificationChannel,
                'challenge' => $challenge,
                'story_viewer_detected' => $storyViewerDetected,
                "account_chooser_detected" => $accountChooserDetected,
                'probe' => $probe,
                'worker' => $result['data'] ?? [],
                'account' => $this->config->findAccount($profile),
            ],
        ];
    }

    private function stepByLabel(array $result, string $label): array
    {
        foreach (($result['data']['results'] ?? []) as $step) {
            if (($step['label'] ?? null) === $label && is_array($step)) {
                return $step;
            }
        }

        return [];
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

    private function restartBrowserProfile(string $profile): array
    {
        $closed = $this->browser->closeProfile($profile);
        usleep(350000);
        $launched = $this->browser->launchProfile($profile);
        usleep(600000);

        return [
            "closed" => $closed,
            "launched" => $launched,
        ];
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
