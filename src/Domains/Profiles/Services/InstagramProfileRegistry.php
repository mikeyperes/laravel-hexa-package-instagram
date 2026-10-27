<?php

namespace hexa_package_instagram\Domains\Profiles\Services;

use hexa_core\Models\ActivityLog;
use hexa_package_browser_worker\Contracts\BrowserSessionRegistryContract;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_instagram\Domains\Profiles\Contracts\InstagramProfileSiteSource;
use hexa_package_instagram\Domains\Profiles\Models\InstagramProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InstagramProfileRegistry
{
    /** @var array<string, InstagramProfileSiteSource> */
    private array $sources = [];

    public function __construct(
        iterable $sources,
        private readonly InstagramConfigRepository $accounts,
        private readonly BrowserSessionRegistryContract $sessions,
        private readonly BrowserWorkerBridgeContract $worker,
    ) {
        foreach ($sources as $source) {
            if (! $source instanceof InstagramProfileSiteSource || isset($this->sources[$source->key()])) {
                throw new \LogicException('Instagram profile sources must have unique registered keys.');
            }
            $this->sources[$source->key()] = $source;
        }
    }

    public function sites(): array
    {
        $sites = [];
        foreach ($this->sources as $source) {
            foreach ($source->sites() as $site) {
                $sites[] = $site + ['source' => $source->key()];
            }
        }

        return $sites;
    }

    public function all(): array
    {
        $sessions = collect($this->sessions->all())->keyBy('profile_id');
        $sites = $this->sites();
        $paused = method_exists($this->worker, 'pausedProfiles') ? $this->worker->pausedProfiles() : [];

        return InstagramProfile::query()->orderBy('id')->get()->map(function (InstagramProfile $profile) use ($sessions, $sites, $paused): array {
            $site = collect($sites)->first(fn (array $site): bool =>
                $site['source'] === $profile->source && (string) $site['key'] === $profile->source_key
            );
            $browser = (string) ($site['browser_profile'] ?? '');
            $session = $sessions->get($browser);
            $binding = $browser !== '' ? $this->accounts->findAccount($browser) : null;
            $catalog = (array) ($profile->catalog ?? []);
            // A changed website must never display the previous website's catalog as its own.
            $catalogCurrent = $site && ($catalog['site_url'] ?? null) === $site['site_url'];
            if (! $catalogCurrent) {
                $catalog = [];
            }

            return [
                'id' => $profile->id, 'slug' => $profile->slug,
                'source' => $profile->source, 'source_key' => $profile->source_key,
                'name' => $site['name'] ?? $profile->slug,
                'site_url' => $site['site_url'] ?? null,
                'region' => $site['region'] ?? '', 'timezone' => $site['timezone'] ?? '',
                'instagram_username' => $site['instagram_username'] ?? '',
                'instagram_list' => $site['instagram_list'] ?? null,
                'scanner_url' => $site['scanner_url'] ?? null,
                'browser_profile' => $browser,
                'console_url' => $session['console_url'] ?? null,
                'desktop_registered' => $session !== null,
                'desktop_state' => $session['lifecycle_state'] ?? 'unregistered',
                'login_state' => $session['auth_state'] ?? 'unverified',
                'paused' => in_array($browser, $paused, true),
                'account_bound' => $binding !== null && $this->accounts->normalizeUsername($binding['instagram_username']) === $this->accounts->normalizeUsername($site['instagram_username'] ?? ''),
                'source_available' => $site !== null,
                'notes' => $profile->notes ?? '',
                'locations' => array_values($catalog['locations'] ?? []),
                'hosts' => array_values($catalog['hosts'] ?? []),
                'synced_at' => $catalogCurrent ? $profile->synced_at?->toIso8601String() : null,
                'synced_at_display' => $catalogCurrent ? $profile->synced_at?->copy()->setTimezone('-05:00')->format('Y-m-d H:i:s').' EST' : null,
                'last_error' => $profile->last_error,
            ];
        })->all();
    }

    public function save(array $values): InstagramProfile
    {
        $values = Validator::make($values, [
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'source' => ['required', 'string', Rule::in(array_keys($this->sources))],
            'source_key' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ])->validate();
        $site = $this->site($values['source'], $values['source_key']);
        $browser = trim((string) ($site['browser_profile'] ?? ''));
        $username = $this->accounts->normalizeUsername((string) ($site['instagram_username'] ?? ''));
        if ($browser === '' || ! preg_match('/^[a-z0-9._]{1,30}$/', $username)) {
            throw ValidationException::withMessages(['source_key' => 'The website needs its own Instagram username and browser desktop first.']);
        }

        return Cache::lock('instagram:profile-writes', 15)->block(5, function () use ($values, $site, $browser, $username): InstagramProfile {
            return DB::transaction(function () use ($values, $site, $browser, $username): InstagramProfile {
                $profile = InstagramProfile::query()->firstOrNew([
                    'source' => $values['source'], 'source_key' => $values['source_key'],
                ]);
                if (InstagramProfile::query()->where('slug', $values['slug'])->when($profile->exists, fn ($q) => $q->where('id', '!=', $profile->id))->exists()) {
                    throw ValidationException::withMessages(['slug' => 'This profile name already belongs to another website.']);
                }
                $binding = $this->accounts->findAccount($browser);
                if ($binding && $this->accounts->normalizeUsername($binding['instagram_username']) !== $username) {
                    throw ValidationException::withMessages(['source_key' => 'The browser desktop is already bound to a different Instagram account.']);
                }
                if (! $binding) {
                    $this->accounts->saveAccount($site['name'], $browser, $username);
                }
                $profile->fill(['slug' => $values['slug'], 'notes' => $values['notes'] ?? null])->save();
                ActivityLog::log('instagram', 'profile_save', 'Saved website profile: '.$profile->slug, [
                    'profile_id' => $profile->id, 'source' => $profile->source, 'source_key' => $profile->source_key,
                ]);

                return $profile;
            });
        });
    }

    /** Freeze a safe website snapshot before applying it to a saved profile. */
    public function readCatalog(string $source, string $key, bool $refresh = true): array
    {
        $site = $this->site($source, $key);
        $catalog = $this->sources[$source]->catalog($key, $refresh);
        if (! is_array($catalog['locations'] ?? null) || ! is_array($catalog['hosts'] ?? null)) {
            throw new \RuntimeException('The website did not return a complete location and host catalog.');
        }

        return ['site_url' => $site['site_url'], 'locations' => array_values($catalog['locations']), 'hosts' => array_values($catalog['hosts'])];
    }

    /** Apply a prepared read through the same persistence path used by the dashboard. */
    public function saveCatalog(InstagramProfile $profile, array $catalog): InstagramProfile
    {
        return Cache::lock('instagram:profile-catalog:'.$profile->id, 300)->block(3, function () use ($profile, $catalog): InstagramProfile {
            $site = $this->site($profile->source, $profile->source_key);
            if (($catalog['site_url'] ?? null) !== $site['site_url']
                || ! is_array($catalog['locations'] ?? null) || ! is_array($catalog['hosts'] ?? null)) {
                throw new \RuntimeException('The prepared catalog no longer matches this profile website.');
            }
            $profile->forceFill([
                'catalog' => ['site_url' => $site['site_url'], 'locations' => array_values($catalog['locations']), 'hosts' => array_values($catalog['hosts'])],
                'synced_at' => now(), 'last_error' => null,
            ])->save();
            ActivityLog::log('instagram', 'profile_refresh', 'Refreshed website catalog: '.$profile->slug, [
                'profile_id' => $profile->id, 'locations' => count($catalog['locations']), 'hosts' => count($catalog['hosts']),
            ]);

            return $profile->fresh();
        });
    }

    public function refresh(InstagramProfile $profile): InstagramProfile
    {
        try {
            return $this->saveCatalog($profile, $this->readCatalog($profile->source, $profile->source_key));
        } catch (\Throwable $error) {
            $profile->forceFill(['last_error' => mb_substr($error->getMessage(), 0, 1000)])->save();
            throw $error;
        }
    }

    private function site(string $source, string $key): array
    {
        foreach (($this->sources[$source] ?? null)?->sites() ?? [] as $site) {
            if ((string) $site['key'] === $key) {
                return $site;
            }
        }

        throw ValidationException::withMessages(['source_key' => 'This website is no longer available from its registered source.']);
    }
}
