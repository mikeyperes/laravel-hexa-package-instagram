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

trait TestsInstagramVerificationAndRoutes
{

    public function test_submit_verification_code_advances_attached_browser_session(): void
    {
        $repository = app(InstagramConfigRepository::class);
        $repository->saveAccount('JPN Main', 'jpn-miami', 'miamijpn', true);

        app()->instance(BrowserWorkerBridgeContract::class, new class implements BrowserWorkerBridgeContract {
            private int $runCalls = 0;

            public function health(): array
            {
                return ['success' => true];
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
                $this->runCalls++;

                if ($this->runCalls === 1) {
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
                                'verification_channel' => '',
                                'challenge' => false,
                                'login_copy_detected' => false,
                                'strong_nav_count' => 3,
                                'alerts' => [],
                                'body_excerpt' => 'Instagram home',
                            ]],
                        ],
                        'final' => [
                            'final_url' => 'https://www.instagram.com/',
                        ],
                    ],
                ];
            }
        });

        $result = app(InstagramAccountSessionService::class)->submitVerificationCode('jpn-miami', '916724');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['connected']);
        $this->assertSame('Instagram verification code submitted.', $result['message']);
    }

    public function test_account_routes_save_and_activate_multiple_profiles(): void
    {
        $this->withoutMiddleware();

        $saveMain = $this->postJson('/instagram/accounts', [
            'label' => 'JPN Main',
            'profile' => 'JPN.Main',
            'instagram_username' => 'jpnmiami',
            'password' => 'secret-pass',
            'set_active' => true,
        ]);

        $saveMain->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('settings.session_profile', 'jpn-main');

        $saveBackup = $this->postJson('/instagram/accounts', [
            'label' => 'Ops Backup',
            'profile' => 'ops.backup',
            'instagram_username' => 'opsbackup',
            'set_active' => false,
        ]);

        $saveBackup->assertOk()
            ->assertJsonPath('success', true);

        $activate = $this->postJson('/instagram/accounts/activate', [
            'profile' => 'ops.backup',
        ]);

        $activate->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('settings.session_profile', 'ops-backup')
            ->assertJsonPath('status.active_profile', 'ops-backup')
            ->assertJsonPath('status.active_account.instagram_username', 'opsbackup');
    }

    public function test_raw_workspace_actions_are_saved_into_history(): void
    {
        $this->withoutMiddleware();

        app()->instance(BrowserWorkerBridgeContract::class, new class implements BrowserWorkerBridgeContract {
            public function health(): array
            {
                return ['success' => true, 'message' => 'ok', 'status_code' => 200];
            }

            public function integrityTest(?string $profile = null): array
            {
                return ['success' => true, 'message' => 'ok'];
            }

            public function status(?string $profile = null): array
            {
                return ['success' => true, 'message' => 'ok'];
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
                $labels = array_column($steps, 'label');

                if (in_array('extract_profile', $labels, true)) {
                    return [
                        'success' => true,
                        'message' => 'Automation flow completed.',
                        'status_code' => 200,
                        'data' => [
                            'results' => [
                                ['label' => 'extract_profile', 'result' => [
                                    'url' => 'https://www.instagram.com/jpnmiami/',
                                    'title' => 'JPN Miami',
                                    'heading' => 'JPN Miami',
                                    'body_excerpt' => 'Profile excerpt',
                                    'post_links' => ['https://www.instagram.com/p/ABC123/'],
                                    'media' => [],
                                ]],
                            ],
                            'final' => ['final_url' => 'https://www.instagram.com/jpnmiami/'],
                        ],
                    ];
                }

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
                                'verification_channel' => '',
                                'challenge' => false,
                                'login_copy_detected' => false,
                                'strong_nav_count' => 4,
                                'alerts' => [],
                                'body_excerpt' => 'Instagram connected',
                                'url' => 'https://www.instagram.com/',
                            ]],
                        ],
                        'final' => ['final_url' => 'https://www.instagram.com/'],
                    ],
                ];
            }
        });

        app()->instance(BrowserHttpService::class, new class extends BrowserHttpService {
            public function getHtml(string $url, array $options = []): array
            {
                if (str_contains($url, '/embed/captioned/')) {
                    return [
                        'success' => true,
                        'status_code' => 200,
                        'body' => '<html><body><img class="EmbeddedMediaImage" srcset="https://cdn.example.com/post-small.jpg 320w, https://cdn.example.com/post-full.jpg 1080w" src="https://cdn.example.com/post-small.jpg"></body></html>',
                        'headers' => ['Content-Type' => ['text/html']],
                        'final_url' => $url,
                        'error' => null,
                    ];
                }

                return [
                    'success' => true,
                    'status_code' => 200,
                    'body' => '<html><head>'
                        . '<meta property="og:title" content="Post by JPN Miami">'
                        . '<meta property="og:description" content="Caption proof text">'
                        . '</head><body></body></html>',
                    'headers' => ['Content-Type' => ['text/html']],
                    'final_url' => $url,
                    'error' => null,
                ];
            }
        });

        $this->postJson('/instagram/accounts', [
            'label' => 'JPN Main',
            'profile' => 'jpn-miami',
            'instagram_username' => 'jpnmiami',
            'set_active' => true,
        ])->assertOk();

        $this->getJson('/instagram/status?profile=jpn-miami')
            ->assertOk()
            ->assertJsonPath('data.connected', true);

        $this->postJson('/instagram/profile-scan', [
            'profile' => 'jpn-miami',
            'instagram_username' => 'jpnmiami',
            'limit' => 6,
        ])->assertOk()
            ->assertJsonPath('data.scan.post_links.0', 'https://www.instagram.com/p/ABC123/');

        $this->postJson('/instagram/import-post', [
            'url' => 'https://www.instagram.com/p/ABC123/',
            'include_image_data' => false,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Post by JPN Miami');

        $this->assertDatabaseHas('activity_logs', [
            'category' => 'instagram',
            'action' => 'raw_status',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'category' => 'instagram',
            'action' => 'raw_profile_scan',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'category' => 'instagram',
            'action' => 'raw_post_import',
        ]);

        $this->get('/instagram/raw')
            ->assertOk()
            ->assertSee('Raw action history')
            ->assertSee('Instagram Raw History');
    }
}
