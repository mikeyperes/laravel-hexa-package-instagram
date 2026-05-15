<?php

return [
    'enabled' => env('INSTAGRAM_ENABLED', true),
    'version' => '1.0.12',
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
