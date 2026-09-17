<?php

namespace hexa_package_instagram\Services;

use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

class InstagramAccountSessionService
{
    public function __construct(
        private readonly InstagramConfigRepository $config,
        private readonly InstagramConnectionService $connection,
    ) {}

    public function status(?string $profile = null, bool $refresh = true): array
    {
        $profile = $this->config->resolveProfile($profile);
        $account = $this->config->findAccount($profile);

        return $this->connection->status(
            $profile,
            (string) ($account['instagram_username'] ?? ''),
            $refresh,
        );
    }

    public function accountPresentation(array $account): array
    {
        $profile = $this->config->normalizeProfile((string) ($account['profile'] ?? ''));

        return array_merge($account, [
            'profile' => $profile,
            'instagram_username' => $this->config->normalizeUsername((string) ($account['instagram_username'] ?? '')),
            'console_url' => route('browser-console.sessions', ['profile' => $profile]),
        ]);
    }
}
