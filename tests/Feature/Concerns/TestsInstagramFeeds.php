<?php

namespace Tests\Feature\Concerns;

use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Services\InstagramScraperService;
use Mockery;

trait TestsInstagramFeeds
{
    public function test_profile_feeds_read_a_batch_into_post_scan_shape(): void
    {
        $feed = [
            'doc_id' => '29015124851429106',
            'accounts' => [
                ['username' => 'palm.beach.chabad', 'ok' => true, 'status' => 200, 'ms' => 812, 'posts' => [[
                    'code' => 'ABC123', 'pk' => '', 'posted_at' => '2026-09-20T17:21:06.000Z',
                    'taken_at' => 1790011266, 'owner' => 'palm.beach.chabad', 'owner_name' => 'Palm Beach Chabad', 'coauthors' => ['rookerymiami'],
                    'caption' => 'Sukkot dinner, September 25 at 7 PM.', 'accessibility_caption' => 'May be a flyer', 'product_type' => 'carousel_container',
                    'pinned' => true, 'primary_tag' => 'img', 'primary_media_url' => 'https://scontent.cdninstagram.com/v/one.jpg',
                    'image_urls' => ['https://scontent.cdninstagram.com/v/one.jpg', 'https://scontent.cdninstagram.com/v/two.jpg'],
                    'video_urls' => [], 'media_count' => 3, 'location' => 'Palm Beach',
                ], [
                    // A private account's long post id is the short id (the post's numeric id in base 64) plus a suffix.
                    'code' => 'CB1vrXrJdZRBG-E2kXXKigj0kjJPDQl9AyCCPk0', 'pk' => '2338985270032324177', 'product_type' => 'feed', 'caption' => 'Shabbat.',
                    'primary_tag' => 'img', 'primary_media_url' => 'https://scontent.cdninstagram.com/v/long.jpg', 'image_urls' => ['https://scontent.cdninstagram.com/v/long.jpg'], 'video_urls' => [], 'media_count' => 1,
                ]]],
                ['username' => 'private_account', 'ok' => false, 'status' => 429, 'ms' => 90, 'error' => 'Please wait a few minutes before you try again.'],
            ],
        ];
        $bridge = Mockery::mock(BrowserWorkerBridgeContract::class);
        $bridge->shouldReceive('runAutomation')->once()->withArgs(function (?string $profile, array $steps): bool {
            return $profile === 'jpn-miami'
                && $steps[0]['url'] === 'https://www.instagram.com/palm.beach.chabad/'
                && $steps[1]['args']['usernames'] === ['palm.beach.chabad', 'private_account', 'never_reached']
                && $steps[1]['args']['limit'] === 6;
        })->andReturn([
            'success' => true,
            'status_code' => 200,
            'data' => ['results' => [['label' => 'open_profile', 'success' => true], ['label' => 'read_feeds', 'success' => true, 'result' => ['text' => json_encode($feed)]]]],
        ]);
        app()->instance(BrowserWorkerBridgeContract::class, $bridge);

        $result = app(InstagramScraperService::class)->profileFeeds('jpn-miami', ['Palm.Beach.Chabad', 'https://instagram.com/private_account/', 'never_reached'], 6);

        $this->assertTrue($result['success']);
        $this->assertSame('1 of 3 Instagram feeds read.', $result['message']);
        $account = $result['data']['accounts']['palm.beach.chabad'];
        $this->assertSame(['https://www.instagram.com/p/ABC123/', 'https://www.instagram.com/p/CB1vrXrJdZR/'], $account['post_links']);
        $this->assertSame('CB1vrXrJdZRBG-E2kXXKigj0kjJPDQl9AyCCPk0', $account['posts']['CB1vrXrJdZR']['instagram_code']);
        $scan = $account['posts']['ABC123'];
        $this->assertSame(['Sukkot dinner, September 25 at 7 PM.'], $scan['caption_blocks']);
        $this->assertSame('img', $scan['primary_media_box']['tag']);
        $this->assertSame(3, $scan['post_media_count']);
        $this->assertSame('https://scontent.cdninstagram.com/v/one.jpg', $scan['image_urls'][0]);
        $this->assertSame('instagram_feed', $scan['source']);
        $this->assertTrue($scan['pinned']);
        $this->assertFalse($result['data']['accounts']['private_account']['success']);
        $this->assertSame('Please wait a few minutes before you try again.', $result['data']['accounts']['private_account']['message']);
        $this->assertSame('Not read in this batch.', $result['data']['accounts']['never_reached']['message']);
    }

