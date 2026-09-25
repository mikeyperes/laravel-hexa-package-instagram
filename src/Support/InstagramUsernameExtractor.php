<?php

namespace hexa_package_instagram\Support;

/**
 * Pulls Instagram usernames out of any pasted text: a raw HTML dump of a following or followers list,
 * profile links, or plain "@name" lists. Returns each account once, in the order first seen.
 */
class InstagramUsernameExtractor
{
    /** Instagram paths that are pages, not accounts. */
    private const RESERVED = [
        'p', 'reel', 'reels', 'tv', 'explore', 'stories', 'accounts', 'direct', 'about', 'legal', 'developer',
        'developers', 'privacy', 'terms', 'api', 'web', 'static', 'challenge', 'emails', 'press', 'help', 'session',
        'oauth', 'graphql', 'ajax', 'locations', 'tags', 'topics', 'create', 'your_activity', 'lite', 'download',
        'nametag', 'directory', 'blog', 'jobs', 'account', 'login', 'signup', 'following', 'followers', 'tagged',
        'saved', 'guides', 'archive', 'settings', 'notifications', 'popular', 'threads', 'meta', 'instagram',
    ];

    /**
     * @param array<int, string> $exclude usernames to leave out (for example the list owner)
     * @return array<int, string>
     */
    public function extract(string $text, array $exclude = [], bool $includeMentions = true): array
    {
        $skip = array_flip(array_map(static fn ($name): string => strtolower(ltrim(trim((string) $name), '@')), $exclude));
        $found = [];
        $add = function (string $name) use (&$found, $skip): void {
            $name = strtolower(trim($name, ". \t\n\r"));
            if ($this->valid($name) && ! isset($skip[$name]) && ! isset($found[$name])) {
                $found[$name] = true;
            }
        };
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        // Full profile links: instagram.com/<name>/ (any scheme, www or m.). Not image hosts such as
        // cdninstagram.com/v/…, which only end in "instagram.com".
        preg_match_all('~(?<![A-Za-z0-9-])(?:www\.|m\.)?instagram\.com/([A-Za-z0-9._]{1,30})(?=[/?"\'\s#]|$)~i', $text, $matches);
        array_map($add, $matches[1]);
        // Relative links inside Instagram's own page HTML: href="/<name>/".
        preg_match_all('~href\s*=\s*["\']/([A-Za-z0-9._]{1,30})/?["\'?#]~i', $text, $matches);
        array_map($add, $matches[1]);
        // Plain "@name" lists.
        if ($includeMentions) {
            preg_match_all('~(?<![A-Za-z0-9._@/])@([A-Za-z0-9._]{1,30})~', strip_tags($text), $matches);
            array_map($add, $matches[1]);
        }

        return array_keys($found);
    }

    private function valid(string $name): bool
    {
        return $name !== ''
            && preg_match('/^[a-z0-9._]{1,30}$/', $name) === 1
            && preg_match('/[a-z0-9]/', $name) === 1
            && ! str_contains($name, '..')
            && ! in_array($name, self::RESERVED, true);
    }
}
