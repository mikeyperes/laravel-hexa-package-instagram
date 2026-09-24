<?php

namespace hexa_package_instagram\Services;

use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

class InstagramScraperService
{
    use \hexa_package_instagram\Services\Concerns\ScansInstagramProfiles;
    use \hexa_package_instagram\Services\Concerns\ScansInstagramPosts;
    use \hexa_package_instagram\Services\Concerns\ReadsInstagramFeeds;
    use \hexa_package_instagram\Services\Concerns\ReadsPublicInstagramFeeds;
    use \hexa_package_instagram\Services\Concerns\ReadsInstagramAccounts;
    use \hexa_package_instagram\Services\Concerns\PublishesInstagramMedia;
    use \hexa_package_instagram\Services\Concerns\ManagesInstagramHighlights;

    public function __construct(
        private InstagramConfigRepository $config,
        private BrowserWorkerBridgeContract $browser,
        private InstagramImportService $imports,
    ) {
    }
}
