<?php

namespace Tests\Feature;

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

class InstagramPackageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireInstalledPackage(
            'hexawebsystems/laravel-hexa-package-instagram',
            InstagramConfigRepository::class
        );

        $appKey = 'base64:' . base64_encode(str_repeat('i', 32));
        config()->set('app.key', $appKey);
        putenv('APP_KEY=' . $appKey);
        $_ENV['APP_KEY'] = $appKey;
        $_SERVER['APP_KEY'] = $appKey;

        Schema::dropIfExists('settings');
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->string('type')->default('text');
            $table->string('label')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('activity_logs');
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->longText('context')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_timezone')->nullable();
            $table->timestamps();
        });
    }

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
                $loginProbe = $this->calls === 1
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
        $this->assertSame('Instagram still shows the login form for this browser profile.', $result['detail']);
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

    public function test_public_import_fallback_extracts_image_caption_and_title(): void
    {
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
                        . '<meta property="og:title" content="Post by Chabad Nobe">'
                        . '<meta property="og:description" content="Men&#039;s Night Out at 7835 Harding Avenue">'
                        . '</head><body></body></html>',
                    'headers' => ['Content-Type' => ['text/html']],
                    'final_url' => $url,
                    'error' => null,
                ];
            }
        });

        $result = app(InstagramImportService::class)->importPost('https://www.instagram.com/p/TEST123/', false);

        $this->assertTrue($result['success']);
        $this->assertSame('Imported Instagram post via public metadata fallback.', $result['message']);
        $this->assertSame('public_embed_srcset', $result['data']['method_used']);
        $this->assertSame('https://cdn.example.com/post-full.jpg', $result['data']['image_url']);
        $this->assertStringContainsString("Men's Night Out", $result['data']['caption']);
    }

    public function test_public_import_accepts_profile_scoped_post_url(): void
    {
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
                        . '<meta property="og:title" content="Post by Yehudi">'
                        . '<meta property="og:description" content="Caption proof text">'
                        . '</head><body></body></html>',
                    'headers' => ['Content-Type' => ['text/html']],
                    'final_url' => $url,
                    'error' => null,
                ];
            }
        });

        $result = app(InstagramImportService::class)->importPost('https://www.instagram.com/yehudip/p/B-tnkMJDN4O/', false);

        $this->assertTrue($result['success']);
        $this->assertSame('https://www.instagram.com/p/B-tnkMJDN4O/', $result['trace'][0]['normalized_url']);
        $this->assertSame('public_embed_srcset', $result['data']['method_used']);
    }

    public function test_profile_scan_fails_cleanly_when_worker_is_redirected_to_login(): void
    {
        app()->instance(BrowserWorkerBridgeContract::class, new class implements BrowserWorkerBridgeContract {
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
                            ['label' => 'extract_profile', 'result' => [
                                'heading' => '',
                                'body_excerpt' => 'Log into Instagram Mobile number, username or email Password Create new account',
                                'post_links' => [],
                                'media' => [['src' => 'https://cdn.example.com/login.jpg']],
                            ]],
                        ],
                        'final' => [
                            'final_url' => 'https://www.instagram.com/accounts/login/?next=https%3A%2F%2Fwww.instagram.com%2Fjpnmiami%2F',
                        ],
                    ],
                ];
            }
        });

        $result = app(InstagramScraperService::class)->profileScan('ops.backup', 'jpnmiami', 3);

        $this->assertFalse($result['success']);
        $this->assertSame('Instagram profile scan requires a connected account.', $result['message']);
        $this->assertStringContainsString('redirected back to the Instagram login flow', $result['detail']);
    }

    public function test_story_scan_fails_cleanly_when_instagram_requires_whatsapp_code_verification(): void
    {
        app()->instance(BrowserWorkerBridgeContract::class, new class implements BrowserWorkerBridgeContract {
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
                    'success' => false,
                    'message' => 'Automation failed on open_story.',
                    'status_code' => 200,
                    'data' => [
                        'results' => [
                            ['label' => 'extract_story', 'result' => [
                                'url' => 'https://www.instagram.com/auth_platform/codeentry/',
                                'body_excerpt' => 'Check your WhatsApp messages Enter the code we sent to your WhatsApp account at +1 ***-***-**71. Continue Try another way',
                                'image_urls' => [],
                                'video_urls' => [],
                                'visible_buttons' => ['Continue', 'Try another way'],
                            ]],
                        ],
                        'final' => [
                            'final_url' => 'https://www.instagram.com/auth_platform/codeentry/',
                        ],
                    ],
                ];
            }
        });

        $result = app(InstagramScraperService::class)->storyScan('jpn-miami', 'miamijpn');

        $this->assertFalse($result['success']);
        $this->assertSame('Instagram story scan requires a connected account.', $result['message']);
        $this->assertStringContainsString('redirected back to the Instagram login flow', $result['detail']);
    }

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

    public function test_profile_probe_captures_profile_scoped_post_links(): void
    {
        $probe = \hexa_package_instagram\Services\InstagramScraperService::profileProbeJs();

        $this->assertStringContainsString("href.includes('/p/')", $probe);
        $this->assertStringContainsString("href.includes('/reel/')", $probe);
        $this->assertStringContainsString("href.includes('/tv/')", $probe);
    }

    public function test_following_scan_returns_followed_usernames_from_worker_result(): void
    {
        app()->instance(BrowserWorkerBridgeContract::class, new class implements BrowserWorkerBridgeContract {
            public function health(): array { return ['success' => true]; }
            public function integrityTest(?string $profile = null): array { return ['success' => true]; }
            public function status(?string $profile = null): array { return ['success' => true]; }
            public function logs(int $limit = 100): array { return ['success' => true]; }
            public function launchProfile(?string $profile = null): array { return ['success' => true]; }
            public function closeProfile(?string $profile = null): array { return ['success' => true]; }
            public function logoutProfile(?string $profile = null): array { return ['success' => true]; }
            public function deleteProfile(?string $profile = null): array { return ['success' => true]; }
            public function pageHtml(?string $profile, string $url, array $options = []): array { return ['success' => true]; }
            public function pageText(?string $profile, string $url, array $options = []): array { return ['success' => true]; }
            public function pageScreenshot(?string $profile, string $url, array $options = []): array { return ['success' => true]; }
            public function runAutomation(?string $profile, array $steps, array $options = []): array
            {
                return [
                    'success' => true,
                    'message' => 'Automation flow completed.',
                    'status_code' => 200,
                    'data' => [
                        'results' => [
                            ['label' => 'extract_following', 'result' => [
                                'body_excerpt' => 'Following dialog proof',
                                'username_count' => 3,
                                'usernames' => ['eli.mishael', 'mikeyperes', 'yjpalmbeach'],
                            ]],
                        ],
                        'final' => [
                            'final_url' => 'https://www.instagram.com/miamijpn/',
                            'screenshot_data_url' => 'data:image/png;base64,proof',
                        ],
                    ],
                ];
            }
        });

        $result = app(InstagramScraperService::class)->followingScan('jpn-miami', 'miamijpn', 80);

        $this->assertTrue($result['success']);
        $this->assertSame('Instagram following scan completed.', $result['message']);
        $this->assertSame(3, $result['data']['scan']['username_count']);
        $this->assertSame('eli.mishael', $result['data']['scan']['usernames'][0]);
    }

    public function test_active_story_candidates_scan_returns_home_feed_usernames(): void
    {
        app()->instance(BrowserWorkerBridgeContract::class, new class implements BrowserWorkerBridgeContract {
            public function health(): array { return ['success' => true]; }
            public function integrityTest(?string $profile = null): array { return ['success' => true]; }
            public function status(?string $profile = null): array { return ['success' => true]; }
            public function logs(int $limit = 100): array { return ['success' => true]; }
            public function launchProfile(?string $profile = null): array { return ['success' => true]; }
            public function closeProfile(?string $profile = null): array { return ['success' => true]; }
            public function logoutProfile(?string $profile = null): array { return ['success' => true]; }
            public function deleteProfile(?string $profile = null): array { return ['success' => true]; }
            public function pageHtml(?string $profile, string $url, array $options = []): array { return ['success' => true]; }
            public function pageText(?string $profile, string $url, array $options = []): array { return ['success' => true]; }
            public function pageScreenshot(?string $profile, string $url, array $options = []): array { return ['success' => true]; }
            public function runAutomation(?string $profile, array $steps, array $options = []): array
            {
                return [
                    'success' => true,
                    'message' => 'Automation flow completed.',
                    'status_code' => 200,
                    'data' => [
                        'results' => [
                            ['label' => 'extract_story_candidates', 'result' => [
                                'body_excerpt' => 'Story tray proof',
                                'username_count' => 2,
                                'usernames' => [
                                    ['username' => 'the_house_of_more', 'source' => 'feed_text'],
                                    ['username' => 'yjpalmbeach', 'source' => 'feed_text'],
                                ],
                            ]],
                        ],
                        'final' => [
                            'final_url' => 'https://www.instagram.com/',
                        ],
                    ],
                ];
            }
        });

        $result = app(InstagramScraperService::class)->activeStoryCandidatesScan('jpn-miami', 'miamijpn', 24);

        $this->assertTrue($result['success']);
        $this->assertSame('Instagram active story candidates loaded.', $result['message']);
        $this->assertSame(2, $result['data']['scan']['username_count']);
        $this->assertSame('the_house_of_more', $result['data']['scan']['usernames'][0]['username']);
    }
}
