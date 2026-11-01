<?php
namespace hexa_package_instagram\Actions;
final class ReadAccountPosts extends ReadAction
{
    public function key(): string { return 'read_account_posts'; }
    public function limitKey(): string { return 'read_posts'; }
    public function routes(): array { return $this->configuredRoutes(); }
    public function parts(): array { return ['posts']; }
}
