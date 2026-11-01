<?php
namespace hexa_package_instagram\Console;
use Illuminate\Console\Command;
use hexa_package_instagram\Actions\{ActionRequest,Mode};
use hexa_package_instagram\Actions\Support\ApprovalVerifier;
final class InstagramApprovalCommand extends Command
{
    protected $signature='instagram:approval {--file= : Verified toolbox envelope JSON} {--json}';
    protected $description='Import a toolbox-approved exact action envelope, returning its private receipt id.';
    public function handle(ApprovalVerifier $verifier): int {
        try {
            $payload=json_decode(file_get_contents((string)$this->option('file')),true,512,JSON_THROW_ON_ERROR);$c=$payload['context'] ?? [];$key=(string)($c['action'] ?? '');
            $r=new ActionRequest((string)($c['session'] ?? ''),(array)($c['targets'] ?? []),str_starts_with($key,'publish_')?Mode::Api:Mode::Human,options:(array)($c['options'] ?? []));
            $verifier->verifyPayload($key,$r,$payload);
            $id=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));$directory=storage_path('app/private/instagram/approvals');if(!is_dir($directory))mkdir($directory,0700,true);
            $path=$directory.'/'.$id.'.json';file_put_contents($path,json_encode($payload,JSON_THROW_ON_ERROR),LOCK_EX);chmod($path,0600);
            $this->line(json_encode(['success'=>true,'receipt_id'=>$id]));return self::SUCCESS;
        }catch(\Throwable $e){$this->line(json_encode(['success'=>false,'errors'=>[$e->getMessage()]]));return self::FAILURE;}
    }
}
