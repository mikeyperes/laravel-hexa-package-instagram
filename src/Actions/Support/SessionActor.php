<?php
namespace hexa_package_instagram\Actions\Support;
use Carbon\CarbonInterface;
use hexa_package_human_behavior\Contracts\Actor;
use hexa_package_social_automation\Automation\Devices\Models\Device;
/** Shares the existing extension identity, including its warm-up date. */
final class SessionActor implements Actor
{
    public function __construct(private readonly string $session) {}
    // CRITICAL - see BUGLOG.md IG-2026-11-06-01: the logged-out reader has no Instagram account, so its
    // read limits protect each exit IP; a new NordVPN machine starts its own budget.
    public function actorKey(): string
    {
        $key = 'social-automation:'.$this->session;
        $nordvpn = 'hexa_package_nordvpn\\Services\\NordVpnService';
        if ($this->session !== config('instagram.public_reader_profile', 'instagram-public') || ! class_exists($nordvpn)) return $key;
        $node = (string) app($nordvpn)->nodeFor($this->session);
        return $node === '' ? $key : $key.'@'.$node;
    }
    public function actorSince(): ?CarbonInterface { return Device::query()->where('profile', $this->session)->value('paired_at'); }
}
