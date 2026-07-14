<?php

namespace hexa_package_instagram\Services;

use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

class InstagramAccountSessionService
{
    use \hexa_package_instagram\Services\Concerns\ManagesInstagramPasswords;
    use \hexa_package_instagram\Services\Concerns\ManagesInstagramAuthentication;
    use \hexa_package_instagram\Services\Concerns\ControlsInstagramWorkerScreen;
    use \hexa_package_instagram\Services\Concerns\VerifiesInstagramSessions;
    use \hexa_package_instagram\Services\Concerns\PresentsInstagramAccounts;

    public function __construct(
        private InstagramConfigRepository $config,
        private BrowserWorkerBridgeContract $browser,
        private CredentialService $credentials,
    ) {
    }
}
