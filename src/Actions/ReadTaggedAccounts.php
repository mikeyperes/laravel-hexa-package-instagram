<?php
namespace hexa_package_instagram\Actions;
final class ReadTaggedAccounts extends ReadAction
{
    public function key(): string { return 'read_tagged_accounts'; }
    public function limitKey(): string { return 'retrieve_post'; }
    public function routes(): array { return $this->configuredRoutes(); }
    public function parts(): array { return ['tagged_accounts', 'mentions', 'coauthors']; }
}
