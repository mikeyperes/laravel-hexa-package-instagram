<?php

namespace hexa_package_instagram\Services;

use hexa_core\Services\CredentialService;
use hexa_package_browser_worker\Services\BrowserHttpService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InstagramImportService
{
    public function __construct(
        private CredentialService $credentials,
        private BrowserHttpService $browserHttp,
    )
    {
    }

    public function importPost(string $url, bool $includeImageData = false): array
    {
        $trace = [
            ['step' => 'instagram.normalize', 'input_url' => $url],
        ];
        $normalized = $this->normalizeInstagramUrl($url);
        if (!$normalized) {
            $trace[0]['result'] = 'invalid';
            return ['success' => false, 'message' => 'Provide a valid public Instagram post URL.', 'trace' => $trace];
        }
        $trace[0]['result'] = 'ok';
        $trace[0]['normalized_url'] = $normalized;

        $token = $this->credentials->get('instagram', 'meta_access_token');
        $trace[] = ['step' => 'instagram.token', 'has_token' => (bool) $token];

        $result = null;
        $fallback = null;

        if ($token) {
            $oembed = $this->importViaOembed($normalized, $token);
            $trace[] = [
                'step' => 'instagram.oembed',
                'success' => (bool) ($oembed['success'] ?? false),
                'message' => $oembed['message'] ?? null,
                'image_url' => $oembed['data']['image_url'] ?? null,
            ];
            $result = $oembed;
        }

        if (!$result || empty($result['success'])) {
            $fallback = $this->importViaPublicMetadata($normalized);
            $trace[] = [
                'step' => 'instagram.public_scrape',
                'success' => (bool) ($fallback['success'] ?? false),
                'message' => $fallback['message'] ?? null,
                'image_url' => $fallback['data']['image_url'] ?? null,
            ];
            if (!empty($fallback['success'])) {
                $result = $fallback;
            }
        }

        if (!$result || empty($result['success'])) {
            $msg = !$token
                ? (($fallback['message'] ?? 'Public metadata fallback failed.') . ' No Instagram / Meta access token is stored for oEmbed.')
                : ($result || $fallback ? trim(((string) ($result['message'] ?? '')) . ' ' . ((string) ($fallback['message'] ?? ''))) : 'Could not import this Instagram post.');
            return ['success' => false, 'message' => $msg, 'trace' => $trace];
        }

        if ($includeImageData && !empty($result['data']['image_url'])) {
            $fetch = $this->fetchImageDataUriVerbose((string) $result['data']['image_url']);
            $trace[] = array_merge(['step' => 'instagram.image_fetch'], $fetch);
            if (!empty($fetch['data_uri'])) {
                $result['data']['image_data_uri'] = $fetch['data_uri'];
            }
            if (!empty($fetch['width'])) {
                $result['data']['width'] = (int) $fetch['width'];
            }
            if (!empty($fetch['height'])) {
                $result['data']['height'] = (int) $fetch['height'];
            }
        } else {
            $trace[] = ['step' => 'instagram.image_fetch', 'skipped' => true, 'has_image_url' => !empty($result['data']['image_url']), 'include_image_data' => $includeImageData];
        }

        $result['trace'] = array_merge($trace, $result['trace'] ?? []);
        return $result;
    }

    public function testToken(?string $token = null): array
    {
        $token ??= $this->credentials->get('instagram', 'meta_access_token');
        if (!$token) {
            return ['success' => false, 'message' => 'No Instagram / Meta access token stored.'];
        }

        $sampleUrl = (string) config('instagram.instagram.test_post_url');
        $result = $this->importViaOembed($sampleUrl, $token);
        if (!empty($result['success'])) {
            return ['success' => true, 'message' => 'Instagram oEmbed token is valid.'];
        }

        return ['success' => false, 'message' => $result['message'] ?? 'Instagram token test failed.'];
    }

    private function importViaOembed(string $url, string $token): array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get((string) config('instagram.instagram.oembed_endpoint'), [
                    'url' => $url,
                    'omitscript' => 'true',
                    'maxwidth' => 1080,
                    'access_token' => $token,
                ]);

            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Meta oEmbed HTTP ' . $response->status() . '.'];
            }

            $json = $response->json();
            $imageUrl = $json['thumbnail_url'] ?? null;
            if (!$imageUrl) {
                return ['success' => false, 'message' => 'Meta oEmbed did not return an image URL.'];
            }

            return [
                'success' => true,
                'message' => 'Imported Instagram post via Meta oEmbed.',
                'data' => [
                    'import_type' => 'instagram_post',
                    'method_used' => 'meta_oembed',
                    'source_url' => $url,
                    'title' => (string) ($json['title'] ?? ''),
                    'caption' => (string) ($json['title'] ?? ''),
                    'author_name' => (string) ($json['author_name'] ?? ''),
                    'author_url' => (string) ($json['author_url'] ?? ''),
                    'provider_name' => (string) ($json['provider_name'] ?? 'Instagram'),
                    'html' => (string) ($json['html'] ?? ''),
                    'image_url' => (string) $imageUrl,
                    'thumbnail_url' => (string) $imageUrl,
                    'width' => (int) ($json['thumbnail_width'] ?? $json['width'] ?? 0),
                    'height' => (int) ($json['thumbnail_height'] ?? $json['height'] ?? 0),
                ],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Meta oEmbed error: ' . $e->getMessage()];
        }
    }

    private function importViaPublicMetadata(string $url): array
    {
        $trace = [];
        $pageFetch = $this->fetchInstagramHtml($url);
        $embedUrl = rtrim($url, '/') . '/embed/captioned/';
        $embedFetch = $this->fetchInstagramHtml($embedUrl);

        $trace[] = [
            'step' => 'instagram.public_page_fetch',
            'url' => $url,
            'http_status' => $pageFetch['http_status'],
            'success' => $pageFetch['success'],
        ];
        $trace[] = [
            'step' => 'instagram.public_embed_fetch',
            'url' => $embedUrl,
            'http_status' => $embedFetch['http_status'],
            'success' => $embedFetch['success'],
        ];

        $pageHtml = $pageFetch['html'];
        $embedHtml = $embedFetch['html'];
        $metaHtml = $pageHtml !== '' ? $pageHtml : $embedHtml;

        if ($metaHtml === '') {
            $message = $embedFetch['message'] ?: $pageFetch['message'] ?: 'No public image metadata found on the Instagram page.';
            return ['success' => false, 'message' => $message, 'trace' => $trace];
        }

        $imageUrl = null;
        $imageMethod = null;
        $width = 0;
        $height = 0;

        $embeddedMedia = $embedHtml !== '' ? $this->extractInstagramEmbeddedMediaImage($embedHtml) : null;
        if ($embeddedMedia) {
            $imageUrl = $embeddedMedia['url'];
            $imageMethod = 'public_embed_srcset';
            $width = (int) ($embeddedMedia['width'] ?? 0);
            $height = (int) ($embeddedMedia['height'] ?? 0);
        }

        if (!$imageUrl && $pageHtml !== '') {
            $pageImage = $this->extractInstagramFullImage($pageHtml);
            if ($pageImage) {
                $imageUrl = $pageImage;
                $imageMethod = 'public_page_json';
            }
        }

        if (!$imageUrl && $embedHtml !== '') {
            $embedImage = $this->extractInstagramFullImage($embedHtml);
            if ($embedImage) {
                $imageUrl = $embedImage;
                $imageMethod = 'public_embed_json';
            }
        }

        if (!$imageUrl) {
            foreach ([$pageHtml, $embedHtml] as $html) {
                if ($html === '') {
                    continue;
                }
                $metaImage = $this->extractMetaTag($html, 'property', 'og:image')
                    ?: $this->extractMetaTag($html, 'name', 'twitter:image');
                if ($metaImage) {
                    $imageUrl = $metaImage;
                    $imageMethod = 'og_image_fallback';
                    break;
                }
            }
        }

        if (!$imageUrl) {
            return ['success' => false, 'message' => 'No public image metadata found on the Instagram page.', 'trace' => $trace];
        }

        $trace[] = [
            'step' => 'instagram.full_image_lookup',
            'method' => $imageMethod,
            'image_url' => $imageUrl,
            'width' => $width ?: null,
            'height' => $height ?: null,
        ];

        return [
            'success' => true,
            'message' => 'Imported Instagram post via public metadata fallback.',
            'data' => [
                'import_type' => 'instagram_post',
                'method_used' => (string) ($imageMethod ?: 'public_metadata'),
                'source_url' => $url,
                'title' => (string) ($this->extractMetaTag($metaHtml, 'property', 'og:title') ?: ''),
                'caption' => (string) (($this->extractMetaTag($pageHtml, 'property', 'og:description') ?: $this->extractMetaTag($embedHtml, 'property', 'og:description')) ?: ''),
                'author_name' => $this->extractAuthorName($pageHtml !== '' ? $pageHtml : $embedHtml),
                'author_url' => '',
                'provider_name' => 'Instagram',
                'html' => '',
                'image_url' => (string) $imageUrl,
                'thumbnail_url' => (string) $imageUrl,
                'width' => $width,
                'height' => $height,
            ],
            'trace' => $trace,
        ];
    }

    private function fetchInstagramHtml(string $url): array
    {
        $response = $this->browserHttp->getHtml($url, [
            'timeout' => 20,
            'headers' => $this->browserHeaders(),
        ]);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'http_status' => null,
                'html' => '',
                'message' => 'Instagram fallback error: ' . $response['error'],
            ];
        }

        if (empty($response['success'])) {
            return [
                'success' => false,
                'http_status' => (int) ($response['status_code'] ?? 0),
                'html' => '',
                'message' => 'Instagram page fetch failed with HTTP ' . (int) ($response['status_code'] ?? 0) . '.',
            ];
        }

        return [
            'success' => true,
            'http_status' => (int) ($response['status_code'] ?? 0),
            'html' => (string) ($response['body'] ?? ''),
            'message' => null,
        ];
    }

    private function extractInstagramEmbeddedMediaImage(string $html): ?array
    {
        if (!preg_match('/<img[^>]+class=["\'][^"\']*EmbeddedMediaImage[^"\']*["\'][^>]*>/i', $html, $match)) {
            return null;
        }

        $tag = $match[0];
        $candidates = [];

        $srcset = $this->extractHtmlAttribute($tag, 'srcset');
        if ($srcset) {
            $candidates = array_merge($candidates, $this->parseSrcsetCandidates($srcset));
        }

        $src = $this->extractHtmlAttribute($tag, 'src');
        if ($src && filter_var($src, FILTER_VALIDATE_URL)) {
            $candidates[] = ['url' => $src, 'width' => 0, 'height' => 0];
        }

        $best = null;
        $bestScore = -1;
        foreach ($candidates as $candidate) {
            $candidateUrl = (string) ($candidate['url'] ?? '');
            if (!filter_var($candidateUrl, FILTER_VALIDATE_URL)) {
                continue;
            }

            $score = (int) ($candidate['width'] ?? 0);
            if ($score > $bestScore) {
                $best = [
                    'url' => $candidateUrl,
                    'width' => (int) ($candidate['width'] ?? 0),
                    'height' => (int) ($candidate['height'] ?? 0),
                ];
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function parseSrcsetCandidates(string $srcset): array
    {
        $srcset = html_entity_decode($srcset, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/(https:\/\/[^\s,]+)\s+(\d+)w/i', $srcset, $matches, PREG_SET_ORDER);

        $candidates = [];
        foreach ($matches as $match) {
            $url = $this->decodeUnicodeJsonString((string) ($match[1] ?? ''));
            if (!$url) {
                continue;
            }

            $candidates[] = [
                'url' => $url,
                'width' => (int) ($match[2] ?? 0),
                'height' => 0,
            ];
        }

        return $candidates;
    }

    private function extractHtmlAttribute(string $tag, string $attribute): ?string
    {
        $pattern = '/\b' . preg_quote($attribute, '/') . '=("|\')(.*?)\1/i';
        if (!preg_match($pattern, $tag, $match)) {
            return null;
        }

        return html_entity_decode((string) $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function extractInstagramFullImage(string $html): ?string
    {
        $best = null;
        $bestArea = 0;
        $offset = 0;

        while (($pos = strpos($html, '"display_resources":', $offset)) !== false) {
            $start = strpos($html, '[', $pos);
            if ($start === false) {
                break;
            }

            $depth = 0;
            $i = $start;
            $len = strlen($html);
            while ($i < $len) {
                $c = $html[$i];
                if ($c === '[') {
                    $depth++;
                } elseif ($c === ']') {
                    $depth--;
                    if ($depth === 0) {
                        $i++;
                        break;
                    }
                }
                $i++;
            }

            $arrJson = substr($html, $start, $i - $start);
            $arr = json_decode($arrJson, true);
            $offset = $i;
            if (!is_array($arr)) {
                continue;
            }

            foreach ($arr as $item) {
                if (!is_array($item) || empty($item['src'])) {
                    continue;
                }
                $w = (int) ($item['config_width'] ?? 0);
                $h = (int) ($item['config_height'] ?? 0);
                $area = $w * $h;
                if ($area > $bestArea) {
                    $bestArea = $area;
                    $best = (string) $item['src'];
                }
            }
        }

        if ($best) {
            return $this->decodeUnicodeJsonString($best);
        }

        if (preg_match('/"display_url":"([^"]+)"/', $html, $match)) {
            $candidate = $this->decodeUnicodeJsonString($match[1]);
            if ($candidate && filter_var($candidate, FILTER_VALIDATE_URL)) {
                return $candidate;
            }
        }

        return null;
    }

    private function decodeUnicodeJsonString(string $s): string
    {
        $decoded = json_decode('"' . $s . '"');
        return is_string($decoded) ? $decoded : str_replace('\\/', '/', $s);
    }

    public function fetchImageDataUri(string $imageUrl): ?string
    {
        $verbose = $this->fetchImageDataUriVerbose($imageUrl);
        return $verbose['data_uri'] ?? null;
    }

    public function fetchImageDataUriVerbose(string $imageUrl): array
    {
        $response = $this->browserHttp->getBinary($imageUrl, [
            'timeout' => 30,
            'headers' => $this->browserHeaders(),
        ]);

        $headers = is_array($response['headers'] ?? null) ? $response['headers'] : [];
        $mimeHeader = $headers['Content-Type'][0] ?? $headers['content-type'][0] ?? 'image/jpeg';
        $mime = is_string($mimeHeader) && $mimeHeader !== '' ? $mimeHeader : 'image/jpeg';
        $binary = is_string($response['body'] ?? null) ? $response['body'] : '';
        $bytes = strlen($binary);
        $dimensions = $this->extractImageDimensionsFromBinary($binary);

        if (!empty($response['error'])) {
            return [
                'image_url' => $imageUrl,
                'http_status' => null,
                'mime' => $mime,
                'bytes' => $bytes,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'data_uri' => null,
                'error' => substr((string) $response['error'], 0, 200),
            ];
        }

        if (empty($response['success'])) {
            return [
                'image_url' => $imageUrl,
                'http_status' => (int) ($response['status_code'] ?? 0),
                'mime' => $mime,
                'bytes' => $bytes,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'data_uri' => null,
                'error' => 'HTTP ' . (int) ($response['status_code'] ?? 0),
            ];
        }

        return [
            'image_url' => $imageUrl,
            'http_status' => (int) ($response['status_code'] ?? 0),
            'mime' => $mime,
            'bytes' => $bytes,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'data_uri' => 'data:' . $mime . ';base64,' . base64_encode($binary),
            'error' => null,
        ];
    }

    private function extractImageDimensionsFromBinary(string $binary): array
    {
        if ($binary === '' || !function_exists('getimagesizefromstring')) {
            return ['width' => 0, 'height' => 0];
        }

        $info = @getimagesizefromstring($binary);
        if (!is_array($info)) {
            return ['width' => 0, 'height' => 0];
        }

        return [
            'width' => (int) ($info[0] ?? 0),
            'height' => (int) ($info[1] ?? 0),
        ];
    }

    private function normalizeInstagramUrl(string $url): ?string
    {
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($url);
        $host = Str::lower((string) ($parts['host'] ?? ''));
        if (!in_array($host, ['instagram.com', 'www.instagram.com'], true)) {
            return null;
        }
        $path = (string) ($parts['path'] ?? '');
        if (preg_match('#^/[^/]+/(p|reel|tv)/([^/]+)/?#', $path, $match)) {
            $path = '/' . $match[1] . '/' . $match[2] . '/';
        }

        if (!preg_match('#^/(p|reel|tv)/[^/]+/?#', $path)) {
            return null;
        }
        return 'https://www.instagram.com' . rtrim($path, '/') . '/';
    }

    private function extractMetaTag(string $html, string $attrName, string $attrValue): ?string
    {
        $pattern = '/<meta[^>]+' . preg_quote($attrName, '/') . '=["\']' . preg_quote($attrValue, '/') . '["\'][^>]+content=["\']([^"\']+)["\']/i';
        if (preg_match($pattern, $html, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $pattern = '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+' . preg_quote($attrName, '/') . '=["\']' . preg_quote($attrValue, '/') . '["\']/i';
        if (preg_match($pattern, $html, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return null;
    }

    private function extractAuthorName(string $html): string
    {
        $title = $this->extractMetaTag($html, 'property', 'og:title') ?: '';
        if ($title && preg_match('/by\s+([^|]+)/i', $title, $match)) {
            return trim($match[1]);
        }
        return '';
    }

    public function browserHeaders(): array
    {
        return $this->browserHttp->headers('chrome');
    }
}
