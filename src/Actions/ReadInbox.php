<?php
namespace hexa_package_instagram\Actions;
final class ReadInbox extends ReadAction
{
    public function key(): string { return 'read_inbox'; }
    public function limitKey(): string { return 'open_inbox'; }
    public function routes(): array { return $this->configuredRoutes(); }
    public function parts(): array { return ['threads','messages','participants']; }
}
