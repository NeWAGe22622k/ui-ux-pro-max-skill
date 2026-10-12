<?php
// Copy this file to config.php (same folder) and fill in your details.
// config.php is never committed to git.

return [
    // hPanel → Databases → MySQL Databases
    'db' => [
        'host'     => 'localhost',
        'name'     => 'u123456789_ebeano',
        'user'     => 'u123456789_ebeano',
        'password' => 'change-me',
    ],

    // Full public address of the site, no trailing slash.
    'base_url' => 'https://princeebeano.com',

    // Set to false only when testing locally over plain http.
    'secure_cookies' => true,

    'timezone' => 'Africa/Lagos',

    'store' => [
        'name'    => 'Prince Ebeano Supermarket',
        'tagline' => 'Your one-stop store for all your daily household needs',
        'phones'  => ['09157294213', '08084065686', '09157294187'],
        'whatsapp' => '2349157294213',  // international format, digits only
        'email'   => 'info@princeebeano.com',
        'hours'   => [
            'Monday – Saturday' => '8:00am – 9:30pm',
            'Sunday'            => '12 noon – 9:00pm',
        ],
        'socials' => [
            'facebook'  => 'https://facebook.com/',
            'instagram' => 'https://instagram.com/',
            'x'         => 'https://x.com/',
        ],
    ],

    // Where contact-form messages are emailed (leave '' to only keep them in the admin inbox).
    'notify_email' => '',
];
