<?php

namespace hexa_package_instagram\Domains\Profiles\Contracts;

/** A website owner supplies its records; Instagram never imports that owner's models. */
interface InstagramProfileSiteSource
{
    public function key(): string;

    /**
     * Safe website descriptors: key, name, site_url, instagram_username,
     * browser_profile, region and timezone. No credentials or browser state.
     */
    public function sites(): array;

    /** Read the complete website catalog, or throw. Return locations and hosts only. */
    public function catalog(string $siteKey, bool $refresh = false): array;
}
