<?php

namespace hexa_package_instagram\Services\Concerns;

use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

trait ManagesInstagramAuthentication
{

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
}
