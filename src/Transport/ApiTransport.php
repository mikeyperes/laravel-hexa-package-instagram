<?php
namespace hexa_package_instagram\Transport;
use hexa_package_instagram\Actions\{ActionRequest,ActionResult,InstagramResult};
use hexa_package_instagram\Actions\Contracts\InstagramAction;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_facebook\Domains\Accounts\Services\MetaAccountRepository;
use hexa_package_facebook\Domains\Publishing\Services\MetaInstagramPublisher;
/** Official API stays owned by the established Facebook package. */
final class ApiTransport
{
    public function __construct(private readonly MetaAccountRepository $accounts,private readonly MetaInstagramPublisher $publisher,private readonly InstagramConfigRepository $profiles) {}
    public function run(InstagramAction $action,ActionRequest $r): ActionResult {
        $account=(string)($r->options['account'] ?? '');$key=trim((string)($r->options['keys'][$r->targets[0]] ?? $r->options['key'] ?? ''));
        $binding=$this->profiles->findAccount($r->session);
        if($account==='' || strtolower(ltrim($account,'@'))!==strtolower(ltrim((string)($binding['instagram_username'] ?? ''),'@')))throw new \InvalidArgumentException('The API account must match the exact session account binding.');
        if($key==='')throw new \InvalidArgumentException('A stable publication key is required.');
        $raw=$this->publisher->publish($this->accounts->find($account),$r->targets[0],(string)($r->options['captions'][$r->targets[0]] ?? $r->options['caption'] ?? ''),$action->key()==='publish_story',$key,(bool)($r->options['force'] ?? false));
        $outcome=match($raw['outcome'] ?? ''){'already_live'=>'already','unknown','quota_unknown'=>'inconclusive',default=>!empty($raw['success'])?'done':'failed'};
        $record=new InstagramResult($r->targets[0],$outcome,(array)($raw['data'] ?? [])+['via'=>'official_api','message'=>$raw['message'] ?? ''],now('UTC')->toIso8601String(),'api',$r->session);
        return new ActionResult(!empty($raw['success']),$action->key(),'api',$r->session,[$record],empty($raw['success'])?[(string)($raw['message'] ?? 'Publishing failed.')]:[]);
    }
}
