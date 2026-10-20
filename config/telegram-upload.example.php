<?php

// Merge this key into the existing array returned by the forum's config.php.
// Never replace the complete forum config.php with this example.
return [
    'telegram-upload' => [
        'base_url' => 'https://your-tg-disk.example.com',
        'chat_id' => '-1000000000000',
        // Match tg-disk's private NUXT_API_KEY (at least 32 characters).
        'api_key' => '',
    ],
];
