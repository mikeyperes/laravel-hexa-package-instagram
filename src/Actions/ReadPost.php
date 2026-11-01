<?php
namespace hexa_package_instagram\Actions;
final class ReadPost extends ReadAction
{
    public function key(): string { return 'read_post'; }
    public function limitKey(): string { return 'retrieve_post'; }
    public function routes(): array { return $this->configuredRoutes(); }
    public function parts(): array { return ['owner', 'url', 'code', 'taken_at', 'caption', 'location', 'like_count', 'comment_count', 'slides', 'comments', 'tagged_accounts', 'mentions', 'coauthors']; }
}
