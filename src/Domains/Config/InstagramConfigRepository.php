<?php

namespace hexa_package_instagram\Domains\Config;

use hexa_core\Models\Setting;

class InstagramConfigRepository
{
    private const KEY_SESSION_PROFILE = 'instagram_session_profile';
    private const KEY_ACCOUNTS = 'instagram_accounts';
    private const KEY_DEFAULT_PROFILE_USERNAME = 'instagram_default_profile_username';
    private const KEY_DEFAULT_STORY_USERNAME = 'instagram_default_story_username';
    private const KEY_DEFAULT_POST_URL = 'instagram_default_post_url';

    public function all(): array
    {
        $sessionProfile = $this->resolveProfile(null);
        $accounts = $this->accounts();

        if ($sessionProfile !== '' && !$this->accountExists($accounts, $sessionProfile)) {
            $accounts[] = [
                'label' => $this->fallbackLabel($sessionProfile),
                'profile' => $sessionProfile,
                'instagram_username' => '',
                'created_at' => null,
                'updated_at' => null,
            ];
            $accounts = $this->sortAccounts($accounts);
        }

        return [
            'session_profile' => $sessionProfile,
            'accounts' => $accounts,
            'default_profile_username' => trim((string) (Setting::getValue(self::KEY_DEFAULT_PROFILE_USERNAME) ?: config('instagram.defaults.default_profile_username', ''))),
            'default_story_username' => trim((string) (Setting::getValue(self::KEY_DEFAULT_STORY_USERNAME) ?: config('instagram.defaults.default_story_username', ''))),
            'default_post_url' => trim((string) (Setting::getValue(self::KEY_DEFAULT_POST_URL) ?: config('instagram.defaults.default_post_url', ''))),
        ];
    }

    public function update(array $values): array
    {
        Setting::setValue(self::KEY_DEFAULT_PROFILE_USERNAME, trim((string) ($values['default_profile_username'] ?? '')), 'packages');
        Setting::setValue(self::KEY_DEFAULT_STORY_USERNAME, trim((string) ($values['default_story_username'] ?? '')), 'packages');
        Setting::setValue(self::KEY_DEFAULT_POST_URL, trim((string) ($values['default_post_url'] ?? '')), 'packages');

        return $this->all();
    }

    public function accounts(): array
    {
        $raw = Setting::getValue(self::KEY_ACCOUNTS);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $accounts = [];
        foreach ($decoded as $account) {
            if (!is_array($account)) {
                continue;
            }

            $profile = $this->normalizeProfile((string) ($account['profile'] ?? ''));
            $accounts[] = [
                'label' => trim((string) ($account['label'] ?? '')) ?: $this->fallbackLabel($profile),
                'profile' => $profile,
                'instagram_username' => trim((string) ($account['instagram_username'] ?? '')),
                'created_at' => $account['created_at'] ?? null,
                'updated_at' => $account['updated_at'] ?? null,
            ];
        }

        return $this->sortAccounts($accounts);
    }

    public function activeAccount(): ?array
    {
        $activeProfile = $this->resolveProfile(null);
        foreach ($this->accounts() as $account) {
            if (($account['profile'] ?? '') === $activeProfile) {
                return $account;
            }
        }

        return null;
    }

    public function findAccount(string $profile): ?array
    {
        $profile = $this->normalizeProfile($profile);
        foreach ($this->accounts() as $account) {
            if (($account['profile'] ?? '') === $profile) {
                return $account;
            }
        }

        return null;
    }

    public function saveAccount(string $label, string $profile, string $instagramUsername, bool $setActive = false): array
    {
        $profile = $this->normalizeProfile($profile);
        $label = trim($label) ?: $this->fallbackLabel($profile);
        $instagramUsername = $this->normalizeUsername($instagramUsername);
        $accounts = $this->accounts();
        $now = now()->toDateTimeString();
        $updated = false;

        foreach ($accounts as &$account) {
            if (($account['profile'] ?? '') !== $profile) {
                continue;
            }

            $account['label'] = $label;
            $account['instagram_username'] = $instagramUsername;
            $account['updated_at'] = $now;
            $updated = true;
            break;
        }
        unset($account);

        if (!$updated) {
            $accounts[] = [
                'label' => $label,
                'profile' => $profile,
                'instagram_username' => $instagramUsername,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->persistAccounts($this->sortAccounts($accounts));

        if ($setActive) {
            $this->setActiveProfile($profile);
        }

        return $this->all();
    }

    public function setActiveProfile(string $profile): array
    {
        $profile = $this->normalizeProfile($profile);
        $accounts = $this->accounts();

        if (!$this->accountExists($accounts, $profile)) {
            $accounts[] = [
                'label' => $this->fallbackLabel($profile),
                'profile' => $profile,
                'instagram_username' => '',
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
            $this->persistAccounts($this->sortAccounts($accounts));
        }

        Setting::setValue(self::KEY_SESSION_PROFILE, $profile, 'packages');

        return $this->all();
    }

    public function deleteAccount(string $profile): array
    {
        $profile = $this->normalizeProfile($profile);
        $accounts = array_values(array_filter(
            $this->accounts(),
            static fn (array $account): bool => ($account['profile'] ?? '') !== $profile
        ));

        $this->persistAccounts($accounts);

        if ($this->resolveProfile(null) === $profile) {
            Setting::setValue(self::KEY_SESSION_PROFILE, $accounts[0]['profile'] ?? $this->defaultProfile(), 'packages');
        }

        return $this->all();
    }

    public function resolveProfile(?string $profile): string
    {
        $rawProfile = strtolower(trim((string) ($profile ?? '')));
        $normalized = $rawProfile === '' ? '' : $this->normalizeProfile($rawProfile);

        if ($normalized !== '') {
            return $normalized;
        }

        return $this->normalizeProfile((string) (Setting::getValue(self::KEY_SESSION_PROFILE) ?: $this->defaultProfile()));
    }

    public function normalizeProfile(string $profile): string
    {
        $profile = strtolower(trim($profile));
        $profile = preg_replace('/[^a-z0-9_-]+/', '-', $profile) ?: '';
        $profile = trim($profile, '-');

        return $profile === '' ? $this->defaultProfile() : $profile;
    }

    public function normalizeUsername(string $username): string
    {
        $username = trim($username);
        $username = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $username) ?: $username;
        $username = trim($username, "/ \t\n\r\0\x0B");

        return strtolower($username);
    }

    private function defaultProfile(): string
    {
        return $this->normalizeProfile((string) config('instagram.defaults.session_profile', 'instagram-main'));
    }

    private function fallbackLabel(string $profile): string
    {
        return ucwords(str_replace(['.', '-', '_'], ' ', $profile));
    }

    private function accountExists(array $accounts, string $profile): bool
    {
        foreach ($accounts as $account) {
            if (($account['profile'] ?? '') === $profile) {
                return true;
            }
        }

        return false;
    }

    private function persistAccounts(array $accounts): void
    {
        Setting::setValue(self::KEY_ACCOUNTS, json_encode(array_values($accounts), JSON_UNESCAPED_SLASHES), 'packages');
    }

    private function sortAccounts(array $accounts): array
    {
        usort($accounts, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return array_values($accounts);
    }
}
