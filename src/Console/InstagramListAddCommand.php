<?php
namespace hexa_package_instagram\Console;
use Illuminate\Console\Command;
use hexa_package_instagram\Actions\{ActionRunner,ActionRequest,Mode};
final class InstagramListAddCommand extends Command
{
    protected $signature='instagram:list-add {--list=} {--account=} {--profile=} {--json}';
    protected $description='Add one account to a local saved list through the Instagram engine.';
    public function handle(ActionRunner $runner): int {
        $result=$runner->run('add_to_list',new ActionRequest((string)$this->option('profile'),[(string)$this->option('account')],Mode::Local,options:['list'=>(string)$this->option('list')]));
        $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return $result->success?self::SUCCESS:self::FAILURE;
    }
}
