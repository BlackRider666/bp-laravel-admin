<?php

return [
    'title' => 'BPAdmin',
    'userEntity'    => \App\User::class,

    'entities' => [
        'users' => [
            'type'      =>  'default',
            'entity'    =>  \App\User::class,
            'key'       =>  'id',
            'paginate'  =>  10,
            'search'    =>  ['name','email'],
            'show_title' => 'User Information',
            'table_headers'   =>  [
                'name','email'
            ],
            'show_fields'   => [
                'name',
                'email',
                'role_id',
            ],
            'validation_type' => 'default',
        ],
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
