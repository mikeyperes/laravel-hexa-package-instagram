<?php
namespace hexa_package_instagram\Console;
use Illuminate\Console\Command;
use hexa_package_instagram\Actions\{ActionRunner,ActionRequest,Mode};
final class InstagramStoriesCommand extends Command
{
    protected $signature='instagram:stories {account} {--profile=} {--mode=efficient} {--download} {--owner-lookups=10} {--json}';
    protected $description='Read current stories or download one story link through the shared engine.';
    public function handle(ActionRunner $runner): int {
        $mode=Mode::tryFrom((string)$this->option('mode')) ?? Mode::Efficient;
        $key=$this->option('download')?'download_story':'read_stories';
        $r=$runner->run($key,new ActionRequest((string)$this->option('profile'),[(string)$this->argument('account')],$mode,options:['owner_lookups'=>(int)$this->option('owner-lookups')]));
        $this->line(json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return $r->success?self::SUCCESS:self::FAILURE;
    }
}
