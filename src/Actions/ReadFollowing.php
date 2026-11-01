<?php
namespace hexa_package_instagram\Actions;
final class ReadFollowing extends ReadAction
{
    public function key(): string { return 'read_following'; }
    public function limitKey(): string { return 'read_following'; }
    public function routes(): array { return $this->configuredRoutes(); }
    public function parts(): array { return ['users','complete']; }
}
