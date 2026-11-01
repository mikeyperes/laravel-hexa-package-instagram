<?php
namespace hexa_package_instagram\Services\Concerns;
trait ReadsInstagramMessages
{
    public function readInbox(string $profile,int $limit=20): array { return $this->readMessages($profile,null,$limit); }
    public function readThread(string $profile,string $thread,int $limit=20): array {
        if(!preg_match('/^[0-9]{1,100}$/',$thread))return ['success'=>false,'message'=>'An exact numeric thread id is required.'];
        return $this->readMessages($profile,$thread,$limit);
    }
    private function readMessages(string $profile,?string $thread,int $limit): array {
        $path=$thread===null?'/api/v1/direct_v2/inbox/':'/api/v1/direct_v2/threads/'.$thread.'/';
        $js=<<<'JS'
 const response=await getJson(args.path+'?limit='+args.limit);
 const body=response.json||{};const thread=body.thread||null;
 const threads=(body.inbox?.threads||[]).map(t=>({id:String(t.thread_id||''),title:t.thread_title||'',participants:(t.users||[]).map(u=>({username:u.username||'',id:String(u.pk||'')})),last_activity_at:t.last_activity_at||null,unread:t.read_state===1}));
 const messages=(thread?.items||[]).slice(0,args.limit).map(m=>({id:String(m.item_id||''),sender_id:String(m.user_id||''),text:m.text||null,kind:m.item_type||'',at:m.timestamp||null}));
 return {text:JSON.stringify({success:response.status===200,status:response.status,message:body.message||'',threads,messages,participants:(thread?.users||[]).map(u=>({username:u.username||'',id:String(u.pk||'')}))})};
JS;
        $run=$this->browser->runAutomation($profile,[['type'=>'goto','label'=>'open_home','url'=>'https://www.instagram.com/','wait_until'=>'domcontentloaded','timeout_ms'=>30000,'wait_ms'=>2500],
            ['type'=>'evaluate','label'=>'read_messages','code'=>self::restJs($js),'args'=>['path'=>$path,'limit'=>max(1,min(50,$limit))]+$this->accountReadGaps()]],['transport_timeout_ms'=>60000]);
        $row=json_decode((string)($this->resultByLabel($run,'read_messages')['text'] ?? ''),true);
        if(!is_array($row))return $this->feedFailure($run,$profile,'','Instagram messages could not be read.');
        return $row;
    }
}
