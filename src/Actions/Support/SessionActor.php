<?php
namespace hexa_package_instagram\Actions\Support;
use Carbon\CarbonInterface;
use hexa_package_human_behavior\Contracts\Actor;
use hexa_package_social_automation\Automation\Devices\Models\Device;
/** Shares the existing extension identity, including its warm-up date. */
final class SessionActor implements Actor
{
    public function __construct(private readonly string $session) {}
    public function actorKey(): string { return 'social-automation:'.$this->session; }
    public function actorSince(): ?CarbonInterface { return Device::query()->where('profile', $this->session)->value('paired_at'); }
}
