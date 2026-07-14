<?php

namespace Tests\Feature;

require_once __DIR__ . '/Concerns/TestsInstagramAccountSessions.php';
require_once __DIR__ . '/Concerns/TestsInstagramPublicImportsAndScans.php';
require_once __DIR__ . '/Concerns/TestsInstagramVerificationAndRoutes.php';
require_once __DIR__ . '/Concerns/TestsInstagramProfileDiscovery.php';

use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_browser_worker\Services\BrowserHttpService;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_instagram\Services\InstagramAccountSessionService;
use hexa_package_instagram\Services\InstagramImportService;
use hexa_package_instagram\Services\InstagramScraperService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstagramPackageTest extends TestCase
{
    use \Tests\Feature\Concerns\TestsInstagramAccountSessions;
    use \Tests\Feature\Concerns\TestsInstagramPublicImportsAndScans;
    use \Tests\Feature\Concerns\TestsInstagramVerificationAndRoutes;
    use \Tests\Feature\Concerns\TestsInstagramProfileDiscovery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireInstalledPackage(
            'hexawebsystems/laravel-hexa-package-instagram',
            InstagramConfigRepository::class
        );

        $appKey = 'base64:' . base64_encode(str_repeat('i', 32));
        config()->set('app.key', $appKey);
        putenv('APP_KEY=' . $appKey);
        $_ENV['APP_KEY'] = $appKey;
        $_SERVER['APP_KEY'] = $appKey;

        Schema::dropIfExists('settings');
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->string('type')->default('text');
            $table->string('label')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('activity_logs');
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->longText('context')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_timezone')->nullable();
            $table->timestamps();
        });
    }
}
