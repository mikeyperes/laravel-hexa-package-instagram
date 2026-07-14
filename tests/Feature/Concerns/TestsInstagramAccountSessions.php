<?php

namespace Tests\Feature\Concerns;

use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_browser_worker\Services\BrowserHttpService;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_instagram\Services\InstagramAccountSessionService;
use hexa_package_instagram\Services\InstagramImportService;
use hexa_package_instagram\Services\InstagramScraperService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

trait TestsInstagramAccountSessions
{

    public function test_repository_saves_multiple_accounts_and_active_profile(): void
    {
        $repository = app(InstagramConfigRepository::class);
        $repository->saveAccount('JPN Main', 'JPN.Main', 'JPNMiami', true);
        $repository->saveAccount('Ops Backup', 'ops_backup', 'ops.backup', false);

        $settings = $repository->all();

        $this->assertSame('jpn-main', $settings['session_profile']);
        $this->assertCount(2, $settings['accounts']);
        $this->assertSame('jpnmiami', $repository->findAccount('JPN.Main')['instagram_username']);
        $this->assertSame('ops.backup', $repository->findAccount('ops_backup')['instagram_username']);
    }

    public function test_login_uses_saved_credentials_and_detects_connected_state(): void
    {
        $repository = app(InstagramConfigRepository::class);
        $repository->saveAccount('JPN Main', 'JPN.Main', 'jpnmiami', true);
        app(CredentialService::class)->store('instagram', 'account_password_jpn-main', 'secret-pass');

        app()->instance(BrowserWorkerBridgeContract::class, new class implements BrowserWorkerBridgeContract {
            public int $calls = 0;

            public function health(): array
            {
                return ['success' => true, 'message' => 'ok'];
            }

            public function integrityTest(?string $profile = null): array
            {
                return ['success' => true, 'message' => 'ok'];
            }

            public function status(?string $profile = null): array
            {
                return ['success' => true, 'message' => 'ok', 'data' => ['profile' => $profile]];
            }

            public function logs(int $limit = 100): array
            {
                return ['success' => true, 'data' => []];
            }

            public function launchProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function closeProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function logoutProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function deleteProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function pageHtml(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function pageText(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function pageScreenshot(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function runAutomation(?string $profile, array $steps, array $options = []): array
            {
                $this->calls++;
                $loginProbe = $this->calls <= 2
                    ? ['connected' => false, 'login_form' => true, 'challenge' => false, 'login_copy_detected' => true, 'strong_nav_count' => 0, 'alerts' => []]
                    : ['connected' => true, 'login_form' => false, 'challenge' => false, 'login_copy_detected' => false, 'strong_nav_count' => 2, 'alerts' => [], 'path' => '/'];

                return [
                    'success' => true,
                    'message' => 'Automation flow completed.',
                    'status_code' => 200,
                    'data' => [
                        'results' => [
                            ['label' => 'detect_login_state', 'result' => $loginProbe],
                        ],
                        'final' => [
                            'screenshot_data_url' => 'data:image/png;base64,proof',
                        ],
                    ],
                ];
            }
        });

        $service = app(InstagramAccountSessionService::class);
        $result = $service->login('JPN.Main');

        $this->assertTrue($result['success']);
        $this->assertSame('Instagram login flow finished.', $result['message']);
        $this->assertTrue($result['data']['connected']);
        $this->assertSame('jpn-main', $result['data']['profile']);
        $this->assertSame('jpnmiami', $result['data']['account']['instagram_username']);
    }

    public function test_integrity_test_reports_worker_and_connected_state(): void
    {
        $repository = app(InstagramConfigRepository::class);
        $repository->saveAccount('JPN Main', 'JPN.Main', 'jpnmiami', true);

        app()->instance(BrowserWorkerBridgeContract::class, new class implements BrowserWorkerBridgeContract {
            public function health(): array
            {
                return ['success' => true, 'message' => 'Browser worker is reachable.', 'status_code' => 200];
            }

            public function integrityTest(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function status(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function logs(int $limit = 100): array
            {
                return ['success' => true];
            }

            public function launchProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function closeProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function logoutProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function deleteProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function pageHtml(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function pageText(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function pageScreenshot(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function runAutomation(?string $profile, array $steps, array $options = []): array
            {
                return [
                    'success' => true,
                    'message' => 'Automation flow completed.',
                    'status_code' => 200,
                    'data' => [
                        'results' => [
                            ['label' => 'detect_login_state', 'result' => ['connected' => true, 'login_form' => false, 'challenge' => false, 'login_copy_detected' => false, 'strong_nav_count' => 2, 'alerts' => []]],
                        ],
                    ],
                ];
            }
        });

        $result = app(InstagramAccountSessionService::class)->integrityTest('JPN.Main');

        $this->assertTrue($result['success']);
        $this->assertSame('Browser worker is healthy and the Instagram account is connected.', $result['message']);
        $this->assertTrue($result['data']['connected']);
    }

    public function test_status_does_not_treat_logged_out_homepage_as_connected(): void
    {
        $repository = app(InstagramConfigRepository::class);
        $repository->saveAccount('Ops Backup', 'ops.backup', 'qa_nonexistent_user_hexa', true);

        app()->instance(BrowserWorkerBridgeContract::class, new class implements BrowserWorkerBridgeContract {
            public function health(): array
            {
                return ['success' => true, 'message' => 'Browser worker is reachable.', 'status_code' => 200];
            }

            public function integrityTest(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function status(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function logs(int $limit = 100): array
            {
                return ['success' => true];
            }

            public function launchProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function closeProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function logoutProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function deleteProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function pageHtml(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function pageText(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function pageScreenshot(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function runAutomation(?string $profile, array $steps, array $options = []): array
            {
                return [
                    'success' => true,
                    'message' => 'Automation flow completed.',
                    'status_code' => 200,
                    'data' => [
                        'results' => [
                            ['label' => 'detect_login_state', 'result' => [
                                'connected' => true,
                                'login_form' => false,
                                'challenge' => false,
                                'login_copy_detected' => true,
                                'strong_nav_count' => 0,
                                'authenticated_markers' => ['/popular/'],
                                'alerts' => [],
                            ]],
                        ],
                    ],
                ];
            }
        });

        $result = app(InstagramAccountSessionService::class)->status('ops.backup');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['connected']);
        $this->assertSame('Instagram account is not connected yet.', $result['message']);
        $this->assertSame('Instagram still shows the login form for this browser profile. Worker detail: Automation flow completed.', $result['detail']);
    }

    public function test_status_preserves_current_verification_screen_and_reports_email_code_requirement(): void
    {
        $repository = app(InstagramConfigRepository::class);
        $repository->saveAccount('JPN Main', 'jpn-miami', 'miamijpn', true);

        app()->instance(BrowserWorkerBridgeContract::class, new class implements BrowserWorkerBridgeContract {
            public function health(): array
            {
                return ['success' => true, 'message' => 'Browser worker is reachable.', 'status_code' => 200];
            }

            public function integrityTest(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function status(?string $profile = null): array
            {
                return [
                    'success' => true,
                    'data' => [
                        'current_url' => 'https://www.instagram.com/auth_platform/codeentry/',
                    ],
                ];
            }

            public function logs(int $limit = 100): array
            {
                return ['success' => true];
            }

            public function launchProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function closeProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function logoutProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function deleteProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function pageHtml(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function pageText(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function pageScreenshot(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function runAutomation(?string $profile, array $steps, array $options = []): array
            {
                return [
                    'success' => true,
                    'message' => 'Automation flow completed.',
                    'status_code' => 200,
                    'data' => [
                        'results' => [
                            ['label' => 'detect_login_state', 'result' => [
                                'connected' => false,
                                'login_form' => false,
                                'verification_required' => true,
                                'verification_channel' => 'email',
                                'challenge' => true,
                                'login_copy_detected' => false,
                                'strong_nav_count' => 0,
                                'alerts' => [],
                                'body_excerpt' => 'Check your email Enter the code we sent to your email.',
                            ]],
                        ],
                        'final' => [
                            'final_url' => 'https://www.instagram.com/auth_platform/codeentry/',
                        ],
                    ],
                ];
            }
        });

        $result = app(InstagramAccountSessionService::class)->status('jpn-miami');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['connected']);
        $this->assertTrue($result['data']['verification_required']);
        $this->assertSame('email', $result['data']['verification_channel']);
        $this->assertSame('Instagram account is not connected yet.', $result['message']);
        $this->assertSame('Instagram accepted the saved credentials and is waiting for the verification code it sent by email.', $result['detail']);
    }


    public function test_status_treats_authenticated_story_viewer_as_connected(): void
    {
        $repository = app(InstagramConfigRepository::class);
        $repository->saveAccount('JPN Main', 'jpn-miami', 'miamijpn', true);

        app()->instance(BrowserWorkerBridgeContract::class, new class implements BrowserWorkerBridgeContract {
            public function health(): array
            {
                return ['success' => true, 'message' => 'Browser worker is reachable.', 'status_code' => 200];
            }

            public function integrityTest(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function status(?string $profile = null): array
            {
                return [
                    'success' => true,
                    'data' => [
                        'current_url' => 'https://www.instagram.com/stories/lasolasjewishcenter/',
                    ],
                ];
            }

            public function logs(int $limit = 100): array
            {
                return ['success' => true];
            }

            public function launchProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function closeProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function logoutProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function deleteProfile(?string $profile = null): array
            {
                return ['success' => true];
            }

            public function pageHtml(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function pageText(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function pageScreenshot(?string $profile, string $url, array $options = []): array
            {
                return ['success' => true];
            }

            public function runAutomation(?string $profile, array $steps, array $options = []): array
            {
                return [
                    'success' => true,
                    'message' => 'Automation flow completed.',
                    'status_code' => 200,
                    'data' => [
                        'results' => [
                            ['label' => 'detect_login_state', 'result' => [
                                'connected' => true,
                                'login_form' => false,
                                'verification_required' => false,
                                'challenge' => false,
                                'login_copy_detected' => false,
                                'strong_nav_count' => 0,
                                'story_viewer_detected' => true,
                                'alerts' => [],
                                'title' => 'Stories • Instagram',
                                'path' => '/stories/lasolasjewishcenter/',
                            ]],
                        ],
                        'final' => [
                            'final_url' => 'https://www.instagram.com/stories/lasolasjewishcenter/',
                        ],
                    ],
                ];
            }
        });

        $result = app(InstagramAccountSessionService::class)->status('jpn-miami');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['connected']);
        $this->assertTrue($result['data']['story_viewer_detected']);
        $this->assertSame('Instagram account status loaded.', $result['message']);
    }
}
