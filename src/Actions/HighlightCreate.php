<?php
namespace hexa_package_instagram\Actions;
final class HighlightCreate extends HumanAction
{
    public function key(): string { return 'highlight_create'; }
    public function limitKey(): string { return 'highlight_edit'; }
}
