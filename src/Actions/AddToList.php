<?php
namespace hexa_package_instagram\Actions;
use hexa_package_instagram\Actions\Contracts\InstagramAction;
use hexa_package_social_automation\Automation\Lists\AccountLists;
final class AddToList implements InstagramAction
{
    public function key(): string { return 'add_to_list'; }
    public function risk(): Risk { return Risk::Read; }
    public function limitKey(): string { return ''; }
    public function modes(): array { return [Mode::Local]; }
    public function defaultMode(): Mode { return Mode::Local; }
    public function routes(): array { return (array)config('social-automation.action_routes.add_to_list',[]); }
    public function parts(): array { return ['membership']; }
    public function run(ActionRequest $r): ActionResult {
        $lists=app(AccountLists::class);$list=$lists->find((string)($r->options['list'] ?? ''));
        $membership=$lists->addOne($list,$r->targets[0]);
        return new ActionResult(true,$this->key(),'local',$r->session,[new InstagramResult($r->targets[0],$membership['added']?'done':'already',['membership'=>$membership],now('UTC')->toIso8601String(),'local',$r->session)]);
    }
}
