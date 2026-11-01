<?php
namespace hexa_package_instagram\Actions;
final class HighlightAdd extends HumanAction
{
    public function key(): string { return 'highlight_add'; }
    public function limitKey(): string { return 'highlight_edit'; }
}
