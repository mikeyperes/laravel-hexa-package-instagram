<?php
namespace hexa_package_instagram\Actions;
use JsonSerializable;
final class InstagramResult implements JsonSerializable
{
    public function __construct(
        public readonly string $target,
        public readonly string $outcome,
        public readonly array $evidence,
        public readonly string $at,
        public readonly string $mode,
        public readonly string $session,
        public readonly array $routes = [],
    ) {}
    public function jsonSerialize(): array { return get_object_vars($this); }
}
