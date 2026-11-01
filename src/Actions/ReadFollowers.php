<?php
namespace hexa_package_instagram\Actions;
final class ReadFollowers extends ReadAction
{
    public function key(): string { return 'read_followers'; }
    public function limitKey(): string { return 'read_followers'; }
    public function routes(): array { return $this->configuredRoutes(); }
    public function parts(): array { return ['users','complete']; }
}
