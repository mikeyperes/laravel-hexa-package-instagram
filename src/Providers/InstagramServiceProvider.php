<?php

namespace hexa_package_instagram\Providers;

use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_instagram\Services\InstagramAccountSessionService;
use hexa_package_instagram\Services\InstagramImportService;
use hexa_package_instagram\Services\InstagramScraperService;
use Illuminate\Support\ServiceProvider;

class InstagramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/instagram.php', 'instagram');

        $this->app->singleton(InstagramConfigRepository::class);
        $this->app->singleton(InstagramImportService::class);
        $this->app->singleton(InstagramScraperService::class);
        $this->app->singleton(InstagramAccountSessionService::class);
    }

    public function boot(): void
    {
        if (!config('instagram.enabled', true)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../../routes/instagram.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'instagram');

        $this->registerWithPackageRegistry();
        $this->registerDocs();
    }

    private function registerWithPackageRegistry(): void
    {
        $registryClass = 'hexa_core\\Services\\PackageRegistryService';
        if (!class_exists($registryClass)) {
            return;
        }

        $this->app->booted(function () use ($registryClass) {
            try {
                /** @var \hexa_core\Services\PackageRegistryService $registry */
                $registry = app($registryClass);

                $domainIcon = 'M4 6h16M4 12h16M4 18h16';
                $sectionIcon = 'M7 2h10a5 5 0 015 5v10a5 5 0 01-5 5H7a5 5 0 01-5-5V7a5 5 0 015-5zm8 2a1 1 0 100 2 1 1 0 000-2zm-3 3.5A4.5 4.5 0 1016.5 12 4.5 4.5 0 0012 7.5z';
                $homeIcon = 'M4 5h16v14H4z M7 9h10m-10 4h8';
                $accountsIcon = 'M17 20h5v-2a4 4 0 00-4-4h-1m-4 6H6a4 4 0 01-4-4v-1a4 4 0 014-4h7a4 4 0 014 4v1a4 4 0 01-4 4zm-3-10a4 4 0 100-8 4 4 0 000 8z';
                $rawIcon = 'M4 5h16v14H4z M7 9h10M7 13h8';
                $settingsIcon = 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z';

                $registry->registerDomainGroup('Automation', $domainIcon, 83);
                $registry->registerSectionGroup('Instagram', 'Automation', $sectionIcon, 86);
                $registry->registerSidebarLink('instagram.index', 'Instagram', $homeIcon, 'Instagram', 'instagram', 70);
                $registry->registerSidebarLink('instagram.accounts', 'Accounts', $accountsIcon, 'Instagram', 'instagram', 71);
                $registry->registerSidebarLink('instagram.raw', 'Raw Workspace', $rawIcon, 'Instagram', 'instagram', 72);
                $registry->registerSidebarLink('settings.instagram', 'Settings', $settingsIcon, 'Instagram', 'instagram', 73);

                if (method_exists($registry, 'registerSidebarSettingsLink')) {
                    $registry->registerSidebarSettingsLink('Instagram', 'settings.instagram', 73);
                }

                if (method_exists($registry, 'registerPackage')) {
                    $registry->registerPackage('instagram', 'hexawebsystems/laravel-hexa-package-instagram', [
                        'title' => 'Instagram',
                        'color' => 'rose',
                        'icon' => $sectionIcon,
                        'settingsRoute' => 'settings.instagram',
                        'settingsShellClass' => 'max-w-5xl',
                        'docsSlug' => 'instagram',
                        'instructions' => [
                            'Save the optional Meta oEmbed token in Settings if you want the official token-based import path available.',
                            'Attach one or more Instagram browser accounts on the Accounts page. Each account gets its own persistent browser profile.',
                            'Run the full connection test in Settings or the Raw Workspace to confirm the active browser account is truly logged in.',
                            'Use Raw Workspace to test profile scans, story pulls, and post import before wiring automation jobs.',
                        ],
                        'apiLinks' => [
                            ['label' => 'Instagram', 'url' => 'https://www.instagram.com/'],
                            ['label' => 'Meta Instagram oEmbed', 'url' => 'https://developers.facebook.com/docs/instagram-platform/oembed'],
                        ],
                    ]);
                }

                if (method_exists($registry, 'registerPermissions')) {
                    $registry->registerPermissions('instagram', [
                        'groups' => [
                            'Instagram' => ['settings.instagram*', 'instagram.*'],
                        ],
                        'roleDefaults' => [
                            'admin' => ['settings.instagram*', 'instagram.*'],
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
            }
        });
    }

    private function registerDocs(): void
    {
        $docsClass = 'hexa_core\\Services\\DocumentationService';
        if (!class_exists($docsClass)) {
            return;
        }

        try {
            app($docsClass)->register('instagram', 'Instagram', 'hexawebsystems/laravel-hexa-package-instagram', [
                [
                    'title' => 'Overview',
                    'content' => '<p>Instagram browser-account attach, connection testing, raw profile/story scraping, and public post import for Hexa apps.</p>',
                ],
                [
                    'title' => 'Main Flow',
                    'content' => '<p>Save the optional Meta oEmbed token, attach one or more Instagram accounts on the Accounts page, log in with the saved account credentials, run the full connection test, and use Raw Workspace for profile, story, and post checks.</p>',
                ],
            ]);
        } catch (\Throwable $e) {
        }
    }
}
