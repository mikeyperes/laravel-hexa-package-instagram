<?php
namespace hexa_package_instagram\Actions;
final class LikePost extends HumanAction
{
    public function key(): string { return 'like_post'; }
    public function limitKey(): string { return 'like_post'; }
    public function risk(): Risk { return Risk::Medium; }
}
