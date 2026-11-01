<?php
namespace hexa_package_instagram\Console;
use Illuminate\Console\Command;
use hexa_package_instagram\Actions\{ActionRunner,ActionRegistry,ActionRequest,Mode};
final class InstagramPublishCommand extends Command
{
    protected $signature='instagram:publish {kind} {image} {--account=} {--profile=} {--caption=} {--caption-file=} {--key=} {--force} {--approval=} {--dry-run} {--json}';
    protected $description='Publish through the official API action engine with a bound receipt and exact account.';
    public function handle(ActionRunner $runner,ActionRegistry $registry): int {
        $key=match((string)$this->argument('kind')){'story'=>'publish_story','post'=>'publish_post',default=>''};if(!$key){$this->error('Choose story or post.');return self::INVALID;}
        if($this->option('dry-run')){$this->line(json_encode(['success'=>true,'action'=>$key,'mode'=>'api','session'=>$this->option('profile'),'records'=>[],'errors'=>[],'stop'=>null,'routes'=>$registry->get($key)->routes(),'dry_run'=>true,'approval_required'=>true]));return self::SUCCESS;}
        $caption=$this->option('caption-file')?(string)file_get_contents((string)$this->option('caption-file')):(string)$this->option('caption');
        $r=$runner->run($key,new ActionRequest((string)$this->option('profile'),[(string)$this->argument('image')],Mode::Api,approval:$this->option('approval'),options:['account'=>(string)$this->option('account'),'caption'=>$caption,'key'=>(string)$this->option('key'),'force'=>(bool)$this->option('force')]));
        $this->line(json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return $r->success?self::SUCCESS:self::FAILURE;
    }
}
