<?php

namespace hexa_package_instagram\Services\Concerns;

use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

trait VerifiesInstagramSessions
{
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
