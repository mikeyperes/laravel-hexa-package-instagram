<?php
namespace hexa_package_instagram\Console;
use hexa_package_instagram\Actions\{ActionRunner,ActionRequest,Mode};
use Illuminate\Console\Command;
final class InstagramTaggedAccountsCommand extends Command
{
    protected $signature = 'instagram:tagged-accounts {post} {--profile=} {--mode=efficient} {--fallback-profile= : Explicit logged-in fallback after an inconclusive public embed} {--only=} {--skip=} {--campaign=} {--json} {--classify=1}';
    protected $description = 'Run read_tagged_accounts through the shared Instagram action runner.';
    public function handle(ActionRunner $runner): int {
        $session = (string)$this->option('profile');
        
        $mode = Mode::tryFrom((string)$this->option('mode'));
        if (!$mode) { $this->line(json_encode(['success'=>false,'action'=>'read_tagged_accounts','mode'=>$this->option('mode'),'session'=>$session,'records'=>[],'errors'=>['Unknown mode.'],'stop'=>null,'routes'=>[]])); return self::INVALID; }
        $split = fn ($v) => array_values(array_filter(array_map('trim',explode(',',(string)$v))));
        $options = ['fallback_session'=>(string)$this->option('fallback-profile'), 'classify'=>(bool)$this->option('classify'), 'comments'=>0];
        $result = $runner->run('read_tagged_accounts',new ActionRequest($session,[(string)$this->argument('post')],$mode,$split($this->option('only')),$split($this->option('skip')),null,$this->option('campaign') ? (int)$this->option('campaign') : null, [], $options));
        $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); return $result->success ? self::SUCCESS : self::FAILURE;
    }
}
