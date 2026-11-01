<?php
namespace hexa_package_instagram\Actions;
final class ReadThread extends ReadAction
{
    public function key(): string { return 'read_thread'; }
    public function limitKey(): string { return 'open_inbox'; }
    public function routes(): array { return $this->configuredRoutes(); }
    public function parts(): array { return ['threads','messages','participants']; }
}
