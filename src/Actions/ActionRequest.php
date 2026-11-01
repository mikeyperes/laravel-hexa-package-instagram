<?php
namespace hexa_package_instagram\Actions;
final class ActionRequest
{
    public function __construct(
        public readonly string $session,
        public readonly array $targets,
        public readonly Mode $mode = Mode::Efficient,
        public readonly array $only = [],
        public readonly array $skip = [],
        public readonly ?string $approval = null,
        public readonly ?int $campaignId = null,
        public readonly array $context = [],
        public readonly array $options = [],
    ) {}
    public function forTarget(string $target): self
    {
        return new self($this->session, [$target], $this->mode, $this->only, [], $this->approval, $this->campaignId, $this->context, $this->options);
    }
}