    public function test_story_feeds_read_current_stories_with_links_and_mentions(): void
    {
        $bridge = Mockery::mock(BrowserWorkerBridgeContract::class);
        $bridge->shouldReceive('runAutomation')->once()->withArgs(fn (?string $profile, array $steps): bool => $steps[1]['args']['ids'] === ['111', '222'])->andReturn([
            'success' => true,
            'data' => ['results' => [['label' => 'read_stories', 'result' => ['text' => json_encode(['errors' => [], 'reels' => [
                '111' => ['username' => 'bdhlshul', 'items' => [['pk' => '3992076629349432787', 'taken_at' => 1790040600, 'expiring_at' => 1790127000,
                    'media_type' => 'video', 'image_url' => 'https://scontent.cdninstagram.com/v/cover.jpg', 'video_url' => 'https://scontent.cdninstagram.com/v/story.mp4',
                    'accessibility_caption' => '', 'mentions' => ['chabadgables'], 'links' => ['http://bdhls.org/lulav'], 'hashtags' => []]]],
            ]])]]]],
        ]);
        app()->instance(BrowserWorkerBridgeContract::class, $bridge);

        $result = app(InstagramScraperService::class)->storyFeeds('jpn-miami', ['111' => 'bdhlshul', '222' => 'cbiboca', 'not-an-id' => 'x']);

        $this->assertTrue($result['success']);
        $this->assertSame('1 of 2 accounts have current stories.', $result['message']);
        $story = $result['data']['reels']['bdhlshul']['stories'][0];
        $this->assertSame('https://www.instagram.com/stories/bdhlshul/3992076629349432787/', $story['url']);
        $this->assertSame(['http://bdhls.org/lulav'], $story['links']);
        $this->assertSame(['chabadgables'], $story['mentions']);
    }

    public function test_following_feed_lists_the_accounts_one_account_follows(): void
    {
        $bridge = Mockery::mock(BrowserWorkerBridgeContract::class);
        $bridge->shouldReceive('runAutomation')->once()->withArgs(fn (?string $profile, array $steps): bool => $steps[0]['url'] === 'https://www.instagram.com/miamijpn/')->andReturn([
            'success' => true,
            'data' => ['results' => [['label' => 'read_following', 'result' => ['text' => json_encode(['user_id' => '999', 'complete' => true, 'error' => null, 'users' => [
                ['username' => 'kesherconnect', 'full_name' => 'Kesher', 'user_id' => '5', 'is_private' => false, 'is_verified' => false],
            ]])]]]],
        ]);
        app()->instance(BrowserWorkerBridgeContract::class, $bridge);

        $result = app(InstagramScraperService::class)->followingFeed('jpn-miami', '@miamijpn');

        $this->assertTrue($result['success']);
        $this->assertSame('@miamijpn follows 1 accounts.', $result['message']);
        $this->assertSame('kesherconnect', $result['data']['users'][0]['username']);
        $this->assertTrue($result['data']['complete']);
    }

    public function test_profile_feeds_fail_cleanly_when_the_page_has_no_query_module(): void
    {
        $bridge = Mockery::mock(BrowserWorkerBridgeContract::class);
        $bridge->shouldReceive('runAutomation')->once()->andReturn([
            'success' => true,
            'data' => ['results' => [['label' => 'read_feeds', 'result' => ['text' => json_encode(['fatal' => 'Instagram page modules are unavailable.'])]]]],
        ]);
        app()->instance(BrowserWorkerBridgeContract::class, $bridge);

        $result = app(InstagramScraperService::class)->profileFeeds('jpn-miami', ['miamijpn']);

        $this->assertFalse($result['success']);
        $this->assertSame('Instagram page modules are unavailable.', $result['detail']);
    }
}
