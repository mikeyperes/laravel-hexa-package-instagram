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

trait TestsInstagramPublicImportsAndScans
{

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
}
