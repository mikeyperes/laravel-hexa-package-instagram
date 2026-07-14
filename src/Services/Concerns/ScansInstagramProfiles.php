<?php

namespace hexa_package_instagram\Services\Concerns;

use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

trait ScansInstagramProfiles
{

    public function importPost(string $url, bool $includeImageData = true): array
    {
        return $this->imports->importPost($url, $includeImageData);
    }

    public function profileScan(?string $profile, string $username, int $limit = 12): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $username = $this->config->normalizeUsername($username);

        if ($username === '') {
            return $this->failure('Instagram username is required.', 'Provide an Instagram username before running the profile scan.');
        }

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'goto',
                'label' => 'open_profile',
                'url' => 'https://www.instagram.com/' . $username . '/',
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
                'label' => 'extract_profile',
                'code' => self::profileProbeJs(),
                'args' => ['limit' => max(1, min($limit, 30))],
            ],
        ], [
            'transport_timeout_ms' => max(90000, ($limit * 4000)),
            'final' => [
                'include_screenshot' => true,
            ],
        ]);

        $probe = $this->resultByLabel($result, 'extract_profile');
        if ($this->isLoginRedirect($result, $probe)) {
            return [
                'success' => false,
                'message' => 'Instagram profile scan requires a connected account.',
                'detail' => 'The browser worker was redirected back to the Instagram login flow instead of the requested profile.',
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => [
                    'profile' => $resolved,
                    'instagram_username' => $username,
                    'scan' => $probe,
                    'worker' => $result['data'] ?? [],
                ],
            ];
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (bool) ($result['success'] ?? false) ? 'Instagram profile scan completed.' : (string) ($result['message'] ?? 'Instagram profile scan failed.'),
            'detail' => (bool) ($result['success'] ?? false)
                ? 'Rendered DOM data came from the authenticated browser worker profile.'
                : (string) ($result['detail'] ?? ''),
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => [
                'profile' => $resolved,
                'instagram_username' => $username,
                'scan' => $probe,
                'worker' => $result['data'] ?? [],
            ],
        ];
    }

    public function followingScan(?string $profile, string $username, int $limit = 80): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $username = $this->config->normalizeUsername($username);
        $limit = max(10, min($limit, 500));

        if ($username === '') {
            return $this->failure('Instagram username is required.', 'Provide the attached Instagram username before scanning the following list.');
        }

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'goto',
                'label' => 'open_profile',
                'url' => 'https://www.instagram.com/' . $username . '/',
                'wait_until' => 'domcontentloaded',
                'timeout_ms' => 30000,
                'wait_ms' => 4000,
            ],
            [
                'type' => 'click_if_exists',
                'label' => 'allow_cookies',
                'selector' => 'button:has-text("Allow all cookies")',
                'timeout_ms' => 4000,
                'wait_ms' => 1000,
            ],
            [
                'type' => 'click_if_exists',
                'label' => 'open_following_dialog',
                'selector' => 'a:has-text("Following"), button:has-text("Following"), div[role="button"]:has-text("Following")',
                'timeout_ms' => 7000,
                'wait_ms' => 1400,
            ],
            [
                'type' => 'wait_ms',
                'label' => 'settle_following_dialog',
                'ms' => 1200,
            ],
            [
                'type' => 'evaluate',
                'label' => 'extract_following',
                'code' => self::followingProbeJs(),
                'args' => [
                    'limit' => $limit,
                ],
            ],
        ], [
            'transport_timeout_ms' => max(90000, ($limit * 180)),
            'final' => [
                'include_screenshot' => true,
            ],
        ]);

        $probe = $this->resultByLabel($result, 'extract_following');
        if ($this->isLoginRedirect($result, $probe)) {
            return [
                'success' => false,
                'message' => 'Instagram following scan requires a connected account.',
                'detail' => 'The browser worker was redirected back to the Instagram login flow instead of the selected account profile.',
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => [
                    'profile' => $resolved,
                    'instagram_username' => $username,
                    'scan' => $probe,
                    'worker' => $result['data'] ?? [],
                ],
            ];
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (bool) ($result['success'] ?? false) ? 'Instagram following scan completed.' : (string) ($result['message'] ?? 'Instagram following scan failed.'),
            'detail' => (bool) ($result['success'] ?? false)
                ? 'The selected attached account profile opened successfully and the current following list was extracted from the live dialog.'
                : (string) ($result['detail'] ?? ''),
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => [
                'profile' => $resolved,
                'instagram_username' => $username,
                'scan' => $probe,
                'worker' => $result['data'] ?? [],
            ],
        ];
    }

    public function activeStoryCandidatesScan(?string $profile, string $currentUsername, int $limit = 24): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $currentUsername = $this->config->normalizeUsername($currentUsername);
        $limit = max(6, min($limit, 40));

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'goto',
                'label' => 'open_home',
                'url' => 'https://www.instagram.com/',
                'wait_until' => 'domcontentloaded',
                'timeout_ms' => 30000,
                'wait_ms' => 3500,
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
                'label' => 'extract_story_candidates',
                'code' => self::activeStoryCandidatesProbeJs(),
                'args' => [
                    'limit' => $limit,
                    'current_username' => $currentUsername,
                ],
            ],
        ], [
            'transport_timeout_ms' => 90000,
            'final' => [
                'include_screenshot' => true,
            ],
        ]);

        $probe = $this->resultByLabel($result, 'extract_story_candidates');
        if ($this->isLoginRedirect($result, $probe)) {
            return [
                'success' => false,
                'message' => 'Instagram story candidate scan requires a connected account.',
                'detail' => 'The browser worker was redirected back to the Instagram login flow instead of the home feed.',
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => [
                    'profile' => $resolved,
                    'instagram_username' => $currentUsername,
                    'scan' => $probe,
                    'worker' => $result['data'] ?? [],
                ],
            ];
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (bool) ($result['success'] ?? false) ? 'Instagram active story candidates loaded.' : (string) ($result['message'] ?? 'Instagram active story candidate scan failed.'),
            'detail' => (bool) ($result['success'] ?? false)
                ? 'The authenticated home feed was scanned for usernames that currently appear in the story tray.'
                : (string) ($result['detail'] ?? ''),
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => [
                'profile' => $resolved,
                'instagram_username' => $currentUsername,
                'scan' => $probe,
                'worker' => $result['data'] ?? [],
            ],
        ];
    }

    public function storyScan(?string $profile, string $username): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $username = $this->config->normalizeUsername($username);

        if ($username === '') {
            return $this->failure('Instagram username is required.', 'Provide an Instagram username before running the story scan.');
        }

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'goto',
                'label' => 'open_story',
                'url' => 'https://www.instagram.com/stories/' . $username . '/',
                'wait_until' => 'domcontentloaded',
                'timeout_ms' => 30000,
                'wait_ms' => 4000,
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
                'label' => 'extract_story',
                'code' => self::storyProbeJs(),
            ],
        ], [
            'transport_timeout_ms' => 90000,
            'final' => [
                'include_screenshot' => true,
            ],
        ]);

        $probe = $this->resultByLabel($result, 'extract_story');
        if ($this->isLoginRedirect($result, $probe)) {
            return [
                'success' => false,
                'message' => 'Instagram story scan requires a connected account.',
                'detail' => 'The browser worker was redirected back to the Instagram login flow instead of the requested stories page.',
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => [
                    'profile' => $resolved,
                    'instagram_username' => $username,
                    'scan' => $probe,
                    'worker' => $result['data'] ?? [],
                ],
            ];
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (bool) ($result['success'] ?? false) ? 'Instagram story scan completed.' : (string) ($result['message'] ?? 'Instagram story scan failed.'),
            'detail' => (bool) ($result['success'] ?? false)
                ? 'If stories are visible to the attached account, the current media URLs are listed below.'
                : (string) ($result['detail'] ?? ''),
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => [
                'profile' => $resolved,
                'instagram_username' => $username,
                'scan' => $probe,
                'worker' => $result['data'] ?? [],
            ],
        ];
    }
}
