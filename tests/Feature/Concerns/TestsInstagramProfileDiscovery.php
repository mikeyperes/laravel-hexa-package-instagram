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

trait TestsInstagramProfileDiscovery
{

    public function test_profile_probe_captures_profile_scoped_post_links(): void
    {
        $probe = \hexa_package_instagram\Services\InstagramScraperService::profileProbeJs();

        $this->assertStringContainsString('normalizePostHref', $probe);
        $this->assertStringContainsString('(p|reel|tv)', $probe);
        $this->assertStringContainsString('array.indexOf(value) === index', $probe);
    }

    public function test_following_scan_returns_followed_usernames_from_worker_result(): void
    {
        $spy = new class implements BrowserWorkerBridgeContract {
            public ?array $lastOptions = null;
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
                $this->lastOptions = $options;
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
        };
        app()->instance(BrowserWorkerBridgeContract::class, $spy);

        $result = app(InstagramScraperService::class)->followingScan('jpn-miami', 'miamijpn', 80);

        $this->assertTrue($result['success']);
        $this->assertSame('Instagram following scan completed.', $result['message']);
        $this->assertSame(3, $result['data']['scan']['username_count']);
        $this->assertSame('eli.mishael', $result['data']['scan']['usernames'][0]);
        $this->assertSame(90000, $spy->lastOptions['transport_timeout_ms'] ?? null);
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
