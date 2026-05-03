<?php

namespace hexa_package_instagram\Services;

use hexa_package_browser_worker\Contracts\BrowserWorkerBridgeContract;
use hexa_package_instagram\Domains\Config\InstagramConfigRepository;

class InstagramScraperService
{
    public function __construct(
        private InstagramConfigRepository $config,
        private BrowserWorkerBridgeContract $browser,
        private InstagramImportService $imports,
    ) {
    }

    public function importPost(string $url, bool $includeImageData = true): array
    {
        return $this->imports->importPost($url, $includeImageData);
    }

    public function profileScan(?string $profile, string $username, int $limit = 12): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $username = $this->config->normalizeUsername($username);

        if ($username === '') {
            return $this->failure('Instagram username is required.', 'Provide an Instagram username before running the profile scan.');
        }

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'goto',
                'label' => 'open_profile',
                'url' => 'https://www.instagram.com/' . $username . '/',
                'wait_until' => 'domcontentloaded',
                'timeout_ms' => 30000,
                'wait_ms' => 2500,
            ],
            [
                'type' => 'click_if_exists',
                'label' => 'allow_cookies',
                'selector' => 'button:has-text("Allow all cookies")',
                'timeout_ms' => 4000,
                'wait_ms' => 1000,
            ],
            [
                'type' => 'evaluate',
                'label' => 'extract_profile',
                'code' => self::profileProbeJs(),
                'args' => ['limit' => max(1, min($limit, 30))],
            ],
        ], [
            'final' => [
                'include_screenshot' => true,
            ],
        ]);

        $probe = $this->resultByLabel($result, 'extract_profile');
        if ($this->isLoginRedirect($result, $probe)) {
            return [
                'success' => false,
                'message' => 'Instagram profile scan requires a connected account.',
                'detail' => 'The browser worker was redirected back to the Instagram login flow instead of the requested profile.',
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => [
                    'profile' => $resolved,
                    'instagram_username' => $username,
                    'scan' => $probe,
                    'worker' => $result['data'] ?? [],
                ],
            ];
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (bool) ($result['success'] ?? false) ? 'Instagram profile scan completed.' : (string) ($result['message'] ?? 'Instagram profile scan failed.'),
            'detail' => (bool) ($result['success'] ?? false)
                ? 'Rendered DOM data came from the authenticated browser worker profile.'
                : (string) ($result['detail'] ?? ''),
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => [
                'profile' => $resolved,
                'instagram_username' => $username,
                'scan' => $probe,
                'worker' => $result['data'] ?? [],
            ],
        ];
    }

    public function storyScan(?string $profile, string $username): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $username = $this->config->normalizeUsername($username);

        if ($username === '') {
            return $this->failure('Instagram username is required.', 'Provide an Instagram username before running the story scan.');
        }

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'goto',
                'label' => 'open_story',
                'url' => 'https://www.instagram.com/stories/' . $username . '/',
                'wait_until' => 'domcontentloaded',
                'timeout_ms' => 30000,
                'wait_ms' => 4000,
            ],
            [
                'type' => 'click_if_exists',
                'label' => 'allow_cookies',
                'selector' => 'button:has-text("Allow all cookies")',
                'timeout_ms' => 4000,
                'wait_ms' => 1000,
            ],
            [
                'type' => 'evaluate',
                'label' => 'extract_story',
                'code' => self::storyProbeJs(),
            ],
        ], [
            'final' => [
                'include_screenshot' => true,
            ],
        ]);

        $probe = $this->resultByLabel($result, 'extract_story');
        if ($this->isLoginRedirect($result, $probe)) {
            return [
                'success' => false,
                'message' => 'Instagram story scan requires a connected account.',
                'detail' => 'The browser worker was redirected back to the Instagram login flow instead of the requested stories page.',
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => [
                    'profile' => $resolved,
                    'instagram_username' => $username,
                    'scan' => $probe,
                    'worker' => $result['data'] ?? [],
                ],
            ];
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (bool) ($result['success'] ?? false) ? 'Instagram story scan completed.' : (string) ($result['message'] ?? 'Instagram story scan failed.'),
            'detail' => (bool) ($result['success'] ?? false)
                ? 'If stories are visible to the attached account, the current media URLs are listed below.'
                : (string) ($result['detail'] ?? ''),
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => [
                'profile' => $resolved,
                'instagram_username' => $username,
                'scan' => $probe,
                'worker' => $result['data'] ?? [],
            ],
        ];
    }

    public function postScan(?string $profile, string $url): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $normalizedUrl = $this->normalizePostUrl($url);

        if ($normalizedUrl === '') {
            return $this->failure('Instagram post URL is required.', 'Provide a valid Instagram post URL before running the post scan.');
        }

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'goto',
                'label' => 'open_post',
                'url' => $normalizedUrl,
                'wait_until' => 'domcontentloaded',
                'timeout_ms' => 30000,
                'wait_ms' => 3000,
            ],
            [
                'type' => 'click_if_exists',
                'label' => 'allow_cookies',
                'selector' => 'button:has-text("Allow all cookies")',
                'timeout_ms' => 4000,
                'wait_ms' => 1000,
            ],
            [
                'type' => 'evaluate',
                'label' => 'extract_post',
                'code' => self::postProbeJs(),
            ],
        ], [
            'final' => [
                'include_screenshot' => true,
            ],
        ]);

        $probe = $this->resultByLabel($result, 'extract_post');
        if ($this->isLoginRedirect($result, $probe)) {
            return [
                'success' => false,
                'message' => 'Instagram post scan requires a connected account.',
                'detail' => 'The browser worker was redirected back to the Instagram login flow instead of the requested post.',
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => [
                    'profile' => $resolved,
                    'url' => $normalizedUrl,
                    'scan' => $probe,
                    'worker' => $result['data'] ?? [],
                ],
            ];
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (bool) ($result['success'] ?? false) ? 'Instagram post scan completed.' : (string) ($result['message'] ?? 'Instagram post scan failed.'),
            'detail' => (bool) ($result['success'] ?? false)
                ? 'Rendered DOM data came from the authenticated Instagram post page.'
                : (string) ($result['detail'] ?? ''),
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => [
                'profile' => $resolved,
                'url' => $normalizedUrl,
                'scan' => $probe,
                'worker' => $result['data'] ?? [],
            ],
        ];
    }

    public static function profileProbeJs(): string
    {
        return <<<'JS'
const limit = Math.max(1, Math.min(Number(args?.limit || 12), 30));
const bodyText = (document.body?.innerText || '').trim();
const postLinks = Array.from(document.querySelectorAll('a[href]'))
  .map((node) => node.getAttribute('href'))
  .filter((href) => typeof href === 'string' && (
    href.includes('/p/')
    || href.includes('/reel/')
    || href.includes('/tv/')
  ))
  .map((href) => href.startsWith('http') ? href : `https://www.instagram.com${href}`)
  .map((href) => href.replace(/https:\/\/www\.instagram\.com\/[^/]+\/(p|reel|tv)\//, 'https://www.instagram.com/$1/'))
  .filter((value, index, array) => array.indexOf(value) === index)
  .slice(0, limit);
const media = Array.from(document.querySelectorAll('img[src]'))
  .map((node) => ({
    alt: (node.getAttribute('alt') || '').trim(),
    src: node.getAttribute('src'),
  }))
  .filter((entry) => entry.src)
  .slice(0, limit);

return {
  url: location.href,
  title: document.title,
  heading: (document.querySelector('h1, h2')?.innerText || '').trim(),
  body_excerpt: bodyText.slice(0, 2000),
  post_links: postLinks,
  media,
};
JS;
    }

    public static function storyProbeJs(): string
    {
        return <<<'JS'
const bodyText = (document.body?.innerText || '').trim();
const imageUrls = Array.from(document.querySelectorAll('img[src]'))
  .map((node) => node.getAttribute('src'))
  .filter(Boolean)
  .filter((value, index, array) => array.indexOf(value) === index);
const videoUrls = Array.from(document.querySelectorAll('video, video source'))
  .map((node) => node.currentSrc || node.getAttribute('src'))
  .filter(Boolean)
  .filter((value, index, array) => array.indexOf(value) === index);

return {
  url: location.href,
  title: document.title,
  body_excerpt: bodyText.slice(0, 2000),
  image_urls: imageUrls,
  video_urls: videoUrls,
  visible_buttons: Array.from(document.querySelectorAll('button')).map((node) => (node.innerText || '').trim()).filter(Boolean).slice(0, 20),
};
JS;
    }

    public static function postProbeJs(): string
    {
        return <<<'JS'
const bodyText = (document.body?.innerText || '').trim();
const timeNode = document.querySelector('time');
const imageUrls = Array.from(document.querySelectorAll('article img[src], img[src]'))
  .map((node) => node.getAttribute('src'))
  .filter(Boolean)
  .filter((value, index, array) => array.indexOf(value) === index);
const videoUrls = Array.from(document.querySelectorAll('article video, article video source, video, video source'))
  .map((node) => node.currentSrc || node.getAttribute('src'))
  .filter(Boolean)
  .filter((value, index, array) => array.indexOf(value) === index);
const captionBlocks = Array.from(document.querySelectorAll('h1, h2, ul h1, ul span, article h1, article span'))
  .map((node) => (node.innerText || '').trim())
  .filter(Boolean)
  .filter((value, index, array) => array.indexOf(value) === index);
const canonical = document.querySelector('link[rel="canonical"]')?.getAttribute('href') || '';

return {
  url: location.href,
  canonical_url: canonical,
  title: document.title,
  posted_at: timeNode?.getAttribute('datetime') || '',
  time_text: (timeNode?.innerText || '').trim(),
  image_urls: imageUrls,
  video_urls: videoUrls,
  caption_blocks: captionBlocks.slice(0, 12),
  body_excerpt: bodyText.slice(0, 3000),
};
JS;
    }

    private function resultByLabel(array $result, string $label): array
    {
        foreach (($result['data']['results'] ?? []) as $step) {
            if (($step['label'] ?? null) === $label && is_array($step['result'] ?? null)) {
                return $step['result'];
            }
        }

        return [];
    }

    private function normalizePostUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        if (!preg_match('#^https?://(www\.)?instagram\.com/#i', $url)) {
            return '';
        }

        if (preg_match('~https?://(www\.)?instagram\.com/([^/]+)/(p|reel|tv)/([^/?#]+)/?$~i', $url, $match)) {
            return 'https://www.instagram.com/' . strtolower($match[3]) . '/' . $match[4] . '/';
        }

        if (preg_match('~https?://(www\.)?instagram\.com/(p|reel|tv)/([^/?#]+)/?$~i', $url, $match)) {
            return 'https://www.instagram.com/' . strtolower($match[2]) . '/' . $match[3] . '/';
        }

        return rtrim($url, '/') . '/';
    }

    private function failure(string $message, string $detail): array
    {
        return [
            'success' => false,
            'message' => $message,
            'detail' => $detail,
            'status_code' => 0,
            'data' => [],
        ];
    }

    private function isLoginRedirect(array $result, array $probe): bool
    {
        $finalUrl = strtolower((string) ($result['data']['final']['final_url'] ?? ''));
        $bodyExcerpt = strtolower((string) ($probe['body_excerpt'] ?? ''));

        if (str_contains($finalUrl, '/accounts/login')) {
            return true;
        }

        if (str_contains($finalUrl, '/auth_platform/codeentry')) {
            return true;
        }

        return str_contains($bodyExcerpt, 'log into instagram')
            || str_contains($bodyExcerpt, 'log in to instagram')
            || str_contains($bodyExcerpt, 'mobile number, username or email')
            || str_contains($bodyExcerpt, 'create new account')
            || str_contains($bodyExcerpt, 'check your whatsapp messages')
            || str_contains($bodyExcerpt, 'enter the code we sent to your whatsapp account');
    }
}
