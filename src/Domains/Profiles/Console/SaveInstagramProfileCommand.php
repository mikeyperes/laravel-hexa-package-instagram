<?php

namespace hexa_package_instagram\Domains\Profiles\Console;

use hexa_package_instagram\Domains\Profiles\Services\InstagramProfileRegistry;
use Illuminate\Console\Command;

class SaveInstagramProfileCommand extends Command
{
    protected $signature = 'instagram:profile-save {slug : Profile name} {source : Registered website source} {source_key : Website record key} {--notes=}';
    protected $description = 'Save one website profile and its existing account binding; never launches a desktop or changes a proxy.';

    public function handle(InstagramProfileRegistry $registry): int
    {
        try {
            $profile = $registry->save([
                'slug' => $this->argument('slug'), 'source' => $this->argument('source'),
                'source_key' => $this->argument('source_key'), 'notes' => $this->option('notes'),
            ]);
            $this->line(json_encode(['success' => true, 'id' => $profile->id, 'slug' => $profile->slug]));

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
