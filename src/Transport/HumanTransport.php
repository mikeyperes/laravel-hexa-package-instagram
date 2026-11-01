<?php
namespace hexa_package_instagram\Transport;
use hexa_package_instagram\Actions\{ActionRequest,ActionResult,ActionRegistry};
use hexa_package_social_automation\Automation\Campaigns\CampaignService;
use hexa_package_social_automation\Automation\Campaigns\Models\Campaign;
use hexa_package_social_automation\Automation\Devices\Models\Device;
final class HumanTransport
{
    public function __construct(private readonly CampaignService $campaigns,private readonly InstagramActionBridge $bridge) {}
    public function run(string $action,ActionRequest $r): ActionResult {
        $device=Device::query()->where('profile',$r->session)->first();
        if (!$device || $device->status!==Device::ACTIVE) return new ActionResult(false,$action,'human',$r->session,[],['The session has no paired active extension.'],'extension_unavailable');
        if ($device->inCooldown()) return new ActionResult(false,$action,'human',$r->session,[],['The session is resting until '.$device->cooldown_until->toIso8601String()],'cooldown');
        $params=['session'=>$r->session,'targets'=>array_values(array_diff(array_unique($r->targets),$r->skip)),'only'=>$r->only,'options'=>$r->options,'approval'=>$r->approval,'context'=>$r->context];
        if (!$params['targets']) return new ActionResult(true,$action,'human',$r->session);
        $definition=$this->bridge->definitions()[$action];
        $existing=$r->campaignId ? Campaign::query()->find($r->campaignId) : ($r->approval ? Campaign::query()->where('network','instagram')->where('profile',$r->session)->where('process',$definition['process_key'])->where('params->approval',$r->approval)->latest('id')->first() : null);
        if($existing) {
            if($existing->profile!==$r->session || $existing->process!==$definition['process_key'] || $existing->param('targets')!==$this->bridge->normalize($action,$params)['targets']) throw new \InvalidArgumentException('The campaign belongs to another request.');
            return new ActionResult(true,$action,'human',$r->session,[],[],null,[],(int)$existing->id);
        }
        $campaign=$this->campaigns->create('instagram',$definition['process_key'],$r->session,$params,null,$definition['approvals']);
        $this->campaigns->start($campaign);
        return new ActionResult(true,$action,'human',$r->session,[],[],null,[],(int)$campaign->id);
    }
}
