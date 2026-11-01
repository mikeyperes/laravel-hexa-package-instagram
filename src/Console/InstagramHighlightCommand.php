<?php
namespace hexa_package_instagram\Console;
use Illuminate\Console\Command;
use hexa_package_instagram\Actions\{ActionRunner,ActionRegistry,ActionRequest,Mode};
final class InstagramHighlightCommand extends Command
{
    protected $signature='instagram:highlight {action=show} {--target=} {--key=} {--title=} {--story-id=*} {--story-key=*} {--exact} {--profile=} {--mode=efficient} {--approval=} {--dry-run} {--json}';
    protected $description='Read highlights or prepare receipt-gated human highlight additions, removals and creation.';
    public function handle(ActionRunner $runner,ActionRegistry $registry): int {
        $key=match((string)$this->argument('action')){'show'=>'read_highlights','add'=>'highlight_add','remove'=>'highlight_remove','create'=>'highlight_create',default=>''};
        if($key===''){$this->error('Use show, add, remove or create. Changes require a bound receipt.');return self::INVALID;}
        $target=(string)($this->option('target') ?: $this->option('key') ?: $this->option('title') ?: 'self');
        $mode=$key==='read_highlights'?(Mode::tryFrom((string)$this->option('mode')) ?? Mode::Efficient):Mode::Human;
        if($this->option('dry-run')){$this->line(json_encode(['success'=>true,'action'=>$key,'mode'=>$mode->value,'session'=>$this->option('profile'),'records'=>[],'errors'=>[],'stop'=>null,'routes'=>$registry->get($key)->routes(),'dry_run'=>true]));return self::SUCCESS;}
        $r=$runner->run($key,new ActionRequest((string)$this->option('profile'),[$target],$mode,approval:$this->option('approval'),options:['title'=>(string)$this->option('title'),'story_ids'=>(array)$this->option('story-id')]));
        $this->line(json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return $r->success?self::SUCCESS:self::FAILURE;
    }
}
