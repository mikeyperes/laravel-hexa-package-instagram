<?php
namespace hexa_package_instagram\Transport;
use hexa_package_social_automation\Contracts\NetworkActions\NetworkActionBridge;
use hexa_package_social_automation\Automation\Campaigns\Models\{Campaign,Task};
use hexa_package_instagram\Actions\{ActionRegistry,InstagramResult,Risk};
use hexa_package_instagram\Actions\Models\StoredResult;
use hexa_package_social_automation\Automation\Processes\{Handles,ReadPostsProcess};
final class InstagramActionBridge implements NetworkActionBridge
{
    public function __construct(private readonly ActionRegistry $registry) {}
    public function network(): string { return 'instagram'; }
    public function definitions(): array {
        $rows=[];
        foreach($this->registry->list() as $row) {
            if (!in_array('human',$row['modes'],true)) continue;
            $rows[$row['key']]=['process_key'=>'instagram-action-'.str_replace('_','-',$row['key']),'label'=>'Instagram '.$row['key'],
                'approvals'=>$row['risk']==='read' ? [] : [$row['limit']]];
        }
        return $rows;
    }
    public function normalize(string $action,array $params): array {
        $targets=(array)($params['targets'] ?? $params['accounts'] ?? (isset($params['account']) ? [$params['account']] : []));
        if (!$targets || count($targets)>500) throw new \InvalidArgumentException('Give 1 to 500 targets.');
        $post=in_array($action,['read_post','read_tagged_accounts','like_post'],true);
        $special=str_starts_with($action,'highlight_') || $action==='read_highlights';
        $params['targets']=array_values(array_unique(array_map(fn($v)=>$special ? trim((string)$v) : ($post ? ReadPostsProcess::postCode((string)$v) : Handles::normalize((string)$v)),$targets)));
        return $params;
    }
    public function steps(string $action,array $params): array {
        $adapter=match($action){'read_post'=>'retrieve_post','read_account_posts'=>'read_posts','read_profile'=>'open_profile','highlight_add','highlight_remove','highlight_create'=>'highlight_edit',default=>$action};
        return array_map(function($target)use($action,$adapter,$params){
            $post=in_array($action,['read_post','read_tagged_accounts','like_post'],true);
            $input=[$post ? 'code':'handle'=>$target,'engine_action'=>$action,'target'=>$target,'comments'=>(int)($params['options']['comments'] ?? 20),'limit'=>(int)($params['options']['limit'] ?? 5),'only'=>$params['only'] ?? [],'include_pinned'=>true,'direction'=>$action==='read_followers'?'followers':'following'];
            if(str_starts_with($action,'highlight_') || $action==='read_highlights') {
                $binding=app(\hexa_package_instagram\Domains\Config\InstagramConfigRepository::class)->findAccount((string)($params['session'] ?? ''));
                $input['handle']=Handles::normalize((string)($binding['instagram_username'] ?? ''));
                $input['highlight_id']=$target==='self'?'':$target;$input['operation']=$action;
                $input['title']=(string)($params['options']['title'] ?? '');$input['story_ids']=(array)($params['options']['story_ids'] ?? []);
                if($action!=='read_highlights' && (!$input['story_ids'] || array_filter($input['story_ids'],fn($id)=>!ctype_digit((string)$id)))) throw new \InvalidArgumentException('Exact numeric story ids are required.');
            }
            if($post) {
                $owner=(string)($params['options']['owner'] ?? '');
                if($owner==='') $owner=(string)(StoredResult::query()->where('action','read_post')->where('target',$target)->where('outcome','done')->latest('id')->first()?->record['evidence']['owner'] ?? '');
                if($owner!=='') $input['handle']=Handles::normalize($owner);
                $input['address_fallback_reason']=$owner!=='' ? 'post_link_not_visible_in_owner_grid' : 'owner_unknown_no_visible_link';
            }
            if($action==='like_post')$input['url']='https://www.instagram.com/p/'.$target.'/';
            return ['action'=>$adapter,'input'=>$input,'key'=>$action.':'.hash('sha256',$target)];
        },$params['targets']);
    }
    public function result(string $action,Campaign $campaign,Task $task,array $result): array {
        $target=(string)($task->input['target'] ?? '');
        if ($action==='read_tagged_accounts' && !empty($task->input['classification'])) {
            $result['classification']=app(\hexa_package_instagram\Actions\Support\AccountTypeClassifier::class)->classify($result + ['biography'=>$result['bio'] ?? '']);
            $target=(string)$task->input['handle'];
        } elseif ($action==='read_tagged_accounts' && ($result['status'] ?? 'ok')==='ok' && ($campaign->param('options.classify',true))) {
            $names=array_unique(array_merge((array)($result['tagged_accounts'] ?? []),(array)($result['mentions'] ?? []),(array)($result['coauthors'] ?? [])));
            $planner=app(\hexa_package_social_automation\Automation\Processes\TaskPlanner::class);
            $queued=0;
            foreach($names as $name) $queued+=$planner->enqueue($campaign,'open_profile',['handle'=>$name,'engine_action'=>'read_profile','target'=>$target,'classification'=>true], 'classify:'.hash('sha256',$target.':'.$name))?1:0;
            $campaign->putState(['total'=>(int)$campaign->stateValue('total',0)+$queued]);
        }
        $status=(string)($result['status'] ?? 'ok');$state=(string)($result['outcome'] ?? '');
        $outcome=match(true){str_starts_with($state,'already_'),$state==='not_following'=>'already',in_array($status,['private','missing','unavailable','inconclusive'],true)=>$status,$status==='ok'=>'done',default=>'failed'};
        $data=$result;unset($data['routes']);
        if(isset($data['author'])){$data['owner']=$data['author'];unset($data['author']);}
        if(isset($data['tagged'])){$data['tagged_accounts']=$data['tagged'];unset($data['tagged']);}
        if($action==='read_profile')$data=['profile'=>$data];
        if($action==='read_account_posts')$data=['posts'=>array_map(fn($p)=>array_intersect_key($p,array_flip(['url','code','pinned'])),(array)($data['posts'] ?? []))];
        if($only=$campaign->param('only',[]))$data=array_intersect_key($data,array_flip($only));
        $record=new InstagramResult($target,$outcome,$data,now('UTC')->toIso8601String(),'human',$campaign->profile,(array)($result['routes'] ?? []));
        StoredResult::query()->updateOrCreate(['repeat_key'=>hash('sha256','human:'.$campaign->id.':'.$task->id)],['action'=>$action,'session'=>$campaign->profile,'target'=>$target,'mode'=>'human','outcome'=>$outcome,'record'=>$record->jsonSerialize(),'performed_at'=>$record->at]);
        return $record->jsonSerialize();
    }
}
