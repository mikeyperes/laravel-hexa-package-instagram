<?php
namespace hexa_package_instagram\Console;
use Illuminate\Console\Command;
use hexa_package_instagram\Actions\{ActionRunner,ActionRegistry,ActionRequest,Mode};
final class InstagramDmCommand extends Command
{
    protected $signature='instagram:dm {operation : inbox, read or send} {target? : Exact thread id for read/send} {--profile=} {--mode=efficient} {--limit=20} {--message=} {--approval=} {--dry-run} {--json}';
    protected $description='Read inbox/thread data or prepare a receipt-gated human message in an existing thread.';
    public function handle(ActionRunner $runner,ActionRegistry $registry): int {
        $key=match((string)$this->argument('operation')){'inbox'=>'read_inbox','read'=>'read_thread','send'=>'send_dm',default=>''};
        if(!$key){$this->error('Choose inbox, read or send.');return self::INVALID;}
        $mode=$key==='send_dm'?Mode::Human:(Mode::tryFrom((string)$this->option('mode')) ?? Mode::Efficient);
        $target=$key==='read_inbox'?'inbox':(string)$this->argument('target');
        $options=['limit'=>(int)$this->option('limit')];if($key==='send_dm')$options=['message'=>(string)$this->option('message')];
        if($this->option('dry-run')){$this->line(json_encode(['success'=>true,'action'=>$key,'mode'=>$mode->value,'session'=>$this->option('profile'),'records'=>[],'errors'=>[],'stop'=>null,'routes'=>$registry->get($key)->routes(),'dry_run'=>true,'approval_required'=>$key==='send_dm']));return self::SUCCESS;}
        $r=$runner->run($key,new ActionRequest((string)$this->option('profile'),[$target],$mode,approval:$this->option('approval'),options:$options));
        $this->line(json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return $r->success?self::SUCCESS:self::FAILURE;
    }
}
