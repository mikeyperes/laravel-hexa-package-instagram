<?php
namespace hexa_package_instagram\Console;
use Illuminate\Console\Command;
use hexa_package_instagram\Actions\{ActionRunner,ActionRegistry,ActionRequest,ActionResult,Mode};
use hexa_package_social_automation\Automation\Campaigns\Models\Campaign;
final class InstagramRunCommand extends Command
{
    protected $signature='instagram:run {action} {--profile=} {--mode=} {--targets=} {--only=} {--skip=} {--options={}} {--context={}} {--approval=} {--campaign=} {--wait} {--json}';
    protected $description='Run one registered action with one or many targets and the shared JSON contract.';
    public function handle(ActionRunner $runner,ActionRegistry $registry): int {
        $key=(string)$this->argument('action');$session=(string)$this->option('profile');$mode=(string)$this->option('mode');
        try {
            $mode=$mode!==''?(Mode::tryFrom($mode) ?? throw new \InvalidArgumentException('Unknown mode.')):$registry->get($key)->defaultMode();
            $list=function($v){$v=(string)$v;if($v==='')return [];if(str_starts_with(trim($v),'['))return json_decode($v,true,512,JSON_THROW_ON_ERROR);return array_values(array_filter(array_map('trim',explode(',',$v))));};
            $r=$runner->run($key,new ActionRequest($session,$list($this->option('targets')),$mode,$list($this->option('only')),$list($this->option('skip')),$this->option('approval'),$this->option('campaign')?(int)$this->option('campaign'):null,json_decode($this->option('context'),true,512,JSON_THROW_ON_ERROR),json_decode($this->option('options'),true,512,JSON_THROW_ON_ERROR)));
            if($this->option('wait') && $r->campaignId){
                $until=microtime(true)+300;
                do {$campaign=Campaign::findOrFail($r->campaignId);if($campaign->isFinished() || $campaign->status===Campaign::PAUSED)break;sleep(2);}while(microtime(true)<$until);
                $r->records=$campaign->records()->where('kind','network_action')->get()->pluck('data')->all();
                $r->routes=app(\hexa_package_social_automation\Automation\Routes\RouteReport::class)->rows($campaign);
                $r->errors=$campaign->tasks()->whereNotNull('error_code')->get(['error_code','error_message'])->toArray();
                $r->success=$campaign->status===Campaign::COMPLETED && !$r->errors;
                $r->stop=$r->success?null:($campaign->status===Campaign::RUNNING?'pending':$campaign->status);
            }
        }catch(\Throwable $e){$r=new ActionResult(false,$key,$mode instanceof Mode?$mode->value:$mode,$session,[],[$e->getMessage()]);}
        $this->line(json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return $r->success?self::SUCCESS:self::FAILURE;
    }
}
