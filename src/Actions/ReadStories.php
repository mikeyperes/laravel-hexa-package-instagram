<?php
namespace hexa_package_instagram\Actions;
final class ReadStories extends ReadAction
{
    public function key(): string { return 'read_stories'; }
    public function limitKey(): string { return 'read_stories'; }
    public function routes(): array { return $this->configuredRoutes(); }
    public function parts(): array { return ['stories']; }
}
