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
            'transport_timeout_ms' => max(90000, ($limit * 4000)),
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

    public function followingScan(?string $profile, string $username, int $limit = 80): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $username = $this->config->normalizeUsername($username);
        $limit = max(10, min($limit, 500));

        if ($username === '') {
            return $this->failure('Instagram username is required.', 'Provide the attached Instagram username before scanning the following list.');
        }

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'goto',
                'label' => 'open_profile',
                'url' => 'https://www.instagram.com/' . $username . '/',
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
                'type' => 'click_if_exists',
                'label' => 'open_following_dialog',
                'selector' => 'a:has-text("Following"), button:has-text("Following"), div[role="button"]:has-text("Following")',
                'timeout_ms' => 7000,
                'wait_ms' => 1400,
            ],
            [
                'type' => 'wait_ms',
                'label' => 'settle_following_dialog',
                'ms' => 1200,
            ],
            [
                'type' => 'evaluate',
                'label' => 'extract_following',
                'code' => self::followingProbeJs(),
                'args' => [
                    'limit' => $limit,
                ],
            ],
        ], [
            'transport_timeout_ms' => max(90000, ($limit * 180)),
            'final' => [
                'include_screenshot' => true,
            ],
        ]);

        $probe = $this->resultByLabel($result, 'extract_following');
        if ($this->isLoginRedirect($result, $probe)) {
            return [
                'success' => false,
                'message' => 'Instagram following scan requires a connected account.',
                'detail' => 'The browser worker was redirected back to the Instagram login flow instead of the selected account profile.',
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
            'message' => (bool) ($result['success'] ?? false) ? 'Instagram following scan completed.' : (string) ($result['message'] ?? 'Instagram following scan failed.'),
            'detail' => (bool) ($result['success'] ?? false)
                ? 'The selected attached account profile opened successfully and the current following list was extracted from the live dialog.'
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

    public function activeStoryCandidatesScan(?string $profile, string $currentUsername, int $limit = 24): array
    {
        $resolved = $this->config->resolveProfile($profile);
        $currentUsername = $this->config->normalizeUsername($currentUsername);
        $limit = max(6, min($limit, 40));

        $result = $this->browser->runAutomation($resolved, [
            [
                'type' => 'goto',
                'label' => 'open_home',
                'url' => 'https://www.instagram.com/',
                'wait_until' => 'domcontentloaded',
                'timeout_ms' => 30000,
                'wait_ms' => 3500,
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
                'label' => 'extract_story_candidates',
                'code' => self::activeStoryCandidatesProbeJs(),
                'args' => [
                    'limit' => $limit,
                    'current_username' => $currentUsername,
                ],
            ],
        ], [
            'transport_timeout_ms' => 90000,
            'final' => [
                'include_screenshot' => true,
            ],
        ]);

        $probe = $this->resultByLabel($result, 'extract_story_candidates');
        if ($this->isLoginRedirect($result, $probe)) {
            return [
                'success' => false,
                'message' => 'Instagram story candidate scan requires a connected account.',
                'detail' => 'The browser worker was redirected back to the Instagram login flow instead of the home feed.',
                'status_code' => (int) ($result['status_code'] ?? 0),
                'data' => [
                    'profile' => $resolved,
                    'instagram_username' => $currentUsername,
                    'scan' => $probe,
                    'worker' => $result['data'] ?? [],
                ],
            ];
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (bool) ($result['success'] ?? false) ? 'Instagram active story candidates loaded.' : (string) ($result['message'] ?? 'Instagram active story candidate scan failed.'),
            'detail' => (bool) ($result['success'] ?? false)
                ? 'The authenticated home feed was scanned for usernames that currently appear in the story tray.'
                : (string) ($result['detail'] ?? ''),
            'status_code' => (int) ($result['status_code'] ?? 0),
            'data' => [
                'profile' => $resolved,
                'instagram_username' => $currentUsername,
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
            'transport_timeout_ms' => 90000,
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
            'transport_timeout_ms' => 90000,
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
const normalizePostHref = (value) => {
  const href = String(value || '').trim();
  if (!href) return '';
  try {
    const url = new URL(href.startsWith('http') ? href : `https://www.instagram.com${href.startsWith('/') ? '' : '/'}${href}`);
    const match = url.pathname.match(/^\/(?:[^/]+\/)?(p|reel|tv)\/([A-Za-z0-9._-]{5,20})\/?$/i);
    if (!match) return '';
    return `https://www.instagram.com/${match[1].toLowerCase()}/${match[2]}/`;
  } catch (_error) {
    return '';
  }
};
const postLinks = Array.from(document.querySelectorAll('a[href]'))
  .map((node) => normalizePostHref(node.getAttribute('href')))
  .filter(Boolean)
  .filter((value, index, array) => array.indexOf(value) === index)
  .slice(0, limit);
const media = Array.from(document.querySelectorAll('img[src], img[srcset]'))
  .map((node) => ({
    alt: (node.getAttribute('alt') || '').trim(),
    src: node.currentSrc || node.getAttribute('src') || '',
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

    public static function followingProbeJs(): string
    {
        return <<<'JS'
return (async () => {
  const limit = Math.max(10, Math.min(Number(args?.limit || 80), 500));
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const text = (node) => (node?.innerText || node?.textContent || '').trim();
  const bodyText = (document.body?.innerText || '').trim();
  const controls = Array.from(document.querySelectorAll('a, button, div[role="button"]'));
  const dialog = document.querySelector('div[role="dialog"]');

  if (!dialog) {
    return {
      found_dialog: false,
      url: location.href,
      title: document.title,
      body_excerpt: bodyText.slice(0, 2000),
      visible_controls: controls.map((node) => text(node)).filter(Boolean).slice(0, 80),
      usernames: [],
      username_count: 0,
    };
  }

  let list = dialog.querySelector('div[style*="overflow"], div._aano, div.x6nl9eh');
  if (!list && dialog) {
    const candidates = Array.from(dialog.querySelectorAll('div')).filter((el) => el.scrollHeight > el.clientHeight + 40);
    list = candidates.sort((a, b) => b.scrollHeight - a.scrollHeight)[0] || null;
  }

  const usernames = [];
  const seen = new Set();
  const pushName = (value) => {
    const cleaned = String(value || '').trim().replace(/^@+/, '').toLowerCase();
    if (!cleaned) return;
    if (!/^[A-Za-z0-9._]+$/.test(cleaned)) return;
    if (seen.has(cleaned)) return;
    seen.add(cleaned);
    usernames.push(cleaned);
  };

  const collectVisibleUsernames = () => {
    Array.from(dialog.querySelectorAll('a[href^="/"]')).forEach((node) => {
      const href = node.getAttribute('href') || '';
      const match = href.match(/^\/([A-Za-z0-9._]+)\/?$/);
      if (match) pushName(match[1]);
    });
  };

  const maxPasses = Math.max(6, Math.min(18, Math.ceil(limit / 18) + 3));
  let stagnantPasses = 0;

  for (let pass = 0; pass < maxPasses && usernames.length < limit; pass += 1) {
    const beforeCount = usernames.length;
    collectVisibleUsernames();
    if (usernames.length >= limit) {
      break;
    }

    let beforeTop = null;
    let afterTop = null;
    if (list) {
      beforeTop = Number(list.scrollTop || 0);
      list.scrollTop = list.scrollHeight;
    }

    await sleep(list ? 450 : 300);
    collectVisibleUsernames();

    if (list) {
      afterTop = Number(list.scrollTop || 0);
    }

    const grew = usernames.length > beforeCount;
    const moved = list ? afterTop !== beforeTop : false;
    stagnantPasses = (grew || moved) ? 0 : (stagnantPasses + 1);

    if (pass >= 2 && stagnantPasses >= 2) {
      break;
    }
  }

  return {
    found_dialog: true,
    url: location.href,
    title: document.title,
    body_excerpt: bodyText.slice(0, 2000),
    dialog_text: text(dialog).slice(0, 2400),
    usernames: usernames.slice(0, limit),
    username_count: usernames.length,
  };
})();
JS;
    }

    public static function activeStoryCandidatesProbeJs(): string
    {
        return <<<'JS'
return (() => {
  const limit = Math.max(6, Math.min(Number(args?.limit || 24), 40));
  const currentUsername = String(args?.current_username || '').trim().toLowerCase();
  const bodyText = (document.body?.innerText || '').trim();
  const seen = new Set();
  const usernames = [];
  const skipValues = new Set(['switch', 'see all', 'suggested for you']);

  const pushUsername = (value, source = 'body') => {
    const username = String(value || '').trim().replace(/^@+/, '').toLowerCase();
    if (!username) return;
    if (username === currentUsername) return;
    if (skipValues.has(username)) return;
    if (!/^[A-Za-z0-9._]+$/.test(username)) return;
    if (seen.has(username)) return;
    seen.add(username);
    usernames.push({ username, source });
  };

  Array.from(document.querySelectorAll('a[href*="/stories/"]')).forEach((node) => {
    const href = node.getAttribute('href') || '';
    const match = href.match(/\/stories\/([A-Za-z0-9._-]+)\/?/);
    if (match) {
      pushUsername(match[1], 'stories_anchor');
    }
  });

  const lines = bodyText
    .split(/\n+/)
    .map((line) => line.trim())
    .filter(Boolean);

  const usernamePattern = /^[A-Za-z0-9._]+$/;
  for (const line of lines) {
    if (line === '•' || /^\d+[smhdw]$/i.test(line) || /^\d+\s*(min|mins|minutes|hour|hours|day|days|week|weeks)$/i.test(line)) {
      break;
    }

    if (/^suggested for you$/i.test(line)) {
      break;
    }

    if (usernamePattern.test(line)) {
      pushUsername(line, 'feed_text');
    }

    if (usernames.length >= limit) {
      break;
    }
  }

  return {
    url: location.href,
    title: document.title,
    body_excerpt: bodyText.slice(0, 2000),
    usernames: usernames.slice(0, limit),
    username_count: usernames.length,
  };
})();
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
const canonical = document.querySelector('link[rel="canonical"]')?.getAttribute('href')
  || document.querySelector('meta[property="og:url"]')?.getAttribute('content')
  || '';
const metaDescription = document.querySelector('meta[property="og:description"]')?.getAttribute('content')
  || document.querySelector('meta[name="description"]')?.getAttribute('content')
  || '';
const ogImage = document.querySelector('meta[property="og:image"]')?.getAttribute('content') || '';
const pickLargestSrcset = (srcset) => {
  const value = String(srcset || '').trim();
  if (!value) return '';
  const candidates = value.split(',').map((part) => part.trim()).filter(Boolean).map((part) => {
    const bits = part.split(/\s+/).filter(Boolean);
    return {
      url: bits[0] || '',
      width: Number.parseInt((bits[1] || '').replace(/[^0-9]/g, ''), 10) || 0,
    };
  }).filter((entry) => entry.url);
  if (!candidates.length) return '';
  candidates.sort((a, b) => b.width - a.width);
  return candidates[0].url;
};
const normalizeUrl = (value) => {
  const url = String(value || '').trim();
  if (!url) return '';
  if (url.includes('static.cdninstagram.com/rsrc.php')) return '';
  if (url.includes('profile_pic')) return '';
  return url;
};
const imageUrls = Array.from(document.querySelectorAll('article img, img'))
  .flatMap((node) => [
    node.currentSrc || '',
    node.getAttribute('src') || '',
    pickLargestSrcset(node.getAttribute('srcset') || ''),
  ])
  .map((value) => normalizeUrl(value))
  .filter(Boolean)
  .filter((value, index, array) => array.indexOf(value) === index);
if (normalizeUrl(ogImage)) {
  imageUrls.unshift(normalizeUrl(ogImage));
}
const dedupedImageUrls = imageUrls.filter((value, index, array) => array.indexOf(value) === index);
const videoUrls = Array.from(document.querySelectorAll('article video, article video source, video, video source'))
  .map((node) => node.currentSrc || node.getAttribute('src'))
  .filter(Boolean)
  .filter((value, index, array) => array.indexOf(value) === index);
const captionBlocks = Array.from(document.querySelectorAll('article h1, article ul li h1, article ul li span[dir="auto"], article div[role="presentation"] span[dir="auto"]'))
  .map((node) => (node.innerText || '').trim())
  .filter(Boolean)
  .filter((value, index, array) => array.indexOf(value) === index);
const mediaCandidates = Array.from((document.querySelector('article') || document.body || document.documentElement).querySelectorAll('img, video'))
  .map((node) => {
    const rect = node.getBoundingClientRect();
    const style = window.getComputedStyle(node);
    const source = normalizeUrl(node.currentSrc || node.getAttribute('src') || pickLargestSrcset(node.getAttribute('srcset') || ''));
    const naturalWidth = Number(node.naturalWidth || node.videoWidth || 0);
    const naturalHeight = Number(node.naturalHeight || node.videoHeight || 0);
    return {
      node,
      rect,
      source,
      naturalWidth,
      naturalHeight,
      objectFit: style.objectFit || '',
      area: Math.max(0, rect.width) * Math.max(0, rect.height),
    };
  })
  .filter((entry) => entry.source && entry.area >= 40000 && entry.rect.width >= 150 && entry.rect.height >= 150 && entry.rect.bottom >= 0 && entry.rect.right >= 0)
  .sort((a, b) => b.area - a.area);
const primaryMedia = mediaCandidates[0] || null;
const primaryMediaBox = primaryMedia ? {
  viewport_left: Math.max(0, Math.round(primaryMedia.rect.left)),
  viewport_top: Math.max(0, Math.round(primaryMedia.rect.top)),
  viewport_width: Math.max(1, Math.round(primaryMedia.rect.width)),
  viewport_height: Math.max(1, Math.round(primaryMedia.rect.height)),
  page_left: Math.max(0, Math.round(primaryMedia.rect.left + (window.scrollX || window.pageXOffset || 0))),
  page_top: Math.max(0, Math.round(primaryMedia.rect.top + (window.scrollY || window.pageYOffset || 0))),
  source_url: primaryMedia.source,
  natural_width: primaryMedia.naturalWidth,
  natural_height: primaryMedia.naturalHeight,
  object_fit: primaryMedia.objectFit,
  viewport_inner_width: Math.max(1, Math.round(window.innerWidth || document.documentElement?.clientWidth || 0)),
  viewport_inner_height: Math.max(1, Math.round(window.innerHeight || document.documentElement?.clientHeight || 0)),
  document_scroll_width: Math.max(1, Math.round(document.documentElement?.scrollWidth || document.body?.scrollWidth || 0)),
  document_scroll_height: Math.max(1, Math.round(document.documentElement?.scrollHeight || document.body?.scrollHeight || 0)),
  scroll_x: Math.max(0, Math.round(window.scrollX || window.pageXOffset || 0)),
  scroll_y: Math.max(0, Math.round(window.scrollY || window.pageYOffset || 0)),
  device_pixel_ratio: Number(window.devicePixelRatio || 1),
  tag: (primaryMedia.node?.tagName || '').toLowerCase(),
} : null;
const primaryMediaUrl = primaryMediaBox?.source_url || '';

return {
  url: location.href,
  canonical_url: canonical,
  title: document.title,
  posted_at: timeNode?.getAttribute('datetime') || '',
  time_text: (timeNode?.innerText || '').trim(),
  image_urls: primaryMediaUrl ? [primaryMediaUrl, ...dedupedImageUrls.filter((value) => value !== primaryMediaUrl)] : dedupedImageUrls,
  video_urls: videoUrls,
  caption_blocks: captionBlocks.slice(0, 12),
  meta_description: metaDescription,
  body_excerpt: bodyText.slice(0, 3000),
  primary_media_url: primaryMediaUrl,
  primary_media_box: primaryMediaBox,
  page_width: Math.max(1, Math.round(document.documentElement?.scrollWidth || document.body?.scrollWidth || window.innerWidth || 0)),
  page_height: Math.max(1, Math.round(document.documentElement?.scrollHeight || document.body?.scrollHeight || window.innerHeight || 0)),
  viewport_width: Math.max(1, Math.round(window.innerWidth || document.documentElement?.clientWidth || 0)),
  viewport_height: Math.max(1, Math.round(window.innerHeight || document.documentElement?.clientHeight || 0)),
};
JS;
    }


    private function isUsablePostScanPayload(array $scan): bool
    {
        $canonicalUrl = $this->normalizePostUrl((string) ($scan["canonical_url"] ?? ""));
        $imageUrls = array_values(array_filter(array_map("strval", (array) ($scan["image_urls"] ?? [])), static fn (string $value): bool => trim($value) !== ""));
        $videoUrls = array_values(array_filter(array_map("strval", (array) ($scan["video_urls"] ?? [])), static fn (string $value): bool => trim($value) !== ""));
        $captionBlocks = array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), (array) ($scan["caption_blocks"] ?? [])), static fn (string $value): bool => $value !== ""));
        $metaDescription = trim((string) ($scan["meta_description"] ?? ""));
        $bodyExcerpt = trim((string) ($scan["body_excerpt"] ?? ""));
        $title = trim((string) ($scan["title"] ?? ""));
        $normalizedTitle = strtolower($title);
        $genericTitles = ["instagram", "instagram • photos and videos"];
        $hasMeaningfulTitle = $title !== "" && !in_array($normalizedTitle, $genericTitles, true);
        $hasReadableBody = strlen($bodyExcerpt) >= 80;

        return $canonicalUrl !== ""
            || $imageUrls !== []
            || $videoUrls !== []
            || $captionBlocks !== []
            || $metaDescription !== ""
            || $hasMeaningfulTitle
            || $hasReadableBody;
    }

    private function resultByLabel(array $result, string $label): array
    {
        foreach (($result["data"]["results"] ?? []) as $step) {
            if (($step["label"] ?? null) === $label && is_array($step["result"] ?? null)) {
                return $step["result"];
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

        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('~/(?:[^/]+/)?(p|reel|tv)/([A-Za-z0-9_-]{5,20})(?:/|$)~i', $path, $match)) {
            return 'https://www.instagram.com/' . strtolower((string) $match[1]) . '/' . (string) $match[2] . '/';
        }

        return '';
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
