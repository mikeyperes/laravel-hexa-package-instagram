<?php
namespace hexa_package_instagram\Console;
use Illuminate\Console\Command;
use hexa_package_instagram\Actions\{ActionRunner,ActionRegistry,ActionRequest,Mode};
final class InstagramActCommand extends Command
{
    protected $signature='instagram:act {action : follow, unfollow or like} {--targets=} {--profile=} {--approval=} {--dry-run} {--json}';
    protected $description='Prepare or run a human-only account action through its verified approval gate.';
    public function handle(ActionRunner $runner,ActionRegistry $registry): int {
        $key=$this->argument('action')==='like'?'like_post':(string)$this->argument('action');
        if(!in_array($key,['follow','unfollow','like_post'],true)){$this->error('Unknown account action.');return self::INVALID;}
        $targets=array_values(array_filter(array_map('trim',explode(',',(string)$this->option('targets')))));
        if($this->option('dry-run')){$this->line(json_encode(['success'=>true,'action'=>$key,'mode'=>'human','session'=>$this->option('profile'),'records'=>[],'errors'=>[],'stop'=>null,'routes'=>$registry->get($key)->routes(),'targets'=>$targets,'dry_run'=>true,'approval_required'=>true]));return self::SUCCESS;}
        $result=$runner->run($key,new ActionRequest((string)$this->option('profile'),$targets,Mode::Human,approval:$this->option('approval')));
        $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return $result->success?self::SUCCESS:self::FAILURE;
    }
}
