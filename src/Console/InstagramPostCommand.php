<?php
namespace hexa_package_instagram\Console;
use hexa_package_instagram\Actions\{ActionRunner,ActionRequest,Mode};
use Illuminate\Console\Command;
final class InstagramPostCommand extends Command
{
    protected $signature = 'instagram:post {post} {--profile=} {--mode=efficient} {--fallback-profile= : Explicit logged-in fallback after an inconclusive public embed} {--owner= : Known post owner for human profile-grid navigation} {--only=} {--skip=} {--campaign=} {--json} {--logged-out} {--comments=20}';
    protected $description = 'Run read_post through the shared Instagram action runner.';
    public function handle(ActionRunner $runner): int {
        $session = (string)$this->option('profile');
        if ($this->option('logged-out') && $session === '') $session = (string)config('instagram.public_reader_profile','instagram-public');
        $mode = Mode::tryFrom((string)$this->option('mode'));
        if (!$mode) { $this->line(json_encode(['success'=>false,'action'=>'read_post','mode'=>$this->option('mode'),'session'=>$session,'records'=>[],'errors'=>['Unknown mode.'],'stop'=>null,'routes'=>[]])); return self::INVALID; }
        $split = fn ($v) => array_values(array_filter(array_map('trim',explode(',',(string)$v))));
        $options = ['owner'=>(string)$this->option('owner'),'fallback_session'=>(string)$this->option('fallback-profile'), 'comments'=>(int)$this->option('comments')];
        $result = $runner->run('read_post',new ActionRequest($session,[(string)$this->argument('post')],$mode,$split($this->option('only')),$split($this->option('skip')),null,$this->option('campaign') ? (int)$this->option('campaign') : null, [], $options));
        $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); return $result->success ? self::SUCCESS : self::FAILURE;
    }
}
