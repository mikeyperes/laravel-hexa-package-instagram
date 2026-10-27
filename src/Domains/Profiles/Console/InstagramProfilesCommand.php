<?php

namespace hexa_package_instagram\Domains\Profiles\Console;

use hexa_package_instagram\Domains\Profiles\Models\InstagramProfile;
use hexa_package_instagram\Domains\Profiles\Services\InstagramProfileRegistry;
use Illuminate\Console\Command;

class InstagramProfilesCommand extends Command
{
    protected $signature = 'instagram:profiles {--profile= : Exact saved profile name} {--refresh : Read website catalogs and save their snapshots}';
    protected $description = 'List website profiles, browser mappings and saved locations/hosts; refresh only with --refresh.';

    public function handle(InstagramProfileRegistry $registry): int
    {
        $query = InstagramProfile::query()->orderBy('id');
        if ($slug = $this->option('profile')) {
            $query->where('slug', $slug);
            if (! (clone $query)->exists()) {
                $this->error('Unknown Instagram profile: '.$slug);

                return self::FAILURE;
            }
        }
        $errors = [];
        if ($this->option('refresh')) {
            foreach ($query->get() as $profile) {
                try {
                    $registry->refresh($profile);
                } catch (\Throwable $error) {
                    $errors[$profile->slug] = $error->getMessage();
                }
            }
        }
        $profiles = array_values(array_filter($registry->all(), fn (array $row): bool => ! $slug || $row['slug'] === $slug));
        $this->line(json_encode(['success' => $errors === [], 'profiles' => $profiles, 'errors' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
