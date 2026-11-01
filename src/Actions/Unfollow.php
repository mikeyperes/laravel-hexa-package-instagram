<?php
namespace hexa_package_instagram\Actions;
final class Unfollow extends HumanAction
{
    public function key(): string { return 'unfollow'; }
    public function limitKey(): string { return 'unfollow'; }
}
