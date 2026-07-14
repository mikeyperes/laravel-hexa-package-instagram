<?php

namespace hexa_package_instagram\Services\Concerns;

use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

trait ControlsInstagramWorkerScreen
{


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
}
