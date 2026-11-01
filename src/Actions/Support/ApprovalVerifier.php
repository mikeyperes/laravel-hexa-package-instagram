<?php
namespace hexa_package_instagram\Actions\Support;
use hexa_package_instagram\Actions\ActionRequest;
use Symfony\Component\Process\Process;
final class ApprovalVerifier
{
    public function verify(string $action,ActionRequest $r): void {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/',(string)$r->approval)) throw new \InvalidArgumentException('A stored approval receipt id is required.');
        $path=storage_path('app/private/instagram/approvals/'.$r->approval.'.json');
        if (!is_file($path)) throw new \InvalidArgumentException('The approval receipt was not found.');
        $payload=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        $this->verifyPayload($action,$r,$payload);
    }
    public function verifyPayload(string $action,ActionRequest $r,array $payload): void {
        $context=$payload['context'] ?? [];
        if (($context['action'] ?? null)!==$action || ($context['session'] ?? null)!==$r->session || ($context['targets'] ?? null)!==$r->targets || ($context['options'] ?? [])!==$r->options) throw new \InvalidArgumentException('Approval is not bound to this action, session, targets and options.');
        $skill=match($action){'follow'=>'instagram-follow','unfollow'=>'instagram-unfollow','like_post'=>'instagram-like','send_dm'=>'instagram-dm-sender','publish_story'=>'instagram-story-publisher','publish_post'=>'instagram-post-publisher',default=>'instagram-highlight-publisher'};
        $process=new Process(['/usr/local/bin/hexa-toolbox','approval','verify','--skill',$skill,'--gate','action','--request','-']);
        $process->setInput(json_encode($payload,JSON_THROW_ON_ERROR));$process->run();
        if (!$process->isSuccessful() || !(json_decode($process->getOutput(),true)['valid'] ?? false)) throw new \InvalidArgumentException('Approval receipt verification failed.');
    }
}
