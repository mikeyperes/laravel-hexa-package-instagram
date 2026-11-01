<?php
namespace hexa_package_instagram\Console;
use Illuminate\Console\Command;
use hexa_package_instagram\Actions\{ActionRunner,ActionRequest,Mode};
final class InstagramNetworkCommand extends Command
{
    protected $signature='instagram:network {direction} {account} {--profile=} {--mode=efficient} {--limit=100} {--json}';
    protected $description='Read following or followers through the shared action engine.';
    public function handle(ActionRunner $runner): int {
        $key=match($this->argument('direction')){'following'=>'read_following','followers'=>'read_followers',default=>''};
        if($key===''){$this->error('Choose following or followers.');return self::INVALID;}
        $r=$runner->run($key,new ActionRequest((string)$this->option('profile'),[(string)$this->argument('account')],Mode::tryFrom((string)$this->option('mode')) ?? Mode::Efficient,options:['limit'=>(int)$this->option('limit')]));
        $this->line(json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return $r->success?self::SUCCESS:self::FAILURE;
    }
}
