<?php

return [
    'title' => 'BPAdmin',

    'entities' => [
        'users' => [
            'type'      =>  'default',
            'entity'    =>  \App\User::class,
            'key'       =>  'id',
            'paginate'  =>  10,
            'table_headers'   =>  [
                'name','email'
            ],
            'show_title'    => 'User Information',
            'show_fields'   => [
                'name',
                'email',
                'role_id',
            ],
            'validation_type' => 'default',
        ],
        'roles' => [
            'type'      =>  'default',
            'entity'    =>  \App\Role::class,
            'key'       =>  'id',
            'paginate'  =>  10,
            'table_headers'   =>  [
                'title'
            ],
            'show_title'    => 'User Information',
            'validation_type' => 'default',
        ],
    ],
    'menu' => [
        'users' => [
            'icon' => 'fa-users',
            'items' => [
                'users',
                'roles',
            ],
        ]
    ],
];
