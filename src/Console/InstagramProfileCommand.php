<?php
namespace hexa_package_instagram\Console;
use hexa_package_instagram\Actions\{ActionRunner,ActionRequest,Mode};
use Illuminate\Console\Command;
final class InstagramProfileCommand extends Command
{
    protected $signature = 'instagram:profile {account} {--profile=} {--mode=efficient} {--only=} {--skip=} {--campaign=} {--json} ';
    protected $description = 'Run read_profile through the shared Instagram action runner.';
    public function handle(ActionRunner $runner): int {
        $session = (string)$this->option('profile');
        
        $mode = Mode::tryFrom((string)$this->option('mode'));
        if (!$mode) { $this->line(json_encode(['success'=>false,'action'=>'read_profile','mode'=>$this->option('mode'),'session'=>$session,'records'=>[],'errors'=>['Unknown mode.'],'stop'=>null,'routes'=>[]])); return self::INVALID; }
        $split = fn ($v) => array_values(array_filter(array_map('trim',explode(',',(string)$v))));
        $options = [];
        $result = $runner->run('read_profile',new ActionRequest($session,[(string)$this->argument('account')],$mode,$split($this->option('only')),$split($this->option('skip')),null,$this->option('campaign') ? (int)$this->option('campaign') : null, [], $options));
        $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); return $result->success ? self::SUCCESS : self::FAILURE;
    }
}
