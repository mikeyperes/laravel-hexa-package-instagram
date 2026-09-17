<?php

namespace Tests\Feature\Concerns;

use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_browser_worker\Domains\Bridge\BrowserWorkerBridge;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_instagram\Services\InstagramAccountSessionService;
use hexa_package_instagram\Services\InstagramConnectionService;

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

    public function test_repository_falls_back_when_the_configured_default_profile_is_empty(): void
    {
        config()->set('instagram.defaults.session_profile', '');

        $repository = app(InstagramConfigRepository::class);

        $this->assertSame('instagram-main', $repository->normalizeProfile(''));
        $this->assertSame('instagram-main', $repository->resolveProfile(null));
    }

    public function test_connection_requires_runtime_authentication_and_exact_account_evidence(): void
    {
        $repository = app(InstagramConfigRepository::class);
        $repository->saveAccount('JPN Main', 'jpn-miami', 'miamijpn', true);
        $this->bindInstagramWorker($this->readyWorkerState('jpn-miami', 'proxy'));

        $result = app(InstagramAccountSessionService::class)->status('jpn-miami');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['connected']);
        $this->assertTrue($result['data']['account_verified']);
        $this->assertSame('proxy', $result['data']['transport']);
        $this->assertSame('miamijpn', $result['data']['expected_username']);
    }

    public function test_connection_rejects_a_valid_session_for_the_wrong_account(): void
    {
        app(InstagramConfigRepository::class)->saveAccount('JPN Main', 'jpn-miami', 'miamijpn', true);
        $state = $this->readyWorkerState('jpn-miami', 'direct');
        $state['expected_account_binding_verified'] = false;
        $this->bindInstagramWorker($state);

        $result = app(InstagramAccountSessionService::class)->status('jpn-miami');

        $this->assertFalse($result['success']);
        $this->assertFalse($result['data']['connected']);
        $this->assertTrue($result['data']['authenticated']);
        $this->assertTrue($result['data']['account_verified']);
        $this->assertFalse($result['data']['expected_account_verified']);
        $this->assertSame('direct', $result['data']['transport']);
        $this->assertStringContainsString('different expected account', $result['detail']);
    }

    public function test_connection_rejects_missing_account_evidence_configuration(): void
    {
        app(InstagramConfigRepository::class)->saveAccount('JPN Main', 'jpn-miami', 'miamijpn', true);
        $state = $this->readyWorkerState('jpn-miami', 'direct');
        $state['account_evidence_configured'] = false;
        $state['account_evidence_verified'] = false;
        $this->bindInstagramWorker($state);

        $result = app(InstagramAccountSessionService::class)->status('jpn-miami');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('account-bound evidence is not configured', $result['detail']);
    }

    protected function readyWorkerState(string $profile, string $transport): array
    {
        return [
            'profile' => $profile,
            'auth_evidence_configured' => true,
            'auth_evidence_valid' => true,
            'auth_evidence_verified' => true,
            'account_evidence_configured' => true,
            'account_evidence_verified' => true,
            'expected_account_binding_verified' => true,
            'proxy' => ['transport_mode' => $transport],
            'runtime_activation' => [
                'schema_version' => 3,
                'profile' => $profile,
                'configured' => true,
                'active' => true,
                'desktop_transport_ready' => true,
                'viewer_ready' => true,
                'window_manager_ready' => true,
                'readiness_recorded' => true,
                'rfb_transport_ready' => true,
                'service_account_owned' => true,
                'x_authority_private' => true,
                'x_access_controlled' => true,
                'loopback_only' => true,
            ],
        ];
    }

    protected function bindInstagramWorker(array $state): void
    {
        app()->forgetInstance(InstagramAccountSessionService::class);
        app()->forgetInstance(InstagramConnectionService::class);
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
        });
    }
}
