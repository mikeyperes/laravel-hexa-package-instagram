<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\{Schema,DB};
use Illuminate\Database\Schema\Blueprint;
use hexa_package_instagram\Actions\{ActionRunner,ActionRequest,Mode,ActionRegistry};
use hexa_package_instagram\Services\InstagramScraperService;
use hexa_package_browser_worker\Contracts\BrowserSessionRegistryContract;
use hexa_package_instagram\Actions\Support\SessionActor;
use hexa_package_human_behavior\Limits\Limiter;
final class InstagramActionRunnerTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        // These fixtures use instagram-public as an ordinary limited session; the logged-out reader is tested separately.
        config(['instagram.unlimited_sessions' => []]);
        Schema::create('settings',function(Blueprint $t){$t->id();$t->string('key')->unique();$t->text('value')->nullable();$t->string('type')->default('text');$t->string('group')->nullable();$t->timestamps();});
        (require base_path('packages/hexawebsystems/laravel-hexa-package-instagram/database/migrations/2026_11_01_172000_create_instagram_action_results.php'))->up();
        (require base_path('packages/hexawebsystems/laravel-hexa-package-human-behavior/database/migrations/2026_10_30_000001_create_human_behavior_actions_table.php'))->up();
        (require base_path('packages/hexawebsystems/laravel-hexa-package-social-automation/database/migrations/2026_10_28_000001_create_social_automation_tables.php'))->up();
        Schema::create('activity_logs',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->string('action')->nullable();$t->string('category')->nullable();$t->text('description')->nullable();$t->text('context')->nullable();$t->string('related_type')->nullable();$t->unsignedBigInteger('related_id')->nullable();$t->string('ip_address')->nullable();$t->string('user_timezone')->nullable();$t->timestamps();});
        $sessions=$this->createMock(BrowserSessionRegistryContract::class);$sessions->method('all')->willReturn([['profile_id'=>'instagram-public']]);$this->app->instance(BrowserSessionRegistryContract::class,$sessions);
    }
    public function test_logged_out_reader_has_no_read_limit(): void {
        config(['instagram.unlimited_sessions' => ['instagram-public']]);
        $reader=$this->createMock(InstagramScraperService::class);$reader->method('readPost')->willReturn(['success'=>true,'post'=>['caption'=>'Hello','owner'=>'test','slides'=>[]]]);$this->app->instance(InstagramScraperService::class,$reader);
        $r=app(ActionRunner::class)->run('read_post',new ActionRequest('instagram-public',['ABCDEF'],only:['caption']));
        $this->assertTrue($r->success);$this->assertSame(0,DB::table('human_behavior_actions')->count());
    }
    public function test_read_filters_parts_persists_and_counts_shared_session(): void {
        $reader=$this->createMock(InstagramScraperService::class);$reader->expects($this->once())->method('readPost')->willReturn(['success'=>true,'post'=>['caption'=>'Hello','owner'=>'test','slides'=>[]]]);$this->app->instance(InstagramScraperService::class,$reader);
        $r=app(ActionRunner::class)->run('read_post',new ActionRequest('instagram-public',['ABCDEF'],only:['caption'],campaignId:10));
        $this->assertTrue($r->success);$this->assertSame(['caption'=>'Hello'],$r->records[0]->evidence);
        $this->assertSame(1,DB::table('instagram_action_results')->count());
        $this->assertSame(1,app(Limiter::class)->status(new SessionActor('instagram-public'),'retrieve_post')['hour_used']);
        $again=app(ActionRunner::class)->run('read_post',new ActionRequest('instagram-public',['ABCDEF'],only:['caption'],campaignId:10));
        $this->assertSame('skipped',$again->records[0]->outcome);$this->assertSame(1,DB::table('human_behavior_actions')->count());
    }
    public function test_unknown_session_and_part_and_unavailable_mode_refuse_before_transport(): void {
        foreach ([new ActionRequest('missing',['ABCDEF']),new ActionRequest('instagram-public',['ABCDEF'],only:['secret']),new ActionRequest('instagram-public',['ABCDEF'],Mode::Human)] as $request) {
            $r=app(ActionRunner::class)->run('read_post',$request);$this->assertFalse($r->success);$this->assertNotEmpty($r->errors);
        }
        $this->assertSame(0,DB::table('human_behavior_actions')->count());
    }
    public function test_exhausted_limit_refuses_with_wait_and_no_transport(): void {
        $reader=$this->createMock(InstagramScraperService::class);$reader->expects($this->never())->method('readPost');$this->app->instance(InstagramScraperService::class,$reader);
        app(\hexa_package_human_behavior\Limits\LimitRegistry::class)->register('retrieve_post','Post',0,0);
        $r=app(ActionRunner::class)->run('read_post',new ActionRequest('instagram-public',['ABCDEF']));
        $this->assertSame('limit_exhausted',$r->stop);$this->assertGreaterThan(0,$r->errors[0]['wait_seconds']);
    }

    public function test_all_foundation_actions_declare_both_modes_and_routes_from_config(): void {
        $rows=array_values(array_filter(app(ActionRegistry::class)->list(),fn($r)=>in_array($r['key'],['read_post','read_account_posts','read_profile','read_tagged_accounts','follow','unfollow','like_post','add_to_list'],true)));$this->assertCount(8,$rows);
        foreach($rows as $row){if($row['key']==='add_to_list'){$this->assertSame(['local'],$row['modes']);continue;}$this->assertSame($row['risk']==='read'?['efficient','human']:['human'],$row['modes']);$this->assertNotEmpty($row['process_key']);$this->assertNotEmpty($row['routes']);$this->assertSame(config('social-automation.action_routes.'.$row['key']),$row['routes']);}
        $this->assertContains('tagged_accounts',app(ActionRegistry::class)->get('read_post')->parts());
    }
    public function test_profile_posts_and_tagged_accounts_use_same_limits_and_result_contract(): void {
        $reader=$this->createMock(InstagramScraperService::class);
        $reader->method('publicProfileFeeds')->willReturn(['success'=>true,'data'=>['accounts'=>['test'=>['success'=>true,'posts'=>[['url'=>'https://www.instagram.com/p/ABCDEF/','shortcode'=>'ABCDEF','caption'=>'must not leak','pinned'=>true]]]]]]);
        $reader->method('readPost')->willReturn(['success'=>true,'post'=>['tagged'=>['test'],'mentions'=>[],'coauthors'=>[]]]);
        $this->app->instance(InstagramScraperService::class,$reader);
        foreach(['read_profile','read_account_posts','read_tagged_accounts'] as $key){
            DB::table('instagram_action_results')->delete();
            $r=app(ActionRunner::class)->run($key,new ActionRequest('instagram-public',['test'],options:['classify'=>false]));
            $this->assertTrue($r->success,json_encode($r));$this->assertSame('done',$r->records[0]->outcome);
            $this->assertSame(1,DB::table('instagram_action_results')->count());
            if($key==='read_account_posts')$this->assertArrayNotHasKey('caption',$r->records[0]->evidence['posts'][0]);
            if($key==='read_tagged_accounts')$this->assertSame(['test'],$r->records[0]->evidence['tagged_accounts']);
        }
    }
    public function test_stop_signal_is_counted_and_persisted_without_inventing_routes(): void {
        $reader=$this->createMock(InstagramScraperService::class);$reader->method('readPost')->willReturn(['success'=>false,'message'=>'Rate limited by Instagram.']);$this->app->instance(InstagramScraperService::class,$reader);
        $r=app(ActionRunner::class)->run('read_post',new ActionRequest('instagram-public',['ABCDEF','GHIJKL']));
        $this->assertSame('rate_limited',$r->stop);$this->assertCount(1,$r->records);$this->assertSame([],$r->routes);
        $this->assertSame(1,DB::table('human_behavior_actions')->count());$this->assertNotNull(DB::table('social_automation_devices')->value('cooldown_until'));
    }

    public function test_human_reads_accept_single_and_multiple_targets_and_count_once_on_claim(): void {
        $device=\hexa_package_social_automation\Automation\Devices\Models\Device::create(['profile'=>'instagram-public','status'=>'active','paired_at'=>now()->subMonth()]);
        foreach(['read_post'=>['ABCDEF'],'read_account_posts'=>['test','other'],'read_profile'=>['test'],'read_tagged_accounts'=>['ABCDEF']] as $key=>$targets){
            $r=app(ActionRunner::class)->run($key,new ActionRequest('instagram-public',$targets,Mode::Human,options:['classify'=>false]));
            $this->assertTrue($r->success,json_encode($r));$this->assertNotNull($r->campaignId);
            $campaign=\hexa_package_social_automation\Automation\Campaigns\Models\Campaign::findOrFail($r->campaignId);
            $this->assertSame(count($targets),$campaign->tasks()->count());
            $queue=app(\hexa_package_social_automation\Automation\Campaigns\TaskQueue::class);
            $claimed=$queue->claim($device);$this->assertNotNull($claimed['task']);
            $task=$claimed['task'];$this->assertNotEmpty($task['routes']);
            $payload=['status'=>'done','result'=>['status'=>'ok','caption'=>'Proof','tagged_accounts'=>[],'routes'=>[['route'=>'post','how'=>'typed'],['route'=>'readback','how'=>'observed_result']]]];
            $before=DB::table('human_behavior_actions')->count();
            $this->assertTrue($queue->report($device,$task['id'],$task['lease_token'],$payload)['ack']);
            $this->assertTrue($queue->report($device,$task['id'],$task['lease_token'],$payload)['duplicate']);
            $this->assertSame($before,DB::table('human_behavior_actions')->count());
            $this->assertSame(1,DB::table('instagram_action_results')->where('action',$key)->count());
            app(\hexa_package_social_automation\Automation\Campaigns\CampaignService::class)->cancel($campaign);
        }
    }
    public function test_all_account_writes_refuse_without_receipt_and_have_dry_route_plans(): void {
        foreach(['follow','unfollow','like_post'] as $key){
            $r=app(ActionRunner::class)->run($key,new ActionRequest('instagram-public',['test'],Mode::Human));
            $this->assertFalse($r->success);$this->assertStringContainsString('approval receipt',implode(' ',$r->errors));
            $this->assertSame(['human'],array_map(fn($m)=>$m->value,app(ActionRegistry::class)->get($key)->modes()));
            $this->assertNotEmpty(app(ActionRegistry::class)->get($key)->routes());
        }
        $this->assertSame(0,DB::table('social_automation_campaigns')->count());$this->assertSame(0,DB::table('human_behavior_actions')->count());
    }
    public function test_media_less_embed_is_inconclusive_and_ownership_conflict_is_session_busy(): void {
        $reader=$this->createMock(InstagramScraperService::class);$reader->method('readPost')->willReturnOnConsecutiveCalls(
            ['success'=>false,'message'=>'Instagram returned the post embed without its media.'],['success'=>false,'message'=>'Browser profile ownership conflict']);
        $this->app->instance(InstagramScraperService::class,$reader);
        $r=app(ActionRunner::class)->run('read_post',new ActionRequest('instagram-public',['ABCDEF']));$this->assertSame('inconclusive',$r->records[0]->outcome);
        DB::table('instagram_action_results')->delete();
        $r=app(ActionRunner::class)->run('read_post',new ActionRequest('instagram-public',['ABCDEF']));$this->assertSame('session_busy',$r->stop);$this->assertStringNotContainsString('ownership conflict',implode(' ',$r->errors));
    }

    public function test_local_list_add_is_repeat_safe_and_never_counts_an_instagram_action(): void {
        (require base_path('packages/hexawebsystems/laravel-hexa-package-social-automation/database/migrations/2026_10_31_000003_create_social_automation_lists.php'))->up();
        $lists=app(\hexa_package_social_automation\Automation\Lists\AccountLists::class);$list=$lists->open('instagram','source','following','Test list');
        foreach(['done','already'] as $outcome){
            DB::table('instagram_action_results')->delete();
            $r=app(ActionRunner::class)->run('add_to_list',new ActionRequest('instagram-public',['test'],Mode::Local,options:['list'=>'Test list']));
            $this->assertTrue($r->success,json_encode($r));$this->assertSame($outcome,$r->records[0]->outcome);$this->assertSame(1,$r->records[0]->evidence['membership']['total']);
        }
        $this->assertSame(0,DB::table('human_behavior_actions')->count());
    }
    public function test_human_stop_pauses_the_session_and_blocks_efficient_reads(): void {
        $device=\hexa_package_social_automation\Automation\Devices\Models\Device::create(['profile'=>'instagram-public','status'=>'active','paired_at'=>now()->subMonth()]);
        $r=app(ActionRunner::class)->run('read_profile',new ActionRequest('instagram-public',['test'],Mode::Human));
        $queue=app(\hexa_package_social_automation\Automation\Campaigns\TaskQueue::class);$task=$queue->claim($device)['task'];
        $answer=$queue->report($device,$task['id'],$task['lease_token'],['status'=>'failed','error_code'=>'challenge','error_message'=>'Instagram security check']);
        $this->assertTrue($answer['stopped']);$this->assertSame(1,DB::table('instagram_action_results')->where('outcome','failed')->count());
        $read=app(ActionRunner::class)->run('read_post',new ActionRequest('instagram-public',['ABCDEF']));$this->assertSame('cooldown',$read->stop);
    }

    public function test_story_reads_share_results_counts_and_support_human_queue(): void {
        $reader=$this->createMock(InstagramScraperService::class);$reader->method('accountStories')->willReturn(['success'=>true,'stories'=>[['id'=>'123','url'=>'https://www.instagram.com/stories/test/123/','image_url'=>'https://cdninstagram.com/story.jpg']]]);$this->app->instance(InstagramScraperService::class,$reader);
        $r=app(ActionRunner::class)->run('read_stories',new ActionRequest('instagram-public',['test']));$this->assertTrue($r->success);$this->assertCount(1,$r->records[0]->evidence['stories']);
        $this->assertSame(1,app(Limiter::class)->status(new SessionActor('instagram-public'),'read_stories')['hour_used']);
        \hexa_package_social_automation\Automation\Devices\Models\Device::create(['profile'=>'instagram-public','status'=>'active','paired_at'=>now()->subMonth()]);
        $r=app(ActionRunner::class)->run('read_stories',new ActionRequest('instagram-public',['test'],Mode::Human));$this->assertTrue($r->success);$this->assertNotNull($r->campaignId);
        $task=app(\hexa_package_social_automation\Automation\Campaigns\TaskQueue::class)->claim(\hexa_package_social_automation\Automation\Devices\Models\Device::first())['task'];
        $this->assertSame('read_stories',$task['action']);$this->assertContains('stories',$task['routes']);
    }
    public function test_download_story_saves_source_metadata_and_rejects_human_mode(): void {
        $reader=$this->createMock(InstagramScraperService::class);$reader->method('mediaByUrl')->willReturn(['success'=>true,'data'=>['item'=>['owner'=>'test','image_url'=>'https://cdninstagram.com/a.png','taken_at'=>123]]]);$this->app->instance(InstagramScraperService::class,$reader);
        $http=$this->createMock(\hexa_package_browser_worker\Services\BrowserHttpService::class);$http->method('getBinary')->willReturn(['success'=>true,'body'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=')]);$this->app->instance(\hexa_package_browser_worker\Services\BrowserHttpService::class,$http);
        $r=app(ActionRunner::class)->run('download_story',new ActionRequest('instagram-public',['https://www.instagram.com/stories/test/123/']));$this->assertTrue($r->success,json_encode($r));$this->assertFileExists($r->records[0]->evidence['download']['path']);$this->assertSame('story',$r->records[0]->evidence['download']['source']['kind']);
        $r=app(ActionRunner::class)->run('download_story',new ActionRequest('instagram-public',['test'],Mode::Human));$this->assertFalse($r->success);
    }
    public function test_highlight_writes_require_receipts_and_shared_minimum_gap(): void {
        foreach(['highlight_add','highlight_remove','highlight_create'] as $key){$r=app(ActionRunner::class)->run($key,new ActionRequest('instagram-public',['123'],Mode::Human));$this->assertFalse($r->success);$this->assertStringContainsString('approval receipt',implode(' ',$r->errors));$this->assertContains('highlight',app(ActionRegistry::class)->get($key)->routes());}
        $actor=new SessionActor('instagram-public');app(Limiter::class)->record($actor,'highlight_edit');$this->assertGreaterThanOrEqual(2690,app(Limiter::class)->waitFor($actor,'highlight_edit'));
    }

    public function test_network_directions_use_the_same_runner_in_both_modes(): void {
        $reader=$this->createMock(InstagramScraperService::class);$reader->method('networkFeed')->willReturn(['success'=>true,'data'=>['users'=>[['username'=>'other']],'complete'=>true]]);$this->app->instance(InstagramScraperService::class,$reader);
        \hexa_package_social_automation\Automation\Devices\Models\Device::create(['profile'=>'instagram-public','status'=>'active','paired_at'=>now()->subMonth()]);
        foreach(['read_following','read_followers'] as $key){
            DB::table('instagram_action_results')->delete();$r=app(ActionRunner::class)->run($key,new ActionRequest('instagram-public',['test']));$this->assertTrue($r->success);$this->assertCount(1,$r->records[0]->evidence['users']);
            $r=app(ActionRunner::class)->run($key,new ActionRequest('instagram-public',['test','other'],Mode::Human));$this->assertTrue($r->success,json_encode($r));$this->assertSame(2,\hexa_package_social_automation\Automation\Campaigns\Models\Campaign::find($r->campaignId)->tasks()->count());
        }
    }

    public function test_inbox_and_thread_reads_work_in_both_modes_with_shared_counting(): void {
        $reader=$this->createMock(InstagramScraperService::class);$reader->method('readInbox')->willReturn(['success'=>true,'threads'=>[['id'=>'123']]]);$reader->method('readThread')->willReturn(['success'=>true,'messages'=>[['id'=>'1','text'=>'Fixture']],'participants'=>[]]);$this->app->instance(InstagramScraperService::class,$reader);
        $device=\hexa_package_social_automation\Automation\Devices\Models\Device::create(['profile'=>'instagram-public','status'=>'active','paired_at'=>now()->subMonth()]);
        foreach(['read_inbox'=>'inbox','read_thread'=>'123'] as $key=>$target){
            DB::table('instagram_action_results')->delete();$r=app(ActionRunner::class)->run($key,new ActionRequest('instagram-public',[$target]));$this->assertTrue($r->success,json_encode($r));
            $r=app(ActionRunner::class)->run($key,new ActionRequest('instagram-public',[$target],Mode::Human));$this->assertTrue($r->success,json_encode($r));
            $queue=app(\hexa_package_social_automation\Automation\Campaigns\TaskQueue::class);$task=$queue->claim($device)['task'];$this->assertSame($key,$task['action']);$this->assertContains('inbox',$task['routes']);
            $queue->report($device,$task['id'],$task['lease_token'],['status'=>'done','result'=>['status'=>'ok','threads'=>[],'messages'=>[],'routes'=>[]]]);
        }
        $this->assertSame(4,app(Limiter::class)->status(new SessionActor('instagram-public'),'open_inbox')['hour_used']);
    }
    public function test_message_send_refuses_missing_receipt_and_uncertain_write_is_never_retried(): void {
        $r=app(ActionRunner::class)->run('send_dm',new ActionRequest('instagram-public',['123'],Mode::Human,options:['message'=>'Fixture only']));$this->assertFalse($r->success);$this->assertSame(0,DB::table('social_automation_campaigns')->count());
        $device=\hexa_package_social_automation\Automation\Devices\Models\Device::create(['profile'=>'instagram-public','status'=>'active','paired_at'=>now()->subMonth()]);
        $campaign=app(\hexa_package_social_automation\Automation\Campaigns\CampaignService::class)->create('instagram','instagram-action-send-dm','instagram-public',['targets'=>['123'],'options'=>['message'=>'Fixture only']],approvedActions:['send_dm']);
        app(\hexa_package_social_automation\Automation\Campaigns\CampaignService::class)->start($campaign);
        $queue=app(\hexa_package_social_automation\Automation\Campaigns\TaskQueue::class);$task=$queue->claim($device)['task'];
        $this->assertSame('send_dm',$task['action']);$out=$queue->report($device,$task['id'],$task['lease_token'],['status'=>'failed','error_code'=>'timeout','error_message'=>'Uncertain send']);$this->assertFalse($out['retry']);
        $this->assertSame('failed',\hexa_package_social_automation\Automation\Campaigns\Models\Task::find($task['id'])->status);
    }

    public function test_official_publish_actions_refuse_without_receipt_and_never_use_background_mode(): void {
        foreach(['publish_story','publish_post'] as $key){
            $r=app(ActionRunner::class)->run($key,new ActionRequest('instagram-public',['https://example.com/image.jpg'],Mode::Api));$this->assertFalse($r->success);$this->assertStringContainsString('approval receipt',implode(' ',$r->errors));
            $r=app(ActionRunner::class)->run($key,new ActionRequest('instagram-public',['https://example.com/image.jpg'],Mode::Efficient));$this->assertFalse($r->success);
        }
        $this->assertSame(0,DB::table('human_behavior_actions')->count());
    }
    public function test_generic_command_optional_json_defaults_and_single_or_list_input(): void {
        $reader=$this->createMock(InstagramScraperService::class);$reader->method('readPost')->willReturn(['success'=>true,'post'=>['caption'=>'Fixture']]);$this->app->instance(InstagramScraperService::class,$reader);
        $this->artisan('instagram:run',['action'=>'read_post','--profile'=>'instagram-public','--targets'=>'ABCDEF','--only'=>'caption','--json'=>true])->assertExitCode(0);
        $this->artisan('instagram:run',['action'=>'read_post','--profile'=>'instagram-public','--targets'=>'["ABCDEF","GHIJKL"]','--skip'=>'["ABCDEF"]','--only'=>'caption','--json'=>true])->assertExitCode(0);
        $this->assertSame(2,DB::table('instagram_action_results')->count());
    }

    public function test_publication_lists_require_distinct_per_target_keys_before_any_dispatch(): void {
        $targets=['https://example.com/one.jpg','https://example.com/two.jpg'];
        foreach([[],['keys'=>array_fill_keys($targets,'same')]] as $options){
            $r=app(ActionRunner::class)->run('publish_post',new ActionRequest('instagram-public',$targets,Mode::Api,options:$options));
            $this->assertFalse($r->success);$this->assertStringContainsString('distinct stable key',implode(' ',$r->errors));
        }
        $r=app(ActionRunner::class)->run('publish_post',new ActionRequest('instagram-public',$targets,Mode::Api,options:['keys'=>array_combine($targets,['one','two'])]));
        $this->assertStringContainsString('approval receipt',implode(' ',$r->errors));$this->assertSame(0,DB::table('human_behavior_actions')->count());
    }

    public function test_highlight_source_account_is_explicit_in_efficient_and_human_reads(): void {
        $reader=$this->createMock(InstagramScraperService::class);$reader->method('accountProfiles')->willReturn(['success'=>true,'data'=>['accounts'=>['fixture'=>['user_id'=>'123']]]]);$reader->method('highlightFeeds')->willReturn(['success'=>true,'data'=>['highlights'=>[['id'=>'456','items'=>[]]]]]);$this->app->instance(InstagramScraperService::class,$reader);
        $r=app(ActionRunner::class)->run('read_highlights',new ActionRequest('instagram-public',['fixture'],options:['account'=>'fixture','limit'=>1]));$this->assertTrue($r->success);$this->assertSame('456',$r->records[0]->evidence['highlights'][0]['id']);
        \hexa_package_social_automation\Automation\Devices\Models\Device::create(['profile'=>'instagram-public','status'=>'active','paired_at'=>now()->subMonth()]);
        $r=app(ActionRunner::class)->run('read_highlights',new ActionRequest('instagram-public',['fixture'],Mode::Human,options:['account'=>'fixture']));$this->assertTrue($r->success);
        $this->assertSame('fixture',\hexa_package_social_automation\Automation\Campaigns\Models\Campaign::find($r->campaignId)->tasks()->first()->input['handle']);
    }

    public static function humanLifecycleActions(): array {
        return array_map(fn($key)=>[$key],['read_post','read_account_posts','read_profile','read_tagged_accounts','follow','unfollow','like_post','read_following','read_followers','read_inbox','read_thread','send_dm','read_stories','read_highlights','highlight_add','highlight_remove','highlight_create']);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('humanLifecycleActions')]
    public function test_every_human_recipe_counts_attempts_preserves_repeats_and_respects_stops_or_minimum_gap(string $key): void {
        $registry=app(ActionRegistry::class);$action=$registry->get($key);
        $targets=match(true){in_array($key,['read_post','read_tagged_accounts','like_post'],true)=>['ABCDEF','GHIJKL'],in_array($key,['read_thread','send_dm'],true),str_starts_with($key,'highlight_')=>['123','456'],default=>['fixture','other']};
        $options=['owner'=>'fixture','account'=>'fixture','message'=>'Fixture only','story_ids'=>['111'],'classify'=>false,'title'=>'Fixture'];
        if($action->risk()!==\hexa_package_instagram\Actions\Risk::Read){$r=app(ActionRunner::class)->run($key,new ActionRequest('instagram-public',$targets,Mode::Human,options:$options));$this->assertFalse($r->success);$this->assertSame(0,DB::table('social_automation_campaigns')->count());}
        app(\hexa_package_instagram\Domains\Config\InstagramConfigRepository::class)->saveAccount('Fixture','instagram-public','fixture',true);
        $device=\hexa_package_social_automation\Automation\Devices\Models\Device::create(['profile'=>'instagram-public','status'=>'active','paired_at'=>now()->subMonth()]);
        $service=app(\hexa_package_social_automation\Automation\Campaigns\CampaignService::class);
        // Isolated task lifecycle fixture: no browser, real account, or approval receipt is used.
        $campaign=$service->create('instagram','instagram-action-'.str_replace('_','-',$key),'instagram-public',['targets'=>$targets,'session'=>'instagram-public','options'=>$options],approvedActions:app(\hexa_package_instagram\Transport\InstagramActionBridge::class)->definitions()[$key]['approvals']);$service->start($campaign);
        $this->assertSame(2,$campaign->tasks()->count());
        $queue=app(\hexa_package_social_automation\Automation\Campaigns\TaskQueue::class);$one=$queue->claim($device)['task'];
        $this->assertSame(1,app(Limiter::class)->status(new SessionActor('instagram-public'),$action->limitKey())['hour_used']);
        $out=['status'=>'done','result'=>['status'=>'ok','posts'=>[],'tagged_accounts'=>[],'routes'=>[['route'=>'readback','how'=>'observed_result']]]];
        $queue->report($device,$one['id'],$one['lease_token'],$out);$queue->report($device,$one['id'],$one['lease_token'],$out);
        $this->assertSame(1,DB::table('instagram_action_results')->count());$this->assertSame(1,$campaign->records()->count());
        if(str_starts_with($key,'highlight_')){$next=$queue->claim($device);$this->assertEmpty($next['task'] ?? null);$this->assertGreaterThanOrEqual(2690,app(Limiter::class)->waitFor(new SessionActor('instagram-public'),'highlight_edit'));return;}
        $two=$queue->claim($device)['task'];$this->assertNotNull($two);$queue->report($device,$two['id'],$two['lease_token'],['status'=>'failed','error_code'=>'login_required','error_message'=>'Fixture stop','result'=>['diagnostics'=>['pathname'=>'/accounts/login/','condition_failed'=>'fixture_login']]]);
        $this->assertSame(2,app(Limiter::class)->status(new SessionActor('instagram-public'),$action->limitKey())['hour_used']);
        $this->assertTrue($device->fresh()->inCooldown());$this->assertSame('paused',$campaign->fresh()->status);
        $failed=DB::table('instagram_action_results')->where('outcome','failed')->first();$this->assertNotNull($failed);$record=json_decode($failed->record,true);$this->assertSame('/accounts/login/',$record['evidence']['diagnostics']['pathname']);
    }

    public static function efficientReadActions(): array {
        return array_map(fn($key)=>[$key],['read_post','read_account_posts','read_profile','read_tagged_accounts','read_following','read_followers','read_inbox','read_thread','read_stories','download_story','read_highlights']);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('efficientReadActions')]
    public function test_every_efficient_read_counts_repeats_skip_lists_and_shared_stops(string $key): void {
        $url='https://www.instagram.com/stories/fixture/123456/';$target=$key==='download_story'?$url:($key==='read_thread'?'123':'fixture');
        $ok=['success'=>true,'post'=>['code'=>'ABCDEF','caption'=>'Fixture','slides'=>[['image_url'=>'https://cdninstagram.com/a.png','is_video'=>false]]],'threads'=>[],'messages'=>[],'participants'=>[],
            'data'=>['users'=>[['username'=>'other']],'complete'=>true,'stories'=>[],'highlights'=>[],'item'=>['owner'=>'fixture','image_url'=>'https://cdninstagram.com/a.png'],
                'accounts'=>['fixture'=>['success'=>true,'username'=>'fixture','posts'=>[['url'=>'https://www.instagram.com/p/ABCDEF/','shortcode'=>'ABCDEF','pinned'=>false]]]]]];
        $blocked=['success'=>false,'message'=>'Rate limited by Instagram.','status_code'=>429,'data'=>['accounts'=>['fixture'=>['success'=>false,'message'=>'Rate limited.']]]];
        $reader=$this->createMock(InstagramScraperService::class);
        foreach(['readPost','publicProfileFeeds','networkFeed','readInbox','readThread','accountStories','mediaByUrl','highlights'] as $method)$reader->method($method)->willReturnOnConsecutiveCalls($ok,$blocked);
        $this->app->instance(InstagramScraperService::class,$reader);
        $http=$this->createMock(\hexa_package_browser_worker\Services\BrowserHttpService::class);$http->method('getBinary')->willReturn(['success'=>true,'body'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=')]);$this->app->instance(\hexa_package_browser_worker\Services\BrowserHttpService::class,$http);
        $device=\hexa_package_social_automation\Automation\Devices\Models\Device::create(['profile'=>'instagram-public','status'=>'active','paired_at'=>now()->subMonth()]);
        $options=['classify'=>false];$runner=app(ActionRunner::class);$action=app(ActionRegistry::class)->get($key);
        $r=$runner->run($key,new ActionRequest('instagram-public',[$target],campaignId:22,options:$options));$this->assertTrue($r->success,json_encode($r));
        $r=$runner->run($key,new ActionRequest('instagram-public',[$target,'skipme'],skip:['skipme'],campaignId:22,options:$options));$this->assertSame(['skipped','skipped'],array_map(fn($record)=>$record->outcome,$r->records));
        $this->assertSame(1,app(Limiter::class)->status(new SessionActor('instagram-public'),$action->limitKey())['hour_used']);
        $this->travel(1)->minutes();
        try{$r=$runner->run($key,new ActionRequest('instagram-public',[$target],options:$options));$this->assertSame('rate_limited',$r->stop,json_encode($r));$this->assertTrue($device->fresh()->inCooldown());$this->assertSame(2,app(Limiter::class)->status(new SessionActor('instagram-public'),$action->limitKey())['hour_used']);}finally{$this->travelBack();}
    }

}
