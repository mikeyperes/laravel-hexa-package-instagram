<?php

return [
    'enabled' => env('INSTAGRAM_ENABLED', true),
    'version' => '1.12.0',
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
    // Stories and feed posts published from a logged-in browser profile (InstagramPublisherService).
    'publishing' => [
        // Stories are uploaded on Instagram's phone site; a 9:16 screen keeps the image uncropped.
        'story_screen' => ['width' => 360, 'height' => 640],
        // The picture is fitted whole inside this box on a blurred copy of itself (nothing is cut off);
        // the top and bottom margins stay clear of Instagram's name bar and reply bar.
        'story_canvas' => ['width' => 1080, 'height' => 1920, 'box_width' => 1000, 'box_height' => 1500],
        // Feed posts are 4:5 portrait, Instagram's tallest feed shape, posted with the "Original" crop.
        'post_canvas' => ['width' => 1080, 'height' => 1350, 'box_width' => 1080, 'box_height' => 1350],
        'jpeg_quality' => 90,
        // Pause between two items in one batch, in seconds.
        'gap_seconds' => [20, 45],
        'caption_max' => 2200,
    ],
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
