<?php

return [
    'docs' => [
        'route' => '/docs',
        'path' => 'resources/docs',
        'landing' => 'overview',
        'versions' => [
            'default' => '1.0',
            'published' => [
                '1.0'
            ]
        ]
    ],

    'ui' => [
        'code_theme' => 'dark',
        'fav' => '/img/favicon.ico',
        'colors' => [
            'primary' => '#787AF6',
            'secondary' => '#2b9cf2'
        ],
        'theme' => 'default',
        'back_to_top' => true,
    ],

    'settings' => [
        'auth' => false,
        'ga_id' => ''
    ],

    'cache' => [
        'enabled' => false,
        'period' => 5
    ],

    'search' => [
        'enabled' => true,
        'default' => 'algolia',
        'engines' => [
            'internal' => [
                'index' => ['h2', 'h3']
            ]
        ]
    ]
];