<?php
namespace hexa_package_instagram\Actions;
final class HighlightRemove extends HumanAction
{
    public function key(): string { return 'highlight_remove'; }
    public function limitKey(): string { return 'highlight_edit'; }
}
