<?php
namespace hexa_package_instagram\Actions;
final class ReadHighlights extends ReadAction
{
    public function key(): string { return 'read_highlights'; }
    public function limitKey(): string { return 'open_profile'; }
    public function routes(): array { return $this->configuredRoutes(); }
    public function parts(): array { return ['highlights']; }
}
