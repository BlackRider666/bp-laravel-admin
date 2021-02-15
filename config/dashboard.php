<?php

return [
    'title' => 'BPAdmin',

    'entities' => [
        'users' => [
            'type'      =>  'default',
            'entity'    =>  \App\User::class,
            'key'       =>  'id',
            'icon'      =>  'fa-users',
            'paginate'  =>  10,
            'table_headers'   =>  [
                'name','email'
            ],
            'show_title'    => 'User Information',
            'variables'     => [
                'name'      =>  'string',
                'email'     =>  'email',
                'password'  =>  'password',
            ],
            'validation_type' => 'default',
            //'create_rules'  => [
            //    'name'  =>  'required|string|max:255',
            //    'email' =>  'required|string|email|unique:users',
            //    'password' => 'required|string|min:6',
            //]
        ],
        'roles' => [
            'type'      =>  'default',
            'entity'    =>  \App\User::class,
            'key'       =>  'id',
            'icon'      =>  'fa-users',
            'paginate'  =>  10,
            'table_headers'   =>  [
                'name','email'
            ],
            'show_title'    => 'User Information',
            'variables'     => [
                'name'      =>  'string',
                'email'     =>  'email',
                'password'  =>  'password',
            ],
            'validation_type' => 'default',
            //'create_rules'  => [
            //    'name'  =>  'required|string|max:255',
            //    'email' =>  'required|string|email|unique:users',
            //    'password' => 'required|string|min:6',
            //]
        ],
    ],
];
