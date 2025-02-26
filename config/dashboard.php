<?php

return [
    'title' => 'BPAdmin',
    'userEntity'    => \App\User::class,
    'languages'     => ['en'],

    'entities' => [
        'users' => \App\User::class,
    ],
    'menu' => [
        'users' => [
            'icon' => 'mdi-account-group',
            'items' => [
                'users' => [
                    'icon' => 'mdi-account-group',
                ],
            ],
        ],
    ],
];
