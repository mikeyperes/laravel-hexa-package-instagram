<?php
namespace hexa_package_instagram\Actions;
final class Follow extends HumanAction
{
    public function key(): string { return 'follow'; }
    public function limitKey(): string { return 'follow'; }
}
