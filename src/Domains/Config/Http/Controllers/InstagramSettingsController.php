<?php

namespace hexa_package_instagram\Domains\Config\Http\Controllers;

use hexa_core\Models\ActivityLog;
use hexa_core\Models\Setting;
use hexa_core\Services\CredentialService;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;
use hexa_package_instagram\Services\InstagramAccountSessionService;
use hexa_package_instagram\Services\InstagramImportService;
use hexa_package_instagram\Services\InstagramScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InstagramSettingsController extends Controller
{
    public function index(InstagramConfigRepository $config, CredentialService $credentials, InstagramAccountSessionService $sessions)
    {
        $settings = $config->all();
        $credentialKey = 'cred_instagram_meta_access_token';
        $credentialRow = Setting::query()->where('key', $credentialKey)->first();
        $hasMetaToken = $credentials->exists('instagram', 'meta_access_token');
        $metaTokenMasked = $credentials->getMasked('instagram', 'meta_access_token');

        if (!$hasMetaToken && $credentials->exists('content_extractor', 'instagram_access_token')) {
            $credentialKey = 'cred_content_extractor_instagram_access_token';
            $credentialRow = Setting::query()->where('key', $credentialKey)->first();
            $hasMetaToken = true;
            $metaTokenMasked = $credentials->getMasked('content_extractor', 'instagram_access_token');
        }
        $activeAccount = $config->activeAccount();

        return view('instagram::settings.index', [
            'settings' => $settings,
            'status' => [
                'accounts' => array_map(fn (array $account) => $sessions->accountPresentation($account), $settings['accounts']),
                'active_profile' => $settings['session_profile'],
                'active_account' => $activeAccount ? $sessions->accountPresentation($activeAccount) : null,
                'has_meta_token' => $hasMetaToken,
                'meta_token_masked' => $metaTokenMasked,
                'credential_key' => $credentialKey,
                'credential_updated_at' => $credentialRow?->updated_at?->toDateTimeString(),
                'package_version' => (string) config('instagram.version', '1.0.0'),
            ],
        ]);
    }

    public function save(Request $request, InstagramConfigRepository $config): JsonResponse
    {
        $validated = $request->validate([
            'default_profile_username' => ['nullable', 'string', 'max:255'],
            'default_story_username' => ['nullable', 'string', 'max:255'],
            'default_post_url' => ['nullable', 'url', 'max:2000'],
        ]);

        ActivityLog::log('instagram', 'settings_save', 'Saved Instagram settings.', $validated);

        return response()->json([
            'success' => true,
            'message' => 'Instagram settings saved.',
            'settings' => $config->update($validated),
        ]);
    }

    public function test(
        Request $request,
        InstagramConfigRepository $config,
        InstagramAccountSessionService $sessions,
        InstagramScraperService $scraper,
    ): JsonResponse {
        $validated = $request->validate([
            'profile' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $profile = $config->resolveProfile($validated['profile'] ?? null);
        $account = $config->findAccount($profile);
        $accountUsername = $config->normalizeUsername((string) ($account['instagram_username'] ?? ''));

        if (!$account || $accountUsername === '') {
            $result = [
                'success' => false,
                'message' => 'Selected saved Instagram account is incomplete.',
                'detail' => 'Pick a saved account that has an Instagram username attached before running the connection test.',
                'status_code' => 0,
                'data' => [
                    'profile' => $profile,
                    'selected_account' => $account ? $sessions->accountPresentation($account) : null,
                ],
            ];

            ActivityLog::log('instagram', 'connection_test', 'Ran Instagram connection test for profile: ' . $profile, [
                'profile' => $profile,
                'success' => false,
                'message' => $result['message'],
                'detail' => $result['detail'],
            ]);

            return response()->json($result);
        }

        $integrity = $sessions->integrityTest($profile);
        $data = is_array($integrity['data'] ?? null) ? $integrity['data'] : [];
        $data['selected_account'] = $sessions->accountPresentation($account);

        if (!(bool) ($integrity['success'] ?? false)) {
            $result = $integrity;
            $result['data'] = $data;

            ActivityLog::log('instagram', 'connection_test', 'Ran Instagram connection test for profile: ' . $profile, [
                'profile' => $profile,
                'success' => false,
                'message' => $result['message'] ?? null,
                'detail' => $result['detail'] ?? null,
                'connected' => (bool) ($data['connected'] ?? false),
            ]);

            return response()->json($result);
        }

        $following = $scraper->followingScan($profile, $accountUsername, 84);
        $followingUsernames = $this->normalizeUsernames((array) data_get($following, 'data.scan.usernames', []), $accountUsername);
        $data['following_sample'] = [
            'success' => (bool) ($following['success'] ?? false),
            'message' => $following['message'] ?? null,
            'detail' => $following['detail'] ?? null,
            'source_username' => $accountUsername,
            'count' => (int) (data_get($following, 'data.scan.username_count') ?: count($followingUsernames)),
            'usernames' => array_slice($followingUsernames, 0, 24),
            'raw' => $following,
        ];

        $storyCandidates = $scraper->activeStoryCandidatesScan($profile, $accountUsername, 24);
        $storyCandidateRows = array_values(array_filter((array) data_get($storyCandidates, 'data.scan.usernames', []), static fn ($row): bool => is_array($row) && !empty($row['username'])));
        $storyCandidateUsernames = $this->normalizeUsernames(array_map(static fn (array $row): string => (string) ($row['username'] ?? ''), $storyCandidateRows), $accountUsername);
        $data['active_story_candidates'] = [
            'success' => (bool) ($storyCandidates['success'] ?? false),
            'message' => $storyCandidates['message'] ?? null,
            'detail' => $storyCandidates['detail'] ?? null,
            'count' => (int) (data_get($storyCandidates, 'data.scan.username_count') ?: count($storyCandidateRows)),
            'usernames' => array_slice($storyCandidateRows, 0, 12),
            'raw' => $storyCandidates,
        ];

        $postSample = $this->sampleRandomFollowingPost($scraper, $profile, $followingUsernames);
        $storySample = $this->sampleRandomFollowingStory($scraper, $profile, $storyCandidateUsernames, $followingUsernames);

        $data['random_following_post'] = $postSample;
        $data['random_following_story'] = $storySample;

        $success = (bool) ($following['success'] ?? false)
            && count($followingUsernames) > 0
            && (bool) ($postSample['success'] ?? false)
            && (bool) ($storySample['success'] ?? false);

        $message = $success
            ? 'Selected Instagram account is connected. Random followed posts and stories loaded successfully.'
            : 'Instagram account is connected, but the followed-content probe is incomplete.';

        $detail = $success
            ? 'The selected saved account opened its following graph, a random followed profile with recent posts, and a live story from a followed account in the same authenticated session.'
            : $this->composeFailureDetail($following, $postSample, $storySample);

        $result = [
            'success' => $success,
            'message' => $message,
            'detail' => $detail,
            'status_code' => (int) ($integrity['status_code'] ?? 200),
            'data' => $data,
        ];

        ActivityLog::log('instagram', 'connection_test', 'Ran Instagram connection test for profile: ' . $profile, [
            'profile' => $profile,
            'success' => $success,
            'message' => $message,
            'detail' => $detail,
            'following_count' => count($followingUsernames),
            'random_post_username' => $postSample['instagram_username'] ?? null,
            'random_story_username' => $storySample['instagram_username'] ?? null,
            'story_source' => $storySample['source'] ?? null,
        ]);

        return response()->json($result);
    }

    public function testMetaToken(InstagramImportService $instagram): JsonResponse
    {
        return response()->json($instagram->testToken());
    }

    private function sampleRandomFollowingPost(InstagramScraperService $scraper, string $profile, array $followingUsernames): array
    {
        $checked = [];
        $lastProfileScan = null;
        $lastPostScan = null;

        foreach ($this->randomizedSample($followingUsernames, 10) as $username) {
            $checked[] = $username;
            $profileScan = $scraper->profileScan($profile, $username, 8);
            $lastProfileScan = $profileScan;
            $postLinks = $this->sanitizeUrls((array) data_get($profileScan, 'data.scan.post_links', []));

            if (!(bool) ($profileScan['success'] ?? false) || count($postLinks) === 0) {
                continue;
            }

            $selectedPostUrl = $postLinks[array_rand($postLinks)];
            $postScan = $scraper->postScan($profile, $selectedPostUrl);
            $lastPostScan = $postScan;

            return [
                'success' => true,
                'message' => 'Recent posts loaded from a random followed account.',
                'detail' => 'The selected attached account opened a random followed profile and loaded its recent Instagram posts.',
                'instagram_username' => $username,
                'checked_usernames' => $checked,
                'recent_post_links' => array_slice($postLinks, 0, 6),
                'selected_post_url' => (string) (data_get($postScan, 'data.scan.canonical_url') ?: data_get($postScan, 'data.url') ?: $selectedPostUrl),
                'profile_scan' => $profileScan,
                'post_scan' => $postScan,
            ];
        }

        return [
            'success' => false,
            'message' => 'No recent posts were found from the sampled followed accounts.',
            'detail' => 'The selected account is connected, but the sampled followed profiles did not return recent Instagram post links during this test window.',
            'checked_usernames' => $checked,
            'profile_scan' => $lastProfileScan,
            'post_scan' => $lastPostScan,
        ];
    }

    private function sampleRandomFollowingStory(InstagramScraperService $scraper, string $profile, array $storyCandidateUsernames, array $followingUsernames): array
    {
        $checked = [];
        $candidatePool = $storyCandidateUsernames;
        $source = 'active_story_candidates';
        $lastStoryScan = null;

        if (count($candidatePool) === 0) {
            $candidatePool = $followingUsernames;
            $source = 'following_fallback';
        }

        foreach ($this->randomizedSample($candidatePool, 8) as $username) {
            $checked[] = $username;
            $storyScan = $scraper->storyScan($profile, $username);
            $lastStoryScan = $storyScan;
            $imageUrls = $this->sanitizeUrls((array) data_get($storyScan, 'data.scan.image_urls', []));
            $videoUrls = $this->sanitizeUrls((array) data_get($storyScan, 'data.scan.video_urls', []));

            if (!(bool) ($storyScan['success'] ?? false) || (count($imageUrls) + count($videoUrls)) === 0) {
                continue;
            }

            return [
                'success' => true,
                'message' => 'An active story was loaded from a followed account.',
                'detail' => 'The selected attached account opened a followed account story page and returned live image or video media URLs.',
                'source' => $source,
                'instagram_username' => $username,
                'checked_usernames' => $checked,
                'image_urls' => array_slice($imageUrls, 0, 6),
                'video_urls' => array_slice($videoUrls, 0, 6),
                'story_scan' => $storyScan,
            ];
        }

        return [
            'success' => false,
            'message' => 'No active stories were found from the sampled followed accounts.',
            'detail' => $source === 'active_story_candidates'
                ? 'The selected account loaded the home feed, but none of the sampled active story usernames returned story media on this run.'
                : 'The selected account did not expose active story tray usernames, and the fallback scan across sampled followed accounts did not find story media.',
            'source' => $source,
            'checked_usernames' => $checked,
            'story_scan' => $lastStoryScan,
        ];
    }

    private function randomizedSample(array $values, int $limit): array
    {
        $values = array_values(array_filter($values, static fn ($value): bool => is_string($value) && trim($value) !== ''));
        if (count($values) <= 1) {
            return array_slice($values, 0, $limit);
        }

        shuffle($values);

        return array_slice($values, 0, $limit);
    }

    private function normalizeUsernames(array $values, string $exclude = ''): array
    {
        $exclude = strtolower(trim($exclude));
        $normalized = [];
        $seen = [];

        foreach ($values as $value) {
            $username = strtolower(trim((string) $value));
            $username = ltrim($username, '@');

            if ($username === '' || $username === $exclude) {
                continue;
            }

            if (!preg_match('/^[A-Za-z0-9._]+$/', $username)) {
                continue;
            }

            if (isset($seen[$username])) {
                continue;
            }

            $seen[$username] = true;
            $normalized[] = $username;
        }

        return $normalized;
    }

    private function sanitizeUrls(array $urls): array
    {
        $clean = [];

        foreach ($urls as $url) {
            $value = trim((string) $url);
            if ($value === '' || !filter_var($value, FILTER_VALIDATE_URL)) {
                continue;
            }
            $clean[] = $value;
        }

        return array_values(array_unique($clean));
    }

    private function composeFailureDetail(array $following, array $postSample, array $storySample): string
    {
        if (!(bool) ($following['success'] ?? false)) {
            return (string) ($following['detail'] ?? 'The following-list scan failed.');
        }

        if (!(bool) ($postSample['success'] ?? false)) {
            return (string) ($postSample['detail'] ?? 'The random followed post sample failed.');
        }

        if (!(bool) ($storySample['success'] ?? false)) {
            return (string) ($storySample['detail'] ?? 'The random followed story sample failed.');
        }

        return 'The Instagram connection test did not finish all content probes.';
    }
}
