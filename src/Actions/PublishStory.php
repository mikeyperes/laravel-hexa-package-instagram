<?php
namespace hexa_package_instagram\Actions;
final class PublishStory extends HumanAction
{
    public function key(): string { return 'publish_story'; }
    public function limitKey(): string { return 'publish'; }
    public function modes(): array { return [Mode::Api]; }
    public function defaultMode(): Mode { return Mode::Api; }
    public function run(ActionRequest $r): ActionResult { return app(\hexa_package_instagram\Transport\ApiTransport::class)->run($this,$r); }
}
