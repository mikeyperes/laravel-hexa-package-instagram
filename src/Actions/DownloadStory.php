<?php
namespace hexa_package_instagram\Actions;
final class DownloadStory extends ReadAction
{
    public function key(): string { return 'download_story'; }
    public function limitKey(): string { return 'read_stories'; }
    public function routes(): array { return $this->configuredRoutes(); }
    public function parts(): array { return ['download']; }
    public function modes(): array { return [Mode::Efficient]; }
}
