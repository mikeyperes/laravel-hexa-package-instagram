<?php

namespace Tests\Feature\Concerns;

use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_browser_worker\Domains\Bridge\BrowserWorkerBridge;
use hexa_package_browser_worker\Services\BrowserHttpService;
use hexa_package_instagram\Services\InstagramAccountSessionService;
use hexa_package_instagram\Services\InstagramConnectionService;

trait TestsInstagramVerificationAndRoutes
{
    public function test_accounts_view_uses_browser_console_for_login_and_routing(): void
    {
        $rendered = view('instagram::accounts.index', [
            'settings' => ['session_profile' => 'instagram-main', 'accounts' => []],
            'status' => ['accounts' => [], 'active_profile' => 'instagram-main', 'active_account' => null],
        ])->render();

        $this->assertStringContainsString('Browser Console owns Instagram login and network routing', $rendered);
        $this->assertStringNotContainsString('saved password', strtolower($rendered));
        $this->assertStringNotContainsString('jpn-miami.settings', $rendered);
    }

    public function test_overview_uses_the_single_connection_status_and_browser_console_handoff(): void
    {
        $rendered = view('instagram::workspace.index', [
            'status' => [
                'active_profile' => 'jpn-miami',
                'active_account' => [
                    'label' => 'Instagram - jpnmiami',
                    'instagram_username' => 'miamijpn',
                    'console_url' => 'https://code.example.test/browser-console/sessions?profile=jpn-miami',
                ],
                'has_meta_token' => false,
            ],
        ])->render();

        $this->assertStringContainsString('Browser Console owns the browser session', $rendered);
        $this->assertStringContainsString('instagram\\/status', $rendered);
        $this->assertStringNotContainsString('saved password', strtolower($rendered));
        $this->assertStringNotContainsString('instagram/integrity', $rendered);
    }

    public function test_obsolete_login_and_remote_screen_routes_are_absent(): void
    {
        $router = app('router');

        foreach ([
            'instagram.accounts.login',
            'instagram.accounts.verification',
            'instagram.accounts.logout',
            'instagram.accounts.screen',
            'instagram.accounts.click',
            'instagram.accounts.reload',
            'instagram.integrity',
        ] as $name) {
            $this->assertFalse($router->has($name), $name.' should be removed.');
        }
    }

    public function test_account_routes_save_activate_and_remove_bindings(): void
    {
        $this->withoutMiddleware();

        $this->postJson('/instagram/accounts', [
            'label' => 'JPN Main',
            'profile' => 'JPN.Main',
            'instagram_username' => 'jpnmiami',
            'set_active' => true,
        ])->assertOk()->assertJsonPath('settings.session_profile', 'jpn-main');

        $this->postJson('/instagram/accounts', [
            'label' => 'Ops Backup',
            'profile' => 'ops.backup',
            'instagram_username' => 'opsbackup',
        ])->assertOk();

        $this->postJson('/instagram/accounts/activate', ['profile' => 'ops.backup'])
            ->assertOk()
            ->assertJsonPath('status.active_profile', 'ops-backup');

        $this->deleteJson('/instagram/accounts', ['profile' => 'jpn-main'])
            ->assertOk()
            ->assertJsonPath('message', 'Instagram account binding removed. The Browser Console profile was preserved.');
    }

    public function test_raw_workspace_actions_are_saved_into_history(): void
    {
        $this->withoutMiddleware();
        $state = $this->readyWorkerState('jpn-miami', 'direct');
        $this->bindInstagramWorker($state);

        app()->instance(BrowserWorkerBridgeContract::class, new class($state) extends BrowserWorkerBridge
        {
            public function __construct(private readonly array $state) {}

            public function readyProfile(string $profile, bool $checkAuthentication = true, ?string $expectedAccountIdentifier = null): array
            {
                return ['success' => true, 'message' => 'ready', 'data' => $this->state];
            }

            public function status(?string $profile = null): array
            {
                return ['success' => true, 'message' => 'ready', 'data' => $this->state];
            }

            public function runAutomation(?string $profile, array $steps, array $options = []): array
            {
                return [
                    'success' => true,
                    'message' => 'Automation flow completed.',
                    'status_code' => 200,
                    'data' => [
                        'results' => [[
                            'label' => 'extract_profile',
                            'result' => [
                                'url' => 'https://www.instagram.com/jpnmiami/',
                                'title' => 'JPN Miami',
                                'heading' => 'JPN Miami',
                                'body_excerpt' => 'Profile excerpt',
                                'post_links' => ['https://www.instagram.com/p/ABC123/'],
                                'media' => [],
                            ],
                        ]],
                        'final' => ['final_url' => 'https://www.instagram.com/jpnmiami/'],
                    ],
                ];
            }
        });
        app()->forgetInstance(InstagramConnectionService::class);
        app()->forgetInstance(InstagramAccountSessionService::class);

        app()->instance(BrowserHttpService::class, new class extends BrowserHttpService
        {
            public function getHtml(string $url, array $options = []): array
            {
                $body = str_contains($url, '/embed/captioned/')
                    ? '<html><body><img class="EmbeddedMediaImage" src="https://cdn.example.com/post-full.jpg"></body></html>'
                    : '<html><head><meta property="og:title" content="Post by JPN Miami"><meta property="og:description" content="Caption proof text"><meta property="og:image" content="https://cdn.example.com/post-full.jpg"></head></html>';

                return ['success' => true, 'status_code' => 200, 'body' => $body, 'headers' => [], 'final_url' => $url, 'error' => null];
            }
        });

        $this->postJson('/instagram/accounts', [
            'label' => 'JPN Main',
            'profile' => 'jpn-miami',
            'instagram_username' => 'jpnmiami',
            'set_active' => true,
        ])->assertOk();

        $this->getJson('/instagram/status?profile=jpn-miami')->assertOk()->assertJsonPath('data.connected', true);
        $this->postJson('/instagram/profile-scan', ['profile' => 'jpn-miami', 'instagram_username' => 'jpnmiami', 'limit' => 6])
            ->assertOk()
            ->assertJsonPath('data.scan.post_links.0', 'https://www.instagram.com/p/ABC123/');
        $this->postJson('/instagram/import-post', ['url' => 'https://www.instagram.com/p/ABC123/', 'include_image_data' => false])
            ->assertOk()
            ->assertJsonPath('data.title', 'Post by JPN Miami');

        foreach (['raw_status', 'raw_profile_scan', 'raw_post_import'] as $action) {
            $this->assertDatabaseHas('activity_logs', ['category' => 'instagram', 'action' => $action]);
        }
    }
}
