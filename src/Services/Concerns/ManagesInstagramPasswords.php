<?php

namespace hexa_package_instagram\Services\Concerns;

use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

trait ManagesInstagramPasswords
{

    public function credentialKey(string $profile): string
    {
        return 'account_password_' . $this->config->normalizeProfile($profile);
    }

    public function storePassword(string $profile, string $password): void
    {
        $password = trim($password);
        if ($password === '') {
            return;
        }

        $this->credentials->store('instagram', $this->credentialKey($profile), $password);
    }

    public function deletePassword(string $profile): void
    {
        $this->credentials->delete('instagram', $this->credentialKey($profile));
    }

    public function hasPassword(string $profile): bool
    {
        return $this->credentials->exists('instagram', $this->credentialKey($profile));
    }

    public function maskedPassword(string $profile): string
    {
        return $this->credentials->getMasked('instagram', $this->credentialKey($profile));
    }

    public function password(string $profile): ?string
    {
        return $this->credentials->get('instagram', $this->credentialKey($profile));
    }
}
