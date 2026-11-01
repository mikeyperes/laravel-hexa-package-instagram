<?php
namespace hexa_package_instagram\Actions;
use hexa_package_instagram\Actions\Contracts\InstagramAction;
abstract class HumanAction implements InstagramAction
{
    public function risk(): Risk { return Risk::High; }
    public function modes(): array { return [Mode::Human]; }
    public function defaultMode(): Mode { return Mode::Human; }
    public function routes(): array { return (array)config('social-automation.action_routes.'.$this->key(),[]); }
    public function parts(): array { return []; }
    public function run(ActionRequest $r): ActionResult { return app(\hexa_package_instagram\Transport\HumanTransport::class)->run($this->key(),$r); }
}
