<?php
namespace Tests\Feature;
use Tests\TestCase;
use hexa_core\AI\Services\AiChatGateway;
use hexa_package_instagram\Services\InstagramFollowAuditService;
use hexa_package_instagram\Services\InstagramScraperService;
/** IG-2026-11-04-01: posts read from a partly refused account are judged; only a pass is final. */
final class InstagramFollowAuditPartialTest extends TestCase
{
    private function audit(string $answer): array
    {
        $ai = $this->createMock(AiChatGateway::class);
        $ai->method('chat')->willReturn(['success' => true, 'content' => $answer]);
        $service = new InstagramFollowAuditService($this->createMock(InstagramScraperService::class), $ai);
        $recent = now()->subDay()->toIso8601String();
        $rows = [];
        $service->audit(null, 'origin', ['jewish' => 'Is it Jewish?'], [
            'source' => 'list', 'usernames' => ['partial'],
            'feed_reader' => fn (): array => ['success' => false, 'message' => 'Refused', 'data' => ['accounts' => ['partial' => [
                'success' => false, 'partial' => true, 'message' => 'Instagram engine read was inconclusive.',
                'posts' => [['url' => 'https://www.instagram.com/p/READ1/', 'posted_at' => $recent, 'caption_blocks' => ['Shabbat dinner']]],
            ]]]],
            'recent_days' => 30,
            'on_result' => function (array $row) use (&$rows): void { $rows[] = $row; },
        ]);

        return $rows[0];
    }

    public function test_partly_read_account_that_passes_is_flagged(): void
    {
        $this->assertSame('match', $this->audit('{"answers": {"jewish": true}, "score": 90, "reason": "Jewish events"}')['status']);
    }

    public function test_partly_read_account_without_a_pass_stays_unreadable_for_a_retry(): void
    {
        $row = $this->audit('{"answers": {"jewish": false}, "score": 5, "reason": "Not Jewish"}');
        $this->assertSame('unreadable', $row['status']);
        $this->assertStringContainsString('Only some posts were read', $row['reason']);
    }
}
