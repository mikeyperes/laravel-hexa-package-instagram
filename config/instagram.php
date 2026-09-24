<?php

return [
    'enabled' => env('INSTAGRAM_ENABLED', true),
    'version' => '1.4.0',
    // Account discovery through another account's following list or stories (InstagramFollowAuditService).
    'follow_audit' => [
        'model' => env('INSTAGRAM_FOLLOW_AUDIT_MODEL', 'claude-haiku-4-5-20251001'),
        'limit' => 60,
        'batch_size' => 8,
        'max_highlights' => 15,
    ],
    // Server-side Instagram page and image requests leave through this browser profile's route
    // (for example its NordVPN route) instead of the server IP. Empty keeps the direct connection.
    'http_route_profile' => env('INSTAGRAM_HTTP_ROUTE_PROFILE', ''),
    // When true, those requests fail instead of falling back to the server IP if the route is Direct.
    'http_require_route' => env('INSTAGRAM_HTTP_REQUIRE_ROUTE', false),
    'defaults' => [
        'session_profile' => env('INSTAGRAM_SESSION_PROFILE', 'instagram-main'),
        'default_profile_username' => env('INSTAGRAM_DEFAULT_PROFILE_USERNAME', ''),
        'default_story_username' => env('INSTAGRAM_DEFAULT_STORY_USERNAME', ''),
        'default_post_url' => env('INSTAGRAM_DEFAULT_POST_URL', 'https://www.instagram.com/p/DO4I7GBDWlF/'),
    ],
    'instagram' => [
        'oembed_endpoint' => env('INSTAGRAM_OEMBED_ENDPOINT', 'https://graph.facebook.com/v22.0/instagram_oembed'),
        'test_post_url' => env('INSTAGRAM_TEST_POST_URL', 'https://www.instagram.com/p/DO4I7GBDWlF/'),
    ],
];
