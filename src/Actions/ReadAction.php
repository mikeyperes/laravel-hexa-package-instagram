<?php
namespace hexa_package_instagram\Actions;
use hexa_package_instagram\Actions\Contracts\InstagramAction;
use hexa_package_instagram\Transport\BackgroundTransport;
abstract class ReadAction implements InstagramAction
{
    public function risk(): Risk { return Risk::Read; }
    public function modes(): array { return [Mode::Efficient, Mode::Human]; }
    public function defaultMode(): Mode { return Mode::Efficient; }
    public function parts(): array { return []; }
    protected function configuredRoutes(): array { return (array) config('social-automation.action_routes.'.$this->key(), []); }
    public function run(ActionRequest $request): ActionResult { return app(BackgroundTransport::class)->run($this, $request); }
}
